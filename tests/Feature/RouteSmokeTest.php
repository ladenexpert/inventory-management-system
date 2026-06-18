<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_rni_routes_render_for_admin(): void
    {
        $user = User::factory()->create();

        $routes = [
            'dashboard',
            'material-usages.index',
            'material-usages.create',
            'sales.index',
            'sales.create',
            'material-receipts.index',
            'material-receipts.create',
            'purchases.create',
            'products.index',
            'batches.index',
            'customers.index',
            'storage-locations.index',
            'reports.inventory',
            'reports.inventory-movement-history',
            'reports.usage-history',
            'reports.expiry',
        ];

        foreach ($routes as $routeName) {
            $this->actingAs($user)
                ->get(route($routeName))
                ->assertOk();
        }
    }
}
