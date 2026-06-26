<x-app-layout title="Usage Report">
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-foreground leading-tight">{{ __('Usage Report') }}</h2>
            <p class="text-sm text-muted-foreground mt-1">Detailed outbound material usage with export, filters, search, and a saved report-table view per user.</p>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:reports.usage-history-table />
        </div>
    </div>
</x-app-layout>
