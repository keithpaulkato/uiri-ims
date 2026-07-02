<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <h1 class="page-title">Edit Category</h1>
            </div>
        </div>
    </x-slot>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('categories.update', $category) }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="name">Category Name *</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $category->name) }}" required>
                    @error('name')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3">{{ old('description', $category->description) }}</textarea>
                    @error('description')
                        <div class="alert alert-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="page-actions">
                    <a href="{{ route('categories.index') }}" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
