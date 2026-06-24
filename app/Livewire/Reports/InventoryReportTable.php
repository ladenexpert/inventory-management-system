<?php

namespace App\Livewire\Reports;

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

    public string $tableName = 'inventory-report-table';
    public string $sortField = 'expiry_date';
    public string $sortDirection = 'asc';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        return [
            PowerGrid::exportable('inventory_report_' . now()->format('Y_m_d'))
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage(perPage: 10, perPageValues: [10, 25, 50, 100])
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return Batch::query()->with(['product.unit', 'product.supplier', 'purchase.supplier', 'storageLocationRecord']);
    }

    public function fields(): PowerGridFields
    {
        $policy = app(BatchPolicyService::class);

        return PowerGrid::fields()
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
            ->add('value', fn (Batch $model) => format_money($policy->inventoryValue($model)))
            ->add('status', fn (Batch $model) => $policy->getStatus($model)->label());
    }

    public function columns(): array
    {
        return [
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
            Column::make('Value', 'value')->bodyAttribute('text-right'),
            Column::make('Status', 'status')->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
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
}
