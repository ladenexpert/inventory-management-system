<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\RolePermissionService;
use App\Support\RolePermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFinancialVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_and_sales_analysis_hide_financial_fields_without_finance_permission(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN_RNI,
        ]);

        $permissions = RolePermissionMatrix::defaultsForRole(UserRole::ADMIN_RNI->value);
        $permissions['finance']['view'] = false;
        $permissions['inventory_value']['view'] = false;

        app(RolePermissionService::class)->syncRolePermissions(UserRole::ADMIN_RNI->value, $permissions);

        $product = Product::factory()->create([
            'sku' => 'FIN-LOCK-001',
            'item_code_ierp' => 'IERP-FIN-LOCK-001',
            'quantity' => 5,
            'purchase_price' => 7000,
            'selling_price' => 9000,
        ]);

        $purchase = Purchase::create([
            'supplier_id' => null,
            'invoice_number' => 'PUR-FIN-LOCK-001',
            'purchase_date' => now(),
            'status' => \App\Enums\PurchaseStatus::RECEIVED,
            'created_by' => $user->id,
            'entry_context' => 'legacy_purchase',
            'total' => 14000,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 7000,
            'selling_price' => 9000,
            'subtotal' => 14000,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-FIN-LOCK-001',
            'transaction_type' => \App\Enums\SaleTransactionType::SALE,
            'created_by' => $user->id,
            'sale_date' => now(),
            'status' => \App\Enums\SaleStatus::COMPLETED,
            'subtotal' => 9000,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 9000,
            'cash_received' => 0,
            'change' => 0,
            'payment_method' => \App\Enums\PaymentMethod::TRANSFER,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'cost_price' => 7000,
            'total_cost' => 7000,
            'unit_price' => 9000,
            'discount' => 0,
            'final_price' => 9000,
            'subtotal' => 9000,
        ]);

        $purchaseResponse = $this->actingAs($user)->get(route('reports.purchase-analysis'));
        $salesResponse = $this->actingAs($user)->get(route('reports.sales-analysis'));

        $purchaseResponse->assertOk()
            ->assertSee('Inbound Units')
            ->assertDontSee('Purchase Total')
            ->assertDontSee('Inventory Value')
            ->assertDontSee('Top Suppliers');

        $salesResponse->assertOk()
            ->assertSee('Sales Count')
            ->assertDontSeeHtml('>Revenue<')
            ->assertDontSeeHtml('>Gross Profit<')
            ->assertDontSee('Top Customers');

        $purchaseExport = $this->actingAs($user)
            ->get(route('reports.purchase-analysis.export', ['format' => 'csv']))
            ->assertOk();
        $salesExport = $this->actingAs($user)
            ->get(route('reports.sales-analysis.export', ['format' => 'csv']))
            ->assertOk();

        $purchaseCsv = $this->downloadedFileContent($purchaseExport);
        $salesCsv = $this->downloadedFileContent($salesExport);

        $this->assertStringNotContainsString('Line Amount', $purchaseCsv);
        $this->assertStringNotContainsString('Purchase Total', $purchaseCsv);
        $this->assertStringNotContainsString('Revenue', $salesCsv);
        $this->assertStringNotContainsString('Sale Total', $salesCsv);
    }

    private function downloadedFileContent($response): string
    {
        $file = $response->baseResponse->getFile();

        $this->assertNotNull($file);

        return (string) file_get_contents($file->getPathname());
    }
}
