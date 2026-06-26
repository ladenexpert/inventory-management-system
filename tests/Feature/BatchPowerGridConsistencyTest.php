<?php

namespace Tests\Feature;

use App\Livewire\Batches\BatchTable;
use App\Livewire\Reports\ExpiryReportTable;
use App\Livewire\Reports\InventoryReportTable;
use App\Models\Batch;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BatchPowerGridConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_and_batch_monitoring_pages_render_with_consistent_rni_labels(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.inventory'))
            ->assertOk()
            ->assertSee('Material Name')
            ->assertSee('Batch No')
            ->assertSee('Stock Available')
            ->assertSee('Expiry Date');

        $this->actingAs($user)
            ->get(route('reports.expiry'))
            ->assertOk()
            ->assertSee('Material Name')
            ->assertSee('Batch No')
            ->assertSee('Stock Available')
            ->assertSee('Expiry Date');

        $this->actingAs($user)
            ->get(route('batches.index'))
            ->assertOk()
            ->assertSee('Material Name')
            ->assertSee('Batch No')
            ->assertSee('Stock Available')
            ->assertSee('Days Remaining');
    }

    public function test_inventory_report_sorting_and_searching_use_safe_batch_backed_mappings(): void
    {
        $user = User::factory()->create();
        [$alphaBatch, $betaBatch, $alphaLocation, $betaLocation] = $this->seedBatchMonitoringFixtures();

        $this->actingAs($user);

        Livewire::test(InventoryReportTable::class, ['preset' => 'inventory'])
            ->set('sortField', 'product_name')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder([$alphaBatch->fresh()->product->name, $betaBatch->fresh()->product->name])
            ->set('sortField', 'days_remaining_sort')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder([$alphaBatch->batch_number, $betaBatch->batch_number])
            ->set('sortField', 'status_sort')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder([$betaBatch->batch_number, $alphaBatch->batch_number])
            ->set('sortField', 'batch_number')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder([$alphaBatch->batch_number, $betaBatch->batch_number])
            ->set('sortField', 'expiry')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder([$alphaBatch->batch_number, $betaBatch->batch_number])
            ->set('sortField', 'storage_location')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder([$alphaLocation->display_label, $betaLocation->display_label])
            ->set('search', 'Alpha Material')
            ->assertSee($alphaBatch->batch_number)
            ->assertDontSee($betaBatch->batch_number)
            ->set('search', 'Rack Beta')
            ->assertSee($betaBatch->batch_number)
            ->assertDontSee($alphaBatch->batch_number);
    }

    public function test_legacy_persisted_product_name_sort_key_is_safe_for_inventory_and_expiry_presets(): void
    {
        $user = User::factory()->create();
        [$alphaBatch, $betaBatch] = $this->seedBatchMonitoringFixtures();

        $this->actingAs($user);

        Livewire::test(InventoryReportTable::class, ['preset' => 'inventory'])
            ->set('sortField', 'product_name')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder([$alphaBatch->fresh()->product->name, $betaBatch->fresh()->product->name]);

        Livewire::test(InventoryReportTable::class, ['preset' => 'expiry'])
            ->set('sortField', 'product_name')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder([$alphaBatch->fresh()->product->name, $betaBatch->fresh()->product->name]);
    }

    public function test_batch_monitoring_and_legacy_expiry_tables_sort_without_virtual_column_sql_errors(): void
    {
        $user = User::factory()->create();
        [$alphaBatch, $betaBatch, $alphaLocation, $betaLocation] = $this->seedBatchMonitoringFixtures();

        $this->actingAs($user);

        Livewire::test(BatchTable::class)
            ->set('sortField', 'product_name')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder([$alphaBatch->fresh()->product->name, $betaBatch->fresh()->product->name])
            ->set('sortField', 'days_left_sort')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder([$alphaBatch->batch_number, $betaBatch->batch_number])
            ->set('sortField', 'lifecycle_status_sort')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder([$betaBatch->batch_number, $alphaBatch->batch_number])
            ->set('sortField', 'storage_location')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder([$alphaLocation->display_label, $betaLocation->display_label]);

        Livewire::test(ExpiryReportTable::class)
            ->set('sortField', 'material_name')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder([$alphaBatch->fresh()->product->name, $betaBatch->fresh()->product->name])
            ->set('sortField', 'days_remaining')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder([$alphaBatch->batch_number, $betaBatch->batch_number])
            ->set('sortField', 'status_sort')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder([$betaBatch->batch_number, $alphaBatch->batch_number])
            ->set('sortField', 'storage_location')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder([$alphaLocation->display_label, $betaLocation->display_label]);
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
