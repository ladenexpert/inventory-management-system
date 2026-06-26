<x-app-layout title="Teams">
    @php
        $user = auth()->user();
        $canImportMasterData = $user?->hasPermission('master_data', 'import') ?? false;
        $canCreateMasterData = $user?->hasPermission('master_data', 'create') ?? false;
        $canUpdateMasterData = $user?->hasPermission('master_data', 'update') ?? false;
    @endphp
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">{{ __('Teams') }}</h2>
                <p class="text-sm text-muted-foreground mt-1">Operational team master data for material usage and future approval, warehouse, and dashboard modules.</p>
            </div>
            <div class="flex items-center gap-2">
                @if($canImportMasterData)
                    <x-secondary-button :href="route('master-imports.template', 'teams')">Download Template</x-secondary-button>
                    <x-secondary-button :href="route('master-imports.show', 'teams')">Import Excel</x-secondary-button>
                @endif
                @if($canCreateMasterData)
                    <x-primary-button x-data x-on:click="$dispatch('create-team')">Create Team</x-primary-button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:teams.team-table />
        </div>
    </div>

    @if($canCreateMasterData || $canUpdateMasterData)
        <livewire:teams.team-form />
    @endif
</x-app-layout>
