<x-app-layout title="Inventory & Expiry Monitoring">
    @php
        $canViewSensitiveValues = (auth()->user()?->canViewInventoryValue() ?? false) || (auth()->user()?->canAccessFinance() ?? false);
    @endphp
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-foreground leading-tight">{{ __('Inventory & Expiry Monitoring') }}</h2>
            <p class="text-sm text-muted-foreground mt-1">
                {{ $canViewSensitiveValues
                    ? 'Track live stock, expiry risk, days remaining, and inventory value with one permission-aware exportable table.'
                    : 'Track live stock, expiry risk, and days remaining with one permission-aware exportable table.' }}
            </p>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:reports.inventory-report-table preset="inventory" />
        </div>
    </div>
</x-app-layout>
