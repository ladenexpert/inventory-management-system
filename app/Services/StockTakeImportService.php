<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Support\RmpTerminology;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

class StockTakeImportService
{
    private const HEADER_LABELS = [
        'item_code' => RmpTerminology::ITEM_CODE,
        'batch_no' => RmpTerminology::BATCH_NO,
        'expiry_date' => RmpTerminology::EXPIRY_DATE,
        'storage_location' => RmpTerminology::STORAGE_LOCATION,
        'counted_qty' => RmpTerminology::COUNTED_QTY,
        'unit' => RmpTerminology::UNIT,
        'reference' => RmpTerminology::REFERENCE_NUMBER,
        'notes' => RmpTerminology::NOTES,
    ];

    private const HEADERS = [
        'item_code' => ['item_code', 'item_code_ierp', RmpTerminology::ITEM_CODE, 'Item Code IERP'],
        'batch_no' => ['batch_no', 'batch_number', RmpTerminology::BATCH_NO],
        'expiry_date' => ['expiry_date', 'exp_date', RmpTerminology::EXPIRY_DATE],
        'storage_location' => ['storage_location', 'location', RmpTerminology::STORAGE_LOCATION],
        'counted_qty' => ['counted_qty', 'counted_quantity', 'qty', RmpTerminology::COUNTED_QTY],
        'unit' => ['unit', 'uom', RmpTerminology::UNIT],
        'reference' => ['reference', 'reference_number', RmpTerminology::REFERENCE_NUMBER],
        'notes' => ['notes', RmpTerminology::NOTES],
    ];

    public function __construct(
        protected BatchService $batchService,
        protected InventoryAdjustmentService $inventoryAdjustmentService,
    ) {
    }

    public function previewFromFile(string $filePath): array
    {
        $reader = ReaderFactory::createFromFileByMimeType($filePath);
        $reader->open($filePath);

        $headerMap = null;
        $rows = [];
        $errors = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    $cells = $row->toArray();

                    if ($headerMap === null) {
                        $headerMap = $this->buildHeaderMap($cells);
                        continue;
                    }

                    if ($this->isCommentRow($cells) || $this->isEmptyRow($cells)) {
                        continue;
                    }

                    try {
                        $mapped = $this->mapRow($cells, $headerMap);
                        $rows[] = $this->normalizePreviewRow($mapped, $rowNumber);
                    } catch (\Throwable $e) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'message' => $e->getMessage(),
                        ];
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return [
            'rows' => $rows,
            'errors' => $errors,
            'summary' => [
                'processed_rows' => count($rows) + count($errors),
                'valid_rows' => count($rows),
                'error_rows' => count($errors),
                'adjustment_rows' => collect($rows)->where('variance', '!=', 0)->count(),
            ],
        ];
    }

    public function applyPreviewRows(array $rows, int $userId): array
    {
        return DB::transaction(function () use ($rows, $userId) {
            $applied = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                if ((int) $row['variance'] === 0) {
                    $skipped++;
                    continue;
                }

                $adjustment = $this->inventoryAdjustmentService->create([
                    'adjustment_type' => 'stock_take_import',
                    'direction' => (int) $row['variance'] > 0 ? 'in' : 'out',
                    'source' => 'stock_take_import',
                    'reference' => $row['reference'] ?: null,
                    'notes' => $row['notes'] ?: "Stock take import for batch {$row['batch_number']}.",
                    'adjusted_by' => $userId,
                    'imported_by' => $userId,
                    'adjusted_at' => now(),
                    'imported_at' => now(),
                    'meta' => [
                        'product_id' => $row['product_id'],
                        'batch_id' => $row['batch_id'],
                        'item_code' => $row['item_code'],
                        'batch_number' => $row['batch_number'],
                        'expiry_date' => $row['expiry_date'],
                        'storage_location' => $row['storage_location'],
                        'current_qty' => $row['current_qty'],
                        'counted_qty' => $row['counted_qty'],
                        'variance' => $row['variance'],
                    ],
                ], 'STK');

                $this->batchService->withinStockMutationScope(function () use ($row, $adjustment) {
                    $batch = Batch::findOrFail($row['batch_id']);

                    $this->batchService->applyStockTakeCount(
                        batch: $batch,
                        countedQuantity: (int) $row['counted_qty'],
                        inventoryAdjustment: $adjustment,
                        notes: trim("Stock take import {$adjustment->transaction_code}. Reference: " . ($row['reference'] ?: '-') . '. ' . ($row['notes'] ?: '')),
                    );
                });

                $applied++;
            }

            return [
                'applied_rows' => $applied,
                'skipped_rows' => $skipped,
            ];
        });
    }

    private function buildHeaderMap(array $headerRow): array
    {
        $normalizedHeaders = [];

        foreach ($headerRow as $index => $value) {
            $header = $this->normalizeHeader((string) $value);

            if ($header !== '') {
                $normalizedHeaders[$header] = $index;
            }
        }

        $headerMap = [];

        foreach (self::HEADERS as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $normalizedHeaders)) {
                    $headerMap[$canonical] = $normalizedHeaders[$alias];
                    break;
                }
            }
        }

        $missing = array_map(
            fn (string $header) => self::HEADER_LABELS[$header] ?? $header,
            array_diff(array_keys(self::HEADERS), array_keys($headerMap))
        );

        if ($missing !== []) {
            throw new \RuntimeException('Template is invalid. Missing headers: ' . implode(', ', $missing));
        }

        return $headerMap;
    }

    private function mapRow(array $cells, array $headerMap): array
    {
        $row = [];

        foreach (array_keys(self::HEADERS) as $header) {
            $row[$header] = $cells[$headerMap[$header]] ?? null;
        }

        return $row;
    }

    private function normalizePreviewRow(array $row, int $rowNumber): array
    {
        $itemCode = $this->requireString($row['item_code'] ?? null, 'Unknown Item Code.');
        $product = Product::query()->where('item_code_ierp', $itemCode)->first();

        if (!$product) {
            throw new \RuntimeException("Unknown Item Code '{$itemCode}'.");
        }

        $batchNumber = $this->requireString($row['batch_no'] ?? null, 'Batch No is required.');
        $batch = Batch::query()->with('product.unit', 'storageLocationRecord')->where('batch_number', $batchNumber)->first();

        if (!$batch) {
            throw new \RuntimeException("Unknown Batch No '{$batchNumber}'.");
        }

        if ((int) $batch->product_id !== (int) $product->id) {
            throw new \RuntimeException("Batch No '{$batchNumber}' does not belong to Item Code '{$itemCode}'.");
        }

        $expiryDate = $this->parseDate($row['expiry_date'] ?? null, 'Invalid Expiry Date format.');
        $currentExpiry = $batch->expiry_date?->format('Y-m-d');

        if ($expiryDate !== $currentExpiry) {
            throw new \RuntimeException("Batch No '{$batchNumber}' expiry does not match the current batch record.");
        }

        $locationValue = $this->requireString($row['storage_location'] ?? null, 'Unknown Storage Location.');
        $location = StorageLocation::query()
            ->where('code', $locationValue)
            ->orWhere('name', $locationValue)
            ->orWhereRaw('LOWER(code) = ?', [Str::lower($locationValue)])
            ->orWhereRaw('LOWER(name) = ?', [Str::lower($locationValue)])
            ->first();

        if (!$location) {
            throw new \RuntimeException("Unknown Storage Location '{$locationValue}'.");
        }

        $locationMatches = $batch->storage_location_id === $location->id
            || strcasecmp((string) $batch->storage_location, $locationValue) === 0
            || strcasecmp((string) $batch->resolved_storage_location, $locationValue) === 0
            || strcasecmp((string) $location->display_label, (string) $batch->resolved_storage_location) === 0;

        if (!$locationMatches) {
            throw new \RuntimeException("Batch No '{$batchNumber}' storage location does not match the current batch record.");
        }

        $countedQty = $this->parseInteger($row['counted_qty'] ?? null, 'Counted Qty is invalid.');

        if ($countedQty < 0) {
            throw new \RuntimeException('Counted Qty cannot be negative.');
        }

        $unitValue = $this->requireString($row['unit'] ?? null, 'Unit is required.');
        $expectedUnit = $product->unit?->symbol ?? $product->unit?->name;

        if ($expectedUnit && strcasecmp($unitValue, $expectedUnit) !== 0) {
            throw new \RuntimeException("Unit '{$unitValue}' does not match material unit '{$expectedUnit}'.");
        }

        $currentQty = (int) $batch->available_quantity;

        return [
            'row_number' => $rowNumber,
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'item_code' => $itemCode,
            'material_name' => $product->name,
            'batch_number' => $batch->batch_number,
            'expiry_date' => $currentExpiry,
            'storage_location' => $batch->resolved_storage_location,
            'unit' => $expectedUnit ?: $unitValue,
            'current_qty' => $currentQty,
            'counted_qty' => $countedQty,
            'variance' => $countedQty - $currentQty,
            'reference' => $this->cleanString($row['reference'] ?? null) ?? '',
            'notes' => $this->cleanString($row['notes'] ?? null) ?? '',
        ];
    }

    private function parseInteger(mixed $value, string $message): int
    {
        if ($value === null || $value === '') {
            throw new \RuntimeException($message);
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        $clean = preg_replace('/[^0-9\-]/', '', trim((string) $value));

        if ($clean === null || $clean === '' || $clean === '-') {
            throw new \RuntimeException($message);
        }

        return (int) $clean;
    }

    private function parseDate(mixed $value, string $message): ?string
    {
        $raw = $this->cleanString($value);

        if ($raw === null) {
            return null;
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            throw new \RuntimeException($message);
        }
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            $clean = $value->format('Y-m-d H:i:s');
        } elseif (is_scalar($value)) {
            $clean = trim((string) $value);
        } elseif (is_object($value) && method_exists($value, '__toString')) {
            $clean = trim((string) $value);
        } else {
            return null;
        }

        return $clean === '' ? null : $clean;
    }

    private function requireString(mixed $value, string $message): string
    {
        $clean = $this->cleanString($value);

        if ($clean === null) {
            throw new \RuntimeException($message);
        }

        return $clean;
    }

    private function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($this->cleanString($cell) !== null) {
                return false;
            }
        }

        return true;
    }

    private function isCommentRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            $value = $this->cleanString($cell);

            if ($value === null) {
                continue;
            }

            return Str::startsWith($value, '#');
        }

        return false;
    }

    private function normalizeHeader(string $header): string
    {
        return RmpTerminology::normalizeHeader($header);
    }
}
