<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <h1 class="page-title">Suppliers</h1>
                <p class="page-sub">{{ $suppliers->count() }} suppliers</p>
            </div>
            @can('manage_suppliers')
                <div class="page-actions">
                    <a href="{{ route('suppliers.create') }}" class="btn btn-primary">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Supplier
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
            <form method="GET" action="{{ route('suppliers.index') }}" class="filter-group" style="padding: 16px 20px;">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search suppliers...">
                <button type="submit" class="btn btn-outline btn-sm">Search</button>
            </form>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Company</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>TIN</th>
                        <th>Status</th>
                        @can('manage_suppliers')
                            <th>Actions</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $i => $supplier)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td><strong>{{ $supplier->company_name }}</strong></td>
                            <td>{{ $supplier->contact_person ?: '—' }}</td>
                            <td>{{ $supplier->email ?: '—' }}</td>
                            <td>{{ $supplier->phone ?: '—' }}</td>
                            <td>{{ $supplier->tin_number ?: '—' }}</td>
                            <td>
                                @if ($supplier->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-danger">Inactive</span>
                                @endif
                            </td>
                            @can('manage_suppliers')
                                <td>
                                    <div class="action-btns">
                                        <a href="{{ route('suppliers.edit', $supplier) }}" class="btn-icon" title="Edit">
                                            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </a>
                                        @if ($supplier->is_active)
                                            <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" style="display:inline" onsubmit="return confirm('Deactivate this supplier?')">
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
                            <td colspan="8">
                                <div class="empty-state">
                                    <h3>No suppliers found</h3>
                                    <p>Try a different search or add a new supplier.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
