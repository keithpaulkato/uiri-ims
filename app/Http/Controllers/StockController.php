<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Notification;
use App\Models\StockTransaction;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the Stock In form. Requires manage_stock.
     */
    public function stockInForm(): View
    {
        $this->authorize('manage_stock');

        return view('stock.in', ['items' => $this->activeBranchItems()]);
    }

    /**
     * Record a Stock In transaction. Requires manage_stock. Only items
     * belonging to the user's active branch may be targeted.
     */
    public function stockIn(Request $request): RedirectResponse
    {
        $this->authorize('manage_stock');

        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $item = $this->findActiveBranchItem($data['item_id']);

        DB::transaction(function () use ($item, $data) {
            $item->increment('current_stock', $data['quantity']);

            StockTransaction::create([
                'item_id' => $item->id,
                'branch_id' => $item->branch_id,
                'user_id' => auth()->id(),
                'transaction_type' => 'stock_in',
                'quantity' => $data['quantity'],
                'unit_price' => $data['unit_price'] ?? $item->unit_price,
                'reference_number' => $data['reference_number'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
            ]);
        });

        return redirect()->route('stock.in')->with('success', 'Stock in recorded.');
    }

    /**
     * Show the Stock Out form. Requires manage_stock.
     */
    public function stockOutForm(): View
    {
        $this->authorize('manage_stock');

        return view('stock.out', ['items' => $this->activeBranchItems()]);
    }

    /**
     * Record a Stock Out transaction. Requires manage_stock. Rejects the
     * request (no mutation) if the requested quantity exceeds current
     * stock.
     */
    public function stockOut(Request $request): RedirectResponse
    {
        $this->authorize('manage_stock');

        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $item = $this->findActiveBranchItem($data['item_id']);

        DB::transaction(function () use ($item, $data) {
            // Lock the row and re-check against fresh committed stock so two
            // concurrent stock-outs can't both pass a stale check and drive
            // current_stock negative (TOCTOU).
            $locked = InventoryItem::whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($data['quantity'] > $locked->current_stock) {
                throw ValidationException::withMessages([
                    'quantity' => 'Quantity exceeds current stock ('.$locked->current_stock.' available).',
                ]);
            }

            $locked->decrement('current_stock', $data['quantity']);

            StockTransaction::create([
                'item_id' => $locked->id,
                'branch_id' => $locked->branch_id,
                'user_id' => auth()->id(),
                'transaction_type' => 'stock_out',
                'quantity' => $data['quantity'],
                'unit_price' => $data['unit_price'] ?? $locked->unit_price,
                'reference_number' => $data['reference_number'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
            ]);

            Notification::notifyLowStock($locked->fresh());
        });

        return redirect()->route('stock.out')->with('success', 'Stock out recorded.');
    }

    /**
     * Show the Stock Adjustment form. Requires manage_stock.
     */
    public function adjustForm(): View
    {
        $this->authorize('manage_stock');

        return view('stock.adjust', ['items' => $this->activeBranchItems()]);
    }

    /**
     * Record a Stock Adjustment: the form supplies a new absolute
     * current_stock value; we compute the delta and record it as an
     * 'adjustment' transaction (quantity = |delta|, direction noted in
     * remarks). Requires manage_stock.
     */
    public function adjust(Request $request): RedirectResponse
    {
        $this->authorize('manage_stock');

        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'current_stock' => ['required', 'integer', 'min:0'],
            'transaction_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $item = $this->findActiveBranchItem($data['item_id']);

        DB::transaction(function () use ($item, $data) {
            // Lock the row so the read-then-set can't lose a concurrent update.
            $locked = InventoryItem::whereKey($item->id)->lockForUpdate()->firstOrFail();

            $previous = $locked->current_stock;
            $new = $data['current_stock'];
            $delta = $new - $previous;
            $direction = $delta >= 0 ? 'increase' : 'decrease';

            $locked->update(['current_stock' => $new]);

            $remarks = trim(sprintf(
                'Adjustment %s from %d to %d.%s',
                $direction,
                $previous,
                $new,
                $data['remarks'] ? ' '.$data['remarks'] : ''
            ));

            StockTransaction::create([
                'item_id' => $locked->id,
                'branch_id' => $locked->branch_id,
                'user_id' => auth()->id(),
                'transaction_type' => 'adjustment',
                'quantity' => abs($delta),
                'unit_price' => $locked->unit_price,
                'reference_number' => null,
                'remarks' => $remarks,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
            ]);

            if ($delta < 0) {
                Notification::notifyLowStock($locked->fresh());
            }
        });

        return redirect()->route('stock.adjust')->with('success', 'Stock adjustment recorded.');
    }

    /**
     * Active, non-deactivated items in the current user's active branch,
     * for populating the item <select> on stock forms.
     */
    private function activeBranchItems()
    {
        return InventoryItem::forBranch(auth()->user()->activeBranchId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Resolve an item by id, guarding against cross-branch access (IDOR):
     * stock operations may only target items in the user's active branch.
     */
    private function findActiveBranchItem(int $itemId): InventoryItem
    {
        $item = InventoryItem::findOrFail($itemId);

        abort_unless($item->branch_id === auth()->user()->activeBranchId(), 404);

        return $item;
    }
}
