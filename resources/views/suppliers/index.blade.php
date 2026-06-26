<x-app-layout title="Suppliers">
    @php
        $user = auth()->user();
        $canImportMasterData = $user?->hasPermission('master_data', 'import') ?? false;
        $canCreateMasterData = $user?->hasPermission('master_data', 'create') ?? false;
        $canUpdateMasterData = $user?->hasPermission('master_data', 'update') ?? false;
    @endphp
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __('Suppliers') }}
            </h2>
            <div class="flex items-center gap-2">
                @if($canImportMasterData)
                    <x-secondary-button :href="route('master-imports.template', 'suppliers')">
                        <x-heroicon-o-arrow-down-tray class="w-4 h-4 mr-2" />
                        {{ __('Download Template') }}
                    </x-secondary-button>
                    <x-secondary-button :href="route('master-imports.show', 'suppliers')">
                        <x-heroicon-o-arrow-up-tray class="w-4 h-4 mr-2" />
                        {{ __('Import Excel') }}
                    </x-secondary-button>
                @endif
                @if($canCreateMasterData)
                    <x-primary-button x-data x-on:click="$dispatch('create-supplier')">
                        <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                        {{ __('Create Supplier') }}
                    </x-primary-button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:suppliers.supplier-table />
        </div>
    </div>

    @if($canCreateMasterData || $canUpdateMasterData)
        <livewire:suppliers.supplier-form />
    @endif
    <livewire:suppliers.supplier-detail />
</x-app-layout>
