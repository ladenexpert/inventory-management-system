<?php

namespace App\Livewire\Reports;

use App\Models\Product;
use App\Services\StockMovementClassificationService;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;

final class StockMovementClassificationTable extends PowerGridComponent
{
    use WithExport;

    public string $tableName = 'stock-movement-classification-table';
    public string $sortField = 'classification_rank';
    public string $sortDirection = 'asc';

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
                PowerGrid::exportable('stock_movement_classification_' . now()->format('Y_m_d'))
                    ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV)
            );
        }

        return $setUp;
    }

    public function datasource(): Builder
    {
        return app(StockMovementClassificationService::class)->query();
    }

    public function fields(): PowerGridFields
    {
        $service = app(StockMovementClassificationService::class);
        $fields = PowerGrid::fields()
            ->add('id')
            ->add('classification_label', fn (object $row) => $service->classificationLabel($row->classification))
            ->add('sku', fn (object $row) => $row->sku ?: '-')
            ->add('item_code', fn (object $row) => $row->item_code_ierp ?: '-')
            ->add('product_name', fn (object $row) => $row->product_name)
            ->add('physical_form_label', fn (object $row) => $service->physicalFormLabel($row->physical_form))
            ->add('stock_available', fn (object $row) => (int) $row->stock_available)
            ->add('unit', fn (object $row) => $row->unit ?: '-')
            ->add('last_usage_date_formatted', fn (object $row) => $row->last_usage_date ? \Carbon\Carbon::parse($row->last_usage_date)->format('d/m/Y') : 'No usage')
            ->add('days_since_last_usage', fn (object $row) => $service->daysSinceMovement($row->movement_basis_date))
            ->add('days_since_last_usage_label', function (object $row) use ($service) {
                $days = $service->daysSinceMovement($row->movement_basis_date);

                return $days === null ? 'Unclassified' : $days . ' days';
            })
            ->add('usage_qty_90_days', fn (object $row) => (int) $row->usage_qty_90_days)
            ->add('usage_qty_180_days', fn (object $row) => (int) $row->usage_qty_180_days)
            ->add('usage_qty_365_days', fn (object $row) => (int) $row->usage_qty_365_days)
            ->add('batch_count', fn (object $row) => (int) $row->batch_count)
            ->add('earliest_expiry_date_formatted', fn (object $row) => $row->earliest_expiry_date ? \Carbon\Carbon::parse($row->earliest_expiry_date)->format('d/m/Y') : 'No expiry')
            ->add('storage_location_summary', fn (object $row) => $row->storage_location_summary
                ? implode(', ', array_filter(array_map('trim', explode(',', $row->storage_location_summary))))
                : '-')
            ->add('status_label', fn (object $row) => $service->lifecycleStatusLabel($row->earliest_expiry_date));

        if ($this->canViewSensitiveValues()) {
            $fields->add('inventory_value_formatted', fn (object $row) => format_money((int) $row->inventory_value));
        }

        return $fields;
    }

    public function columns(): array
    {
        $columns = [
            Column::make('Classification', 'classification_label', 'classification_rank')->sortable(),
            Column::make('Item Code IERP', 'item_code', 'item_code_ierp')->searchable()->sortable(),
            Column::make('SKU', 'sku')->searchable()->sortable(),
            Column::make('RM Name', 'product_name', 'products.name')->searchable()->sortable(),
            Column::make('Physical Form', 'physical_form_label', 'physical_form')->sortable(),
            Column::make('Stock Available', 'stock_available')->sortable()->bodyAttribute('text-center'),
            Column::make('Unit', 'unit'),
            Column::make('Last Usage Date', 'last_usage_date_formatted', 'last_usage_date')->sortable(),
            Column::make('Days Since Last Usage', 'days_since_last_usage_label', 'days_since_last_usage')->sortable(),
            Column::make('Usage Qty 90 Days', 'usage_qty_90_days')->sortable()->bodyAttribute('text-center'),
            Column::make('Usage Qty 180 Days', 'usage_qty_180_days')->sortable()->bodyAttribute('text-center'),
            Column::make('Usage Qty 365 Days', 'usage_qty_365_days')->sortable()->bodyAttribute('text-center'),
            Column::make('Batch Count', 'batch_count')->sortable()->bodyAttribute('text-center'),
            Column::make('Earliest Expiry', 'earliest_expiry_date_formatted', 'earliest_expiry_date')->sortable(),
            Column::make('Storage Locations', 'storage_location_summary'),
            Column::make('Status', 'status_label')->sortable(),
        ];

        if ($this->canViewSensitiveValues()) {
            $columns[] = Column::make('Inventory Value', 'inventory_value_formatted')
                ->bodyAttribute('text-right');
        }

        return $columns;
    }

    public function filters(): array
    {
        $classificationOptions = collect(app(StockMovementClassificationService::class)->classificationOptions())
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values();

        $physicalFormOptions = collect(Product::physicalFormOptions())
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values();

        return [
            Filter::select('classification_label', 'classification')
                ->dataSource($classificationOptions)
                ->optionLabel('label')
                ->optionValue('value')
                ->builder(fn (Builder $query, string $value) => app(StockMovementClassificationService::class)->applyClassificationFilter($query, $value)),
            Filter::select('physical_form_label', 'physical_form')
                ->dataSource($physicalFormOptions)
                ->optionLabel('label')
                ->optionValue('value'),
            Filter::datepicker('last_usage_date_formatted', 'last_usage_date')
                ->params([
                    'enableTime' => false,
                    'dateFormat' => 'Y-m-d',
                    'altInput' => true,
                    'altFormat' => 'd/m/Y',
                ]),
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
