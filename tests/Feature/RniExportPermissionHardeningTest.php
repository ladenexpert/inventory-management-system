<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Batches\BatchTable;
use App\Livewire\Reports\ExpiryReportTable;
use App\Livewire\Reports\InventoryReportTable;
use App\Livewire\Reports\StockMovementClassificationTable;
use App\Livewire\Reports\UsageHistoryTable;
use App\Models\User;
use App\Services\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RniExportPermissionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_monitoring_export_is_blocked_without_batch_export_permission(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::FORMULATOR,
        ]);

        Livewire::actingAs($user);

        Livewire::test(BatchTable::class)
            ->call('exportToCsv')
            ->assertStatus(403);
    }

    public function test_report_exports_are_blocked_when_user_lacks_reports_export_permission(): void
    {
        $this->disableReportsExport(UserRole::FORMULATOR);

        $user = User::factory()->create([
            'role' => UserRole::FORMULATOR,
        ]);

        Livewire::actingAs($user);

        Livewire::test(InventoryReportTable::class, ['preset' => 'inventory'])
            ->call('exportToCsv')
            ->assertStatus(403);

        Livewire::test(ExpiryReportTable::class)
            ->call('exportToCsv')
            ->assertStatus(403);

        Livewire::test(UsageHistoryTable::class)
            ->call('exportToCsv')
            ->assertStatus(403);

        Livewire::test(StockMovementClassificationTable::class)
            ->call('exportToCsv')
            ->assertStatus(403);
    }

    private function disableReportsExport(UserRole $role): void
    {
        $service = app(RolePermissionService::class);
        $permissions = $service->permissionsForRole($role->value);
        $permissions['reports']['export'] = false;

        $service->syncRolePermissions($role->value, $permissions);
    }
}
