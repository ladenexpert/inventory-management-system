<x-app-layout title="Usage History Report">
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-foreground leading-tight">{{ __('Usage History Report') }}</h2>
            <p class="text-sm text-muted-foreground mt-1">Export date, SKU, Item Code IERP, material name, batch, quantity, purpose, formula, project, and issuer details.</p>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:reports.usage-history-table />
        </div>
    </div>
</x-app-layout>
