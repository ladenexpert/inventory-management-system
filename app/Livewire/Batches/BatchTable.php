<?php

namespace App\Livewire\Batches;

use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Services\BatchPolicyService;
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
            ->when(empty($this->sortField), fn ($query) => $query->orderBy('expiry_date'));
    }

    public function fields(): PowerGridFields
    {
        $policy = app(BatchPolicyService::class);
        $nearExpiryDays = $policy->nearExpiryThresholdDays();

        return PowerGrid::fields()
            ->add('id')
            ->add('batch_number')
            ->add('product_name', fn (Batch $model) => $model->product?->name ?? '-')
            ->add('product_sku', fn (Batch $model) => $model->product?->sku ?? '-')
            ->add('product_item_code_ierp', fn (Batch $model) => $model->product?->item_code_ierp ?? '-')
            ->add('physical_form_label', fn (Batch $model) => $model->product?->physical_form_label ?? '-')
            ->add('product_uom', fn (Batch $model) => $model->product?->unit?->symbol ?? $model->product?->unit?->name ?? '-')
            ->add('purchase_invoice', fn (Batch $model) => $model->purchase?->invoice_number ?? '-')
            ->add('storage_location', fn (Batch $model) => $model->storage_location ?? '-')
            ->add('available_quantity')
            ->add('quantity')
            ->add('unit_cost_formatted', fn (Batch $model) => format_money($model->unit_cost))
            ->add('inventory_value_formatted', fn (Batch $model) => format_money($policy->inventoryValue($model)))
            ->add('selling_price_formatted', fn (Batch $model) => format_money((float) ($model->selling_price ?? 0)))
            ->add('source_label', fn (Batch $model) => str($model->source)->headline())
            ->add('expiry_date_formatted', fn (Batch $model) => $model->expiry_date?->format('d/m/Y') ?? 'No expiry')
            ->add('expiry_date_sort', fn (Batch $model) => $model->expiry_date?->format('Y-m-d') ?? '9999-12-31')
            ->add('expiry_bucket', fn () => '')
            ->add('days_left', function (Batch $model) {
                if (!$model->expiry_date) {
                    return 'No expiry';
                }

                $days = now()->startOfDay()->diffInDays($model->expiry_date, false);

                return $days >= 0 ? "{$days} days" : abs($days) . ' days ago';
            })
            ->add('lifecycle_status', function (Batch $model) use ($policy) {
                $status = $policy->getStatus($model);
                $badgeClass = match ($status) {
                    BatchStatus::ACTIVE => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    BatchStatus::NEAR_EXPIRY => 'bg-amber-50 text-amber-700 border-amber-200',
                    BatchStatus::EXPIRED => 'bg-red-50 text-red-700 border-red-200',
                    BatchStatus::DEPLETED => 'bg-zinc-100 text-zinc-700 border-zinc-200',
                    BatchStatus::QUARANTINED => 'bg-violet-50 text-violet-700 border-violet-200',
                };

                return sprintf(
                    '<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium border %s">%s</span>',
                    $badgeClass,
                    e($status->label()),
                );
            })
            ->add('lifecycle_status_value', fn (Batch $model) => $policy->getStatus($model)->value)
            ->add('near_expiry_label', fn () => "Near Expiry ({$nearExpiryDays} days)")
            ->add('is_zero_cost', fn (Batch $model) => (int) $model->unit_cost === 0 ? 'Yes' : 'No');
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

            Column::make('Physical Form', 'physical_form_label')
                ->sortable()
                ->searchable(),

            Column::make('UOM', 'product_uom'),

            Column::make('Storage Location', 'storage_location')
                ->sortable()
                ->searchable(),

            Column::make('Status', 'lifecycle_status', 'expiry_date')
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

            Column::make('Inventory Value', 'inventory_value_formatted')
                ->sortable(false)
                ->bodyAttribute('text-right'),

            Column::make('Zero Cost', 'is_zero_cost', 'unit_cost')
                ->sortable(false)
                ->bodyAttribute('text-center'),

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
            'product' => ['name', 'sku', 'item_code_ierp', 'physical_form'],
            'purchase' => ['invoice_number'],
        ];
    }

    public function filters(): array
    {
        $policy = app(BatchPolicyService::class);
        $nearExpiryDays = $policy->nearExpiryThresholdDays();

        return [
            Filter::select('source', 'source')
                ->dataSource([
                    ['label' => 'Purchase', 'value' => 'purchase'],
                    ['label' => 'Opening Balance', 'value' => 'opening_balance'],
                    ['label' => 'Adjustment In', 'value' => 'adjustment_in'],
                    ['label' => 'Legacy Sync', 'value' => 'legacy_sync'],
                    ['label' => 'Sale Cancel Restore', 'value' => 'sale_cancel_restore'],
                    ['label' => 'Quarantined', 'value' => 'quarantined'],
                ])
                ->optionLabel('label')
                ->optionValue('value'),

            Filter::select('expiry_bucket', 'expiry_bucket')
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
                            ->where('source', '!=', 'quarantined')
                            ->where('available_quantity', '>', 0)
                            ->where(function (Builder $builder) use ($today, $until) {
                                $builder
                                    ->whereNull('expiry_date')
                                    ->orWhereDate('expiry_date', '>', $until);
                            }),
                        BatchStatus::NEAR_EXPIRY->value => $query
                            ->where('source', '!=', 'quarantined')
                            ->where('available_quantity', '>', 0)
                            ->whereDate('expiry_date', '>=', $today)
                            ->whereDate('expiry_date', '<=', $until),
                        BatchStatus::EXPIRED->value => $query
                            ->where('source', '!=', 'quarantined')
                            ->where('available_quantity', '>', 0)
                            ->whereDate('expiry_date', '<', $today),
                        BatchStatus::DEPLETED->value => $query->where('available_quantity', '<=', 0),
                        BatchStatus::QUARANTINED->value => $query->where('source', 'quarantined'),
                        'no_expiry' => $query
                            ->where('available_quantity', '>', 0)
                            ->whereNull('expiry_date'),
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
