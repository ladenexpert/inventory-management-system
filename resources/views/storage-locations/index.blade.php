<x-app-layout title="Storage Locations">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Storage Locations') }}
                </h2>
                <p class="text-sm text-muted-foreground mt-1">Simple room, rack, shelf, and bin master data for RNI inventory.</p>
            </div>
            <div class="flex items-center gap-2">
                <x-secondary-button :href="route('master-imports.template', 'storage-locations')">
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4 mr-2" />
                    {{ __('Download Template') }}
                </x-secondary-button>
                <x-secondary-button :href="route('master-imports.show', 'storage-locations')">
                    <x-heroicon-o-arrow-up-tray class="w-4 h-4 mr-2" />
                    {{ __('Import Excel') }}
                </x-secondary-button>
                <x-primary-button x-data x-on:click="$dispatch('create-storage-location')">
                    <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                    {{ __('Create Location') }}
                </x-primary-button>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:storage-locations.storage-location-table />
        </div>
    </div>

    <livewire:storage-locations.storage-location-form />
</x-app-layout>
