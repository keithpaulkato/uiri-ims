<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <h1 class="page-title">Categories</h1>
                <p class="page-sub">{{ $categories->count() }} categories</p>
            </div>
            @can('manage_inventory')
                <div class="page-actions">
                    <a href="{{ route('categories.create') }}" class="btn btn-primary">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Category
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
            <form method="GET" action="{{ route('categories.index') }}" class="filter-group" style="padding: 16px 20px;">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search categories...">
                <button type="submit" class="btn btn-outline btn-sm">Search</button>
            </form>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Description</th>
                        @can('manage_inventory')
                            <th>Actions</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $i => $category)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td><strong>{{ $category->name }}</strong></td>
                            <td>{{ $category->description ?: '—' }}</td>
                            @can('manage_inventory')
                                <td>
                                    <div class="action-btns">
                                        <a href="{{ route('categories.edit', $category) }}" class="btn-icon" title="Edit">
                                            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </a>
                                        <form method="POST" action="{{ route('categories.destroy', $category) }}" style="display:inline" onsubmit="return confirm('Delete this category?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon btn-icon-danger" title="Delete">
                                                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M9 6V4h6v2"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">
                                    <h3>No categories found</h3>
                                    <p>Try a different search or add a new category.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
