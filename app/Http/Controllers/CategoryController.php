<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use AuthorizesRequests;

    /**
     * List categories. Viewable by any authenticated user. Supports a
     * simple ?search= filter on name.
     */
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();

        $categories = Category::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();

        return view('categories.index', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a new category. Requires manage_inventory.
     */
    public function create(): View
    {
        $this->authorize('manage_inventory', Category::class);

        return view('categories.create');
    }

    /**
     * Store a new category. Requires manage_inventory (enforced in the
     * FormRequest's authorize()).
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return redirect()->route('categories.index')->with('success', 'Category added.');
    }

    /**
     * Show the form for editing a category. Requires manage_inventory.
     */
    public function edit(Category $category): View
    {
        $this->authorize('manage_inventory', Category::class);

        return view('categories.edit', ['category' => $category]);
    }

    /**
     * Update a category. Requires manage_inventory (enforced in the
     * FormRequest's authorize()).
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')->with('success', 'Category updated.');
    }

    /**
     * Delete a category. Categories have no is_active column (unlike
     * suppliers) so this is a hard delete. Requires manage_inventory.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('manage_inventory', Category::class);

        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Category deleted.');
    }
}
