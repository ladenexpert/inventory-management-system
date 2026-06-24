<?php

namespace App\Livewire\Reports;

use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Services\BatchPolicyService;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;

final class ExpiryReportTable extends PowerGridComponent
{
    use WithExport;

    public string $tableName = 'expiry-report-table';
    public string $sortField = 'expiry_date';
    public string $sortDirection = 'asc';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        return [
            PowerGrid::exportable('expiry_report_' . now()->format('Y_m_d'))
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage(perPage: 10, perPageValues: [10, 25, 50, 100])
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return Batch::query()
            ->with('product.unit')
            ->whereNotNull('expiry_date');
    }

    public function fields(): PowerGridFields
    {
        $policy = app(BatchPolicyService::class);

        return PowerGrid::fields()
            ->add('id')
            ->add('sku', fn (Batch $model) => $model->product?->sku_display ?? '-')
            ->add('item_code_ierp', fn (Batch $model) => $model->product?->item_code_ierp_display ?? '-')
            ->add('material_name', fn (Batch $model) => $model->product?->name ?? '-')
            ->add('batch_number')
            ->add('quantity', fn (Batch $model) => (int) $model->available_quantity)
            ->add('unit', fn (Batch $model) => $model->product?->unit?->symbol ?? $model->product?->unit?->name ?? '-')
            ->add('expiry', fn (Batch $model) => $model->expiry_date?->format('d/m/Y') ?? '-')
            ->add('storage_location', fn (Batch $model) => $model->resolved_storage_location)
            ->add('status', fn (Batch $model) => $policy->getStatus($model)->label())
            ->add('status_value', fn (Batch $model) => $policy->getStatus($model)->value)
            ->add('days_remaining', function (Batch $model) {
                $days = now()->startOfDay()->diffInDays($model->expiry_date, false);

                return $days;
            })
            ->add('days_remaining_label', function (Batch $model) {
                $days = now()->startOfDay()->diffInDays($model->expiry_date, false);

                return $days >= 0 ? $days . ' days' : abs($days) . ' days overdue';
            });
    }

    public function columns(): array
    {
        return [
            Column::make('SKU', 'sku')->searchable()->sortable(),
            Column::make('Item Code IERP', 'item_code_ierp')->searchable()->sortable(),
            Column::make('Material / Product Name', 'material_name')->searchable()->sortable(),
            Column::make('Batch', 'batch_number')->searchable()->sortable(),
            Column::make('Qty', 'quantity', 'available_quantity')->sortable()->bodyAttribute('text-center'),
            Column::make('Unit', 'unit'),
            Column::make('Storage Location', 'storage_location')->searchable()->sortable(),
            Column::make('Expiry', 'expiry', 'expiry_date')->sortable(),
            Column::make('Status', 'status')->sortable(),
            Column::make('Days Remaining', 'days_remaining_label', 'days_remaining')->sortable(),
        ];
    }

    public function datasourceFilters(Builder $query): Builder
    {
        return $query;
    }

    public function beforeSearch(): void
    {
        //
    }
}
