<x-modal name="storage-location-form-modal" :title="''">
    <div class="p-6">
        <div class="mb-6 space-y-1.5 text-center sm:text-left border-b border-gray-200 pb-4">
            <h3 class="text-lg font-semibold leading-none tracking-tight text-foreground">
                {{ $isEditing ? 'Edit Storage Location' : 'Create Storage Location' }}
            </h3>
            <p class="text-sm text-muted-foreground">
                {{ $isEditing ? 'Update the storage location details below.' : 'Add a simple room, rack, shelf, or bin for inventory use.' }}
            </p>
        </div>

        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-form-input
                    name="code"
                    label="Code"
                    type="text"
                    wire:model="code"
                    placeholder="e.g. RM-A1"
                    required
                />

                <x-form-input
                    name="name"
                    label="Name"
                    type="text"
                    wire:model="name"
                    placeholder="e.g. Raw Material Rack A1"
                    required
                />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <x-input-label for="type" :value="__('Type')" />
                    <select id="type" wire:model="type" class="block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">Select type</option>
                        @foreach($typeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('type')" />
                </div>

                <div class="space-y-2">
                    <x-input-label for="parent_id" :value="__('Parent Location')" />
                    <select id="parent_id" wire:model="parent_id" class="block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">No parent</option>
                        @foreach($parentOptions as $parent)
                            <option value="{{ $parent->id }}">{{ $parent->display_label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('parent_id')" />
                </div>
            </div>

            <div class="space-y-2">
                <x-input-label for="description" :value="__('Description')" />
                <textarea
                    id="description"
                    wire:model="description"
                    rows="3"
                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    placeholder="Optional description..."
                ></textarea>
                <x-input-error :messages="$errors->get('description')" />
            </div>

            <div class="flex items-center h-full">
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="is_active" class="w-5 h-5 rounded border-2 border-primary text-primary focus:ring-primary/20">
                    <span class="ml-3 text-sm font-medium text-gray-700">Active</span>
                </label>
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t border-gray-200 pt-4">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'storage-location-form-modal' })">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-primary-button type="submit" wire:loading.attr="disabled">
                    <svg wire:loading wire:target="save" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <x-heroicon-o-check wire:loading.remove wire:target="save" class="w-4 h-4 mr-2" />
                    {{ $isEditing ? __('Save Changes') : __('Create Location') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-modal>
