<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    use AuthorizesRequests;

    /**
     * List suppliers. Viewable by any authenticated user. Supports a
     * simple ?search= filter on company_name.
     */
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();

        $suppliers = Supplier::query()
            ->when($search !== '', fn ($query) => $query->where('company_name', 'like', "%{$search}%"))
            ->orderBy('company_name')
            ->get();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a new supplier. Requires manage_suppliers.
     */
    public function create(): View
    {
        $this->authorize('manage_suppliers', Supplier::class);

        return view('suppliers.create');
    }

    /**
     * Store a new supplier. Requires manage_suppliers (enforced in the
     * FormRequest's authorize()).
     */
    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated() + ['is_active' => true]);

        return redirect()->route('suppliers.index')->with('success', 'Supplier added.');
    }

    /**
     * Show the form for editing a supplier. Requires manage_suppliers.
     */
    public function edit(Supplier $supplier): View
    {
        $this->authorize('manage_suppliers', Supplier::class);

        return view('suppliers.edit', ['supplier' => $supplier]);
    }

    /**
     * Update a supplier. Requires manage_suppliers (enforced in the
     * FormRequest's authorize()).
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated.');
    }

    /**
     * Deactivate a supplier (soft-deactivate, matching the legacy app)
     * rather than hard-deleting it. Requires manage_suppliers.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('manage_suppliers', Supplier::class);

        $supplier->update(['is_active' => false]);

        return redirect()->route('suppliers.index')->with('success', 'Supplier deactivated.');
    }
}
