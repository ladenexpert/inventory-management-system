<?php

namespace Tests\Feature;

use App\Enums\FinanceCategoryType;
use App\Livewire\Customers\CustomerTable;
use App\Livewire\FinanceTransactions\FinanceTransactionTable;
use App\Livewire\StorageLocations\StorageLocationForm;
use App\Models\Customer;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\Sale;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StorageLocationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_location_page_renders_for_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('storage-locations.index'))
            ->assertOk()
            ->assertSee('Storage Locations');
    }

    public function test_storage_location_can_be_created_and_updated_via_livewire_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(StorageLocationForm::class)
            ->set('code', 'RM-A1')
            ->set('name', 'Raw Material Rack A1')
            ->set('type', 'rack')
            ->set('is_active', true)
            ->call('save');

        $location = StorageLocation::where('code', 'RM-A1')->firstOrFail();

        Livewire::test(StorageLocationForm::class)
            ->call('edit', $location)
            ->set('name', 'Raw Material Rack A1 Updated')
            ->call('save');

        $this->assertDatabaseHas('storage_locations', [
            'id' => $location->id,
            'name' => 'Raw Material Rack A1 Updated',
        ]);
    }

    public function test_customer_bulk_delete_soft_deletes_selected_rows(): void
    {
        $user = User::factory()->create();
        $customers = Customer::factory()->count(2)->create();

        $this->actingAs($user);

        Livewire::test(CustomerTable::class)
            ->set('checkboxValues', $customers->pluck('id')->all())
            ->call('bulkDelete');

        foreach ($customers as $customer) {
            $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        }
    }

    public function test_finance_bulk_delete_removes_only_manual_transactions(): void
    {
        $user = User::factory()->create();
        $category = FinanceCategory::create([
            'name' => 'Misc Income',
            'slug' => 'misc-income',
            'type' => FinanceCategoryType::Income,
        ]);

        $manualTransaction = FinanceTransaction::create([
            'code' => 'TRX-MANUAL-001',
            'transaction_date' => now()->toDateString(),
            'finance_category_id' => $category->id,
            'amount' => 10000,
            'description' => 'Manual adjustment',
            'created_by' => $user->id,
        ]);

        $autoTransaction = FinanceTransaction::create([
            'code' => 'TRX-AUTO-001',
            'transaction_date' => now()->toDateString(),
            'finance_category_id' => $category->id,
            'amount' => 15000,
            'description' => 'Auto sale journal',
            'created_by' => $user->id,
            'reference_type' => Sale::class,
            'reference_id' => 99,
        ]);

        $this->actingAs($user);

        Livewire::test(FinanceTransactionTable::class)
            ->set('checkboxValues', [$manualTransaction->id, $autoTransaction->id])
            ->call('bulkDelete');

        $this->assertDatabaseMissing('finance_transactions', ['id' => $manualTransaction->id]);
        $this->assertDatabaseHas('finance_transactions', ['id' => $autoTransaction->id]);
    }
}
