<div class="space-y-6">
    <div class="flex flex-col gap-4 rounded-lg border border-border bg-card p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-foreground">Dashboard</h2>
            <p class="text-sm text-muted-foreground">
                {{ $view === 'business-insights' ? 'Business insights for the last 30 days.' : 'RNI operational visibility for current stock and batch risk.' }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="setView('rni-operations')" class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium {{ $view === 'rni-operations' ? 'bg-primary text-white' : 'border border-input bg-background hover:bg-accent hover:text-accent-foreground' }}">
                RNI Operations
            </button>
            <button wire:click="setView('business-insights')" class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium {{ $view === 'business-insights' ? 'bg-primary text-white' : 'border border-input bg-background hover:bg-accent hover:text-accent-foreground' }}">
                Business Insights
            </button>
            <button wire:click="loadStats" class="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-accent hover:text-accent-foreground">
                <x-heroicon-o-arrow-path wire:loading.class="animate-spin" class="mr-2 h-4 w-4" />
                Refresh
            </button>
        </div>
    </div>

    @if($view === 'rni-operations')
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm font-medium">Total Materials</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format($stats['total_rm'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm font-medium">Physical Stock</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format($stats['total_physical_stock_quantity'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm font-medium">Usable Stock</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format($stats['total_usable_stock_quantity'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm font-medium">Low Stock</p>
                <p class="mt-2 text-2xl font-bold text-amber-600">{{ number_format($stats['low_stock'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm font-medium">Near Expiry</p>
                <p class="mt-2 text-2xl font-bold text-amber-600">{{ number_format($stats['near_expiry'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm font-medium">Expired / Zero Cost</p>
                <p class="mt-2 text-2xl font-bold text-red-600">{{ number_format($stats['expired'] ?? 0) }} / {{ number_format($stats['zero_cost_batch_count'] ?? 0) }}</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border bg-card shadow-sm lg:col-span-2">
                <div class="border-b p-4">
                    <h3 class="font-semibold">Recent Receipts</h3>
                    <p class="text-xs text-muted-foreground">Latest inbound documents across legacy purchases and material receipts.</p>
                </div>
                <div class="max-h-[320px] overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-card">
                            <tr class="border-b">
                                <th class="px-4 py-3 text-left">Receipt</th>
                                <th class="px-4 py-3 text-left">Supplier</th>
                                <th class="px-4 py-3 text-right">Lines</th>
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentReceipts as $receipt)
                                <tr class="border-b">
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $receipt['receipt_number'] }}</div>
                                        <div class="text-xs text-muted-foreground">{{ $receipt['purchase_date'] }} | {{ str($receipt['entry_context'])->headline() }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ $receipt['supplier_name'] }}</td>
                                    <td class="px-4 py-3 text-right">{{ $receipt['line_count'] }}</td>
                                    <td class="px-4 py-3 text-right">{{ format_money($receipt['total']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-muted-foreground">No recent receipts.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b p-4">
                    <h3 class="font-semibold">Urgent Batches</h3>
                    <p class="text-xs text-muted-foreground">Expired or approaching expiry.</p>
                </div>
                <div class="space-y-4 p-4">
                    @forelse($urgentBatches as $batch)
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium">{{ $batch['product_name'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $batch['item_code'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $batch['batch_number'] }} | {{ $batch['expiry_date'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $batch['storage_location'] }}</p>
                            </div>
                            <span class="rounded-md px-2 py-1 text-xs font-semibold {{ $batch['status'] === 'expired' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' }}">
                                {{ $batch['available_quantity'] }}
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-muted-foreground">No urgent batches.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b p-4">
                    <h3 class="font-semibold">Recent Usage</h3>
                </div>
                <div class="space-y-4 p-4">
                    @forelse($recentUsage as $usage)
                        <div>
                            <p class="text-sm font-medium">{{ $usage['usage_number'] }}</p>
                            <p class="text-xs text-muted-foreground">{{ $usage['item_codes'] ?: '-' }}</p>
                            <p class="text-xs text-muted-foreground">{{ $usage['purpose'] }} | Qty {{ $usage['total_qty'] }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-muted-foreground">No usage history yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b p-4">
                    <h3 class="font-semibold">Top Used Materials</h3>
                </div>
                <div class="space-y-4 p-4">
                    @forelse($topUsedMaterials as $material)
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium">{{ $material['product_name'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $material['item_code'] }}</p>
                            </div>
                            <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">{{ number_format($material['total_quantity']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-muted-foreground">No material usage this month.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b p-4">
                    <h3 class="font-semibold">Expiry Risk</h3>
                </div>
                <div class="space-y-4 p-4">
                    @forelse($nearExpiryRisks as $risk)
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium">{{ $risk['product_name'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $risk['item_code'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $risk['nearest_expiry_date'] }}</p>
                            </div>
                            <span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">{{ number_format($risk['at_risk_quantity']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-muted-foreground">No near-expiry risks.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b p-4">
                    <h3 class="font-semibold">Low Stock</h3>
                </div>
                <div class="space-y-4 p-4">
                    @forelse($lowStockProducts as $product)
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium">{{ $product['name'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $product['item_code'] ?? '-' }}</p>
                            </div>
                            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold">{{ $product['quantity'] }}/{{ $product['min_stock'] }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-muted-foreground">No low stock alerts.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm font-medium">Inventory Value</p>
                <p class="mt-2 text-2xl font-bold">{{ format_money($businessStats['inventory_value'] ?? 0) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Current batch-cost valuation</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm font-medium">Inbound Trend</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format($this->sumTrend($inboundTrend)) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Units received in the last 30 days</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm font-medium">Outbound Trend</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format($this->sumTrend($outboundTrend)) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Units issued or sold in the last 30 days</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm font-medium">Purchase / Sales</p>
                <p class="mt-2 text-2xl font-bold">{{ format_money($businessStats['purchase_total'] ?? 0) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Purchases vs {{ format_money($businessStats['sales_total'] ?? 0) }} sales</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm font-medium">Material Consumption</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format($businessStats['material_consumption_total'] ?? 0) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Units consumed in RNI workflows</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b p-4"><h3 class="font-semibold">Fast Moving Materials</h3></div>
                <div class="space-y-4 p-4">
                    @forelse($fastMovingMaterials as $material)
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium">{{ $material['product_name'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $material['item_code'] }}</p>
                            </div>
                            <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">{{ number_format($material['total_quantity']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-muted-foreground">No movement data yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b p-4"><h3 class="font-semibold">Slow Moving Materials</h3></div>
                <div class="space-y-4 p-4">
                    @forelse($slowMovingMaterials as $material)
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium">{{ $material['product_name'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $material['item_code'] }}</p>
                            </div>
                            <span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">{{ number_format($material['total_quantity']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-muted-foreground">No slow-moving materials in range.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b p-4"><h3 class="font-semibold">Dead Stock</h3></div>
                <div class="space-y-4 p-4">
                    @forelse($deadStock as $material)
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium">{{ $material['product_name'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $material['item_code'] }}</p>
                            </div>
                            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold">{{ number_format($material['quantity']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-muted-foreground">No dead stock in the last 90 days.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b p-4"><h3 class="font-semibold">Top Suppliers</h3></div>
                <div class="space-y-4 p-4">
                    @forelse($topSuppliers as $supplier)
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium">{{ $supplier['supplier_name'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $supplier['phone'] }}</p>
                            </div>
                            <span class="text-sm font-semibold">{{ format_money($supplier['total_spend']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-muted-foreground">No supplier spend in range.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b p-4"><h3 class="font-semibold">Top Customers</h3></div>
                <div class="space-y-4 p-4">
                    @forelse($topCustomers as $customer)
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium">{{ $customer['customer_name'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $customer['phone'] }}</p>
                            </div>
                            <span class="text-sm font-semibold">{{ format_money($customer['total_spent']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-muted-foreground">No customer sales in range.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
