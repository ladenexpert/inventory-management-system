<x-app-layout title="Batches">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Batch Monitoring') }}
                </h2>
                <p class="text-sm text-muted-foreground mt-1">Track expired and near-expiry inventory by batch number.</p>
            </div>
            <x-secondary-button href="{{ route('products.index') }}">
                {{ __('Back to Products') }}
            </x-secondary-button>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:batches.batch-table />
        </div>
    </div>
</x-app-layout>
