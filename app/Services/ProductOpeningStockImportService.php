<?php

namespace App\Services;

use App\DTOs\ProductData;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use DateTimeInterface;
use RuntimeException;
use Illuminate\Support\Str;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

class ProductOpeningStockImportService
{
    private const REQUIRED_HEADERS = [
        'name',
        'category',
        'unit',
        'purchase_price',
        'selling_price',
        'opening_quantity',
    ];

    private const HEADER_ALIASES = [
        'sku' => ['sku', 'product_sku'],
        'item_code_ierp' => ['item_code_ierp', 'ierp_item_code', 'ierp_code', 'item_code'],
        'name' => ['name', 'product_name'],
        'category' => ['category', 'category_name', 'category_slug', 'category_id'],
        'unit' => ['unit', 'unit_name', 'unit_symbol', 'unit_id'],
        'purchase_price' => ['purchase_price', 'buy_price', 'cost_price', 'harga_beli'],
        'selling_price' => ['selling_price', 'sale_price', 'harga_jual'],
        'opening_quantity' => ['opening_quantity', 'quantity', 'qty', 'stock_awal'],
        'opening_batch_number' => ['opening_batch_number', 'batch_number', 'opening_batch', 'batch'],
        'min_stock' => ['min_stock', 'minimum_stock', 'min_qty'],
        'is_active' => ['is_active', 'active'],
        'description' => ['description'],
        'notes' => ['notes', 'internal_notes'],
    ];

    public function __construct(
        protected ProductService $productService
    ) {
    }

    public function importFromFile(string $filePath): array
    {
        $reader = ReaderFactory::createFromFileByMimeType($filePath);
        $reader->open($filePath);

        $headerMap = null;
        $processedRows = 0;
        $skippedRows = 0;
        $createdRows = 0;
        $failedRows = 0;
        $errors = [];
        $seen = [
            'sku' => [],
            'item_code_ierp' => [],
            'opening_batch_number' => [],
        ];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    $cells = $row->toArray();

                    if ($headerMap === null) {
                        $headerMap = $this->buildHeaderMap($cells);
                        continue;
                    }

                    if ($this->isCommentRow($cells) || $this->isEmptyRow($cells)) {
                        $skippedRows++;
                        continue;
                    }

                    $processedRows++;

                    try {
                        $rowData = $this->mapRow($cells, $headerMap);
                        $payload = $this->normalizePayload($rowData, $seen);
                        $this->productService->createProduct(ProductData::fromArray($payload));
                        $createdRows++;
                    } catch (\Throwable $e) {
                        $failedRows++;

                        if (count($errors) < 100) {
                            $errors[] = [
                                'row' => $rowNumber,
                                'message' => $e->getMessage(),
                            ];
                        }
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        if ($headerMap === null) {
            throw new RuntimeException('Template tidak valid: baris header tidak ditemukan.');
        }

        return [
            'processed_rows' => $processedRows,
            'skipped_rows' => $skippedRows,
            'created_rows' => $createdRows,
            'failed_rows' => $failedRows,
            'errors' => $errors,
        ];
    }

    private function buildHeaderMap(array $headerRow): array
    {
        $normalizedHeaders = [];

        foreach ($headerRow as $index => $headerCell) {
            $header = $this->normalizeHeader((string) $headerCell);

            if ($header !== '') {
                $normalizedHeaders[$header] = $index;
            }
        }

        $headerMap = [];

        foreach (self::HEADER_ALIASES as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $normalizedHeaders)) {
                    $headerMap[$canonical] = $normalizedHeaders[$alias];
                    break;
                }
            }
        }

        $missingRequired = array_values(array_filter(self::REQUIRED_HEADERS, fn($field) => !array_key_exists($field, $headerMap)));

        if (!empty($missingRequired)) {
            throw new RuntimeException('Template tidak valid. Kolom wajib tidak ditemukan: ' . implode(', ', $missingRequired));
        }

        return $headerMap;
    }

    private function mapRow(array $cells, array $headerMap): array
    {
        $row = [];

        foreach (self::HEADER_ALIASES as $canonical => $aliases) {
            $columnIndex = $headerMap[$canonical] ?? null;
            $row[$canonical] = $columnIndex !== null ? ($cells[$columnIndex] ?? null) : null;
        }

        return $row;
    }

    private function normalizePayload(array $row, array &$seen): array
    {
        $sku = $this->cleanString($row['sku'] ?? null);
        $itemCodeIerp = $this->cleanString($row['item_code_ierp'] ?? null);
        $name = $this->requireString($row['name'] ?? null, 'Product name wajib diisi.');

        $categoryId = $this->resolveCategoryId($row['category'] ?? null);
        $unitId = $this->resolveUnitId($row['unit'] ?? null);
        $purchasePrice = $this->parseInteger($row['purchase_price'] ?? null, 'Purchase price tidak valid.', true);
        $sellingPrice = $this->parseInteger($row['selling_price'] ?? null, 'Selling price tidak valid.', true);
        $quantity = $this->parseInteger($row['opening_quantity'] ?? null, 'Opening quantity tidak valid.', true);
        $minStock = $this->parseInteger($row['min_stock'] ?? null, 'Min stock tidak valid.', false) ?? 0;
        $isActive = $this->parseBoolean($row['is_active'] ?? null);
        $description = $this->cleanString($row['description'] ?? null);
        $notes = $this->cleanString($row['notes'] ?? null);
        $openingBatchNumber = $this->cleanString($row['opening_batch_number'] ?? null);

        if ($purchasePrice < 0 || $sellingPrice < 0 || $quantity < 0 || $minStock < 0) {
            throw new RuntimeException('Nilai numerik tidak boleh negatif.');
        }

        if ($quantity === 0) {
            $openingBatchNumber = null;
        }

        if ($sku !== null) {
            $skuKey = Str::upper($sku);
            if (isset($seen['sku'][$skuKey])) {
                throw new RuntimeException("SKU '{$sku}' duplikat di file import.");
            }
            if (Product::where('sku', $sku)->exists()) {
                throw new RuntimeException("SKU '{$sku}' sudah terdaftar.");
            }
            $seen['sku'][$skuKey] = true;
        }

        if ($itemCodeIerp !== null) {
            $itemCodeKey = Str::upper($itemCodeIerp);
            if (isset($seen['item_code_ierp'][$itemCodeKey])) {
                throw new RuntimeException("Item Code IERP '{$itemCodeIerp}' duplikat di file import.");
            }
            if (Product::where('item_code_ierp', $itemCodeIerp)->exists()) {
                throw new RuntimeException("Item Code IERP '{$itemCodeIerp}' sudah terdaftar.");
            }
            $seen['item_code_ierp'][$itemCodeKey] = true;
        }

        if ($openingBatchNumber !== null) {
            $batchKey = Str::upper($openingBatchNumber);
            if (isset($seen['opening_batch_number'][$batchKey])) {
                throw new RuntimeException("Opening batch number '{$openingBatchNumber}' duplikat di file import.");
            }
            if (Batch::where('batch_number', $openingBatchNumber)->exists()) {
                throw new RuntimeException("Opening batch number '{$openingBatchNumber}' sudah terdaftar.");
            }
            $seen['opening_batch_number'][$batchKey] = true;
        }

        return [
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'sku' => $sku,
            'item_code_ierp' => $itemCodeIerp,
            'name' => $name,
            'purchase_price' => $purchasePrice,
            'selling_price' => $sellingPrice,
            'quantity' => $quantity,
            'opening_batch_number' => $openingBatchNumber,
            'min_stock' => $minStock,
            'is_active' => $isActive,
            'description' => $description,
            'notes' => $notes,
        ];
    }

    private function resolveCategoryId(mixed $value): int
    {
        $raw = $this->cleanString($value);

        if ($raw === null) {
            throw new RuntimeException('Category wajib diisi.');
        }

        if (ctype_digit($raw)) {
            $category = Category::find((int) $raw);
            if ($category) {
                return $category->id;
            }
        }

        $slug = Str::slug($raw);

        $category = Category::query()
            ->where('name', $raw)
            ->orWhere('slug', $raw)
            ->orWhere('slug', $slug)
            ->first();

        if (!$category) {
            throw new RuntimeException("Category '{$raw}' tidak ditemukan.");
        }

        return $category->id;
    }

    private function resolveUnitId(mixed $value): int
    {
        $raw = $this->cleanString($value);

        if ($raw === null) {
            throw new RuntimeException('Unit wajib diisi.');
        }

        if (ctype_digit($raw)) {
            $unit = Unit::find((int) $raw);
            if ($unit) {
                return $unit->id;
            }
        }

        $unit = Unit::query()
            ->where('name', $raw)
            ->orWhere('symbol', $raw)
            ->orWhereRaw('LOWER(name) = ?', [Str::lower($raw)])
            ->orWhereRaw('LOWER(symbol) = ?', [Str::lower($raw)])
            ->first();

        if (!$unit) {
            throw new RuntimeException("Unit '{$raw}' tidak ditemukan.");
        }

        return $unit->id;
    }

    private function parseInteger(mixed $value, string $errorMessage, bool $required): ?int
    {
        if ($value === null || $value === '') {
            if ($required) {
                throw new RuntimeException($errorMessage);
            }

            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        if ($value instanceof DateTimeInterface) {
            throw new RuntimeException($errorMessage);
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        $raw = trim((string) $value);
        $clean = preg_replace('/[^0-9\-]/', '', $raw);

        if ($clean === null || $clean === '' || $clean === '-') {
            throw new RuntimeException($errorMessage);
        }

        return (int) $clean;
    }

    private function parseBoolean(mixed $value): bool
    {
        $raw = $this->cleanString($value);

        if ($raw === null) {
            return true;
        }

        $normalized = Str::lower($raw);

        if (in_array($normalized, ['1', 'true', 'yes', 'y', 'active'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'n', 'inactive'], true)) {
            return false;
        }

        throw new RuntimeException("Nilai is_active '{$raw}' tidak valid. Gunakan 1/0, true/false, yes/no.");
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
            throw new RuntimeException($message);
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
        $header = Str::of($header)
            ->lower()
            ->replace([' ', '-'], '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->trim('_')
            ->toString();

        return $header;
    }
}
