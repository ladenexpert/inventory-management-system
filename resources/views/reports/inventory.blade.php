<x-app-layout title="Current Inventory Report">
    @php
        $canViewSensitiveValues = (auth()->user()?->canViewInventoryValue() ?? false) || (auth()->user()?->canAccessFinance() ?? false);
    @endphp
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-foreground leading-tight">{{ __('Current Inventory Report') }}</h2>
            <p class="text-sm text-muted-foreground mt-1">
                {{ $canViewSensitiveValues
                    ? 'SKU, Item Code IERP, material name, batch, physical form, supplier, storage location, quantity, expiry, value, and status in one exportable view.'
                    : 'SKU, Item Code IERP, material name, batch, physical form, supplier, storage location, quantity, expiry, and status in one exportable view.' }}
            </p>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:reports.inventory-report-table />
        </div>
    </div>
</x-app-layout>
