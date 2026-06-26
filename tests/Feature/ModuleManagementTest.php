<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_module_redirects_user_with_friendly_message(): void
    {
        $user = User::factory()->create();
        Setting::set('module_sales_enabled', '0');

        $response = $this->actingAs($user)->get(route('sales.index'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error', 'Sales module is currently disabled.');
    }

    public function test_disabled_module_is_hidden_from_navigation(): void
    {
        $user = User::factory()->create();
        Setting::set('module_reports_enabled', '0');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Inventory & Expiry Monitoring');
        $response->assertDontSee('Stock Movement Classification');
    }
}
