<?php

namespace App\Livewire\PhysicalForms;

use App\DTOs\PhysicalFormData;
use App\Exceptions\PhysicalFormException;
use App\Livewire\Concerns\AuthorizesComponentPermissions;
use App\Models\PhysicalForm;
use App\Services\PhysicalFormService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class PhysicalFormForm extends Component
{
    use AuthorizesComponentPermissions;

    public bool $isEditing = false;
    public ?PhysicalForm $physicalForm = null;

    public string $code = '';
    public string $name = '';
    public string $description = '';
    public bool $is_active = true;

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('physical_forms', 'code')->ignore($this->physicalForm?->id)],
            'name' => ['required', 'string', 'max:100', Rule::unique('physical_forms', 'name')->ignore($this->physicalForm?->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    public function render()
    {
        return view('livewire.physical-forms.physical-form-form');
    }

    #[On('create-physical-form')]
    public function create(): void
    {
        $this->authorizePermission('master_data', 'create');

        $this->reset(['code', 'name', 'description', 'physicalForm', 'isEditing']);
        $this->is_active = true;

        $this->dispatch('open-modal', name: 'physical-form-form-modal');
    }

    #[On('edit-physical-form')]
    public function edit(PhysicalForm $physicalForm): void
    {
        $this->authorizePermission('master_data', 'update');

        $this->physicalForm = $physicalForm;
        $this->code = $physicalForm->code;
        $this->name = $physicalForm->name;
        $this->description = $physicalForm->description ?? '';
        $this->is_active = $physicalForm->is_active;
        $this->isEditing = true;

        $this->dispatch('open-modal', name: 'physical-form-form-modal');
    }

    public function save(PhysicalFormService $service): void
    {
        $this->authorizePermission('master_data', $this->isEditing ? 'update' : 'create');

        $validated = $this->validate();
        $data = PhysicalFormData::fromArray($validated);

        try {
            if ($this->isEditing && $this->physicalForm) {
                $service->updatePhysicalForm($this->physicalForm, $data);
                $message = 'Physical form updated successfully.';
            } else {
                $service->createPhysicalForm($data);
                $message = 'Physical form created successfully.';
            }

            $this->dispatch('close-modal', name: 'physical-form-form-modal');
            $this->dispatch('pg:eventRefresh-physical-form-table');
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (PhysicalFormException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        } catch (\Throwable) {
            $this->dispatch('toast', message: 'An unexpected error occurred.', type: 'error');
        }
    }
}
