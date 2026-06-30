<x-app-layout title="Material Receipt">
    @php
        $canCreateReceipt = auth()->user()?->hasPermission('material_receipt', 'create') ?? false;
    @endphp
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Material Receipt') }}
                </h2>
                <p class="text-sm text-muted-foreground mt-1">Receive raw materials into batch stock without changing the underlying receipt rules.</p>
            </div>
            @if($canCreateReceipt)
                <x-primary-button x-data x-on:click="window.location.href = '{{ route('material-receipts.create') }}'" class="w-full justify-center sm:w-auto">
                    <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                    {{ __('Create Receipt') }}
                </x-primary-button>
            @endif
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:purchases.purchase-table :context="\App\Support\TransactionContext::MATERIAL_RECEIPT" />
        </div>
    </div>

    <livewire:components.delete-modal />
</x-app-layout>
