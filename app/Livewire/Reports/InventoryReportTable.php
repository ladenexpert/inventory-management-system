<?php

namespace App\Livewire\Reports;

use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Services\BatchPolicyService;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;

final class InventoryReportTable extends PowerGridComponent
{
    use WithExport;

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
        return Batch::query()
            ->with(['product.unit', 'product.supplier', 'purchase.supplier', 'storageLocationRecord'])
            ->when($this->preset === 'expiry', fn (Builder $query) => $query->whereNotNull('expiry_date'));
    }

    public function fields(): PowerGridFields
    {
        $policy = app(BatchPolicyService::class);
        $fields = PowerGrid::fields()
            ->add('id')
            ->add('product_name', fn (Batch $model) => $model->product?->name ?? '-')
            ->add('sku', fn (Batch $model) => $model->product?->sku_display ?? '-')
            ->add('item_code_ierp', fn (Batch $model) => $model->product?->item_code_ierp_display ?? '-')
            ->add('batch_number')
            ->add('uom', fn (Batch $model) => $model->product?->unit?->symbol ?? $model->product?->unit?->name ?? '-')
            ->add('physical_form', fn (Batch $model) => $model->product?->physical_form_label ?? '-')
            ->add('supplier_name', fn (Batch $model) => $model->purchase?->supplier?->name ?? $model->product?->supplier?->name ?? '-')
            ->add('storage_location', fn (Batch $model) => $model->resolved_storage_location)
            ->add('quantity', fn (Batch $model) => (int) $model->available_quantity)
            ->add('expiry', fn (Batch $model) => $model->expiry_date?->format('d/m/Y') ?? 'No expiry')
            ->add('expiry_bucket', fn () => '')
            ->add('status', fn (Batch $model) => $policy->getStatus($model)->label())
            ->add('status_value', fn (Batch $model) => $policy->getStatus($model)->value)
            ->add('days_remaining_sort', function (Batch $model) {
                if (!$model->expiry_date) {
                    return 99999;
                }

                return now()->startOfDay()->diffInDays($model->expiry_date, false);
            })
            ->add('days_remaining', function (Batch $model) {
                if (!$model->expiry_date) {
                    return 'No expiry';
                }

                $days = now()->startOfDay()->diffInDays($model->expiry_date, false);

                return $days >= 0 ? $days . ' days' : abs($days) . ' days overdue';
            });

        if ($this->canViewSensitiveValues()) {
            $fields->add('value', fn (Batch $model) => format_money($policy->inventoryValue($model)));
        }

        return $fields;
    }

    public function columns(): array
    {
        $columns = [
            Column::make('SKU', 'sku')->searchable()->sortable(),
            Column::make('Item Code IERP', 'item_code_ierp')->searchable()->sortable(),
            Column::make('Material / Product Name', 'product_name')->searchable()->sortable(),
            Column::make('Batch', 'batch_number')->searchable()->sortable(),
            Column::make('Unit', 'uom'),
            Column::make('Physical Form', 'physical_form')->searchable()->sortable(),
            Column::make('Supplier', 'supplier_name')->searchable(),
            Column::make('Storage Location', 'storage_location')->searchable()->sortable(),
            Column::make('Qty', 'quantity', 'available_quantity')->sortable()->bodyAttribute('text-center'),
            Column::make('Expiry', 'expiry', 'expiry_date')->sortable(),
            Column::make('Days Remaining', 'days_remaining', 'days_remaining_sort')->sortable(),
            Column::make('Status', 'status')->sortable(),
        ];

        if ($this->canViewSensitiveValues()) {
            array_splice($columns, 10, 0, [
                Column::make('Value', 'value')->bodyAttribute('text-right'),
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
            'product' => ['name', 'sku', 'item_code_ierp', 'physical_form'],
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
}
