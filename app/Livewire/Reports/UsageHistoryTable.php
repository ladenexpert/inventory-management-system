<?php

namespace App\Livewire\Reports;

use App\Enums\SaleTransactionType;
use App\Enums\SaleStatus;
use App\Models\SaleItemBatch;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;

final class UsageHistoryTable extends PowerGridComponent
{
    use WithExport;

    public string $tableName = 'usage-history-table';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        return [
            PowerGrid::exportable('usage_history_' . now()->format('Y_m_d'))
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage(perPage: 10, perPageValues: [10, 25, 50, 100])
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return SaleItemBatch::query()
            ->with(['batch.product.unit', 'saleItem.product.unit', 'saleItem.sale.creator', 'saleItem.sale.issuer'])
            ->whereHas('saleItem.sale', function (Builder $query) {
                $query
                    ->where('transaction_type', SaleTransactionType::MATERIAL_USAGE->value)
                    ->where('status', '!=', SaleStatus::CANCELLED->value);

                if (auth()->user()?->isFormulator()) {
                    $query->where(function (Builder $nested) {
                        $nested
                            ->where('issued_by', auth()->id())
                            ->orWhere('created_by', auth()->id());
                    });
                }
            });
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('usage_number', fn (SaleItemBatch $model) => $model->saleItem->sale->invoice_number ?? '-')
            ->add('usage_date', fn (SaleItemBatch $model) => optional($model->saleItem->sale->usage_date ?? $model->saleItem->sale->sale_date)->format('d/m/Y'))
            ->add('sku', fn (SaleItemBatch $model) => $model->batch?->product?->sku_display ?? $model->saleItem->product?->sku_display ?? '-')
            ->add('item_code_ierp', fn (SaleItemBatch $model) => $model->batch?->product?->item_code_ierp_display ?? $model->saleItem->product?->item_code_ierp_display ?? '-')
            ->add('material_name', fn (SaleItemBatch $model) => $model->batch?->product?->name ?? $model->saleItem->product?->name ?? '-')
            ->add('batch_number', fn (SaleItemBatch $model) => $model->batch?->batch_number ?? '-')
            ->add('expiry_date', fn (SaleItemBatch $model) => $model->batch?->expiry_date?->format('d/m/Y') ?? '-')
            ->add('storage_location', fn (SaleItemBatch $model) => $model->batch?->resolved_storage_location ?? '-')
            ->add('quantity')
            ->add('unit', fn (SaleItemBatch $model) => $model->batch?->product?->unit?->symbol ?? $model->saleItem->product?->unit?->symbol ?? '-')
            ->add('purpose', fn (SaleItemBatch $model) => $model->saleItem->sale->purpose ?? '-')
            ->add('formula', fn (SaleItemBatch $model) => $model->saleItem->sale->formula ?? '-')
            ->add('project', fn (SaleItemBatch $model) => $model->saleItem->sale->project ?? '-')
            ->add('requested_by', fn (SaleItemBatch $model) => $model->saleItem->sale->requested_by ?? '-')
            ->add('issued_by_name', fn (SaleItemBatch $model) => $model->saleItem->sale->issuer->name ?? $model->saleItem->sale->creator->name ?? '-')
            ->add('notes', fn (SaleItemBatch $model) => $model->saleItem->sale->notes ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::action('Action'),
            Column::make('Usage No.', 'usage_number')->searchable()->sortable(),
            Column::make('Date', 'usage_date', 'saleItem.sale.usage_date')->sortable(),
            Column::make('SKU', 'sku')->searchable(),
            Column::make('Item Code IERP', 'item_code_ierp')->searchable(),
            Column::make('Material / Product Name', 'material_name')->searchable(),
            Column::make('Batch', 'batch_number')->searchable(),
            Column::make('Expiry Date', 'expiry_date'),
            Column::make('Storage Location', 'storage_location')->searchable(),
            Column::make('Qty', 'quantity')->sortable()->bodyAttribute('text-center'),
            Column::make('Unit', 'unit'),
            Column::make('Purpose', 'purpose')->searchable(),
            Column::make('Formula', 'formula')->searchable(),
            Column::make('Project', 'project')->searchable(),
            Column::make('Requested By', 'requested_by')->searchable(),
            Column::make('Issued By', 'issued_by_name')->searchable(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::datepicker('usage_date', 'saleItem.sale.usage_date')
                ->params([
                    'enableTime' => false,
                    'dateFormat' => 'Y-m-d',
                    'altInput' => true,
                    'altFormat' => 'd/m/Y',
                ]),
        ];
    }

    public function actions(SaleItemBatch $row): array
    {
        $sale = $row->saleItem->sale;

        return [
            Button::add('view')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>')
                ->class('bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-md flex items-center justify-center')
                ->route('material-usages.show', ['sale' => $sale?->id])
                ->tooltip('View usage'),
            Button::add('print')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5h10.5m-10.5 3h10.5m-10.5 3h4.5M6 18.75h12A2.25 2.25 0 0 0 20.25 16.5v-9A2.25 2.25 0 0 0 18 5.25H6A2.25 2.25 0 0 0 3.75 7.5v9A2.25 2.25 0 0 0 6 18.75Z" /></svg>')
                ->class('bg-indigo-500 hover:bg-indigo-600 text-white p-2 rounded-md flex items-center justify-center')
                ->route('material-usages.print', ['sale' => $sale?->id])
                ->tooltip('Print usage slip'),
        ];
    }
}
