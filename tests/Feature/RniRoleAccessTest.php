<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RniRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_formulator_can_access_usage_and_inventory_but_not_admin_pages(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::FORMULATOR,
        ]);

        $this->actingAs($user)->get(route('material-usages.index'))->assertOk();
        $this->actingAs($user)->get(route('products.index'))->assertOk();
        $this->actingAs($user)->get(route('reports.inventory'))->assertOk();

        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('material-receipts.index'))->assertForbidden();
        $this->actingAs($user)->get(route('finance.transactions.index'))->assertForbidden();
    }
}
