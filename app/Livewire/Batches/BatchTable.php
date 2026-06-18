<?php

namespace App\Livewire\Batches;

use App\Models\Batch;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;

final class BatchTable extends PowerGridComponent
{
    use WithExport;

    public string $tableName = 'batch-table';
    public string $sortField = 'expiry_date';
    public string $sortDirection = 'asc';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        return [
            PowerGrid::exportable('batch_export_' . now()->format('Y_m_d'))
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),

            PowerGrid::header()
                ->showSearchInput(),

            PowerGrid::footer()
                ->showPerPage(perPage: 10, perPageValues: [10, 25, 50, 100])
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return Batch::query()
            ->with(['product.unit', 'purchase'])
            ->when(empty($this->sortField), fn($query) => $query->orderBy('expiry_date'));
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('batch_number')
            ->add('product_name', fn(Batch $model) => $model->product?->name ?? '-')
            ->add('product_sku', fn(Batch $model) => $model->product?->sku ?? '-')
            ->add('product_item_code_ierp', fn(Batch $model) => $model->product?->item_code_ierp ?? '-')
            ->add('product_uom', fn(Batch $model) => $model->product?->unit?->symbol ?? $model->product?->unit?->name ?? '-')
            ->add('purchase_invoice', fn(Batch $model) => $model->purchase?->invoice_number ?? '-')
            ->add('available_quantity')
            ->add('quantity')
            ->add('unit_cost_formatted', fn(Batch $model) => format_money($model->unit_cost))
            ->add('selling_price_formatted', fn(Batch $model) => format_money((float) ($model->selling_price ?? 0)))
            ->add('source_label', fn(Batch $model) => str($model->source)->headline())
            ->add('expiry_date_formatted', fn(Batch $model) => $model->expiry_date?->format('d/m/Y') ?? 'No expiry')
            ->add('expiry_date_sort', fn(Batch $model) => $model->expiry_date?->format('Y-m-d') ?? '9999-12-31')
            ->add('expiry_bucket', fn() => '')
            ->add('days_left', function (Batch $model) {
                if (!$model->expiry_date) {
                    return 'No expiry';
                }

                $days = now()->startOfDay()->diffInDays($model->expiry_date, false);

                return $days >= 0 ? "{$days} days" : abs($days) . ' days ago';
            })
            ->add('expiry_status', function (Batch $model) {
                if (!$model->expiry_date) {
                    return '<span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 border border-slate-200">No Expiry</span>';
                }

                if ($model->available_quantity <= 0) {
                    return '<span class="inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700 border border-zinc-200">Depleted</span>';
                }

                if ($model->expiry_date->lt(now()->startOfDay())) {
                    return '<span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 border border-red-200">Expired</span>';
                }

                if ($model->expiry_date->lte(now()->addDays(30)->endOfDay())) {
                    return '<span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 border border-amber-200">Near Expiry</span>';
                }

                return '<span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 border border-emerald-200">Safe</span>';
            });
    }

    public function columns(): array
    {
        return [
            Column::make('Batch No', 'batch_number')
                ->sortable()
                ->searchable(),

            Column::make('Product', 'product_name')
                ->sortable()
                ->searchable(),

            Column::make('SKU', 'product_sku')
                ->sortable()
                ->searchable(),

            Column::make('Item Code IERP', 'product_item_code_ierp')
                ->searchable(),

            Column::make('UOM', 'product_uom'),

            Column::make('Status', 'expiry_status', 'expiry_date')
                ->sortable()
                ->headerAttribute('text-center')
                ->bodyAttribute('text-center'),

            Column::make('Expiry Date', 'expiry_date_formatted', 'expiry_date')
                ->sortable(),

            Column::make('Days Left', 'days_left', 'expiry_date')
                ->sortable()
                ->bodyAttribute('text-center'),

            Column::make('Available', 'available_quantity')
                ->sortable()
                ->bodyAttribute('text-center'),

            Column::make('Original Qty', 'quantity')
                ->sortable()
                ->bodyAttribute('text-center'),

            Column::make('Cost', 'unit_cost_formatted', 'unit_cost')
                ->sortable()
                ->bodyAttribute('text-right'),

            Column::make('Sell Price', 'selling_price_formatted', 'selling_price')
                ->sortable()
                ->bodyAttribute('text-right'),

            Column::make('Source', 'source_label', 'source')
                ->sortable(),

            Column::make('Purchase Inv.', 'purchase_invoice', 'purchase_id')
                ->sortable()
                ->searchable(),

            Column::action('Action'),
        ];
    }

    public function relationSearch(): array
    {
        return [
            'product' => ['name', 'sku', 'item_code_ierp'],
            'purchase' => ['invoice_number'],
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('source', 'source')
                ->dataSource([
                    ['label' => 'Purchase', 'value' => 'purchase'],
                    ['label' => 'Opening Balance', 'value' => 'opening_balance'],
                    ['label' => 'Adjustment In', 'value' => 'adjustment_in'],
                    ['label' => 'Legacy Sync', 'value' => 'legacy_sync'],
                ])
                ->optionLabel('label')
                ->optionValue('value'),

            Filter::select('expiry_bucket', 'expiry_bucket')
                ->dataSource([
                    ['label' => 'Expired', 'value' => 'expired'],
                    ['label' => 'Near Expiry (30 days)', 'value' => 'near_expiry'],
                    ['label' => 'Safe', 'value' => 'safe'],
                    ['label' => 'No Expiry', 'value' => 'no_expiry'],
                    ['label' => 'Depleted', 'value' => 'depleted'],
                ])
                ->optionLabel('label')
                ->optionValue('value')
                ->builder(function (Builder $query, string $value) {
                    $today = now()->startOfDay();
                    $until = now()->addDays(30)->endOfDay();

                    match ($value) {
                        'expired' => $query
                            ->where('available_quantity', '>', 0)
                            ->whereDate('expiry_date', '<', $today),
                        'near_expiry' => $query
                            ->where('available_quantity', '>', 0)
                            ->whereDate('expiry_date', '>=', $today)
                            ->whereDate('expiry_date', '<=', $until),
                        'safe' => $query
                            ->where('available_quantity', '>', 0)
                            ->whereDate('expiry_date', '>', $until),
                        'no_expiry' => $query->whereNull('expiry_date'),
                        'depleted' => $query->where('available_quantity', '<=', 0),
                        default => $query,
                    };
                }),

            Filter::datepicker('expiry_date_formatted', 'expiry_date')
                ->params([
                    'enableTime' => false,
                    'dateFormat' => 'Y-m-d',
                    'altInput' => true,
                    'altFormat' => 'd/m/Y',
                ]),
        ];
    }

    public function actions(Batch $row): array
    {
        $actions = [];

        if ($row->purchase_id) {
            $actions[] = Button::add('purchase')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 21a8.25 8.25 0 0 0 0-16.5 8.25 8.25 0 0 0 0 16.5ZM4.5 19.5h4.5M6.75 17.25v4.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M12.75 8.25h-1.5a.75.75 0 0 0-.75.75v6a.75.75 0 0 0 .75.75h1.5a.75.75 0 0 0 .75-.75V9a.75.75 0 0 0-.75-.75Z" /></svg>')
                ->class('bg-sky-500 hover:bg-sky-600 text-white p-2 rounded-md flex items-center justify-center')
                ->route('purchases.show', ['purchase' => $row->purchase_id])
                ->tooltip('View purchase');
        }

        return $actions;
    }
}
