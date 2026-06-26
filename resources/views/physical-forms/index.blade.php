<x-app-layout title="Physical Forms">
    @php
        $user = auth()->user();
        $canImportMasterData = $user?->hasPermission('master_data', 'import') ?? false;
        $canCreateMasterData = $user?->hasPermission('master_data', 'create') ?? false;
        $canUpdateMasterData = $user?->hasPermission('master_data', 'update') ?? false;
    @endphp
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">{{ __('Physical Forms') }}</h2>
                <p class="text-sm text-muted-foreground mt-1">Reusable material form master data for RNI today and future ERP modules later.</p>
            </div>
            <div class="flex items-center gap-2">
                @if($canImportMasterData)
                    <x-secondary-button :href="route('master-imports.template', 'physical-forms')">Download Template</x-secondary-button>
                    <x-secondary-button :href="route('master-imports.show', 'physical-forms')">Import Excel</x-secondary-button>
                @endif
                @if($canCreateMasterData)
                    <x-primary-button x-data x-on:click="$dispatch('create-physical-form')">Create Physical Form</x-primary-button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:physical-forms.physical-form-table />
        </div>
    </div>

    @if($canCreateMasterData || $canUpdateMasterData)
        <livewire:physical-forms.physical-form-form />
    @endif
</x-app-layout>
