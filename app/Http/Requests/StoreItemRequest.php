<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage_inventory') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'item_code' => ['required', 'string', 'max:50', 'unique:inventory_items,item_code'],
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'asset_code' => ['nullable', 'string', 'max:100'],
            'qr_code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'unit' => ['nullable', 'string', 'max:30'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'current_stock' => ['nullable', 'integer', 'min:0'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
            'asset_type' => ['nullable', 'string', 'max:50'],
            'purchase_date' => ['nullable', 'date'],
            'warranty_date' => ['nullable', 'date'],
            'image' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
