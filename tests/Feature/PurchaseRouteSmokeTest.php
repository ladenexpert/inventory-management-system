<?php

namespace Tests\Feature;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_and_material_receipt_create_routes_render_successfully(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('purchases.create'))
            ->assertOk()
            ->assertSee('Create Purchase');

        $this->actingAs($user)
            ->get(route('material-receipts.create'))
            ->assertOk()
            ->assertSee('Create Material Receipt');
    }

    public function test_purchase_and_material_receipt_edit_routes_render_successfully(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'total' => 0,
            'status' => PurchaseStatus::DRAFT,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('purchases.edit', $purchase))
            ->assertOk()
            ->assertSee('Edit Purchase');

        $this->actingAs($user)
            ->get(route('material-receipts.edit', $purchase))
            ->assertOk()
            ->assertSee('Edit Material Receipt');
    }
}
