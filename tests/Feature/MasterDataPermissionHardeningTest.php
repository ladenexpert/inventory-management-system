<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Categories\CategoryDetail;
use App\Livewire\Categories\CategoryForm;
use App\Livewire\Categories\CategoryTable;
use App\Livewire\Customers\CustomerDetail;
use App\Livewire\Customers\CustomerForm;
use App\Livewire\Customers\CustomerTable;
use App\Livewire\Products\ProductForm;
use App\Livewire\Products\ProductTable;
use App\Livewire\PhysicalForms\PhysicalFormForm;
use App\Livewire\PhysicalForms\PhysicalFormTable;
use App\Livewire\StorageLocations\StorageLocationForm;
use App\Livewire\StorageLocations\StorageLocationTable;
use App\Livewire\Suppliers\SupplierDetail;
use App\Livewire\Suppliers\SupplierForm;
use App\Livewire\Suppliers\SupplierTable;
use App\Livewire\Teams\TeamForm;
use App\Livewire\Teams\TeamTable;
use App\Livewire\Units\UnitDetail;
use App\Livewire\Units\UnitForm;
use App\Livewire\Units\UnitTable;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PhysicalForm;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\Team;
use App\Models\Unit;
use App\Models\User;
use App\Services\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterDataPermissionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_rm_desk_master_data_pages_stay_view_only_while_export_remains_available(): void
    {
        $this->configureViewOnlyMasterDataRole(UserRole::RM_DESK);

        $user = User::factory()->create([
            'role' => UserRole::RM_DESK,
        ]);

        Category::factory()->create(['name' => 'RM Desk Category']);
        Unit::factory()->create(['name' => 'RM Desk Unit', 'symbol' => 'RDU']);
        Supplier::factory()->create(['name' => 'RM Desk Supplier']);
        Customer::factory()->create(['name' => 'RM Desk Customer']);
        StorageLocation::factory()->create(['code' => 'RM-LOC-01', 'name' => 'RM Desk Location']);
        Product::factory()->create(['name' => 'RM Desk Material']);

        foreach ($this->masterDataPageDefinitions() as $definition) {
            $response = $this->actingAs($user)->get(route($definition['route']));

            $response->assertOk();
            $response->assertSee($definition['title']);
            $response->assertDontSee($definition['create_label']);
            $response->assertDontSee('Import Excel');
            $response->assertDontSee('Download Template');
        }

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertDontSee('Create Material')
            ->assertDontSee('Import Excel')
            ->assertDontSee('Download Template')
            ->assertDontSee('Upload Opening Stock');

        Livewire::actingAs($user);

        Livewire::test(CategoryTable::class)
            ->assertDontSeeHtml('wire:click="selectCheckboxAll"')
            ->call('exportToCsv')
            ->assertFileDownloaded();

        Livewire::test(ProductTable::class)
            ->assertDontSeeHtml('wire:click="selectCheckboxAll"');

        Livewire::test(CategoryDetail::class)
            ->call('show', Category::first())
            ->assertDontSee('Edit Category');

        Livewire::test(UnitDetail::class)
            ->call('show', Unit::first())
            ->assertDontSee('Edit Unit');

        Livewire::test(SupplierDetail::class)
            ->call('show', Supplier::first())
            ->assertDontSee('Edit Supplier');

        Livewire::test(CustomerDetail::class)
            ->call('show', Customer::first())
            ->assertDontSee('Edit Customer');
    }

    public function test_formulator_master_data_pages_are_view_only_with_same_permission_profile(): void
    {
        $this->configureViewOnlyMasterDataRole(UserRole::FORMULATOR);

        $user = User::factory()->create([
            'role' => UserRole::FORMULATOR,
        ]);

        foreach ($this->masterDataPageDefinitions() as $definition) {
            $response = $this->actingAs($user)->get(route($definition['route']));

            $response->assertOk();
            $response->assertSee($definition['title']);
            $response->assertDontSee($definition['create_label']);
            $response->assertDontSee('Import Excel');
            $response->assertDontSee('Download Template');
        }

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertDontSee('Create Material')
            ->assertDontSee('Import Excel')
            ->assertDontSee('Download Template')
            ->assertDontSee('Upload Opening Stock');

        Livewire::actingAs($user);

        Livewire::test(CategoryTable::class)->assertDontSeeHtml('wire:click="selectCheckboxAll"');
        Livewire::test(ProductTable::class)->assertDontSeeHtml('wire:click="selectCheckboxAll"');
    }

    public function test_rm_desk_cannot_mutate_master_data_livewire_or_import_actions(): void
    {
        $this->configureViewOnlyMasterDataRole(UserRole::RM_DESK);

        $user = User::factory()->create([
            'role' => UserRole::RM_DESK,
        ]);

        $category = Category::factory()->create();
        $unit = Unit::factory()->create();
        $supplier = Supplier::factory()->create();
        $customer = Customer::factory()->create();
        $location = StorageLocation::factory()->create();
        $physicalForm = PhysicalForm::factory()->create([
            'code' => 'custom_form',
            'name' => 'Custom Form',
        ]);
        $team = Team::factory()->create([
            'code' => 'TEAM-CUSTOM',
            'name' => 'Custom Team',
        ]);
        $product = Product::factory()->create();

        Livewire::actingAs($user);

        Livewire::test(CategoryForm::class)->call('create')->assertStatus(403);
        Livewire::test(CategoryForm::class)->call('edit', $category)->assertStatus(403);
        Livewire::test(CategoryForm::class)->call('save')->assertStatus(403);
        Livewire::test(CategoryTable::class)->call('delete', $category->id)->assertStatus(403);
        Livewire::test(CategoryTable::class)->set('checkboxValues', [$category->id])->call('bulkDelete')->assertStatus(403);

        Livewire::test(UnitForm::class)->call('create')->assertStatus(403);
        Livewire::test(UnitForm::class)->call('edit', $unit)->assertStatus(403);
        Livewire::test(UnitForm::class)->call('save')->assertStatus(403);
        Livewire::test(UnitTable::class)->call('delete', $unit->id)->assertStatus(403);
        Livewire::test(UnitTable::class)->set('checkboxValues', [$unit->id])->call('bulkDelete')->assertStatus(403);

        Livewire::test(SupplierForm::class)->call('create')->assertStatus(403);
        Livewire::test(SupplierForm::class)->call('edit', $supplier)->assertStatus(403);
        Livewire::test(SupplierForm::class)->call('save')->assertStatus(403);
        Livewire::test(SupplierTable::class)->call('delete', $supplier->id)->assertStatus(403);
        Livewire::test(SupplierTable::class)->set('checkboxValues', [$supplier->id])->call('bulkDelete')->assertStatus(403);

        Livewire::test(CustomerForm::class)->call('create')->assertStatus(403);
        Livewire::test(CustomerForm::class)->call('edit', $customer)->assertStatus(403);
        Livewire::test(CustomerForm::class)->call('save')->assertStatus(403);
        Livewire::test(CustomerTable::class)->call('delete', $customer->id)->assertStatus(403);
        Livewire::test(CustomerTable::class)->set('checkboxValues', [$customer->id])->call('bulkDelete')->assertStatus(403);

        Livewire::test(StorageLocationForm::class)->call('create')->assertStatus(403);
        Livewire::test(StorageLocationForm::class)->call('edit', $location)->assertStatus(403);
        Livewire::test(StorageLocationForm::class)->call('save')->assertStatus(403);
        Livewire::test(StorageLocationTable::class)->call('delete', $location->id)->assertStatus(403);
        Livewire::test(StorageLocationTable::class)->set('checkboxValues', [$location->id])->call('bulkDelete')->assertStatus(403);

        Livewire::test(PhysicalFormForm::class)->call('create')->assertStatus(403);
        Livewire::test(PhysicalFormForm::class)->call('edit', $physicalForm)->assertStatus(403);
        Livewire::test(PhysicalFormForm::class)->call('save')->assertStatus(403);
        Livewire::test(PhysicalFormTable::class)->call('delete', $physicalForm->id)->assertStatus(403);
        Livewire::test(PhysicalFormTable::class)->set('checkboxValues', [$physicalForm->id])->call('bulkDelete')->assertStatus(403);

        Livewire::test(TeamForm::class)->call('create')->assertStatus(403);
        Livewire::test(TeamForm::class)->call('edit', $team)->assertStatus(403);
        Livewire::test(TeamForm::class)->call('save')->assertStatus(403);
        Livewire::test(TeamTable::class)->call('delete', $team->id)->assertStatus(403);
        Livewire::test(TeamTable::class)->set('checkboxValues', [$team->id])->call('bulkDelete')->assertStatus(403);

        Livewire::test(ProductForm::class)->call('create')->assertStatus(403);
        Livewire::test(ProductForm::class)->call('edit', $product)->assertStatus(403);
        Livewire::test(ProductForm::class)->call('save')->assertStatus(403);
        Livewire::test(ProductTable::class)->call('delete', $product->id)->assertStatus(403);
        Livewire::test(ProductTable::class)->set('checkboxValues', [$product->id])->call('bulkDelete')->assertStatus(403);

        foreach ($this->masterDataImportResources() as $resource) {
            $this->actingAs($user)->get(route('master-imports.show', $resource))->assertForbidden();
            $this->actingAs($user)->post(route('master-imports.store', $resource))->assertForbidden();
            $this->actingAs($user)->get(route('master-imports.template', $resource))->assertForbidden();
        }

        $this->actingAs($user)
            ->postJson(route('ajax.customers.store'), [
                'name' => 'Blocked Customer',
            ])
            ->assertForbidden();
    }

    public function test_admin_keeps_master_data_mutation_import_and_export_access(): void
    {
        $user = User::factory()->create();

        $pageResponse = $this->actingAs($user)->get(route('categories.index'));
        $pageResponse->assertOk();
        $pageResponse->assertSee('Create Category');
        $pageResponse->assertSee('Import Excel');
        $pageResponse->assertSee('Download Template');

        Livewire::actingAs($user);

        Livewire::test(CategoryTable::class)
            ->assertSeeHtml('wire:click="selectCheckboxAll"')
            ->call('exportToCsv')
            ->assertFileDownloaded();

        Livewire::test(CategoryForm::class)
            ->set('name', 'Admin Created Category')
            ->set('description', 'Created by admin')
            ->call('save');

        $category = Category::where('name', 'Admin Created Category')->firstOrFail();

        Livewire::test(CategoryForm::class)
            ->call('edit', $category)
            ->set('name', 'Admin Updated Category')
            ->call('save');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Admin Updated Category',
        ]);

        Livewire::test(CategoryTable::class)->call('delete', $category->id);
        $this->assertSoftDeleted('categories', ['id' => $category->id]);

        $this->actingAs($user)
            ->get(route('master-imports.show', 'categories'))
            ->assertOk()
            ->assertSee('Import Excel');

        $this->actingAs($user)
            ->get(route('master-imports.template', 'categories'))
            ->assertDownload('template-categories.xlsx');
    }

    private function configureViewOnlyMasterDataRole(UserRole $role): void
    {
        $service = app(RolePermissionService::class);
        $permissions = $service->permissionsForRole($role->value);

        $permissions['master_data'] = array_merge($permissions['master_data'], [
            'view' => true,
            'create' => false,
            'update' => false,
            'delete' => false,
            'import' => false,
            'export' => true,
        ]);

        $permissions['materials'] = array_merge($permissions['materials'], [
            'view' => true,
            'create' => false,
            'update' => false,
            'delete' => false,
            'export' => false,
        ]);

        $service->syncRolePermissions($role->value, $permissions);
    }

    private function masterDataPageDefinitions(): array
    {
        return [
            ['route' => 'categories.index', 'title' => 'Categories', 'create_label' => 'Create Category'],
            ['route' => 'units.index', 'title' => 'Units', 'create_label' => 'Create Unit'],
            ['route' => 'suppliers.index', 'title' => 'Suppliers', 'create_label' => 'Create Supplier'],
            ['route' => 'customers.index', 'title' => 'Customers', 'create_label' => 'Create Customer'],
            ['route' => 'storage-locations.index', 'title' => 'Storage Locations', 'create_label' => 'Create Location'],
            ['route' => 'physical-forms.index', 'title' => 'Physical Forms', 'create_label' => 'Create Physical Form'],
            ['route' => 'teams.index', 'title' => 'Teams', 'create_label' => 'Create Team'],
        ];
    }

    private function masterDataImportResources(): array
    {
        return [
            'materials',
            'categories',
            'units',
            'suppliers',
            'customers',
            'storage-locations',
            'physical-forms',
            'teams',
        ];
    }
}
