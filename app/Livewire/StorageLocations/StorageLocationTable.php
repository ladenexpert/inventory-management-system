<?php

namespace App\Livewire\StorageLocations;

use App\Exceptions\StorageLocationException;
use App\Livewire\Concerns\AuthorizesComponentPermissions;
use App\Models\StorageLocation;
use App\Services\StorageLocationService;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class StorageLocationTable extends PowerGridComponent
{
    use AuthorizesComponentPermissions;

    public string $tableName = 'storage-location-table';
    public string $sortField = 'code';
    public string $sortDirection = 'asc';

    public function setUp(): array
    {
        if ($this->canDeleteMasterData()) {
            $this->showCheckBox();
        }

        return [
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()->showPerPage(perPage: 10, perPageValues: [10, 25, 50, 100])->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return StorageLocation::query()->with('parent');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('code')
            ->add('name')
            ->add('type_label', fn (StorageLocation $model) => $model->type ? str($model->type)->headline()->toString() : '-')
            ->add('parent_name', fn (StorageLocation $model) => $model->parent?->display_label ?? '-')
            ->add('is_active_label', fn (StorageLocation $model) => $model->is_active ? 'Active' : 'Inactive');
    }

    public function columns(): array
    {
        return [
            Column::make('Code', 'code')->sortable()->searchable(),
            Column::make('Name', 'name')->sortable()->searchable(),
            Column::make('Type', 'type_label', 'type')->sortable()->searchable(),
            Column::make('Parent', 'parent_name')->searchable(),
            Column::make('Status', 'is_active_label', 'is_active')->sortable(),
            Column::action('Action'),
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
                    'component' => 'storage-locations.storage-location-table',
                    'method' => 'bulkDelete',
                    'title' => 'Delete Selected Storage Locations?',
                    'description' => 'Selected storage locations will be soft-deleted. Locations referenced by batches or receipts will remain protected by existing constraints.',
                    'confirmButtonText' => 'Delete Selected',
                    'confirmButtonClass' => 'bg-red-600 text-white hover:bg-red-700',
                ]),
        ];
    }

    public function actions(StorageLocation $row): array
    {
        return [
            Button::add('edit')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>')
                ->class('bg-amber-500 hover:bg-amber-600 text-white p-2 rounded-md flex items-center justify-center')
                ->dispatch('edit-storage-location', ['location' => $row->id])
                ->tooltip('Edit Storage Location')
                ->can(fn () => $this->canUpdateMasterData()),

            Button::add('delete')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>')
                ->class('bg-red-500 hover:bg-red-600 text-white p-2 rounded-md flex items-center justify-center')
                ->dispatch('open-delete-modal', [
                    'component' => 'storage-locations.storage-location-table',
                    'method' => 'delete',
                    'params' => ['rowId' => $row->id],
                    'title' => 'Delete Storage Location?',
                    'description' => "Are you sure you want to delete storage location '{$row->display_label}'? This action cannot be undone.",
                ])
                ->tooltip('Delete Storage Location')
                ->can(fn () => $this->canDeleteMasterData()),
        ];
    }

    #[\Livewire\Attributes\On('delete')]
    public function delete($rowId, StorageLocationService $service): void
    {
        $this->authorizePermission('master_data', 'delete');

        $location = StorageLocation::find($rowId);

        if (!$location) {
            return;
        }

        try {
            $service->deleteLocation($location);
            $this->dispatch('toast', message: 'Storage location deleted successfully.', type: 'success');
        } catch (\Throwable $e) {
            $message = $e instanceof StorageLocationException
                ? $e->getMessage()
                : 'Failed to delete storage location: ' . $e->getMessage();

            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    #[\Livewire\Attributes\On('bulkDelete')]
    public function bulkDelete(StorageLocationService $service): void
    {
        $this->authorizePermission('master_data', 'delete');

        $selectedIds = collect($this->checkboxValues)->filter()->values();

        if ($selectedIds->isEmpty()) {
            $this->dispatch('toast', message: 'No storage locations selected.', type: 'warning');
            return;
        }

        $deleted = 0;
        $failed = 0;

        foreach (StorageLocation::whereIn('id', $selectedIds)->get() as $location) {
            try {
                $service->deleteLocation($location);
                $deleted++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $this->checkboxValues = [];

        $message = "Storage locations deleted: {$deleted}.";
        if ($failed > 0) {
            $message .= " Failed: {$failed}.";
        }

        $this->dispatch('toast', message: $message, type: $failed > 0 ? 'warning' : 'success');
    }

    private function canUpdateMasterData(): bool
    {
        return $this->hasPermission('master_data', 'update');
    }

    private function canDeleteMasterData(): bool
    {
        return $this->hasPermission('master_data', 'delete');
    }
}
