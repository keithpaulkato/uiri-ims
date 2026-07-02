<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <h1 class="page-title">Stock Adjustment</h1>
                <p class="page-sub">Correct recorded stock for {{ auth()->user()->activeBranch()?->name }}</p>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('stock.adjust.store') }}">
                @csrf

                <div class="form-group">
                    <label for="item_id">Item *</label>
                    <select id="item_id" name="item_id" required>
                        <option value="">Select item</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}" data-current="{{ $item->current_stock }}" @selected(old('item_id') == $item->id)>
                                {{ $item->item_code }} — {{ $item->name }} (current: {{ $item->current_stock }})
                            </option>
                        @endforeach
                    </select>
                    @error('item_id')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="current_stock">New Stock Count *</label>
                    <input type="number" min="0" id="current_stock" name="current_stock" value="{{ old('current_stock') }}" required>
                    @error('current_stock')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="transaction_date">Date *</label>
                    <input type="date" id="transaction_date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" required>
                    @error('transaction_date')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="remarks">Reason for Adjustment</label>
                    <textarea id="remarks" name="remarks" rows="3" placeholder="e.g. Physical stock count correction">{{ old('remarks') }}</textarea>
                    @error('remarks')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="page-actions">
                    <a href="{{ route('transactions.index') }}" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary">Record Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
