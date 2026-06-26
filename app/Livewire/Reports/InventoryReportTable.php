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
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;

final class InventoryReportTable extends PowerGridComponent
{
    use BuildsBatchPowerGridSql;
    use HandlesPowerGridExportSorting;
    use WithExport {
        HandlesPowerGridExportSorting::prepareToExport insteadof WithExport;
        WithExport::prepareToExport as protected powerGridPrepareToExport;
    }

    public string $tableName = 'inventory-expiry-monitoring-table';
    public string $sortField = 'expiry_date';
    public string $sortDirection = 'asc';
    public string $preset = 'inventory';

    public function mount(string $preset = 'inventory'): void
    {
        $this->preset = $preset;
        $this->tableName = $preset === 'expiry'
            ? 'inventory-expiry-monitoring-expiry-table'
            : 'inventory-expiry-monitoring-table';

        parent::mount();
    }

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        $this->persist(['columns', 'filters', 'sorting'], (string) (auth()->id() ?? 'guest'));

        $setUp = [
            PowerGrid::header()
                ->showSearchInput()
                ->showToggleColumns(),
            PowerGrid::footer()
                ->showPerPage(perPage: 10, perPageValues: [10, 25, 50, 100])
                ->showRecordCount(),
        ];

        if ($this->canExportReport()) {
            array_unshift(
                $setUp,
                PowerGrid::exportable('inventory_expiry_monitoring_' . now()->format('Y_m_d'))
                    ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV)
            );
        }

        return $setUp;
    }

    public function datasource(): Builder
    {
        $this->normalizePowerGridSortingState();

        $nearExpiryDays = app(BatchPolicyService::class)->nearExpiryThresholdDays();

        return Batch::query()
            ->select([
                'batches.*',
                'products.name as product_name',
                'products.sku as sku',
                'products.item_code_ierp as item_code_ierp',
                DB::raw($this->unitExpression() . ' as uom'),
                DB::raw($this->physicalFormExpression() . ' as physical_form'),
                DB::raw($this->supplierNameExpression() . ' as supplier_name'),
                DB::raw($this->storageLocationExpression() . ' as storage_location_label'),
                DB::raw($this->expiryDisplayExpression() . ' as expiry'),
                DB::raw($this->daysRemainingSortExpression() . ' as days_remaining_sort'),
                DB::raw($this->daysRemainingLabelExpression() . ' as days_remaining'),
                DB::raw($this->batchStatusLabelExpression($nearExpiryDays) . ' as status'),
                DB::raw($this->batchStatusSortExpression($nearExpiryDays) . ' as status_sort'),
            ])
            ->leftJoin('products', 'products.id', '=', 'batches.product_id')
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->leftJoin('physical_forms', 'physical_forms.id', '=', 'products.physical_form_id')
            ->leftJoin('purchases', 'purchases.id', '=', 'batches.purchase_id')
            ->leftJoin('suppliers as purchase_suppliers', 'purchase_suppliers.id', '=', 'purchases.supplier_id')
            ->leftJoin('suppliers as product_suppliers', 'product_suppliers.id', '=', 'products.supplier_id')
            ->leftJoin('storage_locations', 'storage_locations.id', '=', 'batches.storage_location_id')
            ->when($this->preset === 'expiry', fn (Builder $query) => $query->whereNotNull('expiry_date'));
    }

    public function fields(): PowerGridFields
    {
        $policy = app(BatchPolicyService::class);
        $fields = PowerGrid::fields()
            ->add('id')
            ->add('product_name', fn (Batch $model) => $model->product_name ?? $model->product?->name ?? '-')
            ->add('sku', fn (Batch $model) => $model->sku ?? $model->product?->sku_display ?? '-')
            ->add('item_code_ierp', fn (Batch $model) => $model->item_code_ierp ?? $model->product?->item_code_ierp_display ?? '-')
            ->add('batch_number')
            ->add('uom', fn (Batch $model) => $model->uom ?? $model->product?->unit?->symbol ?? $model->product?->unit?->name ?? '-')
            ->add('physical_form', fn (Batch $model) => $model->physical_form ?? $model->product?->physical_form_label ?? '-')
            ->add('supplier_name', fn (Batch $model) => $model->supplier_name ?? $model->purchase?->supplier?->name ?? $model->product?->supplier?->name ?? '-')
            ->add('storage_location', fn (Batch $model) => $model->storage_location_label ?? $model->resolved_storage_location)
            ->add('quantity', fn (Batch $model) => (int) $model->available_quantity)
            ->add('expiry', fn (Batch $model) => $model->expiry ?? $model->expiry_date?->format('d/m/Y') ?? 'No expiry')
            ->add('expiry_bucket', fn () => '')
            ->add('status', fn (Batch $model) => $model->status ?? $policy->getStatus($model)->label())
            ->add('status_value', fn (Batch $model) => $policy->getStatus($model)->value)
            ->add('days_remaining_sort', fn (Batch $model) => (int) ($model->days_remaining_sort ?? ($model->expiry_date ? now()->startOfDay()->diffInDays($model->expiry_date, false) : 99999)))
            ->add('days_remaining', fn (Batch $model) => $model->days_remaining ?? ($model->expiry_date ? (now()->startOfDay()->diffInDays($model->expiry_date, false) >= 0 ? now()->startOfDay()->diffInDays($model->expiry_date, false) . ' days' : abs(now()->startOfDay()->diffInDays($model->expiry_date, false)) . ' days overdue') : 'No expiry'));

        if ($this->canViewSensitiveValues()) {
            $fields->add('value', fn (Batch $model) => format_money($policy->inventoryValue($model)));
        }

        return $fields;
    }

    public function columns(): array
    {
        $nearExpiryDays = app(BatchPolicyService::class)->nearExpiryThresholdDays();

        $columns = [
            Column::make('SKU', 'sku', 'products.sku')->searchable()->sortable(),
            Column::make(RmpTerminology::ITEM_CODE, 'item_code_ierp', 'products.item_code_ierp')->searchable()->sortable(),
            Column::make(RmpTerminology::MATERIAL_NAME, 'product_name', 'products.name')->searchable()->sortable(),
            Column::make('Batch No', 'batch_number', 'batches.batch_number')->searchable()->sortable(),
            Column::make('Unit', 'uom'),
            Column::make('Physical Form', 'physical_form')
                ->searchableRaw('LOWER(' . $this->physicalFormExpression() . ') like ?')
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderByRaw($this->physicalFormExpression() . " {$direction}")),
            Column::make('Supplier', 'supplier_name')
                ->searchableRaw('LOWER(' . $this->supplierNameExpression() . ') like ?'),
            Column::make(RmpTerminology::STORAGE_LOCATION, 'storage_location')
                ->searchableRaw('LOWER(' . $this->storageLocationExpression() . ') like ?')
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderByRaw($this->storageLocationExpression() . " {$direction}")),
            Column::make(RmpTerminology::STOCK_AVAILABLE, 'quantity', 'batches.available_quantity')
                ->sortable()
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderBy('batches.available_quantity', $direction))
                ->bodyAttribute('text-center'),
            Column::make(RmpTerminology::EXPIRY_DATE, 'expiry', 'batches.expiry_date')->sortable(),
            Column::make(RmpTerminology::DAYS_REMAINING, 'days_remaining', 'days_remaining_sort')
                ->sortable()
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderByRaw($this->daysRemainingSortExpression() . " {$direction}")),
            Column::make(RmpTerminology::STATUS, 'status', 'status_sort')
                ->sortable()
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderByRaw($this->batchStatusSortExpression($nearExpiryDays) . " {$direction}")),
        ];

        if ($this->canViewSensitiveValues()) {
            array_splice($columns, 10, 0, [
                Column::make(RmpTerminology::INVENTORY_VALUE, 'value')->bodyAttribute('text-right'),
            ]);
        }

        return $columns;
    }

    public function filters(): array
    {
        $policy = app(BatchPolicyService::class);
        $nearExpiryDays = $policy->nearExpiryThresholdDays();

        return [
            Filter::select('status_value', 'expiry_bucket')
                ->dataSource([
                    ['label' => 'Active', 'value' => BatchStatus::ACTIVE->value],
                    ['label' => "Near Expiry ({$nearExpiryDays} days)", 'value' => BatchStatus::NEAR_EXPIRY->value],
                    ['label' => 'Expired', 'value' => BatchStatus::EXPIRED->value],
                    ['label' => 'Depleted', 'value' => BatchStatus::DEPLETED->value],
                    ['label' => 'Quarantined', 'value' => BatchStatus::QUARANTINED->value],
                    ['label' => 'No Expiry', 'value' => 'no_expiry'],
                ])
                ->optionLabel('label')
                ->optionValue('value')
                ->builder(function (Builder $query, string $value) use ($nearExpiryDays) {
                    $today = now()->startOfDay();
                    $until = $today->copy()->addDays($nearExpiryDays)->endOfDay();

                    match ($value) {
                        BatchStatus::ACTIVE->value => $query
                            ->where('source', '!=', BatchStatus::QUARANTINED->value)
                            ->where('available_quantity', '>', 0)
                            ->where(function (Builder $builder) use ($today, $until) {
                                $builder
                                    ->whereNull('expiry_date')
                                    ->orWhereDate('expiry_date', '>', $until);
                            }),
                        BatchStatus::NEAR_EXPIRY->value => $query
                            ->where('source', '!=', BatchStatus::QUARANTINED->value)
                            ->where('available_quantity', '>', 0)
                            ->whereDate('expiry_date', '>=', $today)
                            ->whereDate('expiry_date', '<=', $until),
                        BatchStatus::EXPIRED->value => $query
                            ->where('source', '!=', BatchStatus::QUARANTINED->value)
                            ->where('available_quantity', '>', 0)
                            ->whereDate('expiry_date', '<', $today),
                        BatchStatus::DEPLETED->value => $query->where('available_quantity', '<=', 0),
                        BatchStatus::QUARANTINED->value => $query->where('source', BatchStatus::QUARANTINED->value),
                        'no_expiry' => $query
                            ->where('available_quantity', '>', 0)
                            ->whereNull('expiry_date'),
                        default => $query,
                    };
                }),
            Filter::multiSelectAsync('storage_location', 'storage_location_id')
                ->url(route('ajax.storage-locations.search'))
                ->method('POST')
                ->optionValue('value')
                ->optionLabel('text'),
            Filter::datepicker('expiry', 'expiry_date')
                ->params([
                    'enableTime' => false,
                    'dateFormat' => 'Y-m-d',
                    'altInput' => true,
                    'altFormat' => 'd/m/Y',
                ]),
        ];
    }

    public function relationSearch(): array
    {
        return [
            'product' => ['name', 'sku', 'item_code_ierp'],
        ];
    }

    private function canViewSensitiveValues(): bool
    {
        $user = auth()->user();

        return ($user?->canViewInventoryValue() ?? false)
            || ($user?->canAccessFinance() ?? false);
    }

    private function canExportReport(): bool
    {
        return auth()->user()?->hasPermission('reports', 'export') ?? false;
    }

    protected function legacyPowerGridSortFieldMap(): array
    {
        return [
            'batch_number' => 'batches.batch_number',
            'days_remaining' => 'days_remaining_sort',
            'expiry' => 'batches.expiry_date',
            'item_code_ierp' => 'products.item_code_ierp',
            'material_name' => 'products.name',
            'physical_form_label' => 'physical_form',
            'product_name' => 'products.name',
            'quantity' => 'batches.available_quantity',
            'sku' => 'products.sku',
            'status' => 'status_sort',
            'storage_location_label' => 'storage_location',
        ];
    }
}
