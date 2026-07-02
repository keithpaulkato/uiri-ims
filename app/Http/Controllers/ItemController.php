<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Supplier;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItemController extends Controller
{
    use AuthorizesRequests;

    /**
     * List inventory items for the current user's active branch. Viewable
     * by any authenticated user. Supports ?search= on name/item_code and
     * an optional ?low_stock=1 filter.
     */
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $lowStock = $request->boolean('low_stock');

        $items = InventoryItem::forBranch(auth()->user()->activeBranchId())
            ->with(['category', 'supplier'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%");
                });
            })
            ->when($lowStock, function ($query) {
                $query->whereColumn('current_stock', '<=', 'minimum_stock');
            })
            ->orderBy('name')
            ->get();

        return view('items.index', [
            'items' => $items,
            'search' => $search,
            'lowStock' => $lowStock,
        ]);
    }

    /**
     * Show the form for creating a new item. Requires manage_inventory.
     */
    public function create(): View
    {
        $this->authorize('manage_inventory', InventoryItem::class);

        return view('items.create', [
            'categories' => Category::all(),
            'suppliers' => Supplier::where('is_active', true)->get(),
        ]);
    }

    /**
     * Store a new item. Requires manage_inventory (enforced in the
     * FormRequest's authorize()).
     */
    public function store(StoreItemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['branch_id'] = auth()->user()->activeBranchId();
        $data['created_by'] = auth()->id();
        $data['current_stock'] = $data['current_stock'] ?? 0;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('items', 'public');
        }

        InventoryItem::create($data);

        return redirect()->route('items.index')->with('success', 'Item added.');
    }

    /**
     * Show the form for editing an item. Requires manage_inventory.
     */
    public function edit(InventoryItem $item): View
    {
        $this->authorize('manage_inventory', InventoryItem::class);

        return view('items.edit', [
            'item' => $item,
            'categories' => Category::all(),
            'suppliers' => Supplier::where('is_active', true)->get(),
        ]);
    }

    /**
     * Update an item. Requires manage_inventory (enforced in the
     * FormRequest's authorize()).
     */
    public function update(UpdateItemRequest $request, InventoryItem $item): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('items', 'public');
        }

        $item->update($data);

        return redirect()->route('items.index')->with('success', 'Item updated.');
    }

    /**
     * Deactivate an item (soft-deactivate, matching the legacy app) rather
     * than hard-deleting it. Requires manage_inventory.
     */
    public function destroy(InventoryItem $item): RedirectResponse
    {
        $this->authorize('manage_inventory', InventoryItem::class);

        $item->update(['is_active' => false]);

        return redirect()->route('items.index')->with('success', 'Item deactivated.');
    }
}
