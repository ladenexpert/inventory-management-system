<x-app-layout title="Purchase Analysis">
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">Purchase Analysis</h2>
                <p class="mt-1 text-sm text-muted-foreground">Inbound and supplier spend visibility for the last 30 days.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-secondary-button :href="route('reports.purchase-analysis.export', ['format' => 'xlsx'])">Export XLSX</x-secondary-button>
                <x-secondary-button :href="route('reports.purchase-analysis.export', ['format' => 'csv'])">Export CSV</x-secondary-button>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-sm font-medium">Purchase Total</p>
                    <p class="mt-2 text-2xl font-bold">{{ format_money($businessStats['purchase_total'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-sm font-medium">Inbound Units</p>
                    <p class="mt-2 text-2xl font-bold">{{ number_format($businessStats['inbound_total'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-sm font-medium">Inventory Value</p>
                    <p class="mt-2 text-2xl font-bold">{{ format_money($businessStats['inventory_value'] ?? 0) }}</p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border bg-card shadow-sm">
                    <div class="border-b p-4">
                        <h3 class="font-semibold">Top Suppliers</h3>
                    </div>
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
                    <div class="border-b p-4">
                        <h3 class="font-semibold">Inbound Trend</h3>
                    </div>
                    <div class="grid gap-3 p-4 sm:grid-cols-2">
                        @foreach($inboundTrend as $date => $total)
                            <div class="rounded-lg border border-gray-200 p-3">
                                <p class="text-xs text-muted-foreground">{{ $date }}</p>
                                <p class="mt-1 text-sm font-semibold">{{ number_format($total) }} units</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b p-4">
                    <h3 class="font-semibold">Purchase Trend</h3>
                    <p class="text-xs text-muted-foreground">Daily purchase totals for the last 30 days.</p>
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-5">
                    @foreach($purchaseTrend as $date => $total)
                        <div class="rounded-lg border border-gray-200 p-3">
                            <p class="text-xs text-muted-foreground">{{ $date }}</p>
                            <p class="mt-1 text-sm font-semibold">{{ format_money($total) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
