<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <h1 class="page-title">Edit Inventory Item</h1>
            </div>
        </div>
    </x-slot>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('items.update', $item) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="item_code">Item Code *</label>
                    <input type="text" id="item_code" name="item_code" value="{{ old('item_code', $item->item_code) }}" required>
                    @error('item_code')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $item->name) }}" required>
                    @error('name')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3">{{ old('description', $item->description) }}</textarea>
                    @error('description')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="category_id">Category *</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $item->category_id) == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="supplier_id">Supplier</label>
                    <select id="supplier_id" name="supplier_id">
                        <option value="">Select supplier</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id', $item->supplier_id) == $supplier->id)>{{ $supplier->company_name }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="unit">Unit</label>
                    <input type="text" id="unit" name="unit" value="{{ old('unit', $item->unit) }}">
                    @error('unit')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="unit_price">Unit Price (UGX)</label>
                    <input type="number" step="0.01" min="0" id="unit_price" name="unit_price" value="{{ old('unit_price', $item->unit_price) }}">
                    @error('unit_price')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="current_stock">Current Stock</label>
                    <input type="number" min="0" id="current_stock" name="current_stock" value="{{ old('current_stock', $item->current_stock) }}">
                    @error('current_stock')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="minimum_stock">Minimum Stock</label>
                    <input type="number" min="0" id="minimum_stock" name="minimum_stock" value="{{ old('minimum_stock', $item->minimum_stock) }}">
                    @error('minimum_stock')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="asset_type">Asset Type</label>
                    <input type="text" id="asset_type" name="asset_type" value="{{ old('asset_type', $item->asset_type) }}">
                    @error('asset_type')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="purchase_date">Purchase Date</label>
                    <input type="date" id="purchase_date" name="purchase_date" value="{{ old('purchase_date', $item->purchase_date?->format('Y-m-d')) }}">
                    @error('purchase_date')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="warranty_date">Warranty Date</label>
                    <input type="date" id="warranty_date" name="warranty_date" value="{{ old('warranty_date', $item->warranty_date?->format('Y-m-d')) }}">
                    @error('warranty_date')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="image">Image</label>
                    @if ($item->image)
                        <div style="margin-bottom:8px;">
                            <img src="{{ asset('storage/'.$item->image) }}" alt="{{ $item->name }}" style="width:80px;height:80px;object-fit:cover;border-radius:4px;">
                        </div>
                    @endif
                    <input type="file" id="image" name="image" accept="image/*">
                    @error('image')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="page-actions">
                    <a href="{{ route('items.index') }}" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
