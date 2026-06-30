<x-app-layout title="Edit Material Receipt">
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __('Edit Material Receipt') }}
            </h2>
            <x-secondary-button href="{{ route('material-receipts.show', $purchase) }}" class="w-full justify-center sm:w-auto">
                &larr; {{ __('Back to Detail') }}
            </x-secondary-button>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form action="{{ route('purchases.update', $purchase) }}" method="POST" enctype="multipart/form-data"
                x-data="purchaseForm({
                    items: {{ Js::from(old('items', $purchase->items->map(fn ($item) => [
                        'key' => 'existing-' . $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'product_code' => 'SKU: ' . $item->product->sku_display . ' | Item Code: ' . $item->product->item_code_ierp_display,
                        'batch_number' => $item->batch_number,
                        'expiry_date' => optional($item->expiry_date)->format('Y-m-d'),
                        'storage_location' => $item->storage_location,
                        'storage_location_id' => $item->storage_location_id,
                        'storage_location_label' => $item->storageLocation?->display_label ?? $item->storage_location,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'selling_price' => $item->selling_price,
                        'subtotal' => $item->subtotal,
                    ]))) }},
                    supplier_id: {{ Js::from(old('supplier_id', $purchase->supplier_id)) }},
                    status: {{ Js::from(old('status', $purchase->status->value)) }},
                    errors: {{ Js::from($errors->any() ? $errors->toArray() : []) }}
                })"
                @submit.prevent="submitForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="context" value="material_receipt">
                <input type="hidden" name="entry_context" value="material_receipt">

                @include('purchases.form', ['context' => 'material_receipt'])
            </form>
        </div>
    </div>
</x-app-layout>
