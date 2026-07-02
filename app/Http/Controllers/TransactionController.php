<?php

namespace App\Http\Controllers;

use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    /**
     * List stock transactions for the current user's active branch, most
     * recent first. Viewable by any authenticated user. Supports optional
     * ?type= and ?item_id= filters.
     */
    public function index(Request $request): View
    {
        $type = $request->string('type')->trim()->toString();
        $itemId = $request->integer('item_id') ?: null;

        $transactions = StockTransaction::with(['item', 'user'])
            ->where('branch_id', auth()->user()->activeBranchId())
            ->when($type !== '', fn ($query) => $query->where('transaction_type', $type))
            ->when($itemId, fn ($query) => $query->where('item_id', $itemId))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        return view('transactions.index', [
            'transactions' => $transactions,
            'type' => $type,
            'itemId' => $itemId,
        ]);
    }
}
