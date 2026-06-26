<x-app-layout title="Inventory & Expiry Monitoring">
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-foreground leading-tight">{{ __('Inventory & Expiry Monitoring') }}</h2>
            <p class="text-sm text-muted-foreground mt-1">Opened from the legacy Expiry Report route with expiry-focused defaults for backward compatibility.</p>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:reports.inventory-report-table preset="expiry" />
        </div>
    </div>
</x-app-layout>
