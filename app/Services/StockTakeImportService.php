<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Product;
use App\Models\StockTakeRow;
use App\Models\StockTakeSession;
use App\Models\StorageLocation;
use App\Support\RmpTerminology;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

class StockTakeImportService
{
    private const HEADER_LABELS = [
        'sku' => RmpTerminology::SKU,
        'item_code' => RmpTerminology::ITEM_CODE,
        'material' => 'Material',
        'batch_no' => RmpTerminology::BATCH_NO,
        'expiry_date' => 'Expiry',
        'storage_location' => RmpTerminology::STORAGE_LOCATION,
        'counted_qty' => RmpTerminology::COUNTED_QTY,
        'reference' => 'Reference',
        'notes' => RmpTerminology::NOTES,
    ];

    private const HEADERS = [
        'sku' => ['sku', RmpTerminology::SKU],
        'item_code' => ['item_code', 'item_code_ierp', RmpTerminology::ITEM_CODE, 'Item Code IERP'],
        'material' => ['material', 'material_name', RmpTerminology::MATERIAL_NAME],
        'batch_no' => ['batch_no', 'batch_number', RmpTerminology::BATCH_NO],
        'expiry_date' => ['expiry', 'expiry_date', 'exp_date', RmpTerminology::EXPIRY_DATE],
        'storage_location' => ['storage_location', 'location', RmpTerminology::STORAGE_LOCATION],
        'counted_qty' => ['counted_qty', 'counted_quantity', 'qty', RmpTerminology::COUNTED_QTY],
        'reference' => ['reference', 'reference_number', RmpTerminology::REFERENCE_NUMBER],
        'notes' => ['notes', RmpTerminology::NOTES],
    ];

    private const REQUIRED_HEADERS = [
        'sku',
        'batch_no',
        'counted_qty',
    ];

    public function __construct(
        protected BatchService $batchService,
        protected InventoryAdjustmentService $inventoryAdjustmentService,
        protected TransactionCodeService $transactionCodeService,
    ) {
    }

    public function createSessionFromFile(string $filePath, int $userId): StockTakeSession
    {
        $preview = $this->previewFromFile($filePath);
        $reference = $this->resolveSessionReference($preview['rows']);

        return DB::transaction(function () use ($preview, $reference, $userId) {
            $session = StockTakeSession::create([
                'session_code' => $this->transactionCodeService->forStockTakeSession(),
                'status' => 'imported',
                'reference' => $reference,
                'notes' => 'Stock Take import created from uploaded file.',
                'row_count' => count($preview['rows']),
                'error_count' => (int) $preview['summary']['error_rows'],
                'imported_by' => $userId,
                'imported_at' => now(),
                'meta' => $preview['summary'],
            ]);

            $session->rows()->createMany(array_map(function (array $row): array {
                return [
                    'row_number' => $row['row_number'],
                    'status' => $row['status'],
                    'error_message' => $row['error_message'],
                    'product_id' => $row['product_id'],
                    'batch_id' => $row['batch_id'],
                    'sku' => $row['sku'],
                    'item_code' => $row['item_code'],
                    'material_name' => $row['material_name'],
                    'batch_number' => $row['batch_number'],
                    'expiry_date' => $row['expiry_date'],
                    'storage_location' => $row['storage_location'],
                    'system_qty' => $row['system_qty'],
                    'counted_qty' => $row['counted_qty'],
                    'variance_qty' => $row['variance_qty'],
                    'reference' => $row['reference'],
                    'notes' => $row['notes'],
                    'meta' => $row['meta'] ?? null,
                ];
            }, $preview['rows']));

            return $this->loadSession($session);
        });
    }

    public function previewFromFile(string $filePath): array
    {
        $reader = ReaderFactory::createFromFileByMimeType($filePath);
        $reader->open($filePath);

        $headerMap = null;
        $rows = [];

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

                    $mapped = $this->mapRow($cells, $headerMap);

                    try {
                        $rows[] = $this->normalizePreviewRow($mapped, $rowNumber);
                    } catch (\Throwable $e) {
                        $rows[] = $this->errorPreviewRow($mapped, $rowNumber, $e->getMessage());
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        $errors = collect($rows)
            ->where('status', 'error')
            ->map(fn (array $row) => [
                'row' => $row['row_number'],
                'message' => $row['error_message'],
            ])
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'errors' => $errors,
            'summary' => $this->summarizeRowPayloads($rows),
        ];
    }

    public function recalculateSession(StockTakeSession $session, int $userId): StockTakeSession
    {
        return DB::transaction(function () use ($session, $userId) {
            $lockedSession = StockTakeSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            if (in_array($lockedSession->status, ['posted', 'closed'], true)) {
                throw new \RuntimeException('Posted or closed stock take sessions cannot be recalculated. Open a new session if another count is required.');
            }

            $rows = StockTakeRow::query()
                ->where('stock_take_session_id', $lockedSession->id)
                ->orderBy('row_number')
                ->get();

            foreach ($rows as $row) {
                if ($row->status === 'error' || !$row->batch_id || $row->counted_qty === null) {
                    continue;
                }

                $batch = Batch::query()
                    ->with('product')
                    ->lockForUpdate()
                    ->find($row->batch_id);

                if (!$batch) {
                    $row->update([
                        'status' => 'error',
                        'error_message' => 'Batch record no longer exists.',
                        'variance_qty' => null,
                        'system_qty' => null,
                    ]);

                    continue;
                }

                $systemQty = (int) $batch->available_quantity;
                $countedQty = (int) $row->counted_qty;

                $row->update([
                    'status' => 'reviewed',
                    'error_message' => null,
                    'product_id' => $batch->product_id,
                    'sku' => $batch->product?->sku,
                    'item_code' => $batch->product?->item_code_ierp,
                    'material_name' => $batch->product?->name,
                    'batch_number' => $batch->batch_number,
                    'expiry_date' => $batch->expiry_date,
                    'storage_location' => $batch->resolved_storage_location,
                    'system_qty' => $systemQty,
                    'variance_qty' => $countedQty - $systemQty,
                ]);
            }

            $summary = $this->summarizeSessionRows($lockedSession->rows()->get());

            $lockedSession->update([
                'status' => 'reviewed',
                'error_count' => (int) $summary['error_rows'],
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
                'meta' => $summary,
            ]);

            return $this->loadSession($lockedSession);
        });
    }

    public function postSession(StockTakeSession $session, int $userId): array
    {
        return DB::transaction(function () use ($session, $userId) {
            $lockedSession = StockTakeSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($lockedSession->status === 'closed') {
                return [
                    'status' => 'blocked',
                    'message' => 'This stock take session is already closed and cannot be posted again.',
                    'session' => $this->loadSession($lockedSession),
                ];
            }

            if ($lockedSession->status === 'posted') {
                return [
                    'status' => 'blocked',
                    'message' => 'This stock take session has already been posted.',
                    'session' => $this->loadSession($lockedSession),
                ];
            }

            $rows = StockTakeRow::query()
                ->where('stock_take_session_id', $lockedSession->id)
                ->orderBy('row_number')
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                return [
                    'status' => 'blocked',
                    'message' => 'This stock take session has no rows available to post.',
                    'session' => $this->loadSession($lockedSession),
                ];
            }

            if ($rows->contains(fn (StockTakeRow $row) => $row->status === 'error')) {
                return [
                    'status' => 'blocked',
                    'message' => 'Posting is blocked because some rows still have errors. Correct the file or re-import the session before posting.',
                    'session' => $this->loadSession($lockedSession),
                ];
            }

            $batchIds = $rows->pluck('batch_id')->filter()->unique()->sort()->values()->all();
            $batches = Batch::query()
                ->with('product')
                ->whereIn('id', $batchIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $staleRows = [];

            foreach ($rows as $row) {
                if ($row->status === 'stale') {
                    $staleRows[] = $row;
                    continue;
                }

                if (!$row->batch_id) {
                    $staleRows[] = $row;
                    continue;
                }

                /** @var Batch|null $batch */
                $batch = $batches->get($row->batch_id);

                if (!$batch || $row->system_qty === null || (int) $batch->available_quantity !== (int) $row->system_qty) {
                    $staleRows[] = $row;
                }
            }

            if ($staleRows !== []) {
                $staleIds = collect($staleRows)->pluck('id')->all();

                StockTakeRow::query()
                    ->whereIn('id', $staleIds)
                    ->update([
                        'status' => 'stale',
                        'error_message' => 'Current batch quantity changed after review. Recalculate the session before posting.',
                    ]);

                StockTakeRow::query()
                    ->where('stock_take_session_id', $lockedSession->id)
                    ->whereNotIn('id', $staleIds)
                    ->where('status', '!=', 'error')
                    ->update([
                        'status' => 'reviewed',
                    ]);

                $summary = $this->summarizeSessionRows($lockedSession->rows()->get());

                $lockedSession->update([
                    'status' => 'reviewed',
                    'error_count' => (int) $summary['error_rows'],
                    'reviewed_by' => $userId,
                    'reviewed_at' => now(),
                    'meta' => $summary,
                ]);

                return [
                    'status' => 'stale',
                    'message' => 'Posting is blocked because one or more batch quantities changed after review. Recalculate the session, review it again, then post.',
                    'stale_rows' => collect($staleRows)->map(fn (StockTakeRow $row) => $row->row_number)->all(),
                    'session' => $this->loadSession($lockedSession),
                ];
            }

            $appliedRows = 0;
            $skippedRows = 0;

            $this->batchService->withinStockMutationScope(function () use ($rows, $userId, &$appliedRows, &$skippedRows, $lockedSession) {
                foreach ($rows as $row) {
                    $varianceQty = (int) ($row->variance_qty ?? 0);

                    if ($varianceQty === 0) {
                        $row->update([
                            'status' => 'posted',
                            'error_message' => null,
                            'posted_by' => $userId,
                            'posted_at' => now(),
                        ]);

                        $skippedRows++;

                        continue;
                    }

                    $adjustment = $this->inventoryAdjustmentService->create([
                        'adjustment_type' => 'stock_take_import',
                        'direction' => $varianceQty > 0 ? 'in' : 'out',
                        'source' => 'stock_take_import',
                        'reference' => $row->reference ?: $lockedSession->reference,
                        'notes' => $row->notes ?: "Stock take session {$lockedSession->session_code} row {$row->row_number}.",
                        'adjusted_by' => $userId,
                        'imported_by' => $lockedSession->imported_by ?: $userId,
                        'adjusted_at' => now(),
                        'imported_at' => $lockedSession->imported_at ?? now(),
                        'meta' => [
                            'stock_take_session_id' => $lockedSession->id,
                            'stock_take_row_id' => $row->id,
                            'product_id' => $row->product_id,
                            'batch_id' => $row->batch_id,
                            'item_code' => $row->item_code,
                            'batch_number' => $row->batch_number,
                            'expiry_date' => $row->expiry_date?->format('Y-m-d'),
                            'storage_location' => $row->storage_location,
                            'system_qty' => $row->system_qty,
                            'counted_qty' => $row->counted_qty,
                            'variance_qty' => $varianceQty,
                            'session_code' => $lockedSession->session_code,
                        ],
                    ], 'STK');

                    $this->batchService->applyStockTakeCount(
                        batch: Batch::findOrFail($row->batch_id),
                        countedQuantity: (int) $row->counted_qty,
                        inventoryAdjustment: $adjustment,
                        notes: trim(
                            "Stock take {$lockedSession->session_code} row {$row->row_number}. "
                            . 'Reference: ' . ($row->reference ?: '-') . '. '
                            . ($row->notes ?: '')
                        ),
                    );

                    $row->update([
                        'status' => 'posted',
                        'error_message' => null,
                        'inventory_adjustment_id' => $adjustment->id,
                        'posted_by' => $userId,
                        'posted_at' => now(),
                    ]);

                    $appliedRows++;
                }
            });

            $summary = $this->summarizeSessionRows($lockedSession->rows()->get());

            $lockedSession->update([
                'status' => 'posted',
                'error_count' => (int) $summary['error_rows'],
                'reviewed_by' => $lockedSession->reviewed_by ?: $userId,
                'reviewed_at' => $lockedSession->reviewed_at ?: now(),
                'posted_by' => $userId,
                'posted_at' => now(),
                'meta' => $summary,
            ]);

            return [
                'status' => 'posted',
                'message' => "Stock take posted successfully. Adjusted rows: {$appliedRows}; zero-variance rows kept as evidence: {$skippedRows}.",
                'applied_rows' => $appliedRows,
                'skipped_rows' => $skippedRows,
                'session' => $this->loadSession($lockedSession),
            ];
        });
    }

    public function closeSession(StockTakeSession $session, int $userId): StockTakeSession
    {
        return DB::transaction(function () use ($session, $userId) {
            $lockedSession = StockTakeSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($lockedSession->status === 'closed') {
                return $this->loadSession($lockedSession);
            }

            if ($lockedSession->status !== 'posted') {
                throw new \RuntimeException('Only posted stock take sessions can be closed. Review and post the session first.');
            }

            StockTakeRow::query()
                ->where('stock_take_session_id', $lockedSession->id)
                ->where('status', 'posted')
                ->update([
                    'status' => 'closed',
                    'closed_by' => $userId,
                    'closed_at' => now(),
                ]);

            $summary = $this->summarizeSessionRows($lockedSession->rows()->get());

            $lockedSession->update([
                'status' => 'closed',
                'closed_by' => $userId,
                'closed_at' => now(),
                'meta' => $summary,
            ]);

            return $this->loadSession($lockedSession);
        });
    }

    public function exportRows(StockTakeSession $session, bool $includeValuation): Collection
    {
        return $session->rows()
            ->with(['batch.product.unit', 'product.unit', 'inventoryAdjustment'])
            ->orderBy('row_number')
            ->get()
            ->map(function (StockTakeRow $row) use ($includeValuation): array {
                $product = $row->product ?: $row->batch?->product;
                $unitCost = (int) ($row->batch?->unit_cost ?? 0);
                $averageCost = (int) ($product?->purchase_price ?? 0);
                $inventoryValue = (int) (($row->batch?->available_quantity ?? 0) * $unitCost);
                $adjustmentValue = $row->variance_qty === null ? null : ((int) $row->variance_qty * $unitCost);

                $payload = [
                    'row_number' => $row->row_number,
                    'sku' => $row->sku ?? '-',
                    'item_code' => $row->item_code ?? '-',
                    'material_name' => $row->material_name ?? '-',
                    'batch_number' => $row->batch_number ?? '-',
                    'system_qty' => $row->system_qty,
                    'counted_qty' => $row->counted_qty,
                    'variance_qty' => $row->variance_qty,
                    'expiry_date' => $row->expiry_date?->format('Y-m-d') ?? '-',
                    'storage_location' => $row->storage_location ?? '-',
                    'reference' => $row->reference ?? '-',
                    'status' => $row->status,
                    'notes' => $row->notes ?? '-',
                    'error_message' => $row->error_message ?? '-',
                ];

                if ($includeValuation) {
                    $payload['unit_cost'] = $unitCost;
                    $payload['adjustment_value'] = $adjustmentValue;
                    $payload['inventory_value'] = $inventoryValue;
                    $payload['average_cost'] = $averageCost;
                }

                return $payload;
            });
    }

    public function summarizeSessionRows(Collection $rows): array
    {
        return [
            'processed_rows' => $rows->count(),
            'valid_rows' => $rows->where('status', '!=', 'error')->count(),
            'error_rows' => $rows->where('status', 'error')->count(),
            'adjustment_rows' => $rows->filter(fn ($row) => $row->status !== 'error' && (int) ($row->variance_qty ?? 0) !== 0)->count(),
            'zero_variance_rows' => $rows->filter(fn ($row) => $row->status !== 'error' && (int) ($row->variance_qty ?? 0) === 0)->count(),
            'stale_rows' => $rows->where('status', 'stale')->count(),
            'posted_rows' => $rows->filter(fn ($row) => in_array($row->status, ['posted', 'closed'], true))->count(),
        ];
    }

    public function loadSession(StockTakeSession $session): StockTakeSession
    {
        return $session->fresh([
            'importedByUser',
            'reviewedByUser',
            'postedByUser',
            'closedByUser',
            'rows.batch.product.unit',
            'rows.inventoryAdjustment',
        ]);
    }

    private function summarizeRowPayloads(array $rows): array
    {
        return $this->summarizeSessionRows(collect($rows)->map(function (array $row) {
            return (object) [
                'status' => $row['status'],
                'variance_qty' => $row['variance_qty'],
            ];
        }));
    }

    private function resolveSessionReference(array $rows): ?string
    {
        $references = collect($rows)
            ->pluck('reference')
            ->filter(fn (?string $value) => is_string($value) && trim($value) !== '')
            ->unique()
            ->values();

        return $references->count() === 1 ? $references->first() : null;
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
            array_diff(self::REQUIRED_HEADERS, array_keys($headerMap))
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
            $index = $headerMap[$header] ?? null;
            $row[$header] = $index === null ? null : ($cells[$index] ?? null);
        }

        return $row;
    }

    private function normalizePreviewRow(array $row, int $rowNumber): array
    {
        $sku = $this->requireString($row['sku'] ?? null, 'SKU is required.');
        $product = Product::query()->with('unit')->where('sku', $sku)->first();

        if (!$product) {
            throw new \RuntimeException("SKU '{$sku}' was not found in the current material master.");
        }

        $itemCode = $this->cleanString($row['item_code'] ?? null);
        $storedItemCode = $product->item_code_ierp ? trim((string) $product->item_code_ierp) : null;

        if ($itemCode !== null && $storedItemCode !== null && strcasecmp($itemCode, $storedItemCode) !== 0) {
            throw new \RuntimeException("Item Code '{$itemCode}' does not match SKU '{$sku}'.");
        }

        $batchNumber = $this->requireString($row['batch_no'] ?? null, 'Batch No is required.');
        $matchingBatches = Batch::query()
            ->with('product.unit', 'storageLocationRecord')
            ->where('product_id', $product->id)
            ->where('batch_number', $batchNumber)
            ->get();

        if ($matchingBatches->isEmpty()) {
            $existingBatch = Batch::query()->where('batch_number', $batchNumber)->first();

            if ($existingBatch) {
                throw new \RuntimeException("Batch No '{$batchNumber}' does not belong to SKU '{$sku}'.");
            }

            throw new \RuntimeException("Batch No '{$batchNumber}' was not found for the current stock records.");
        }

        $expiryDate = $this->parseDate($row['expiry_date'] ?? null, 'Invalid Expiry Date format.');
        $locationValue = $this->cleanString($row['storage_location'] ?? null);
        $candidateBatches = $matchingBatches;

        if ($expiryDate !== null) {
            $expiryMatchedBatches = $candidateBatches->filter(
                fn (Batch $candidate) => $candidate->expiry_date?->format('Y-m-d') === $expiryDate
            )->values();

            if ($expiryMatchedBatches->isEmpty()) {
                throw new \RuntimeException("Batch No '{$batchNumber}' expiry does not match the current batch record.");
            }

            $candidateBatches = $expiryMatchedBatches;
        }

        if ($locationValue !== null) {
            $location = StorageLocation::query()
                ->where('code', $locationValue)
                ->orWhere('name', $locationValue)
                ->orWhereRaw('LOWER(code) = ?', [Str::lower($locationValue)])
                ->orWhereRaw('LOWER(name) = ?', [Str::lower($locationValue)])
                ->first();

            if (!$location) {
                throw new \RuntimeException("Unknown Storage Location '{$locationValue}'.");
            }

            $locationMatchedBatches = $candidateBatches->filter(
                fn (Batch $candidate) => $this->batchMatchesLocation($candidate, $locationValue, $location)
            )->values();

            if ($locationMatchedBatches->isEmpty()) {
                throw new \RuntimeException("Batch No '{$batchNumber}' storage location does not match the current batch record.");
            }

            $candidateBatches = $locationMatchedBatches;
        }

        if ($candidateBatches->count() > 1) {
            throw new \RuntimeException("Multiple batch records match SKU '{$sku}' and Batch No '{$batchNumber}'. Please provide Expiry and/or Storage Location to disambiguate.");
        }

        /** @var Batch $batch */
        $batch = $candidateBatches->first();
        $currentExpiry = $batch->expiry_date?->format('Y-m-d');

        if ($this->cleanString($row['counted_qty'] ?? null) === null) {
            throw new \RuntimeException('Counted Qty is required.');
        }

        $countedQty = $this->parseInteger($row['counted_qty'] ?? null, 'Counted Qty is invalid.');

        if ($countedQty < 0) {
            throw new \RuntimeException('Counted Qty cannot be negative.');
        }

        $currentQty = (int) $batch->available_quantity;
        $varianceQty = $countedQty - $currentQty;

        return [
            'row_number' => $rowNumber,
            'status' => 'imported',
            'error_message' => null,
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'sku' => $product->sku,
            'item_code' => $storedItemCode,
            'material_name' => $product->name,
            'batch_number' => $batch->batch_number,
            'expiry_date' => $currentExpiry,
            'storage_location' => $batch->resolved_storage_location,
            'system_qty' => $currentQty,
            'current_qty' => $currentQty,
            'counted_qty' => $countedQty,
            'variance_qty' => $varianceQty,
            'variance' => $varianceQty,
            'reference' => $this->cleanString($row['reference'] ?? null) ?? '',
            'notes' => $this->cleanString($row['notes'] ?? null) ?? '',
            'meta' => null,
        ];
    }

    private function errorPreviewRow(array $row, int $rowNumber, string $message): array
    {
        $countedQty = null;

        try {
            if ($this->cleanString($row['counted_qty'] ?? null) !== null) {
                $countedQty = $this->parseInteger($row['counted_qty'] ?? null, 'Counted Qty is invalid.');
            }
        } catch (\Throwable) {
            $countedQty = null;
        }

        return [
            'row_number' => $rowNumber,
            'status' => 'error',
            'error_message' => $message,
            'product_id' => null,
            'batch_id' => null,
            'sku' => $this->cleanString($row['sku'] ?? null) ?? '',
            'item_code' => $this->cleanString($row['item_code'] ?? null) ?? '',
            'material_name' => $this->cleanString($row['material'] ?? null) ?? '',
            'batch_number' => $this->cleanString($row['batch_no'] ?? null) ?? '',
            'expiry_date' => $this->parseDateOrNull($row['expiry_date'] ?? null),
            'storage_location' => $this->cleanString($row['storage_location'] ?? null) ?? '',
            'system_qty' => null,
            'current_qty' => null,
            'counted_qty' => $countedQty,
            'variance_qty' => null,
            'variance' => null,
            'reference' => $this->cleanString($row['reference'] ?? null) ?? '',
            'notes' => $this->cleanString($row['notes'] ?? null) ?? '',
            'meta' => null,
        ];
    }

    private function batchMatchesLocation(Batch $batch, string $locationValue, StorageLocation $location): bool
    {
        return $batch->storage_location_id === $location->id
            || strcasecmp((string) $batch->storage_location, $locationValue) === 0
            || strcasecmp((string) $batch->resolved_storage_location, $locationValue) === 0
            || strcasecmp((string) $location->display_label, (string) $batch->resolved_storage_location) === 0;
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

    private function parseDateOrNull(mixed $value): ?string
    {
        try {
            return $this->parseDate($value, 'Invalid Expiry Date format.');
        } catch (\Throwable) {
            return null;
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
