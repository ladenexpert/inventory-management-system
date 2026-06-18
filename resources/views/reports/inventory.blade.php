<x-app-layout title="Current Inventory Report">
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-foreground leading-tight">{{ __('Current Inventory Report') }}</h2>
            <p class="text-sm text-muted-foreground mt-1">RM, batch, quantity, expiry, value, and lifecycle status in one exportable view.</p>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:reports.inventory-report-table />
        </div>
    </div>
</x-app-layout>
