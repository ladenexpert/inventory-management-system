<div>
    <x-modal name="physical-form-form-modal" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="save" class="p-6 space-y-4">
            <h2 class="text-lg font-medium text-gray-900">{{ $isEditing ? 'Edit Physical Form' : 'Create Physical Form' }}</h2>

            <div>
                <x-input-label for="physical_form_code" :value="__('Code')" />
                <x-text-input id="physical_form_code" wire:model="code" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="physical_form_name" :value="__('Name')" />
                <x-text-input id="physical_form_name" wire:model="name" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="physical_form_description" :value="__('Description')" />
                <textarea id="physical_form_description" wire:model="description" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <label class="inline-flex items-center gap-2">
                <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 shadow-sm" />
                <span class="text-sm text-gray-700">Active</span>
            </label>

            <div class="flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'physical-form-form-modal' })">Cancel</x-secondary-button>
                <x-primary-button type="submit">{{ $isEditing ? 'Update' : 'Save' }}</x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
