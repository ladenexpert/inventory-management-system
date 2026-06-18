<x-app-layout title="Create Material Receipt">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __('Create Material Receipt') }}
            </h2>
            <x-secondary-button href="{{ route('material-receipts.index') }}">
                &larr; {{ __('Back to List') }}
            </x-secondary-button>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form action="{{ route('purchases.store') }}" method="POST" enctype="multipart/form-data"
                x-data="purchaseForm({
                    items: {{ Js::from(old('items', [])) }},
                    supplier_id: {{ Js::from(old('supplier_id')) }},
                    status: {{ Js::from(old('status', 'draft')) }},
                    errors: {{ Js::from($errors->any() ? $errors->toArray() : []) }}
                })"
                @submit.prevent="submitForm">
                @csrf
                <input type="hidden" name="context" value="material_receipt">
                <input type="hidden" name="entry_context" value="material_receipt">

                @include('purchases.form', ['context' => 'material_receipt'])
            </form>
        </div>
    </div>
</x-app-layout>
