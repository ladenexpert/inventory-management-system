<?php

namespace App\Services;

use App\DTOs\CategoryData;
use App\DTOs\CustomerData;
use App\DTOs\PhysicalFormData;
use App\DTOs\ProductData;
use App\DTOs\StorageLocationData;
use App\DTOs\SupplierData;
use App\DTOs\TeamData;
use App\DTOs\UnitData;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PhysicalForm;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\Team;
use App\Models\Unit;
use App\Support\RmpTerminology;
use DateTimeInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

class MasterDataImportService
{
    public function __construct(
        protected ProductService $productService,
        protected CategoryService $categoryService,
        protected UnitService $unitService,
        protected SupplierService $supplierService,
        protected CustomerService $customerService,
        protected StorageLocationService $storageLocationService,
        protected PhysicalFormService $physicalFormService,
        protected TeamService $teamService,
    ) {
    }

    public function definitions(): array
    {
        return [
            'materials' => [
                'label' => 'Materials',
                'headers' => ['sku', 'item_code_ierp', 'name', 'category', 'unit', 'physical_form', 'supplier', 'purchase_price', 'selling_price', 'min_stock', 'is_active', 'description', 'notes'],
                'template_headers' => ['SKU', RmpTerminology::ITEM_CODE, RmpTerminology::MATERIAL_NAME, 'Category', RmpTerminology::UNIT, RmpTerminology::PHYSICAL_FORM, 'Supplier', 'Purchase Price', 'Selling Price', 'Min Stock', RmpTerminology::STATUS, 'Description', RmpTerminology::NOTES],
                'header_aliases' => [
                    'item_code_ierp' => ['item_code', 'Item Code IERP'],
                    'name' => ['product_name'],
                    'is_active' => ['active'],
                ],
                'sample_rows' => [[
                    'RM-0001',
                    'IERP-RM-0001',
                    'Microcrystalline Cellulose',
                    'Excipients',
                    'KG',
                    'powder',
                    'PT Sample Supplier',
                    '25000',
                    '32000',
                    '5',
                    '1',
                    'Pharma excipient',
                    'Initial master import',
                ]],
            ],
            'categories' => [
                'label' => 'Categories',
                'headers' => ['name', 'slug', 'description'],
                'template_headers' => ['Name', 'Slug', 'Description'],
                'sample_rows' => [[
                    'Excipients',
                    'excipients',
                    'Supportive raw material category',
                ]],
            ],
            'units' => [
                'label' => 'Units',
                'headers' => ['name', 'symbol'],
                'template_headers' => ['Name', 'Symbol'],
                'sample_rows' => [[
                    'Kilogram',
                    'KG',
                ]],
            ],
            'suppliers' => [
                'label' => 'Suppliers',
                'headers' => ['name', 'contact_person', 'email', 'phone', 'address', 'notes'],
                'template_headers' => ['Supplier Name', 'Contact Person', 'Email', 'Phone', 'Address', RmpTerminology::NOTES],
                'sample_rows' => [[
                    'PT Sample Supplier',
                    'Nina',
                    'nina@example.com',
                    '08123456789',
                    'Jakarta',
                    'Preferred vendor',
                ]],
            ],
            'customers' => [
                'label' => 'Customers',
                'headers' => ['name', 'email', 'phone', 'address', 'notes'],
                'template_headers' => ['Customer Name', 'Email', 'Phone', 'Address', RmpTerminology::NOTES],
                'sample_rows' => [[
                    'Apotek Sehat',
                    'buyer@example.com',
                    '0812000000',
                    'Bandung',
                    'Retail account',
                ]],
            ],
            'storage-locations' => [
                'label' => 'Storage Locations',
                'headers' => ['code', 'name', 'type', 'parent', 'description', 'is_active'],
                'template_headers' => ['Storage Location Code', 'Storage Location Name', 'Type', 'Parent', 'Description', RmpTerminology::STATUS],
                'sample_rows' => [[
                    'RACK-A1',
                    'Rack A1',
                    'rack',
                    '',
                    'Primary raw material rack',
                    '1',
                ]],
            ],
            'physical-forms' => [
                'label' => 'Physical Forms',
                'headers' => ['code', 'name', 'description', 'is_active'],
                'template_headers' => ['Physical Form Code', 'Physical Form Name', 'Description', RmpTerminology::STATUS],
                'sample_rows' => [[
                    'powder',
                    'Powder',
                    'Default powder physical form',
                    '1',
                ]],
            ],
            'teams' => [
                'label' => 'Teams',
                'headers' => ['code', 'name', 'description', 'is_active'],
                'template_headers' => ['Team Code', 'Team Name', 'Description', RmpTerminology::STATUS],
                'sample_rows' => [[
                    'RND',
                    'R&D',
                    'Research and development team',
                    '1',
                ]],
            ],
        ];
    }

    public function definition(string $resource): array
    {
        $definition = $this->definitions()[$resource] ?? null;

        if ($definition === null) {
            throw new RuntimeException("Unsupported import resource '{$resource}'.");
        }

        return $definition;
    }

    public function buildTemplateFile(string $resource): string
    {
        $definition = $this->definition($resource);
        $directory = storage_path('app/temp');

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filePath = $directory . DIRECTORY_SEPARATOR . "template-{$resource}-" . Str::random(8) . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues($definition['template_headers'] ?? $definition['headers']));

        foreach ($definition['sample_rows'] as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->addRow(Row::fromValues(array_pad(['# rows starting with # are ignored during import'], count($definition['headers']), '')));
        $writer->close();

        return $filePath;
    }

    public function importFromFile(string $resource, string $filePath): array
    {
        $definition = $this->definition($resource);
        $reader = ReaderFactory::createFromFileByMimeType($filePath);
        $reader->open($filePath);

        $headerMap = null;
        $processedRows = 0;
        $skippedRows = 0;
        $createdRows = 0;
        $failedRows = 0;
        $errors = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    $cells = $row->toArray();

                    if ($headerMap === null) {
                        $headerMap = $this->buildHeaderMap($definition, $cells);
                        continue;
                    }

                    if ($this->isCommentRow($cells) || $this->isEmptyRow($cells)) {
                        $skippedRows++;
                        continue;
                    }

                    $processedRows++;

                    try {
                        $rowData = $this->mapRow($definition['headers'], $headerMap, $cells);
                        $this->persistRow($resource, $rowData);
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
            throw new RuntimeException('Template is invalid: header row not found.');
        }

        return [
            'processed_rows' => $processedRows,
            'skipped_rows' => $skippedRows,
            'created_rows' => $createdRows,
            'failed_rows' => $failedRows,
            'errors' => $errors,
        ];
    }

    protected function persistRow(string $resource, array $row): void
    {
        match ($resource) {
            'materials' => $this->persistMaterial($row),
            'categories' => $this->persistCategory($row),
            'units' => $this->persistUnit($row),
            'suppliers' => $this->persistSupplier($row),
            'customers' => $this->persistCustomer($row),
            'storage-locations' => $this->persistStorageLocation($row),
            'physical-forms' => $this->persistPhysicalForm($row),
            'teams' => $this->persistTeam($row),
            default => throw new RuntimeException("Unsupported import resource '{$resource}'."),
        };
    }

    protected function persistMaterial(array $row): void
    {
        $name = $this->requireString($row['name'] ?? null, RmpTerminology::MATERIAL_NAME . ' is required.');
        $sku = $this->cleanString($row['sku'] ?? null);
        $itemCodeIerp = $this->cleanString($row['item_code_ierp'] ?? null);

        if ($sku && Product::where('sku', $sku)->exists()) {
            throw new RuntimeException("SKU '{$sku}' is already registered.");
        }

        if ($itemCodeIerp && Product::where('item_code_ierp', $itemCodeIerp)->exists()) {
            throw new RuntimeException(RmpTerminology::ITEM_CODE . " '{$itemCodeIerp}' is already registered.");
        }

        if (Product::where('name', $name)->exists()) {
            throw new RuntimeException("Material '{$name}' already exists.");
        }

        $this->productService->createProduct(ProductData::fromArray([
            'category_id' => $this->resolveCategoryId($row['category'] ?? null),
            'unit_id' => $this->resolveUnitId($row['unit'] ?? null),
            'supplier_id' => $this->resolveSupplierId($row['supplier'] ?? null),
            'sku' => $sku,
            'item_code_ierp' => $itemCodeIerp,
            'name' => $name,
            'physical_form' => $this->normalizePhysicalForm($row['physical_form'] ?? null),
            'purchase_price' => $this->parseInteger($row['purchase_price'] ?? null, 'Purchase price is invalid.', true),
            'selling_price' => $this->parseInteger($row['selling_price'] ?? null, 'Selling price is invalid.', true),
            'quantity' => 0,
            'min_stock' => $this->parseInteger($row['min_stock'] ?? null, 'Minimum stock is invalid.', false) ?? 0,
            'is_active' => $this->parseBoolean($row['is_active'] ?? null),
            'description' => $this->cleanString($row['description'] ?? null),
            'notes' => $this->cleanString($row['notes'] ?? null),
        ]));
    }

    protected function persistCategory(array $row): void
    {
        $name = $this->requireString($row['name'] ?? null, 'Category name is required.');
        $slug = $this->cleanString($row['slug'] ?? null);

        if (Category::where('name', $name)->exists() || ($slug && Category::where('slug', $slug)->exists())) {
            throw new RuntimeException("Category '{$name}' already exists.");
        }

        $this->categoryService->createCategory(CategoryData::fromArray([
            'name' => $name,
            'slug' => $slug,
            'description' => $this->cleanString($row['description'] ?? null),
        ]));
    }

    protected function persistUnit(array $row): void
    {
        $name = $this->requireString($row['name'] ?? null, 'Unit name is required.');
        $symbol = $this->requireString($row['symbol'] ?? null, 'Unit symbol is required.');

        if (Unit::where('name', $name)->exists() || Unit::where('symbol', $symbol)->exists()) {
            throw new RuntimeException("Unit '{$name}' / '{$symbol}' already exists.");
        }

        $this->unitService->createUnit(UnitData::fromArray([
            'name' => $name,
            'symbol' => $symbol,
        ]));
    }

    protected function persistSupplier(array $row): void
    {
        $name = $this->requireString($row['name'] ?? null, 'Supplier name is required.');

        if (Supplier::where('name', $name)->exists()) {
            throw new RuntimeException("Supplier '{$name}' already exists.");
        }

        $this->supplierService->createSupplier(SupplierData::fromArray([
            'name' => $name,
            'contact_person' => $this->cleanString($row['contact_person'] ?? null) ?? '',
            'email' => $this->cleanString($row['email'] ?? null),
            'phone' => $this->cleanString($row['phone'] ?? null),
            'address' => $this->cleanString($row['address'] ?? null),
            'notes' => $this->cleanString($row['notes'] ?? null),
        ]));
    }

    protected function persistCustomer(array $row): void
    {
        $name = $this->requireString($row['name'] ?? null, 'Customer name is required.');

        if (Customer::where('name', $name)->exists()) {
            throw new RuntimeException("Customer '{$name}' already exists.");
        }

        $this->customerService->createCustomer(CustomerData::fromArray([
            'name' => $name,
            'email' => $this->cleanString($row['email'] ?? null),
            'phone' => $this->cleanString($row['phone'] ?? null),
            'address' => $this->cleanString($row['address'] ?? null),
            'notes' => $this->cleanString($row['notes'] ?? null),
        ]));
    }

    protected function persistStorageLocation(array $row): void
    {
        $code = Str::upper($this->requireString($row['code'] ?? null, 'Location code is required.'));
        $name = $this->requireString($row['name'] ?? null, 'Location name is required.');

        if (StorageLocation::where('code', $code)->exists()) {
            throw new RuntimeException("Storage location code '{$code}' already exists.");
        }

        $this->storageLocationService->createLocation(StorageLocationData::fromArray([
            'code' => $code,
            'name' => $name,
            'type' => $this->cleanString($row['type'] ?? null),
            'parent_id' => $this->resolveStorageLocationParentId($row['parent'] ?? null),
            'description' => $this->cleanString($row['description'] ?? null),
            'is_active' => $this->parseBoolean($row['is_active'] ?? null),
        ]));
    }

    protected function persistPhysicalForm(array $row): void
    {
        $code = Str::lower($this->requireString($row['code'] ?? null, 'Physical form code is required.'));
        $name = $this->requireString($row['name'] ?? null, 'Physical form name is required.');

        if (PhysicalForm::where('code', $code)->exists() || PhysicalForm::where('name', $name)->exists()) {
            throw new RuntimeException("Physical form '{$name}' already exists.");
        }

        $this->physicalFormService->createPhysicalForm(PhysicalFormData::fromArray([
            'code' => $code,
            'name' => $name,
            'description' => $this->cleanString($row['description'] ?? null),
            'is_active' => $this->parseBoolean($row['is_active'] ?? null),
        ]));
    }

    protected function persistTeam(array $row): void
    {
        $code = Str::upper($this->requireString($row['code'] ?? null, 'Team code is required.'));
        $name = $this->requireString($row['name'] ?? null, 'Team name is required.');

        if (Team::where('code', $code)->exists() || Team::where('name', $name)->exists()) {
            throw new RuntimeException("Team '{$name}' already exists.");
        }

        $this->teamService->createTeam(TeamData::fromArray([
            'code' => $code,
            'name' => $name,
            'description' => $this->cleanString($row['description'] ?? null),
            'is_active' => $this->parseBoolean($row['is_active'] ?? null),
        ]));
    }

    protected function buildHeaderMap(array $definition, array $headerRow): array
    {
        $expectedHeaders = $definition['headers'];
        $templateHeaders = $definition['template_headers'] ?? $expectedHeaders;
        $normalizedHeaders = [];

        foreach ($headerRow as $index => $headerCell) {
            $header = $this->normalizeHeader((string) $headerCell);

            if ($header !== '') {
                $normalizedHeaders[$header] = $index;
            }
        }

        $headerMap = [];
        $headerLabels = [];

        foreach ($expectedHeaders as $index => $header) {
            $label = $templateHeaders[$index] ?? $header;
            $headerLabels[$header] = $label;
            $aliases = array_merge(
                [$header, $label],
                $definition['header_aliases'][$header] ?? [],
            );

            foreach (array_unique(array_map([$this, 'normalizeHeader'], $aliases)) as $normalizedAlias) {
                if (array_key_exists($normalizedAlias, $normalizedHeaders)) {
                    $headerMap[$header] = $normalizedHeaders[$normalizedAlias];
                    break;
                }
            }
        }

        $missingHeaders = array_values(array_map(
            fn (string $header) => $headerLabels[$header] ?? $header,
            array_filter($expectedHeaders, fn (string $header) => !array_key_exists($header, $headerMap))
        ));

        if (!empty($missingHeaders)) {
            throw new RuntimeException('Template is invalid. Missing headers: ' . implode(', ', $missingHeaders));
        }

        return $headerMap;
    }

    protected function mapRow(array $headers, array $headerMap, array $cells): array
    {
        $row = [];

        foreach ($headers as $header) {
            $columnIndex = $headerMap[$header] ?? null;
            $row[$header] = $columnIndex !== null ? ($cells[$columnIndex] ?? null) : null;
        }

        return $row;
    }

    protected function resolveCategoryId(mixed $value): int
    {
        $raw = $this->requireString($value, 'Category is required.');

        if (ctype_digit($raw) && ($category = Category::find((int) $raw))) {
            return $category->id;
        }

        $slug = Str::slug($raw);
        $category = Category::query()
            ->where('name', $raw)
            ->orWhere('slug', $raw)
            ->orWhere('slug', $slug)
            ->first();

        if (!$category) {
            throw new RuntimeException("Category '{$raw}' was not found.");
        }

        return $category->id;
    }

    protected function resolveUnitId(mixed $value): int
    {
        $raw = $this->requireString($value, 'Unit is required.');

        if (ctype_digit($raw) && ($unit = Unit::find((int) $raw))) {
            return $unit->id;
        }

        $unit = Unit::query()
            ->where('name', $raw)
            ->orWhere('symbol', $raw)
            ->orWhereRaw('LOWER(name) = ?', [Str::lower($raw)])
            ->orWhereRaw('LOWER(symbol) = ?', [Str::lower($raw)])
            ->first();

        if (!$unit) {
            throw new RuntimeException("Unit '{$raw}' was not found.");
        }

        return $unit->id;
    }

    protected function resolveSupplierId(mixed $value): ?int
    {
        $raw = $this->cleanString($value);

        if ($raw === null) {
            return null;
        }

        if (ctype_digit($raw) && ($supplier = Supplier::find((int) $raw))) {
            return $supplier->id;
        }

        $supplier = Supplier::query()
            ->where('name', $raw)
            ->orWhereRaw('LOWER(name) = ?', [Str::lower($raw)])
            ->first();

        if (!$supplier) {
            throw new RuntimeException("Supplier '{$raw}' was not found.");
        }

        return $supplier->id;
    }

    protected function resolveStorageLocationParentId(mixed $value): ?int
    {
        $raw = $this->cleanString($value);

        if ($raw === null) {
            return null;
        }

        if (ctype_digit($raw) && ($location = StorageLocation::find((int) $raw))) {
            return $location->id;
        }

        $location = StorageLocation::query()
            ->where('code', $raw)
            ->orWhere('name', $raw)
            ->orWhereRaw('LOWER(code) = ?', [Str::lower($raw)])
            ->orWhereRaw('LOWER(name) = ?', [Str::lower($raw)])
            ->first();

        if (!$location) {
            throw new RuntimeException("Parent storage location '{$raw}' was not found.");
        }

        return $location->id;
    }

    protected function normalizePhysicalForm(mixed $value): ?string
    {
        $raw = $this->cleanString($value);

        if ($raw === null) {
            return null;
        }

        $normalized = Str::of($raw)
            ->trim()
            ->lower()
            ->replace([' ', '-'], '_')
            ->toString();

        $options = collect(Product::physicalFormOptions());

        if ($options->has($normalized)) {
            return $normalized;
        }

        $matchedCode = $options->search(fn (string $label) => Str::lower($label) === Str::lower($raw));

        if ($matchedCode === false) {
            throw new RuntimeException("Physical form '{$raw}' is invalid.");
        }

        return (string) $matchedCode;
    }

    protected function parseInteger(mixed $value, string $errorMessage, bool $required): ?int
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

    protected function parseBoolean(mixed $value): bool
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

        throw new RuntimeException("Boolean value '{$raw}' is invalid. Use 1/0, true/false, or yes/no.");
    }

    protected function cleanString(mixed $value): ?string
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

    protected function requireString(mixed $value, string $message): string
    {
        $clean = $this->cleanString($value);

        if ($clean === null) {
            throw new RuntimeException($message);
        }

        return $clean;
    }

    protected function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($this->cleanString($cell) !== null) {
                return false;
            }
        }

        return true;
    }

    protected function isCommentRow(array $cells): bool
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

    protected function normalizeHeader(string $header): string
    {
        return RmpTerminology::normalizeHeader($header);
    }
}
