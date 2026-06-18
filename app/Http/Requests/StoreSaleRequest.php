<?php

namespace App\Http\Requests;

use App\Enums\SaleStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleTransactionType;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transaction_type' => ['nullable', Rule::enum(SaleTransactionType::class)],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'sale_date' => ['required', 'date'],
            'usage_date' => ['nullable', 'date'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'status' => ['nullable', Rule::enum(SaleStatus::class)],
            'purpose' => ['nullable', 'string', 'max:255'],
            'formula' => ['nullable', 'string', 'max:255'],
            'project' => ['nullable', 'string', 'max:255'],
            'requested_by' => ['nullable', 'string', 'max:255'],
            'issued_by' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'change' => ['nullable', 'numeric', 'min:0'],
            'global_discount' => ['nullable', 'numeric', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.product_id.exists' => 'The selected product does not exist.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.unit_price.min' => 'Unit price must be at least 0.',
            'items.*.discount.min' => 'Discount must be at least 0.',
            'issued_by.exists' => 'The selected issuer does not exist.',
        ];
    }
}
