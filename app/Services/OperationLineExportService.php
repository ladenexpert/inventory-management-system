<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\TransactionContext;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OperationLineExportService
{
    public function download(
        PowerGridComponent $component,
        string $context,
        string $format,
        bool $selected = false
    ): BinaryFileResponse {
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);

        $extension = $format === 'csv' ? 'csv' : 'xlsx';
        $headers = $this->headers($context);
        $rows = $this->rows($component, $context, $selected);
        $filePath = $this->prepareExportFile($context, $extension);
        $writer = $extension === 'csv' ? new CsvWriter() : new XlsxWriter();

        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($this->sanitizeRow($row)));
        }

        $writer->close();

        return response()
            ->download($filePath, TransactionContext::exportFilename($context, $extension))
            ->deleteFileAfterSend(true);
    }

    private function rows(PowerGridComponent $component, string $context, bool $selected): Collection
    {
        return match ($context) {
            TransactionContext::MATERIAL_RECEIPT,
            TransactionContext::LEGACY_PURCHASE => $this->purchaseRows($component, $context, $selected),
            TransactionContext::MATERIAL_USAGE,
            TransactionContext::LEGACY_SALE => $this->saleRows($component, $context, $selected),
            default => collect(),
        };
    }

    private function headers(string $context): array
    {
        return match ($context) {
            TransactionContext::MATERIAL_RECEIPT => $this->purchaseHeaders('Receipt Date', 'Received Qty', $this->canViewPurchaseValues()),
            TransactionContext::LEGACY_PURCHASE => $this->purchaseHeaders('Purchase Date', 'Purchased Qty', $this->canViewPurchaseValues()),
            TransactionContext::MATERIAL_USAGE => [
                'Transaction Number',
                'Reference Number',
                'Usage Date',
                'Team',
                'Requested By',
                'Item Code',
                'SKU',
                'Material Name',
                'Batch No',
                'Expiry Date',
                'Storage Location',
                'Usage Qty',
                'Unit',
                'Status',
                'Created By',
            ],
            TransactionContext::LEGACY_SALE => $this->salesHeaders($this->canViewSalesValues()),
            default => [],
        };
    }

    private function purchaseHeaders(string $dateLabel, string $quantityLabel, bool $includeValues): array
    {
        $headers = [
            'Transaction Number',
            'Reference Number',
            $dateLabel,
            'Supplier',
            'Item Code',
            'SKU',
            'Material Name',
            'Batch No',
            'Expiry Date',
            'Storage Location',
            $quantityLabel,
            'Unit',
        ];

        if ($includeValues) {
            $headers[] = 'Unit Cost';
            $headers[] = 'Line Value';
        }

        $headers[] = 'Status';
        $headers[] = 'Created By';

        return $headers;
    }

    private function salesHeaders(bool $includeValues): array
    {
        $headers = [
            'Transaction Number',
            'Reference Number',
            'Sale Date',
            'Customer',
            'Item Code',
            'SKU',
            'Material Name',
            'Batch No',
            'Expiry Date',
            'Storage Location',
            'Sales Qty',
            'Unit',
        ];

        if ($includeValues) {
            $headers[] = 'Unit Price';
            $headers[] = 'Sales Value';
        }

        $headers[] = 'Status';
        $headers[] = 'Created By';

        return $headers;
    }

    private function purchaseRows(PowerGridComponent $component, string $context, bool $selected): Collection
    {
        $headerScope = $this->purchaseHeaderScope($component, $context, $selected);

        if ($headerScope['ids']->isEmpty()) {
            return collect();
        }

        $includeValues = $this->canViewPurchaseValues();

        return PurchaseItem::query()
            ->with([
                'purchase.supplier',
                'purchase.creator',
                'product.unit',
                'storageLocation',
                'batch.storageLocationRecord',
            ])
            ->whereHas('purchase', function (Builder $query) use ($context, $headerScope) {
                TransactionContext::applyPurchaseContext($query, $context);
                $this->applyTransactionSelectionScope($query, $headerScope['ids'], $headerScope['transaction_codes']);
            })
            ->orderBy('purchase_id')
            ->orderBy('id')
            ->get()
            ->map(function (PurchaseItem $item) use ($context, $includeValues) {
                $purchase = $item->purchase;
                $row = [
                    $purchase?->display_transaction_number ?? '-',
                    $purchase?->reference_number ?: '-',
                    $this->formatDate($purchase?->purchase_date),
                    $purchase?->supplier?->name ?? '-',
                    $item->product?->item_code_ierp_display ?? '-',
                    $item->product?->sku_display ?? '-',
                    $item->product?->name ?? '-',
                    $item->batch_number ?: $item->batch?->batch_number ?: '-',
                    $this->formatDate($item->expiry_date ?? $item->batch?->expiry_date),
                    $item->storageLocation?->display_label ?? $item->batch?->resolved_storage_location ?? $item->storage_location ?: '-',
                    (int) $item->quantity,
                    $item->product?->unit?->symbol ?? $item->product?->unit?->name ?? '-',
                ];

                if ($includeValues) {
                    $row[] = (int) $item->unit_price;
                    $row[] = (int) $item->subtotal;
                }

                $row[] = $purchase?->status?->label() ?? '-';
                $row[] = $purchase?->creator?->name ?? '-';

                return $row;
            })
            ->values();
    }

    private function saleRows(PowerGridComponent $component, string $context, bool $selected): Collection
    {
        $headerScope = $this->saleHeaderScope($component, $context, $selected);

        if ($headerScope['ids']->isEmpty()) {
            return collect();
        }

        $includeValues = $context === TransactionContext::LEGACY_SALE && $this->canViewSalesValues();

        $rows = collect();

        SaleItem::query()
            ->with([
                'sale.customer',
                'sale.creator',
                'sale.issuer',
                'sale.team',
                'product.unit',
                'saleItemBatches.batch.storageLocationRecord',
            ])
            ->whereHas('sale', function (Builder $query) use ($context, $headerScope) {
                TransactionContext::applySaleContext($query, $context);
                $this->applyTransactionSelectionScope($query, $headerScope['ids'], $headerScope['transaction_codes']);
            })
            ->orderBy('sale_id')
            ->orderBy('id')
            ->get()
            ->each(function (SaleItem $item) use ($context, $includeValues, $rows) {
                $sale = $item->sale;
                $baseRow = [
                    $sale?->display_transaction_number ?? '-',
                    $sale?->reference_number ?: '-',
                    $this->formatDate($sale?->usage_date ?? $sale?->sale_date),
                ];

                if ($context === TransactionContext::MATERIAL_USAGE) {
                    $baseRow[] = $sale?->team?->name ?? $sale?->project ?? '-';
                    $baseRow[] = $sale?->requested_by ?? '-';
                } else {
                    $baseRow[] = $sale?->customer?->name ?? 'Guest';
                }

                $baseRow[] = $item->product?->item_code_ierp_display ?? '-';
                $baseRow[] = $item->product?->sku_display ?? '-';
                $baseRow[] = $item->product?->name ?? '-';

                $allocations = $item->saleItemBatches;

                if ($allocations->isEmpty()) {
                    $rows->push($this->finalizeSaleRow(
                        $baseRow,
                        context: $context,
                        batchNumber: '-',
                        expiryDate: '-',
                        storageLocation: '-',
                        quantity: (int) $item->quantity,
                        unit: $item->product?->unit?->symbol ?? $item->product?->unit?->name ?? '-',
                        includeValues: $includeValues,
                        unitValue: $context === TransactionContext::MATERIAL_USAGE ? (int) $item->cost_price : (int) $item->final_price,
                        lineValue: $context === TransactionContext::MATERIAL_USAGE ? (int) $item->total_cost : (int) $item->subtotal,
                        status: $sale?->status?->label() ?? '-',
                        createdBy: $sale?->issuer?->name ?? $sale?->creator?->name ?? '-',
                    ));

                    return;
                }

                foreach ($allocations as $allocation) {
                    $rows->push($this->finalizeSaleRow(
                        $baseRow,
                        context: $context,
                        batchNumber: $allocation->batch?->batch_number ?? '-',
                        expiryDate: $this->formatDate($allocation->batch?->expiry_date),
                        storageLocation: $allocation->batch?->resolved_storage_location ?? '-',
                        quantity: (int) $allocation->quantity,
                        unit: $item->product?->unit?->symbol ?? $item->product?->unit?->name ?? '-',
                        includeValues: $includeValues,
                        unitValue: $context === TransactionContext::MATERIAL_USAGE ? (int) $allocation->unit_cost : (int) $item->final_price,
                        lineValue: $context === TransactionContext::MATERIAL_USAGE
                            ? (int) ($allocation->quantity * $allocation->unit_cost)
                            : (int) round(($item->quantity > 0 ? $item->subtotal / $item->quantity : 0) * $allocation->quantity),
                        status: $sale?->status?->label() ?? '-',
                        createdBy: $sale?->issuer?->name ?? $sale?->creator?->name ?? '-',
                    ));
                }
            });

        return $rows->values();
    }

    private function finalizeSaleRow(
        array $baseRow,
        string $context,
        string $batchNumber,
        string $expiryDate,
        string $storageLocation,
        int $quantity,
        string $unit,
        bool $includeValues,
        int $unitValue,
        int $lineValue,
        string $status,
        string $createdBy
    ): array {
        $row = [
            ...$baseRow,
            $batchNumber,
            $expiryDate,
            $storageLocation,
            $quantity,
            $unit,
        ];

        if ($context === TransactionContext::LEGACY_SALE && $includeValues) {
            $row[] = $unitValue;
            $row[] = $lineValue;
        }

        $row[] = $status;
        $row[] = $createdBy;

        return $row;
    }

    private function purchaseHeaderScope(PowerGridComponent $component, string $context, bool $selected): array
    {
        $ids = $this->headerIds($component, $selected);

        if ($ids->isEmpty()) {
            return ['ids' => collect(), 'transaction_codes' => collect()];
        }

        $headers = TransactionContext::applyPurchaseContext(Purchase::query(), $context)
            ->whereIn('id', $ids)
            ->get(['id', 'transaction_code']);

        return [
            'ids' => $headers->pluck('id')->map(fn ($id) => (int) $id)->values(),
            'transaction_codes' => $headers->pluck('transaction_code')->filter()->values(),
        ];
    }

    private function saleHeaderScope(PowerGridComponent $component, string $context, bool $selected): array
    {
        $ids = $this->headerIds($component, $selected);

        if ($ids->isEmpty()) {
            return ['ids' => collect(), 'transaction_codes' => collect()];
        }

        $headers = TransactionContext::applySaleContext(Sale::query(), $context)
            ->whereIn('id', $ids)
            ->get(['id', 'transaction_code']);

        return [
            'ids' => $headers->pluck('id')->map(fn ($id) => (int) $id)->values(),
            'transaction_codes' => $headers->pluck('transaction_code')->filter()->values(),
        ];
    }

    private function headerIds(PowerGridComponent $component, bool $selected): Collection
    {
        return collect($component->prepareToExport($selected))
            ->map(fn ($row) => (int) data_get($row, 'id'))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();
    }

    private function applyTransactionSelectionScope(Builder $query, Collection $ids, Collection $transactionCodes): void
    {
        $query->where(function (Builder $scope) use ($ids, $transactionCodes) {
            if ($transactionCodes->isNotEmpty()) {
                $scope->whereIn('transaction_code', $transactionCodes->all());
            }

            if ($ids->isNotEmpty()) {
                $method = $transactionCodes->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                $scope->{$method}('id', $ids->all());
            }
        });
    }

    private function canViewPurchaseValues(): bool
    {
        $user = auth()->user();

        return ($user?->canViewInventoryValue() ?? false)
            || ($user?->canAccessFinance() ?? false);
    }

    private function canViewSalesValues(): bool
    {
        return auth()->user()?->canAccessFinance() ?? false;
    }

    private function sanitizeRow(array $row): array
    {
        return array_map(fn ($value) => $this->sanitizeValue($value), $row);
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if ($value === null) {
            return '-';
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (!is_scalar($value)) {
            return '-';
        }

        $string = trim((string) $value);
        $string = str_replace(["\r\n", "\r", "\n"], ' ', $string);
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $string) ?? '';

        if ($string === '') {
            return '-';
        }

        if (preg_match('/^[=\+\-@]/', $string) === 1) {
            return "'" . $string;
        }

        return $string;
    }

    private function formatDate(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && trim($value) !== '') {
            return Str::contains($value, ' ')
                ? (string) Str::before($value, ' ')
                : $value;
        }

        return '-';
    }

    private function prepareExportFile(string $context, string $format): string
    {
        $directory = storage_path('app/temp');

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        return $directory
            . DIRECTORY_SEPARATOR
            . TransactionContext::definition($context)['export_prefix']
            . '-'
            . Str::random(8)
            . '.'
            . $format;
    }
}
