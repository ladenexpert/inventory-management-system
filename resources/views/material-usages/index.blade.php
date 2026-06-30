<x-app-layout title="Material Usage">
    @php
        $canCreateUsage = auth()->user()?->hasPermission('material_usage', 'create') ?? false;
    @endphp
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Material Usage') }}
                </h2>
                <p class="text-sm text-muted-foreground mt-1">Issue raw materials with FEFO-aware batch allocation and exportable history.</p>
            </div>
            @if($canCreateUsage)
                <x-primary-button x-data x-on:click="window.location.href = '{{ route('material-usages.create') }}'" class="w-full justify-center sm:w-auto">
                    <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                    {{ __('Create Usage') }}
                </x-primary-button>
            @endif
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:material-usages.material-usage-table />
        </div>
    </div>
</x-app-layout>
