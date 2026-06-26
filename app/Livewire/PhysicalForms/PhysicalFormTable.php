<?php

namespace App\Livewire\PhysicalForms;

use App\Exceptions\PhysicalFormException;
use App\Livewire\Concerns\AuthorizesComponentPermissions;
use App\Models\PhysicalForm;
use App\Services\PhysicalFormService;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;

final class PhysicalFormTable extends PowerGridComponent
{
    use WithExport {
        exportToCsv as protected powerGridExportToCsv;
        exportToXLS as protected powerGridExportToXLS;
    }
    use AuthorizesComponentPermissions;

    public string $tableName = 'physical-form-table';
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
                PowerGrid::exportable('physical_forms_export_' . now()->format('Y_m_d'))
                    ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV)
            );
        }

        return $setUp;
    }

    public function datasource(): Builder
    {
        return PhysicalForm::query();
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('code')
            ->add('name')
            ->add('description')
            ->add('status_label', fn (PhysicalForm $model) => $model->is_active ? 'Active' : 'Inactive');
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

    public function actions(PhysicalForm $row): array
    {
        return [
            Button::add('edit')
                ->slot('Edit')
                ->class('bg-amber-500 hover:bg-amber-600 text-white px-3 py-2 rounded-md text-sm')
                ->dispatch('edit-physical-form', ['physicalForm' => $row->id])
                ->can(fn () => $this->canUpdateMasterData()),
            Button::add('delete')
                ->slot('Delete')
                ->class('bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-md text-sm')
                ->dispatch('open-delete-modal', [
                    'component' => 'physical-forms.physical-form-table',
                    'method' => 'delete',
                    'params' => ['rowId' => $row->id],
                    'title' => 'Delete Physical Form?',
                    'description' => "Are you sure you want to delete physical form '{$row->name}'? This action cannot be undone.",
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
                    'component' => 'physical-forms.physical-form-table',
                    'method' => 'bulkDelete',
                    'title' => 'Delete Selected Physical Forms?',
                    'description' => 'Selected physical forms will be soft-deleted. Materials linked to them will retain their historical value text.',
                    'confirmButtonText' => 'Delete Selected',
                    'confirmButtonClass' => 'bg-red-600 text-white hover:bg-red-700',
                ]),
        ];
    }

    #[\Livewire\Attributes\On('delete')]
    public function delete($rowId, PhysicalFormService $service): void
    {
        $this->authorizePermission('master_data', 'delete');

        $physicalForm = PhysicalForm::find($rowId);

        if (!$physicalForm) {
            return;
        }

        try {
            $service->deletePhysicalForm($physicalForm);
            $this->dispatch('toast', message: 'Physical form deleted successfully.', type: 'success');
        } catch (\Throwable $e) {
            $message = $e instanceof PhysicalFormException
                ? $e->getMessage()
                : 'Failed to delete physical form: ' . $e->getMessage();

            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    #[\Livewire\Attributes\On('bulkDelete')]
    public function bulkDelete(PhysicalFormService $service): void
    {
        $this->authorizePermission('master_data', 'delete');

        $selectedIds = collect($this->checkboxValues)->filter()->values();

        if ($selectedIds->isEmpty()) {
            $this->dispatch('toast', message: 'No physical forms selected.', type: 'warning');
            return;
        }

        $deleted = 0;
        $failed = 0;

        foreach (PhysicalForm::whereIn('id', $selectedIds)->get() as $physicalForm) {
            try {
                $service->deletePhysicalForm($physicalForm);
                $deleted++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $this->checkboxValues = [];
        $message = "Physical forms deleted: {$deleted}.";
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
