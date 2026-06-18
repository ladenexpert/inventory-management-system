<x-app-layout title="Expiry Report">
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-foreground leading-tight">{{ __('Expiry Report') }}</h2>
            <p class="text-sm text-muted-foreground mt-1">Monitor expired and near-expiry raw material batches with days remaining.</p>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:reports.expiry-report-table />
        </div>
    </div>
</x-app-layout>
