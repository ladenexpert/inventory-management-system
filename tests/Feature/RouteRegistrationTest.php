<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RouteRegistrationTest extends TestCase
{
    public function test_route_list_command_registers_all_application_routes(): void
    {
        $exitCode = Artisan::call('route:list', ['--except-vendor' => true]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertNotNull(app('router')->getRoutes()->getByName('finance.transactions.index'));
        $this->assertNotNull(app('router')->getRoutes()->getByName('ajax.finance-categories.search'));
    }
}
