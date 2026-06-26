<?php

namespace App\Livewire\Teams;

use App\Exceptions\TeamException;
use App\Livewire\Concerns\AuthorizesComponentPermissions;
use App\Models\Team;
use App\Services\TeamService;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;

final class TeamTable extends PowerGridComponent
{
    use WithExport {
        exportToCsv as protected powerGridExportToCsv;
        exportToXLS as protected powerGridExportToXLS;
    }
    use AuthorizesComponentPermissions;

    public string $tableName = 'team-table';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    public function setUp(): array
    {
        if ($this->canDeleteMasterData()) {
            $this->showCheckBox();
        }

        $setUp = [
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()->showPerPage(perPage: 10, perPageValues: [10, 25, 50, 100])->showRecordCount(),
        ];

        if ($this->canExportMasterData()) {
            array_unshift(
                $setUp,
                PowerGrid::exportable('teams_export_' . now()->format('Y_m_d'))
                    ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV)
            );
        }

        return $setUp;
    }

    public function datasource(): Builder
    {
        return Team::query();
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('code')
            ->add('name')
            ->add('description')
            ->add('status_label', fn (Team $model) => $model->is_active ? 'Active' : 'Inactive');
    }

    public function columns(): array
    {
        return [
            Column::make('Code', 'code')->sortable()->searchable(),
            Column::make('Name', 'name')->sortable()->searchable(),
            Column::make('Description', 'description')->searchable(),
            Column::make('Status', 'status_label', 'is_active')->sortable(),
            Column::action('Action'),
        ];
    }

    public function actions(Team $row): array
    {
        return [
            Button::add('edit')
                ->slot('Edit')
                ->class('bg-amber-500 hover:bg-amber-600 text-white px-3 py-2 rounded-md text-sm')
                ->dispatch('edit-team', ['team' => $row->id])
                ->can(fn () => $this->canUpdateMasterData()),
            Button::add('delete')
                ->slot('Delete')
                ->class('bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-md text-sm')
                ->dispatch('open-delete-modal', [
                    'component' => 'teams.team-table',
                    'method' => 'delete',
                    'params' => ['rowId' => $row->id],
                    'title' => 'Delete Team?',
                    'description' => "Are you sure you want to delete team '{$row->name}'? This action cannot be undone.",
                ])
                ->can(fn () => $this->canDeleteMasterData()),
        ];
    }

    public function header(): array
    {
        if (!$this->canDeleteMasterData()) {
            return [];
        }

        return [
            Button::add('delete-selected')
                ->slot('Delete Selected')
                ->class('bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-md font-medium text-sm')
                ->dispatch('open-delete-modal', [
                    'component' => 'teams.team-table',
                    'method' => 'bulkDelete',
                    'title' => 'Delete Selected Teams?',
                    'description' => 'Selected teams will be soft-deleted. Historical material usage remains preserved through fallback project text.',
                    'confirmButtonText' => 'Delete Selected',
                    'confirmButtonClass' => 'bg-red-600 text-white hover:bg-red-700',
                ]),
        ];
    }

    #[\Livewire\Attributes\On('delete')]
    public function delete($rowId, TeamService $service): void
    {
        $this->authorizePermission('master_data', 'delete');

        $team = Team::find($rowId);

        if (!$team) {
            return;
        }

        try {
            $service->deleteTeam($team);
            $this->dispatch('toast', message: 'Team deleted successfully.', type: 'success');
        } catch (\Throwable $e) {
            $message = $e instanceof TeamException
                ? $e->getMessage()
                : 'Failed to delete team: ' . $e->getMessage();

            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    #[\Livewire\Attributes\On('bulkDelete')]
    public function bulkDelete(TeamService $service): void
    {
        $this->authorizePermission('master_data', 'delete');

        $selectedIds = collect($this->checkboxValues)->filter()->values();

        if ($selectedIds->isEmpty()) {
            $this->dispatch('toast', message: 'No teams selected.', type: 'warning');
            return;
        }

        $deleted = 0;
        $failed = 0;

        foreach (Team::whereIn('id', $selectedIds)->get() as $team) {
            try {
                $service->deleteTeam($team);
                $deleted++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $this->checkboxValues = [];
        $message = "Teams deleted: {$deleted}.";
        if ($failed > 0) {
            $message .= " Failed: {$failed}.";
        }

        $this->dispatch('toast', message: $message, type: $failed > 0 ? 'warning' : 'success');
    }

    public function exportToXLS(bool $selected = false): \Symfony\Component\HttpFoundation\BinaryFileResponse|bool
    {
        $this->authorizePermission('master_data', 'export');

        return $this->powerGridExportToXLS($selected);
    }

    public function exportToCsv(bool $selected = false): \Symfony\Component\HttpFoundation\BinaryFileResponse|bool
    {
        $this->authorizePermission('master_data', 'export');

        return $this->powerGridExportToCsv($selected);
    }

    private function canUpdateMasterData(): bool
    {
        return $this->hasPermission('master_data', 'update');
    }

    private function canDeleteMasterData(): bool
    {
        return $this->hasPermission('master_data', 'delete');
    }

    private function canExportMasterData(): bool
    {
        return $this->hasPermission('master_data', 'export');
    }
}
