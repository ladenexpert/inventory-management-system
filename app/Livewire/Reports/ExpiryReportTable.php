<?php

namespace App\Livewire\Reports;

use App\Enums\BatchStatus;
use App\Livewire\Concerns\BuildsBatchPowerGridSql;
use App\Livewire\Concerns\HandlesPowerGridExportSorting;
use App\Models\Batch;
use App\Services\BatchPolicyService;
use App\Support\RmpTerminology;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;

final class ExpiryReportTable extends PowerGridComponent
{
    use BuildsBatchPowerGridSql;
    use HandlesPowerGridExportSorting;
    use WithExport {
        HandlesPowerGridExportSorting::prepareToExport insteadof WithExport;
        WithExport::prepareToExport as protected powerGridPrepareToExport;
    }

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
        $this->normalizePowerGridSortingState();

        $nearExpiryDays = app(BatchPolicyService::class)->nearExpiryThresholdDays();

        return Batch::query()
            ->select([
                'batches.*',
                'products.sku as sku',
                'products.item_code_ierp as item_code_ierp',
                'products.name as material_name',
                DB::raw($this->unitExpression() . ' as unit'),
                DB::raw($this->storageLocationExpression() . ' as storage_location_label'),
                DB::raw($this->expiryDisplayExpression(false) . ' as expiry'),
                DB::raw($this->batchStatusLabelExpression($nearExpiryDays) . ' as status'),
                DB::raw($this->batchStatusSortExpression($nearExpiryDays) . ' as status_sort'),
                DB::raw($this->daysRemainingSortExpression() . ' as days_remaining'),
                DB::raw($this->daysRemainingLabelExpression() . ' as days_remaining_label'),
            ])
            ->leftJoin('products', 'products.id', '=', 'batches.product_id')
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->leftJoin('storage_locations', 'storage_locations.id', '=', 'batches.storage_location_id')
            ->whereNotNull('expiry_date');
    }

    public function fields(): PowerGridFields
    {
        $policy = app(BatchPolicyService::class);

        return PowerGrid::fields()
            ->add('id')
            ->add('sku', fn (Batch $model) => $model->sku ?? $model->product?->sku_display ?? '-')
            ->add('item_code_ierp', fn (Batch $model) => $model->item_code_ierp ?? $model->product?->item_code_ierp_display ?? '-')
            ->add('material_name', fn (Batch $model) => $model->material_name ?? $model->product?->name ?? '-')
            ->add('batch_number')
            ->add('quantity', fn (Batch $model) => (int) $model->available_quantity)
            ->add('unit', fn (Batch $model) => $model->unit ?? $model->product?->unit?->symbol ?? $model->product?->unit?->name ?? '-')
            ->add('expiry', fn (Batch $model) => $model->expiry ?? $model->expiry_date?->format('d/m/Y') ?? '-')
            ->add('storage_location', fn (Batch $model) => $model->storage_location_label ?? $model->resolved_storage_location)
            ->add('status', fn (Batch $model) => $model->status ?? $policy->getStatus($model)->label())
            ->add('status_value', fn (Batch $model) => $policy->getStatus($model)->value)
            ->add('days_remaining', fn (Batch $model) => (int) ($model->days_remaining ?? now()->startOfDay()->diffInDays($model->expiry_date, false)))
            ->add('days_remaining_label', fn (Batch $model) => $model->days_remaining_label ?? ((now()->startOfDay()->diffInDays($model->expiry_date, false) >= 0) ? now()->startOfDay()->diffInDays($model->expiry_date, false) . ' days' : abs(now()->startOfDay()->diffInDays($model->expiry_date, false)) . ' days overdue'));
    }

    public function columns(): array
    {
        $nearExpiryDays = app(BatchPolicyService::class)->nearExpiryThresholdDays();

        return [
            Column::make('SKU', 'sku', 'products.sku')->searchable()->sortable(),
            Column::make(RmpTerminology::ITEM_CODE, 'item_code_ierp', 'products.item_code_ierp')->searchable()->sortable(),
            Column::make(RmpTerminology::MATERIAL_NAME, 'material_name', 'products.name')->searchable()->sortable(),
            Column::make('Batch No', 'batch_number', 'batches.batch_number')->searchable()->sortable(),
            Column::make(RmpTerminology::STOCK_AVAILABLE, 'quantity', 'batches.available_quantity')
                ->sortable()
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderBy('batches.available_quantity', $direction))
                ->bodyAttribute('text-center'),
            Column::make(RmpTerminology::UNIT, 'unit'),
            Column::make(RmpTerminology::STORAGE_LOCATION, 'storage_location')
                ->searchableRaw('LOWER(' . $this->storageLocationExpression() . ') like ?')
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderByRaw($this->storageLocationExpression() . " {$direction}")),
            Column::make(RmpTerminology::EXPIRY_DATE, 'expiry', 'batches.expiry_date')->sortable(),
            Column::make(RmpTerminology::STATUS, 'status', 'status_sort')
                ->sortable()
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderByRaw($this->batchStatusSortExpression($nearExpiryDays) . " {$direction}")),
            Column::make(RmpTerminology::DAYS_REMAINING, 'days_remaining_label', 'days_remaining')
                ->sortable()
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderByRaw($this->daysRemainingSortExpression() . " {$direction}")),
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

    protected function legacyPowerGridSortFieldMap(): array
    {
        return [
            'batch_number' => 'batches.batch_number',
            'days_remaining_label' => 'days_remaining',
            'expiry' => 'batches.expiry_date',
            'item_code_ierp' => 'products.item_code_ierp',
            'material_name' => 'products.name',
            'product_name' => 'products.name',
            'quantity' => 'batches.available_quantity',
            'sku' => 'products.sku',
            'status' => 'status_sort',
            'storage_location_label' => 'storage_location',
        ];
    }
}
