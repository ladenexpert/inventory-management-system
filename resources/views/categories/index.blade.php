<x-app-layout title="Categories">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __('Categories') }}
            </h2>
            <div class="flex items-center gap-2">
                <x-secondary-button :href="route('master-imports.template', 'categories')">
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4 mr-2" />
                    {{ __('Download Template') }}
                </x-secondary-button>
                <x-secondary-button :href="route('master-imports.show', 'categories')">
                    <x-heroicon-o-arrow-up-tray class="w-4 h-4 mr-2" />
                    {{ __('Import Excel') }}
                </x-secondary-button>
                <x-primary-button x-data x-on:click="$dispatch('create-category')">
                    <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                    {{ __('Create Category') }}
                </x-primary-button>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:categories.category-table />
        </div>
    </div>

    <livewire:categories.category-form />
    <livewire:categories.category-detail />
</x-app-layout>
