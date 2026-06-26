<?php

namespace App\Livewire\MaterialUsages;

use App\Enums\SaleStatus;
use App\Livewire\Concerns\AuthorizesComponentPermissions;
use App\Models\Sale;
use App\Services\OperationLineExportService;
use App\Support\RmpTerminology;
use App\Support\TransactionContext;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;

final class MaterialUsageTable extends PowerGridComponent
{
    use WithExport {
        exportToCsv as protected powerGridExportToCsv;
        exportToXLS as protected powerGridExportToXLS;
    }
    use AuthorizesComponentPermissions;

    public string $tableName = 'material-usage-table';
    public string $sortField = 'usage_date';
    public string $sortDirection = 'desc';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        if ($this->canExportMaterialUsage()) {
            $this->showCheckBox();
        }

        $setUp = [
            PowerGrid::header()
                ->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];

        if ($this->canExportMaterialUsage()) {
            array_unshift(
                $setUp,
                PowerGrid::exportable(
                    TransactionContext::definition(TransactionContext::MATERIAL_USAGE)['export_prefix'] . '_' . now()->format('Y_m_d')
                )->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV)
            );
        }

        return $setUp;
    }

    public function datasource(): Builder
    {
        return TransactionContext::applySaleContext(
            Sale::query(),
            TransactionContext::MATERIAL_USAGE,
        )
            ->with(['creator', 'issuer', 'team'])
            ->withCount('items')
            ->withSum('items as total_quantity', 'quantity')
            ->where('status', '!=', SaleStatus::CANCELLED->value);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('transaction_number', fn (Sale $model) => $model->display_transaction_number)
            ->add('reference_number', fn (Sale $model) => $model->reference_number ?: '-')
            ->add('usage_date_formatted', fn (Sale $model) => optional($model->usage_date ?? $model->sale_date)?->format('d/m/Y') ?? '-')
            ->add('team_name', fn (Sale $model) => $model->team?->name ?? $model->project ?? '-')
            ->add('requested_by_name', fn (Sale $model) => $model->requested_by ?: '-')
            ->add('total_lines', fn (Sale $model) => (int) $model->items_count)
            ->add('total_qty', fn (Sale $model) => (int) ($model->total_quantity ?? 0))
            ->add('status_label', fn (Sale $model) => $model->status->label())
            ->add('creator_name', fn (Sale $model) => $model->issuer?->name ?? $model->creator?->name ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::action('Action'),
            Column::make(RmpTerminology::TRANSACTION_NUMBER, 'transaction_number', 'transaction_code')
                ->searchable()
                ->sortable(),
            Column::make(RmpTerminology::REFERENCE_NUMBER, 'reference_number', 'invoice_number')
                ->searchable()
                ->sortable(),
            Column::make('Usage Date', 'usage_date_formatted', 'usage_date')
                ->sortable(),
            Column::make(RmpTerminology::TEAM, 'team_name')
                ->searchableRaw("LOWER(COALESCE((select name from teams where teams.id = sales.team_id), sales.project, '-')) like ?"),
            Column::make(RmpTerminology::REQUESTED_BY, 'requested_by_name', 'requested_by')
                ->searchable()
                ->sortable(),
            Column::make('Total Lines', 'total_lines', 'items_count')
                ->sortable()
                ->bodyAttribute('text-center'),
            Column::make('Total Qty', 'total_qty', 'total_quantity')
                ->sortable()
                ->bodyAttribute('text-center'),
            Column::make('Status', 'status_label', 'status')
                ->sortable()
                ->bodyAttribute('text-center'),
            Column::make('Created By', 'creator_name', 'created_by')
                ->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::multiSelect('status', 'status')
                ->dataSource(collect(SaleStatus::cases())->map(fn ($status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])->toArray())
                ->optionLabel('label')
                ->optionValue('value'),
            Filter::datepicker('usage_date_formatted', 'usage_date')
                ->params([
                    'enableTime' => false,
                    'dateFormat' => 'Y-m-d',
                    'altInput' => true,
                    'altFormat' => 'd/m/Y',
                ]),
        ];
    }

    public function actions(Sale $row): array
    {
        return [
            Button::add('view')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>')
                ->class('bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-md flex items-center justify-center')
                ->route('material-usages.show', ['sale' => $row->id])
                ->tooltip('View usage'),
            Button::add('print')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5h10.5m-10.5 3h10.5m-10.5 3h4.5M6 18.75h12A2.25 2.25 0 0 0 20.25 16.5v-9A2.25 2.25 0 0 0 18 5.25H6A2.25 2.25 0 0 0 3.75 7.5v9A2.25 2.25 0 0 0 6 18.75Z" /></svg>')
                ->class('bg-indigo-500 hover:bg-indigo-600 text-white p-2 rounded-md flex items-center justify-center')
                ->route('material-usages.print', ['sale' => $row->id])
                ->tooltip('Print usage slip'),
        ];
    }

    public function exportToXLS(bool $selected = false): \Symfony\Component\HttpFoundation\BinaryFileResponse|bool
    {
        $this->authorizePermission('material_usage', 'export');

        return app(OperationLineExportService::class)->download($this, TransactionContext::MATERIAL_USAGE, 'xlsx', $selected);
    }

    public function exportToCsv(bool $selected = false): \Symfony\Component\HttpFoundation\BinaryFileResponse|bool
    {
        $this->authorizePermission('material_usage', 'export');

        return app(OperationLineExportService::class)->download($this, TransactionContext::MATERIAL_USAGE, 'csv', $selected);
    }

    private function canExportMaterialUsage(): bool
    {
        return $this->hasPermission('material_usage', 'export');
    }
}
