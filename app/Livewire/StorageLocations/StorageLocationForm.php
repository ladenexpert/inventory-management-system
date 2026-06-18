<?php

namespace App\Livewire\StorageLocations;

use App\DTOs\StorageLocationData;
use App\Exceptions\StorageLocationException;
use App\Models\StorageLocation;
use App\Services\StorageLocationService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class StorageLocationForm extends Component
{
    public bool $isEditing = false;
    public ?StorageLocation $location = null;

    public string $code = '';
    public string $name = '';
    public ?string $type = null;
    public ?int $parent_id = null;
    public string $description = '';
    public bool $is_active = true;

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('storage_locations', 'code')->ignore($this->location?->id)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['nullable', 'in:room,rack,shelf,bin,other'],
            'parent_id' => ['nullable', 'exists:storage_locations,id', Rule::notIn([$this->location?->id])],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    public function render()
    {
        return view('livewire.storage-locations.storage-location-form', [
            'parentOptions' => StorageLocation::query()
                ->when($this->location, fn ($query) => $query->whereKeyNot($this->location->id))
                ->orderBy('code')
                ->get(),
            'typeOptions' => [
                'room' => 'Room',
                'rack' => 'Rack',
                'shelf' => 'Shelf',
                'bin' => 'Bin',
                'other' => 'Other',
            ],
        ]);
    }

    #[On('create-storage-location')]
    public function create(): void
    {
        $this->reset(['code', 'name', 'type', 'parent_id', 'description', 'location', 'isEditing']);
        $this->is_active = true;

        $this->dispatch('open-modal', name: 'storage-location-form-modal');
    }

    #[On('edit-storage-location')]
    public function edit(StorageLocation $location): void
    {
        $this->location = $location;
        $this->code = $location->code;
        $this->name = $location->name;
        $this->type = $location->type;
        $this->parent_id = $location->parent_id;
        $this->description = $location->description ?? '';
        $this->is_active = $location->is_active;
        $this->isEditing = true;

        $this->dispatch('open-modal', name: 'storage-location-form-modal');
    }

    public function save(StorageLocationService $service): void
    {
        $validated = $this->validate();
        $data = StorageLocationData::fromArray($validated);

        try {
            if ($this->isEditing && $this->location) {
                $service->updateLocation($this->location, $data);
                $message = 'Storage location updated successfully.';
            } else {
                $service->createLocation($data);
                $message = 'Storage location created successfully.';
            }

            $this->dispatch('close-modal', name: 'storage-location-form-modal');
            $this->dispatch('pg:eventRefresh-storage-location-table');
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (StorageLocationException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        } catch (\Throwable) {
            $this->dispatch('toast', message: 'An unexpected error occurred.', type: 'error');
        }
    }
}
