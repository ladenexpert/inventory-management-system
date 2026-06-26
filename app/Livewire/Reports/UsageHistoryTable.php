<?php

namespace App\Livewire\Reports;

use App\Enums\SaleTransactionType;
use App\Enums\SaleStatus;
use App\Livewire\Concerns\HandlesPowerGridExportSorting;
use App\Models\SaleItemBatch;
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

final class UsageHistoryTable extends PowerGridComponent
{
    use HandlesPowerGridExportSorting;
    use WithExport {
        HandlesPowerGridExportSorting::prepareToExport insteadof WithExport;
        WithExport::prepareToExport as protected powerGridPrepareToExport;
    }

    public string $tableName = 'usage-history-table';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

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
                PowerGrid::exportable('usage_report_' . now()->format('Y_m_d'))
                    ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV)
            );
        }

        return $setUp;
    }

    public function datasource(): Builder
    {
        $this->normalizePowerGridSortingState();

        return SaleItemBatch::query()
            ->select([
                'sale_item_batches.*',
                'sales.id as usage_sale_id',
                DB::raw('COALESCE(sales.transaction_code, sales.invoice_number) as usage_number'),
                'sales.invoice_number as reference_number_value',
                'sales.usage_date as usage_date_value',
                'sales.purpose as purpose_value',
                'sales.formula as formula_value',
                DB::raw("COALESCE(teams.name, sales.project, '-') as team_value"),
                'sales.requested_by as requested_by_value',
                'sales.notes as notes_value',
                'products.sku as sku_value',
                'products.item_code_ierp as item_code_ierp_value',
                'products.name as material_name_value',
                'units.symbol as unit_value',
                'batches.batch_number as batch_number_value',
                'batches.expiry_date as expiry_date_value',
                DB::raw("COALESCE(storage_locations.name, batches.storage_location, '-') as storage_location_value"),
                DB::raw("COALESCE(issuer_users.name, creator_users.name, '-') as issued_by_name"),
            ])
            ->join('sale_items', 'sale_items.id', '=', 'sale_item_batches.sale_item_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('batches', 'batches.id', '=', 'sale_item_batches.batch_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->leftJoin('storage_locations', 'storage_locations.id', '=', 'batches.storage_location_id')
            ->leftJoin('teams', 'teams.id', '=', 'sales.team_id')
            ->leftJoin('users as issuer_users', 'issuer_users.id', '=', 'sales.issued_by')
            ->leftJoin('users as creator_users', 'creator_users.id', '=', 'sales.created_by')
            ->where('sales.transaction_type', SaleTransactionType::MATERIAL_USAGE->value)
            ->where('sales.status', '!=', SaleStatus::CANCELLED->value);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('usage_number', fn (SaleItemBatch $model) => $model->usage_number ?? $model->saleItem?->sale?->display_transaction_number ?? '-')
            ->add('reference_number', fn (SaleItemBatch $model) => $model->reference_number_value ?? $model->saleItem?->sale?->reference_number ?? '-')
            ->add('usage_date', fn (SaleItemBatch $model) => $model->usage_date_value
                ? \Carbon\Carbon::parse($model->usage_date_value)->format('d/m/Y')
                : optional($model->saleItem?->sale?->usage_date ?? $model->saleItem?->sale?->sale_date)?->format('d/m/Y') ?? '-')
            ->add('sku', fn (SaleItemBatch $model) => $model->sku_value ?? $model->batch?->product?->sku_display ?? $model->saleItem?->product?->sku_display ?? '-')
            ->add('item_code_ierp', fn (SaleItemBatch $model) => $model->item_code_ierp_value ?? $model->batch?->product?->item_code_ierp_display ?? $model->saleItem?->product?->item_code_ierp_display ?? '-')
            ->add('material_name', fn (SaleItemBatch $model) => $model->material_name_value ?? $model->batch?->product?->name ?? $model->saleItem?->product?->name ?? '-')
            ->add('batch_number', fn (SaleItemBatch $model) => $model->batch_number_value ?? $model->batch?->batch_number ?? '-')
            ->add('expiry_date', fn (SaleItemBatch $model) => $model->expiry_date_value
                ? \Carbon\Carbon::parse($model->expiry_date_value)->format('d/m/Y')
                : $model->batch?->expiry_date?->format('d/m/Y') ?? '-')
            ->add('storage_location', fn (SaleItemBatch $model) => $model->storage_location_value ?? $model->batch?->resolved_storage_location ?? '-')
            ->add('quantity')
            ->add('unit', fn (SaleItemBatch $model) => $model->unit_value ?? $model->batch?->product?->unit?->symbol ?? $model->saleItem?->product?->unit?->symbol ?? '-')
            ->add('purpose', fn (SaleItemBatch $model) => $model->purpose_value ?? $model->saleItem?->sale?->purpose ?? '-')
            ->add('formula', fn (SaleItemBatch $model) => $model->formula_value ?? $model->saleItem?->sale?->formula ?? '-')
            ->add('team', fn (SaleItemBatch $model) => $model->team_value ?? $model->saleItem?->sale?->team?->name ?? $model->saleItem?->sale?->project ?? '-')
            ->add('requested_by', fn (SaleItemBatch $model) => $model->requested_by_value ?? $model->saleItem?->sale?->requested_by ?? '-')
            ->add('issued_by_name', fn (SaleItemBatch $model) => $model->issued_by_name ?? $model->saleItem?->sale?->issuer?->name ?? $model->saleItem?->sale?->creator?->name ?? '-')
            ->add('notes', fn (SaleItemBatch $model) => $model->notes_value ?? $model->saleItem?->sale?->notes ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::action('Action'),
            Column::make(RmpTerminology::TRANSACTION_NUMBER, 'usage_number')
                ->searchableRaw('LOWER(COALESCE(sales.transaction_code, sales.invoice_number)) like ?')
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderByRaw("COALESCE(sales.transaction_code, sales.invoice_number) {$direction}")),
            Column::make(RmpTerminology::REFERENCE_NUMBER, 'reference_number', 'sales.invoice_number')->searchable()->sortable(),
            Column::make('Date', 'usage_date', 'sales.usage_date')->sortable(),
            Column::make('SKU', 'sku', 'products.sku')->searchable()->sortable(),
            Column::make(RmpTerminology::ITEM_CODE, 'item_code_ierp', 'products.item_code_ierp')->searchable()->sortable(),
            Column::make(RmpTerminology::MATERIAL_NAME, 'material_name', 'products.name')->searchable()->sortable(),
            Column::make(RmpTerminology::BATCH_NO, 'batch_number', 'batches.batch_number')->searchable()->sortable(),
            Column::make(RmpTerminology::EXPIRY_DATE, 'expiry_date'),
            Column::make(RmpTerminology::STORAGE_LOCATION, 'storage_location')
                ->searchableRaw("LOWER(COALESCE(storage_locations.name, batches.storage_location, '-')) like ?")
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderByRaw("COALESCE(storage_locations.name, batches.storage_location, '-') {$direction}")),
            Column::make(RmpTerminology::USAGE_QTY, 'quantity')->sortable()->bodyAttribute('text-center'),
            Column::make(RmpTerminology::UNIT, 'unit'),
            Column::make('Purpose', 'purpose', 'sales.purpose')->searchable(),
            Column::make('Formula', 'formula', 'sales.formula')->searchable(),
            Column::make(RmpTerminology::TEAM, 'team')
                ->searchableRaw("LOWER(COALESCE(teams.name, sales.project, '-')) like ?")
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderByRaw("COALESCE(teams.name, sales.project, '-') {$direction}")),
            Column::make(RmpTerminology::REQUESTED_BY, 'requested_by', 'sales.requested_by')->searchable(),
            Column::make('Issued By', 'issued_by_name')
                ->searchableRaw("LOWER(COALESCE(issuer_users.name, creator_users.name, '-')) like ?")
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderByRaw("COALESCE(issuer_users.name, creator_users.name, '-') {$direction}")),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::datepicker('usage_date', 'sales.usage_date')
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
        $saleId = $row->usage_sale_id ?? $row->saleItem?->sale_id;

        return [
            Button::add('view')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>')
                ->class('bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-md flex items-center justify-center')
                ->route('material-usages.show', ['sale' => $saleId])
                ->tooltip('View usage'),
            Button::add('print')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5h10.5m-10.5 3h10.5m-10.5 3h4.5M6 18.75h12A2.25 2.25 0 0 0 20.25 16.5v-9A2.25 2.25 0 0 0 18 5.25H6A2.25 2.25 0 0 0 3.75 7.5v9A2.25 2.25 0 0 0 6 18.75Z" /></svg>')
                ->class('bg-indigo-500 hover:bg-indigo-600 text-white p-2 rounded-md flex items-center justify-center')
                ->route('material-usages.print', ['sale' => $saleId])
                ->tooltip('Print usage slip'),
        ];
    }

    private function canExportReport(): bool
    {
        return auth()->user()?->hasPermission('reports', 'export') ?? false;
    }

    protected function legacyPowerGridSortFieldMap(): array
    {
        return [
            'batch_number' => 'batches.batch_number',
            'item_code_ierp' => 'products.item_code_ierp',
            'material_name' => 'products.name',
            'reference_number' => 'sales.invoice_number',
            'sku' => 'products.sku',
            'usage_date_value' => 'sales.usage_date',
        ];
    }
}
