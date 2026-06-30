<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\InventoryAdjustment;
use App\Models\InventoryLog;
use App\Models\PhysicalForm;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\RolePermission;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\DashboardStatsService;
use App\Services\RolePermissionService;
use App\Support\RolePermissionMatrix;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultSeedPilotReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_database_seeder_is_pilot_clean_and_keeps_reference_data_available(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('username', 'admin')->first();

        $this->assertNotNull($admin);
        $this->assertSame(UserRole::ADMIN_RNI, $admin->role);
        $this->assertGreaterThan(0, RolePermission::query()->count());
        $this->assertSame(
            count(UserRole::cases()) * count(RolePermissionMatrix::MODULES),
            RolePermission::query()->count(),
        );

        $permissions = app(RolePermissionService::class);
        $this->assertTrue($permissions->allows($admin, 'finance', 'view'));
        $this->assertTrue($permissions->allows($admin, 'materials', 'view'));

        $this->assertSame('1', Setting::get('module_finance_enabled'));
        $this->assertSame('1', Setting::get('module_materials_enabled'));
        $this->assertGreaterThan(0, FinanceCategory::query()->count());
        $this->assertGreaterThan(0, Unit::query()->count());
        $this->assertGreaterThan(0, Category::query()->count());
        $this->assertGreaterThan(0, PhysicalForm::query()->count());
        $this->assertGreaterThan(0, StorageLocation::query()->count());
        $this->assertGreaterThan(0, Supplier::query()->count());
        $this->assertGreaterThan(0, Customer::query()->count());
        $this->assertGreaterThan(0, Product::query()->count());

        $this->assertSame(0, Product::query()->where('quantity', '>', 0)->count());
        $this->assertSame(0, Batch::query()->count());
        $this->assertSame(0, InventoryLog::query()->count());
        $this->assertSame(0, InventoryAdjustment::query()->count());
        $this->assertSame(0, Purchase::query()->count());
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(0, FinanceTransaction::query()->count());
        $this->assertSame(0, Product::query()->where('quantity', '>', 0)->whereDoesntHave('batches')->count());

        $dashboard = app(DashboardStatsService::class);
        $this->assertSame(0, $dashboard->getInventoryValuation()['cost_value']);
        $this->assertSame(0, $dashboard->getRniOverviewStats()['total_physical_stock_quantity']);
    }

    public function test_demo_seeder_is_explicit_and_creates_batch_backed_stock_without_quantity_drift(): void
    {
        $this->seed(DemoSeeder::class);

        $stockedProducts = Product::query()
            ->where('quantity', '>', 0)
            ->with('batches')
            ->get();

        $this->assertGreaterThan(0, $stockedProducts->count());
        $this->assertGreaterThan(0, Batch::query()->count());
        $this->assertGreaterThan(0, InventoryLog::query()->count());

        foreach ($stockedProducts as $product) {
            $this->assertGreaterThan(0, $product->batches->count());
            $this->assertSame(
                (int) $product->quantity,
                (int) $product->batches->sum('available_quantity'),
            );
        }

        $this->assertSame(0, Product::query()->where('quantity', '>', 0)->whereDoesntHave('batches')->count());
        $this->assertGreaterThan(0, app(DashboardStatsService::class)->getInventoryValuation()['cost_value']);
    }
}
