<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $supplierRule = $this->input('context') === 'material_receipt'
            ? ['nullable', 'exists:suppliers,id']
            : ['required', 'exists:suppliers,id'];

        return [
            'supplier_id' => $supplierRule,
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'notes' => ['nullable', 'string'],
            'proof_image' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'entry_context' => ['nullable', 'string', 'in:legacy_purchase,material_receipt'],
            // 'status' is preserved from existing record
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.batch_number' => ['nullable', 'string', 'max:100'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.storage_location' => ['nullable', 'string', 'max:150'],
            'items.*.storage_location_id' => ['nullable', 'exists:storage_locations,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.selling_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier is required for legacy purchase flow.',
            'proof_image.mimes' => 'Attachment must be a PDF, JPG, JPEG, or PNG file.',
            'items.required' => 'Please add at least one item.',
            'items.*.product_id.required' => 'Product is required.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}
