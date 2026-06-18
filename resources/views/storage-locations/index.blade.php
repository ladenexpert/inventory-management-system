<x-app-layout title="Storage Locations">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Storage Locations') }}
                </h2>
                <p class="text-sm text-muted-foreground mt-1">Simple room, rack, shelf, and bin master data for RNI inventory.</p>
            </div>
            <x-primary-button x-data x-on:click="$dispatch('create-storage-location')">
                <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                {{ __('Create Location') }}
            </x-primary-button>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:storage-locations.storage-location-table />
        </div>
    </div>

    <livewire:storage-locations.storage-location-form />
</x-app-layout>
