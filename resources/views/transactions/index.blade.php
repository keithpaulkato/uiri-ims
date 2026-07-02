<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <h1 class="page-title">Stock Transactions</h1>
                <p class="page-sub">{{ $transactions->count() }} transactions</p>
            </div>
            @can('manage_stock')
                <div class="page-actions">
                    <a href="{{ route('stock.in') }}" class="btn btn-outline btn-sm">Stock In</a>
                    <a href="{{ route('stock.out') }}" class="btn btn-outline btn-sm">Stock Out</a>
                    <a href="{{ route('stock.adjust') }}" class="btn btn-primary btn-sm">Adjust</a>
                </div>
            @endcan
        </div>
    </x-slot>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body p0">
            <form method="GET" action="{{ route('transactions.index') }}" class="filter-group" style="padding: 16px 20px;">
                <select name="type" onchange="this.form.submit()">
                    <option value="">All types</option>
                    @foreach (['stock_in' => 'Stock In', 'stock_out' => 'Stock Out', 'adjustment' => 'Adjustment', 'transfer_in' => 'Transfer In', 'transfer_out' => 'Transfer Out'] as $value => $label)
                        <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-outline btn-sm">Filter</button>
            </form>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Reference</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->transaction_date->format('Y-m-d') }}</td>
                            <td>
                                @php
                                    $badgeClass = match ($transaction->transaction_type) {
                                        'stock_in', 'transfer_in' => 'badge-success',
                                        'stock_out', 'transfer_out' => 'badge-danger',
                                        'adjustment' => 'badge-warn',
                                        default => 'badge-blue',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}</span>
                            </td>
                            <td>{{ $transaction->item?->name ?? '—' }}</td>
                            <td>{{ $transaction->quantity }}</td>
                            <td>UGX {{ number_format($transaction->unit_price, 0) }}</td>
                            <td>{{ $transaction->reference_number ?? '—' }}</td>
                            <td>{{ $transaction->user?->full_name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <h3>No transactions found</h3>
                                    <p>Stock in, stock out, and adjustment activity will appear here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
