<div>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-card p-4 rounded-lg border border-border shadow-sm">
            <div>
                <h2 class="text-lg font-semibold text-foreground">RNI Operations Overview</h2>
                <p class="text-sm text-muted-foreground">Track raw materials, expiry risk, and the latest usage activity.</p>
            </div>
            <button wire:click="loadStats" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-4 py-2 gap-2">
                <x-heroicon-o-arrow-path wire:loading.class="animate-spin" class="h-4 w-4" />
                Refresh
            </button>
        </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
        <div class="rounded-xl border bg-card text-card-foreground shadow-sm">
            <div class="p-4 flex flex-row items-center justify-between space-y-0 pb-2">
                <h3 class="tracking-tight text-sm font-medium">Total RM</h3>
                <x-heroicon-o-cube class="h-4 w-4 text-muted-foreground" />
            </div>
            <div class="p-4 pt-0">
                <div class="text-xl sm:text-2xl font-bold">{{ $stats['total_rm'] ?? 0 }}</div>
                <p class="text-xs text-muted-foreground mt-1">Active material master records</p>
            </div>
        </div>

        <div class="rounded-xl border bg-card text-card-foreground shadow-sm">
            <div class="p-4 flex flex-row items-center justify-between space-y-0 pb-2">
                <h3 class="tracking-tight text-sm font-medium">Total Batch</h3>
                <x-heroicon-o-archive-box class="h-4 w-4 text-muted-foreground" />
            </div>
            <div class="p-4 pt-0">
                <div class="text-xl sm:text-2xl font-bold">{{ $stats['total_batch'] ?? 0 }}</div>
                <p class="text-xs text-muted-foreground mt-1">Tracked batch layers</p>
            </div>
        </div>

        <div class="rounded-xl border bg-card text-card-foreground shadow-sm">
            <div class="p-4 flex flex-row items-center justify-between space-y-0 pb-2">
                <h3 class="tracking-tight text-sm font-medium">Low Stock</h3>
                 <x-heroicon-o-exclamation-triangle class="h-4 w-4 text-orange-500" />
            </div>
            <div class="p-4 pt-0">
                <div class="text-xl sm:text-2xl font-bold">{{ $stats['low_stock'] ?? 0 }}</div>
                <p class="text-xs text-muted-foreground mt-1">Items at or below minimum stock</p>
            </div>
        </div>

        <div class="rounded-xl border bg-card text-card-foreground shadow-sm">
            <div class="p-4 flex flex-row items-center justify-between space-y-0 pb-2">
                <h3 class="tracking-tight text-sm font-medium">Near Expiry</h3>
                <x-heroicon-o-beaker class="h-4 w-4 text-amber-500" />
            </div>
            <div class="p-4 pt-0">
                <div class="text-xl sm:text-2xl font-bold text-amber-600">{{ $stats['near_expiry'] ?? 0 }}</div>
                <p class="text-xs text-muted-foreground mt-1">Expiring soon under current policy</p>
            </div>
        </div>

        <div class="rounded-xl border bg-card text-card-foreground shadow-sm">
            <div class="p-4 flex flex-row items-center justify-between space-y-0 pb-2">
                <h3 class="tracking-tight text-sm font-medium">Expired</h3>
                <x-heroicon-o-clock class="h-4 w-4 text-red-500" />
            </div>
            <div class="p-4 pt-0">
                <div class="text-xl sm:text-2xl font-bold text-red-600">{{ $stats['expired'] ?? 0 }}</div>
                <p class="text-xs text-muted-foreground mt-1">Available stock past expiry</p>
            </div>
        </div>

         <div class="rounded-xl border bg-card text-card-foreground shadow-sm">
            <div class="p-4 flex flex-row items-center justify-between space-y-0 pb-2">
                <h3 class="tracking-tight text-sm font-medium">Zero Cost Batch</h3>
                <x-heroicon-o-scale class="h-4 w-4 text-sky-500" />
            </div>
            <div class="p-4 pt-0">
                <div class="text-xl sm:text-2xl font-bold text-sky-600">{{ $stats['zero_cost_batch'] ?? 0 }}</div>
                <p class="text-xs text-muted-foreground mt-1">Batches with valid stock and zero cost</p>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <div class="col-span-1 lg:col-span-2 rounded-xl border bg-card text-card-foreground shadow-sm break-inside-avoid">
            <div class="p-4 flex flex-col space-y-1.5 border-b">
                <h3 class="font-semibold leading-none tracking-tight">Recent Material Usage</h3>
                <p class="text-xs text-muted-foreground">Latest issued material activity.</p>
            </div>
            <div class="p-0">
                <div class="relative w-full overflow-auto max-h-[300px]">
                    <table class="w-full caption-bottom text-sm">
                        <thead class="[&_tr]:border-b sticky top-0 bg-card z-10">
                            <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Usage</th>
                                <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Purpose</th>
                                <th class="h-10 px-4 text-right align-middle font-medium text-muted-foreground">Qty</th>
                            </tr>
                        </thead>
                        <tbody class="[&_tr:last-child]:border-0 bg-transparent">
                            @forelse($recentUsage as $usage)
                                <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                    <td class="px-4 py-2 align-middle">
                                        <div class="font-medium">{{ $usage['usage_number'] }}</div>
                                        <div class="text-[11px] text-muted-foreground font-normal">{{ $usage['usage_date'] }} | {{ $usage['issued_by'] }}</div>
                                    </td>
                                    <td class="px-4 py-2 align-middle">
                                        <div class="font-medium">{{ $usage['purpose'] }}</div>
                                        <div class="text-[11px] text-muted-foreground">{{ $usage['formula'] !== '-' ? $usage['formula'] : ($usage['project'] !== '-' ? $usage['project'] : 'No formula/project') }}</div>
                                    </td>
                                    <td class="px-4 py-2 align-middle text-right font-medium text-emerald-600">{{ $usage['total_qty'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="p-4 text-center text-muted-foreground">No recent material usage.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-span-1 rounded-xl border bg-card text-card-foreground shadow-sm break-inside-avoid">
            <div class="p-4 flex items-center justify-between border-b">
                <div class="space-y-1.5">
                    <h3 class="font-semibold leading-none tracking-tight">Urgent Batches</h3>
                    <p class="text-xs text-muted-foreground">Expired or expiring soon.</p>
                </div>
                <a href="{{ route('batches.index') }}" class="text-xs font-medium text-primary hover:underline">View all</a>
            </div>
             <div class="p-4 pt-4 max-h-[300px] overflow-auto">
                <div class="space-y-4">
                    @forelse($urgentBatches as $batch)
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1 flex-1 min-w-0">
                                <p class="text-sm font-medium leading-none truncate" title="{{ $batch['product_name'] }}">{{ $batch['product_name'] }}</p>
                                <p class="text-[11px] text-muted-foreground">{{ $batch['batch_number'] }} | {{ $batch['sku'] }}</p>
                                <p class="text-[11px] {{ $batch['status'] === 'expired' ? 'text-red-600' : 'text-amber-600' }}">
                                    {{ $batch['status_label'] }}: {{ $batch['expiry_date'] }}
                                </p>
                            </div>
                            <div class="font-semibold text-sm px-2 py-1 rounded-md whitespace-nowrap {{ $batch['status'] === 'expired' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600' }}">
                                {{ $batch['available_quantity'] }}
                            </div>
                        </div>
                    @empty
                         <p class="text-xs text-muted-foreground text-center py-2">No urgent batches.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="col-span-1 rounded-xl border bg-card text-card-foreground shadow-sm break-inside-avoid">
            <div class="p-4 flex flex-col space-y-1.5 border-b">
                <h3 class="font-semibold leading-none tracking-tight">Batch Valuation</h3>
                <p class="text-xs text-muted-foreground">Top remaining inventory value by batch.</p>
            </div>
            <div class="p-4 pt-4 max-h-[300px] overflow-auto">
                <div class="space-y-4">
                    @forelse($batchValuation as $batch)
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1 flex-1 min-w-0">
                                <p class="text-sm font-medium leading-none truncate" title="{{ $batch['product_name'] }}">{{ $batch['product_name'] }}</p>
                                <p class="text-[11px] text-muted-foreground">{{ $batch['batch_number'] }} | {{ $batch['sku'] }}</p>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ $batch['available_quantity'] }} x @money($batch['unit_cost']) | {{ $batch['status_label'] }}
                                </p>
                            </div>
                            <div class="font-semibold text-sm text-sky-700 bg-sky-50 px-2 py-1 rounded-md whitespace-nowrap">
                                @money($batch['inventory_value'])
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-muted-foreground text-center py-2">No batch valuation data.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-span-1 rounded-xl border bg-card text-card-foreground shadow-sm break-inside-avoid">
            <div class="p-4 flex flex-col space-y-1.5 border-b">
                <h3 class="font-semibold leading-none tracking-tight">Low Stock Materials</h3>
                <p class="text-xs text-muted-foreground">Materials that need replenishment attention.</p>
            </div>
             <div class="p-4 pt-4 max-h-[300px] overflow-auto">
                <div class="space-y-4">
                    @forelse($lowStockProducts as $product)
                        <div class="flex items-center justify-between">
                            <div class="space-y-1 flex-1">
                                <p class="text-sm font-medium leading-none truncate pr-2" title="{{ $product['name'] }}">{{ $product['name'] }}</p>
                                <p class="text-[11px] text-muted-foreground">{{ $product['sku'] ?? '-' }}</p>
                            </div>
                            <div class="font-semibold text-sm bg-muted px-2 py-1 rounded-md">
                                {{ $product['quantity'] }} / {{ $product['min_stock'] }} <span class="text-xs font-normal text-muted-foreground">min</span>
                            </div>
                        </div>
                    @empty
                         <p class="text-xs text-muted-foreground text-center py-2">No low stock alerts.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
</div>
