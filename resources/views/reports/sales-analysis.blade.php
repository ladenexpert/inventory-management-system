<x-app-layout title="Sales Analysis">
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">Sales Analysis</h2>
                <p class="mt-1 text-sm text-muted-foreground">Legacy sales performance for the last 30 days. RNI material usage stays outside this analysis.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-secondary-button :href="route('reports.sales-analysis.export', ['format' => 'xlsx'])">Export XLSX</x-secondary-button>
                <x-secondary-button :href="route('reports.sales-analysis.export', ['format' => 'csv'])">Export CSV</x-secondary-button>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 {{ $canViewSalesFinancials ? 'md:grid-cols-3' : 'md:grid-cols-1' }}">
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-sm font-medium">Sales Count</p>
                    <p class="mt-2 text-2xl font-bold">{{ number_format($stats['count'] ?? 0) }}</p>
                </div>
                @if($canViewSalesFinancials)
                    <div class="rounded-xl border bg-card p-4 shadow-sm">
                        <p class="text-sm font-medium">Revenue</p>
                        <p class="mt-2 text-2xl font-bold">{{ format_money($stats['total_revenue'] ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl border bg-card p-4 shadow-sm">
                        <p class="text-sm font-medium">Gross Profit</p>
                        <p class="mt-2 text-2xl font-bold">{{ format_money($stats['gross_profit'] ?? 0) }}</p>
                    </div>
                @endif
            </div>

            @if($canViewSalesFinancials)
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-xl border bg-card shadow-sm">
                        <div class="border-b p-4">
                            <h3 class="font-semibold">Top Customers</h3>
                        </div>
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

                    <div class="rounded-xl border bg-card shadow-sm">
                        <div class="border-b p-4">
                            <h3 class="font-semibold">Sales Trend</h3>
                            <p class="text-xs text-muted-foreground">Daily revenue totals for the last 30 days.</p>
                        </div>
                        <div class="p-4">
                            <x-report-chart :config="$salesTrendChart" height="20rem" />
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-dashed bg-card p-4 text-sm text-muted-foreground shadow-sm">
                    Revenue, gross profit, customer spend, and sales-value trend charts are hidden unless the signed-in role can access finance data.
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
