<?php

namespace Tests\Feature;

use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Services\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialReceiptPermissionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_only_material_receipt_user_can_access_list_without_create_button(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::FORMULATOR,
        ]);

        $this->syncRolePermissions(UserRole::FORMULATOR, function (array $permissions): array {
            $permissions['material_receipt']['view'] = true;
            $permissions['material_receipt']['create'] = false;

            return $permissions;
        });

        $this->actingAs($user)
            ->get(route('material-receipts.index'))
            ->assertOk()
            ->assertSee('Material Receipt')
            ->assertDontSee('Create Receipt');
    }

    public function test_view_only_material_receipt_user_cannot_open_create_page_directly(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::FORMULATOR,
        ]);

        $this->syncRolePermissions(UserRole::FORMULATOR, function (array $permissions): array {
            $permissions['material_receipt']['view'] = true;
            $permissions['material_receipt']['create'] = false;

            return $permissions;
        });

        $this->actingAs($user)
            ->get(route('material-receipts.create'))
            ->assertForbidden();
    }

    public function test_admin_can_see_material_receipt_create_button(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN_RNI,
        ]);

        $this->actingAs($user)
            ->get(route('material-receipts.index'))
            ->assertOk()
            ->assertSee('Create Receipt');
    }

    public function test_view_only_material_receipt_detail_hides_mutation_actions(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::FORMULATOR,
        ]);

        $this->syncRolePermissions(UserRole::FORMULATOR, function (array $permissions): array {
            $permissions['material_receipt']['view'] = true;
            $permissions['material_receipt']['update'] = false;
            $permissions['material_receipt']['delete'] = false;
            $permissions['material_receipt']['confirm'] = false;
            $permissions['material_receipt']['cancel'] = false;
            $permissions['material_receipt']['restore'] = false;

            return $permissions;
        });

        $draftReceipt = $this->createMaterialReceiptFixture($user, PurchaseStatus::DRAFT, 'MR-VIEW-DRAFT');
        $orderedReceipt = $this->createMaterialReceiptFixture($user, PurchaseStatus::ORDERED, 'MR-VIEW-ORDERED');

        $this->actingAs($user)
            ->get(route('material-receipts.show', $draftReceipt))
            ->assertOk()
            ->assertDontSee('Delete Draft Receipt')
            ->assertDontSee('Mark as Planned');

        $this->actingAs($user)
            ->get(route('material-receipts.show', $orderedReceipt))
            ->assertOk()
            ->assertDontSee(route('material-receipts.edit', $orderedReceipt), false)
            ->assertDontSee('Cancel Receipt')
            ->assertDontSee('Confirm Material Receipt');
    }

    public function test_formulator_material_usage_list_remains_view_only_without_create_button(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::FORMULATOR,
        ]);

        $this->actingAs($user)
            ->get(route('material-usages.index'))
            ->assertOk()
            ->assertSee('Material Usage')
            ->assertDontSee('Create Usage');
    }

    private function syncRolePermissions(UserRole $role, callable $mutator): void
    {
        $service = app(RolePermissionService::class);
        $permissions = $service->permissionsForRole($role->value);

        $service->syncRolePermissions($role->value, $mutator($permissions));
    }

    private function createMaterialReceiptFixture(User $user, PurchaseStatus $status, string $reference): Purchase
    {
        $product = Product::factory()->create([
            'name' => "Fixture {$reference}",
        ]);

        $purchase = Purchase::create([
            'supplier_id' => null,
            'invoice_number' => $reference,
            'purchase_date' => now(),
            'total' => 24000,
            'status' => $status,
            'created_by' => $user->id,
            'entry_context' => 'material_receipt',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => "{$reference}-BATCH",
            'expiry_date' => now()->addMonths(3)->toDateString(),
            'storage_location' => 'Fixture Rack',
            'quantity' => 3,
            'unit_price' => 8000,
            'selling_price' => 10000,
            'subtotal' => 24000,
        ]);

        return $purchase;
    }
}
