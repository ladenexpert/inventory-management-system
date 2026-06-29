<?php

namespace App\Livewire\Batches;

use App\Enums\BatchStatus;
use App\Livewire\Concerns\BuildsBatchPowerGridSql;
use App\Livewire\Concerns\HandlesPowerGridExportSorting;
use App\Models\Batch;
use App\Services\BatchPolicyService;
use App\Support\RmpTerminology;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
    use BuildsBatchPowerGridSql;
    use HandlesPowerGridExportSorting;
    use WithExport {
        HandlesPowerGridExportSorting::prepareToExport insteadof WithExport;
        WithExport::prepareToExport as protected powerGridPrepareToExport;
    }

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
        $this->normalizePowerGridSortingState();

        $nearExpiryDays = app(BatchPolicyService::class)->nearExpiryThresholdDays();

        return Batch::query()
            ->select([
                'batches.*',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.item_code_ierp as product_item_code_ierp',
                'purchases.transaction_code as source_transaction_number',
                'purchases.entry_context as source_entry_context',
                DB::raw($this->physicalFormExpression() . ' as physical_form_label'),
                DB::raw($this->unitExpression() . ' as product_uom'),
                DB::raw($this->purchaseDocumentExpression() . ' as purchase_invoice'),
                DB::raw($this->storageLocationExpression() . ' as storage_location_label'),
                DB::raw($this->expiryDisplayExpression() . ' as expiry_date_formatted'),
                DB::raw($this->daysRemainingSortExpression() . ' as days_left_sort'),
                DB::raw($this->daysRemainingLabelExpression() . ' as days_left'),
                DB::raw($this->batchStatusLabelExpression($nearExpiryDays) . ' as lifecycle_status'),
                DB::raw($this->batchStatusSortExpression($nearExpiryDays) . ' as lifecycle_status_sort'),
            ])
            ->leftJoin('products', 'products.id', '=', 'batches.product_id')
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->leftJoin('physical_forms', 'physical_forms.id', '=', 'products.physical_form_id')
            ->leftJoin('purchases', 'purchases.id', '=', 'batches.purchase_id')
            ->leftJoin('suppliers as purchase_suppliers', 'purchase_suppliers.id', '=', 'purchases.supplier_id')
            ->leftJoin('suppliers as product_suppliers', 'product_suppliers.id', '=', 'products.supplier_id')
            ->leftJoin('storage_locations', 'storage_locations.id', '=', 'batches.storage_location_id')
            ->whereNotNull('products.id')
            ->whereNull('products.deleted_at')
            ->when(empty($this->sortField), fn ($query) => $query->orderBy('expiry_date'));
    }

    public function fields(): PowerGridFields
    {
        $policy = app(BatchPolicyService::class);
        $nearExpiryDays = $policy->nearExpiryThresholdDays();
        $fields = PowerGrid::fields()
            ->add('id')
            ->add('batch_number')
            ->add('product_name', fn (Batch $model) => $model->product_name ?? $model->product?->name ?? '-')
            ->add('product_sku', fn (Batch $model) => $model->product_sku ?? $model->product?->sku_display ?? '-')
            ->add('product_item_code_ierp', fn (Batch $model) => $model->product_item_code_ierp ?? $model->product?->item_code_ierp_display ?? '-')
            ->add('physical_form_label', fn (Batch $model) => $model->physical_form_label ?? $model->product?->physical_form_label ?? '-')
            ->add('product_uom', fn (Batch $model) => $model->product_uom ?? $model->product?->unit?->symbol ?? $model->product?->unit?->name ?? '-')
            ->add('purchase_invoice', fn (Batch $model) => $model->purchase_invoice ?? $model->purchase?->display_transaction_number ?? '-')
            ->add('storage_location', fn (Batch $model) => $model->storage_location_label ?? $model->resolved_storage_location)
            ->add('available_quantity')
            ->add('quantity')
            ->add('source_label', fn (Batch $model) => $model->source_label)
            ->add('expiry_date_formatted', fn (Batch $model) => $model->expiry_date_formatted ?? $model->expiry_date?->format('d/m/Y') ?? 'No expiry')
            ->add('expiry_date_sort', fn (Batch $model) => $model->expiry_date?->format('Y-m-d') ?? '9999-12-31')
            ->add('expiry_bucket', fn () => '')
            ->add('days_left', fn (Batch $model) => $model->days_left ?? ($model->expiry_date ? (now()->startOfDay()->diffInDays($model->expiry_date, false) >= 0 ? now()->startOfDay()->diffInDays($model->expiry_date, false) . ' days' : abs(now()->startOfDay()->diffInDays($model->expiry_date, false)) . ' days overdue') : 'No expiry'))
            ->add('lifecycle_status', function (Batch $model) use ($policy) {
                $status = $model->lifecycle_status ?? $policy->getStatus($model)->label();
                $statusEnum = $policy->getStatus($model);
                $badgeClass = match ($status) {
                    'Active' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'Near Expiry' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'Expired' => 'bg-red-50 text-red-700 border-red-200',
                    'Depleted' => 'bg-zinc-100 text-zinc-700 border-zinc-200',
                    'Quarantined' => 'bg-violet-50 text-violet-700 border-violet-200',
                    default => match ($statusEnum) {
                        BatchStatus::ACTIVE => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        BatchStatus::NEAR_EXPIRY => 'bg-amber-50 text-amber-700 border-amber-200',
                        BatchStatus::EXPIRED => 'bg-red-50 text-red-700 border-red-200',
                        BatchStatus::DEPLETED => 'bg-zinc-100 text-zinc-700 border-zinc-200',
                        BatchStatus::QUARANTINED => 'bg-violet-50 text-violet-700 border-violet-200',
                    },
                };

                return sprintf(
                    '<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium border %s">%s</span>',
                    $badgeClass,
                    e($status),
                );
            })
            ->add('lifecycle_status_export', fn (Batch $model) => $model->lifecycle_status ?? $policy->getStatus($model)->label())
            ->add('lifecycle_status_value', fn (Batch $model) => $policy->getStatus($model)->value)
            ->add('near_expiry_label', fn () => "Near Expiry ({$nearExpiryDays} days)")
            ->add('is_zero_cost', fn (Batch $model) => (int) $model->unit_cost === 0 ? 'Yes' : 'No');

        if ($this->canViewSensitiveValues()) {
            $fields
                ->add('unit_cost_formatted', fn (Batch $model) => format_money($model->unit_cost))
                ->add('inventory_value_formatted', fn (Batch $model) => format_money($policy->inventoryValue($model)))
                ->add('selling_price_formatted', fn (Batch $model) => format_money((float) ($model->selling_price ?? 0)));
        }

        return $fields;
    }

    public function columns(): array
    {
        $nearExpiryDays = app(BatchPolicyService::class)->nearExpiryThresholdDays();

        $columns = [
            Column::make('Batch No', 'batch_number')
                ->sortable()
                ->searchable(),

            Column::make(RmpTerminology::MATERIAL_NAME, 'product_name', 'products.name')
                ->sortable()
                ->searchable(),

            Column::make('SKU', 'product_sku', 'products.sku')
                ->sortable()
                ->searchable(),

            Column::make(RmpTerminology::ITEM_CODE, 'product_item_code_ierp', 'products.item_code_ierp')
                ->sortable()
                ->searchable(),

            Column::make(RmpTerminology::PHYSICAL_FORM, 'physical_form_label')
                ->searchableRaw('LOWER(' . $this->physicalFormExpression() . ') like ?')
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderByRaw($this->physicalFormExpression() . " {$direction}")),

            Column::make(RmpTerminology::UNIT, 'product_uom'),

            Column::make(RmpTerminology::STORAGE_LOCATION, 'storage_location')
                ->searchableRaw('LOWER(' . $this->storageLocationExpression() . ') like ?')
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderByRaw($this->storageLocationExpression() . " {$direction}")),

            Column::make(RmpTerminology::STATUS, 'lifecycle_status', 'lifecycle_status_sort')
                ->sortable()
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderByRaw($this->batchStatusSortExpression($nearExpiryDays) . " {$direction}"))
                ->headerAttribute('text-center')
                ->bodyAttribute('text-center')
                ->visibleInExport(false),

            Column::make(RmpTerminology::STATUS, 'lifecycle_status_export')
                ->hidden()
                ->visibleInExport(true),

            Column::make(RmpTerminology::EXPIRY_DATE, 'expiry_date_formatted', 'expiry_date')
                ->sortable(),

            Column::make(RmpTerminology::DAYS_REMAINING, 'days_left', 'days_left_sort')
                ->sortable()
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderByRaw($this->daysRemainingSortExpression() . " {$direction}"))
                ->bodyAttribute('text-center'),

            Column::make(RmpTerminology::STOCK_AVAILABLE, 'available_quantity')
                ->sortable()
                ->bodyAttribute('text-center'),

            Column::make('Original Qty', 'quantity')
                ->sortable()
                ->bodyAttribute('text-center'),

            Column::make('Source', 'source_label', 'source')
                ->sortable(),

            Column::make('Source Transaction Number', 'purchase_invoice')
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderByRaw($this->purchaseDocumentExpression() . " {$direction}"))
                ->sortable()
                ->searchableRaw('LOWER(' . $this->purchaseDocumentExpression() . ') like ?'),

            Column::action('Action'),
        ];

        if ($this->canViewSensitiveValues()) {
            array_splice($columns, 12, 0, [
                Column::make('Cost', 'unit_cost_formatted', 'unit_cost')
                    ->sortable()
                    ->bodyAttribute('text-right'),

                Column::make(RmpTerminology::INVENTORY_VALUE, 'inventory_value_formatted')
                    ->sortable(false)
                    ->bodyAttribute('text-right'),

                Column::make('Zero Cost', 'is_zero_cost', 'unit_cost')
                    ->sortable(false)
                    ->bodyAttribute('text-center'),

                Column::make('Sell Price', 'selling_price_formatted', 'selling_price')
                    ->sortable()
                    ->bodyAttribute('text-right'),
            ]);
        }

        return $columns;
    }

    public function relationSearch(): array
    {
        return [
            'product' => ['name', 'sku', 'item_code_ierp'],
            'purchase' => ['invoice_number', 'transaction_code'],
        ];
    }

    public function filters(): array
    {
        $policy = app(BatchPolicyService::class);
        $nearExpiryDays = $policy->nearExpiryThresholdDays();

        return [
            Filter::select('source', 'source')
                ->dataSource([
                    ['label' => 'Inbound Receipt', 'value' => 'purchase'],
                    ['label' => 'Opening Stock', 'value' => 'opening_balance'],
                    ['label' => 'Stock Adjustment', 'value' => 'adjustment_in'],
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
        $link = \App\Support\TransactionContext::resolveBatchTransactionLink($row, auth()->user());

        if ($link === null) {
            return [];
        }

        return [
            Button::add('transaction')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-4.5-4.5 6-6m0 0H15m4.5 0V4.5" /></svg>')
                ->class('bg-sky-500 hover:bg-sky-600 text-white p-2 rounded-md flex items-center justify-center')
                ->route($link['route'], $link['parameters'])
                ->tooltip($link['tooltip'] ?? 'View transaction'),
        ];
    }

    private function canViewSensitiveValues(): bool
    {
        $user = auth()->user();

        return ($user?->canViewInventoryValue() ?? false)
            || ($user?->canAccessFinance() ?? false);
    }

    protected function legacyPowerGridSortFieldMap(): array
    {
        return [
            'batch_number' => 'batches.batch_number',
            'days_left' => 'days_left_sort',
            'expiry_date_formatted' => 'expiry_date',
            'lifecycle_status' => 'lifecycle_status_sort',
            'physical_form' => 'physical_form_label',
            'product_item_code_ierp' => 'products.item_code_ierp',
            'product_name' => 'products.name',
            'product_sku' => 'products.sku',
            'source_label' => 'source',
            'storage_location_label' => 'storage_location',
        ];
    }
}
