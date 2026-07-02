<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <h1 class="page-title">Edit Supplier</h1>
            </div>
        </div>
    </x-slot>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('suppliers.update', $supplier) }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="company_name">Company Name *</label>
                    <input type="text" id="company_name" name="company_name" value="{{ old('company_name', $supplier->company_name) }}" required>
                    @error('company_name')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="contact_person">Contact Person</label>
                    <input type="text" id="contact_person" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}">
                    @error('contact_person')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $supplier->email) }}">
                    @error('email')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $supplier->phone) }}">
                    @error('phone')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3">{{ old('address', $supplier->address) }}</textarea>
                    @error('address')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="tin_number">TIN Number</label>
                    <input type="text" id="tin_number" name="tin_number" value="{{ old('tin_number', $supplier->tin_number) }}">
                    @error('tin_number')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="is_active">
                        <input type="checkbox" id="is_active" name="is_active" value="1" style="width:auto; display:inline-block;" @checked(old('is_active', $supplier->is_active))>
                        Active
                    </label>
                    @error('is_active')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="page-actions">
                    <a href="{{ route('suppliers.index') }}" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
