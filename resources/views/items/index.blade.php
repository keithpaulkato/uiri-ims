<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <h1 class="page-title">Inventory Items</h1>
                <p class="page-sub">{{ $items->count() }} items</p>
            </div>
            @can('manage_inventory')
                <div class="page-actions">
                    <a href="{{ route('items.create') }}" class="btn btn-primary">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Item
                    </a>
                </div>
            @endcan
        </div>
    </x-slot>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body p0">
            <form method="GET" action="{{ route('items.index') }}" class="filter-group" style="padding: 16px 20px;">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search items by name or code...">
                <label style="display:inline-flex;align-items:center;gap:6px;margin-left:12px;">
                    <input type="checkbox" name="low_stock" value="1" @checked($lowStock) onchange="this.form.submit()">
                    Low stock only
                </label>
                <button type="submit" class="btn btn-outline btn-sm">Search</button>
            </form>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Image</th>
                        <th>Item Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th>Unit Price</th>
                        <th>Stock</th>
                        @can('manage_inventory')
                            <th>Actions</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $i => $item)
                        <tr class="{{ $item->isLowStock() ? 'row-low-stock' : '' }}">
                            <td>{{ $i + 1 }}</td>
                            <td>
                                @if ($item->image)
                                    <img src="{{ asset('storage/'.$item->image) }}" alt="{{ $item->name }}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">
                                @else
                                    <span>&mdash;</span>
                                @endif
                            </td>
                            <td>{{ $item->item_code }}</td>
                            <td><strong>{{ $item->name }}</strong></td>
                            <td>{{ $item->category?->name ?? '—' }}</td>
                            <td>{{ $item->supplier?->company_name ?? '—' }}</td>
                            <td>UGX {{ number_format($item->unit_price, 0) }}</td>
                            <td>
                                {{ $item->current_stock }}
                                @if ($item->isLowStock())
                                    <span class="badge badge-danger">Low Stock</span>
                                @endif
                            </td>
                            @can('manage_inventory')
                                <td>
                                    <div class="action-btns">
                                        <a href="{{ route('items.edit', $item) }}" class="btn-icon" title="Edit">
                                            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </a>
                                        @if ($item->is_active)
                                            <form method="POST" action="{{ route('items.destroy', $item) }}" style="display:inline" onsubmit="return confirm('Deactivate this item?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-icon btn-icon-danger" title="Deactivate">
                                                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M9 6V4h6v2"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <h3>No items found</h3>
                                    <p>Try a different search or add a new item.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
