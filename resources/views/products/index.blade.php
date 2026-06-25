<x-app-layout title="Materials">
    @php
        $user = auth()->user();
        $canManageMaterials = $user?->hasPermission('materials', 'create');
        $canImportMasterData = $user?->hasPermission('master_data', 'import');
        $canImportOpeningStock = $user?->hasPermission('opening_stock', 'import');
    @endphp
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __('Materials') }}
            </h2>
            <div class="flex items-center gap-2">
                @if($canImportMasterData)
                    <x-secondary-button :href="route('master-imports.template', 'materials')">
                        <x-heroicon-o-arrow-down-tray class="w-4 h-4 mr-2" />
                        {{ __('Download Template') }}
                    </x-secondary-button>
                    <x-secondary-button :href="route('master-imports.show', 'materials')">
                        <x-heroicon-o-arrow-up-tray class="w-4 h-4 mr-2" />
                        {{ __('Import Excel') }}
                    </x-secondary-button>
                @endif
                @if($canImportOpeningStock)
                    <x-secondary-button :href="route('products.import-opening-stock')">
                        <x-heroicon-o-arrow-up-tray class="w-4 h-4 mr-2" />
                        {{ __('Upload Opening Stock') }}
                    </x-secondary-button>
                @endif
                @if($canManageMaterials)
                    <x-primary-button x-data x-on:click="$dispatch('create-product')">
                        <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                        {{ __('Create Material') }}
                    </x-primary-button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:products.product-table />
        </div>
    </div>

    <livewire:products.product-form />
    <livewire:products.product-detail />
</x-app-layout>
