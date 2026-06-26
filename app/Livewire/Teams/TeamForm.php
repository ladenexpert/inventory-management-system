<?php

namespace App\Livewire\Teams;

use App\DTOs\TeamData;
use App\Exceptions\TeamException;
use App\Livewire\Concerns\AuthorizesComponentPermissions;
use App\Models\Team;
use App\Services\TeamService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class TeamForm extends Component
{
    use AuthorizesComponentPermissions;

    public bool $isEditing = false;
    public ?Team $team = null;

    public string $code = '';
    public string $name = '';
    public string $description = '';
    public bool $is_active = true;

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('teams', 'code')->ignore($this->team?->id)],
            'name' => ['required', 'string', 'max:150', Rule::unique('teams', 'name')->ignore($this->team?->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    public function render()
    {
        return view('livewire.teams.team-form');
    }

    #[On('create-team')]
    public function create(): void
    {
        $this->authorizePermission('master_data', 'create');

        $this->reset(['code', 'name', 'description', 'team', 'isEditing']);
        $this->is_active = true;

        $this->dispatch('open-modal', name: 'team-form-modal');
    }

    #[On('edit-team')]
    public function edit(Team $team): void
    {
        $this->authorizePermission('master_data', 'update');

        $this->team = $team;
        $this->code = $team->code;
        $this->name = $team->name;
        $this->description = $team->description ?? '';
        $this->is_active = $team->is_active;
        $this->isEditing = true;

        $this->dispatch('open-modal', name: 'team-form-modal');
    }

    public function save(TeamService $service): void
    {
        $this->authorizePermission('master_data', $this->isEditing ? 'update' : 'create');

        $validated = $this->validate();
        $data = TeamData::fromArray($validated);

        try {
            if ($this->isEditing && $this->team) {
                $service->updateTeam($this->team, $data);
                $message = 'Team updated successfully.';
            } else {
                $service->createTeam($data);
                $message = 'Team created successfully.';
            }

            $this->dispatch('close-modal', name: 'team-form-modal');
            $this->dispatch('pg:eventRefresh-team-table');
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (TeamException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        } catch (\Throwable) {
            $this->dispatch('toast', message: 'An unexpected error occurred.', type: 'error');
        }
    }
}
