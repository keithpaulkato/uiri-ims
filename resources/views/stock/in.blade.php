<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <h1 class="page-title">Stock In</h1>
                <p class="page-sub">Record incoming stock for {{ auth()->user()->activeBranch()?->name }}</p>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('stock.in.store') }}">
                @csrf

                <div class="form-group">
                    <label for="item_id">Item *</label>
                    <select id="item_id" name="item_id" required>
                        <option value="">Select item</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>
                                {{ $item->item_code }} — {{ $item->name }} (current: {{ $item->current_stock }})
                            </option>
                        @endforeach
                    </select>
                    @error('item_id')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="quantity">Quantity *</label>
                    <input type="number" min="1" id="quantity" name="quantity" value="{{ old('quantity', 1) }}" required>
                    @error('quantity')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="unit_price">Unit Price (UGX)</label>
                    <input type="number" step="0.01" min="0" id="unit_price" name="unit_price" value="{{ old('unit_price') }}">
                    @error('unit_price')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="reference_number">Reference Number</label>
                    <input type="text" id="reference_number" name="reference_number" value="{{ old('reference_number') }}" placeholder="e.g. PO-2026-001">
                    @error('reference_number')
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
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="3">{{ old('remarks') }}</textarea>
                    @error('remarks')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="page-actions">
                    <a href="{{ route('transactions.index') }}" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary">Record Stock In</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
