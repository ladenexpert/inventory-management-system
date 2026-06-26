<?php

namespace Tests\Feature;

use App\Livewire\Batches\BatchTable;
use App\Livewire\Reports\ExpiryReportTable;
use App\Livewire\Reports\InventoryReportTable;
use App\Models\Batch;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Models\User;
use App\Support\RmpTerminology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\ReadsDownloadedSpreadsheet;
use Tests\TestCase;

class ReportExportRegressionTest extends TestCase
{
    use ReadsDownloadedSpreadsheet;
    use RefreshDatabase;

    public function test_current_inventory_export_normalizes_legacy_product_name_sort_and_returns_a_valid_xlsx(): void
    {
        $user = User::factory()->create();
        [$alphaBatch, $betaBatch] = $this->seedBatchMonitoringFixtures();

        $this->actingAs($user);

        [$component, $response, $queries] = $this->exportSpreadsheet(
            InventoryReportTable::class,
            ['preset' => 'inventory'],
            ['sortField' => 'product_name', 'sortDirection' => 'asc'],
        );

        $this->assertQueriesDoNotContain($queries, 'batches.product_name');
        $this->assertDownloadedXlsx($response);

        $rows = $this->downloadedSpreadsheetRows($response);
        $materialNameColumn = $this->columnIndex($rows[0], RmpTerminology::MATERIAL_NAME);

        $this->assertSame($alphaBatch->fresh()->product->name, $rows[1][$materialNameColumn]);
        $this->assertSame($betaBatch->fresh()->product->name, $rows[2][$materialNameColumn]);

        $component->assertSee($alphaBatch->batch_number)
            ->assertSee($betaBatch->batch_number);
    }

    public function test_current_inventory_export_normalizes_legacy_storage_location_sort_without_losing_component_rows(): void
    {
        $user = User::factory()->create();
        [$alphaBatch, $betaBatch, $alphaLocation, $betaLocation] = $this->seedBatchMonitoringFixtures();

        $this->actingAs($user);

        [$component, $response, $queries] = $this->exportSpreadsheet(
            InventoryReportTable::class,
            ['preset' => 'inventory'],
            ['sortField' => 'storage_location_label', 'sortDirection' => 'asc'],
        );

        $this->assertQueriesDoNotContain($queries, 'batches.storage_location_label');
        $this->assertDownloadedXlsx($response);

        $rows = $this->downloadedSpreadsheetRows($response);
        $locationColumn = $this->columnIndex($rows[0], RmpTerminology::STORAGE_LOCATION);

        $this->assertSame($alphaLocation->display_label, $rows[1][$locationColumn]);
        $this->assertSame($betaLocation->display_label, $rows[2][$locationColumn]);

        $component->assertSee($alphaBatch->batch_number)
            ->assertSee($betaBatch->batch_number);
    }

    public function test_current_inventory_export_keeps_status_and_days_remaining_sort_keys_safe(): void
    {
        $user = User::factory()->create();
        [$alphaBatch, $betaBatch] = $this->seedBatchMonitoringFixtures();

        $this->actingAs($user);

        [, $daysResponse, $dayQueries] = $this->exportSpreadsheet(
            InventoryReportTable::class,
            ['preset' => 'inventory'],
            ['sortField' => 'days_remaining_sort', 'sortDirection' => 'asc'],
        );

        $this->assertQueriesDoNotContain($dayQueries, 'batches.days_remaining_sort');
        $this->assertDownloadedXlsx($daysResponse);

        $dayRows = $this->downloadedSpreadsheetRows($daysResponse);
        $batchColumn = $this->columnIndex($dayRows[0], 'Batch No');

        $this->assertSame($alphaBatch->batch_number, $dayRows[1][$batchColumn]);
        $this->assertSame($betaBatch->batch_number, $dayRows[2][$batchColumn]);

        [, $statusResponse, $statusQueries] = $this->exportSpreadsheet(
            InventoryReportTable::class,
            ['preset' => 'inventory'],
            ['sortField' => 'status_sort', 'sortDirection' => 'asc'],
        );

        $this->assertQueriesDoNotContain($statusQueries, 'batches.status_sort');
        $this->assertDownloadedXlsx($statusResponse);

        $statusRows = $this->downloadedSpreadsheetRows($statusResponse);

        $this->assertSame($betaBatch->batch_number, $statusRows[1][$batchColumn]);
        $this->assertSame($alphaBatch->batch_number, $statusRows[2][$batchColumn]);
    }

    public function test_expiry_report_export_normalizes_legacy_product_name_sort_and_returns_a_valid_xlsx(): void
    {
        $user = User::factory()->create();
        [$alphaBatch, $betaBatch] = $this->seedBatchMonitoringFixtures();

        $this->actingAs($user);

        [, $response, $queries] = $this->exportSpreadsheet(
            ExpiryReportTable::class,
            [],
            ['sortField' => 'product_name', 'sortDirection' => 'asc'],
        );

        $this->assertQueriesDoNotContain($queries, 'batches.product_name');
        $this->assertDownloadedXlsx($response);

        $rows = $this->downloadedSpreadsheetRows($response);
        $materialNameColumn = $this->columnIndex($rows[0], RmpTerminology::MATERIAL_NAME);

        $this->assertSame($alphaBatch->fresh()->product->name, $rows[1][$materialNameColumn]);
        $this->assertSame($betaBatch->fresh()->product->name, $rows[2][$materialNameColumn]);
    }

    public function test_batch_monitoring_export_normalizes_legacy_product_name_sort_and_returns_a_valid_xlsx(): void
    {
        $user = User::factory()->create();
        [$alphaBatch, $betaBatch] = $this->seedBatchMonitoringFixtures();

        $this->actingAs($user);

        [, $response, $queries] = $this->exportSpreadsheet(
            BatchTable::class,
            [],
            ['sortField' => 'product_name', 'sortDirection' => 'asc'],
        );

        $this->assertQueriesDoNotContain($queries, 'batches.product_name');
        $this->assertDownloadedXlsx($response);

        $rows = $this->downloadedSpreadsheetRows($response);
        $materialNameColumn = $this->columnIndex($rows[0], RmpTerminology::MATERIAL_NAME);

        $this->assertSame($alphaBatch->fresh()->product->name, $rows[1][$materialNameColumn]);
        $this->assertSame($betaBatch->fresh()->product->name, $rows[2][$materialNameColumn]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $state
     * @return array{0: Testable, 1: TestResponse, 2: array<int, string>}
     */
    private function exportSpreadsheet(string $componentClass, array $parameters = [], array $state = []): array
    {
        $component = Livewire::test($componentClass, $parameters);

        foreach ($state as $property => $value) {
            $component->set($property, $value);
        }

        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $baseResponse = $component->instance()->exportToXLS();
        $queries = array_map(
            static fn (array $entry): string => $entry['query'],
            $connection->getQueryLog(),
        );

        $connection->disableQueryLog();

        $this->assertNotFalse($baseResponse);

        return [$component, TestResponse::fromBaseResponse($baseResponse), $queries];
    }

    /**
     * @param  array<int, string>  $queries
     */
    private function assertQueriesDoNotContain(array $queries, string $fragment): void
    {
        $normalizedFragment = str_replace('`', '', strtolower($fragment));

        $this->assertFalse(
            collect($queries)->contains(function (string $query) use ($normalizedFragment): bool {
                return str_contains(str_replace('`', '', strtolower($query)), $normalizedFragment);
            }),
            'Encountered unsafe export SQL: ' . implode(' | ', $queries),
        );
    }

    private function assertDownloadedXlsx(TestResponse $response): void
    {
        $response->assertDownload();

        $file = $response->baseResponse->getFile();

        $this->assertNotNull($file);
        $this->assertSame('PK', file_get_contents($file->getPathname(), false, null, 0, 2));
    }

    /**
     * @param  array<int, mixed>  $headerRow
     */
    private function columnIndex(array $headerRow, string $columnLabel): int
    {
        $index = array_search($columnLabel, $headerRow, true);

        $this->assertNotFalse($index, "Unable to find export column [{$columnLabel}].");

        return $index;
    }

    /**
     * @return array{0: Batch, 1: Batch, 2: StorageLocation, 3: StorageLocation}
     */
    private function seedBatchMonitoringFixtures(): array
    {
        $alphaLocation = StorageLocation::factory()->create([
            'code' => 'RM-A1',
            'name' => 'Rack Alpha',
        ]);

        $betaLocation = StorageLocation::factory()->create([
            'code' => 'RM-B1',
            'name' => 'Rack Beta',
        ]);

        $alphaProduct = Product::factory()->create([
            'name' => 'Alpha Material',
            'sku' => 'SKU-ALPHA',
            'item_code_ierp' => 'IERP-ALPHA',
            'quantity' => 12,
        ]);

        $betaProduct = Product::factory()->create([
            'name' => 'Beta Material',
            'sku' => 'SKU-BETA',
            'item_code_ierp' => 'IERP-BETA',
            'quantity' => 12,
        ]);

        $alphaBatch = Batch::create([
            'product_id' => $alphaProduct->id,
            'batch_number' => 'ALPHA-BATCH-001',
            'expiry_date' => now()->addDays(10)->toDateString(),
            'received_at' => now()->subDays(2),
            'storage_location' => $alphaLocation->display_label,
            'storage_location_id' => $alphaLocation->id,
            'unit_cost' => 7000,
            'selling_price' => 9000,
            'quantity' => 12,
            'available_quantity' => 7,
            'source' => 'purchase',
        ]);

        $betaBatch = Batch::create([
            'product_id' => $betaProduct->id,
            'batch_number' => 'BETA-BATCH-001',
            'expiry_date' => now()->addDays(45)->toDateString(),
            'received_at' => now()->subDay(),
            'storage_location' => $betaLocation->display_label,
            'storage_location_id' => $betaLocation->id,
            'unit_cost' => 8000,
            'selling_price' => 11000,
            'quantity' => 14,
            'available_quantity' => 9,
            'source' => 'purchase',
        ]);

        return [$alphaBatch, $betaBatch, $alphaLocation, $betaLocation];
    }
}
