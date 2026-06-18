<x-app-layout title="Material Usage">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Material Usage') }}
                </h2>
                <p class="text-sm text-muted-foreground mt-1">Issue raw materials with FEFO-aware batch allocation and exportable history.</p>
            </div>
            <x-primary-button x-data x-on:click="window.location.href = '{{ route('material-usages.create') }}'">
                <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                {{ __('Create Usage') }}
            </x-primary-button>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:reports.usage-history-table />
        </div>
    </div>
</x-app-layout>
