<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Enums\SaleTransactionType;
use App\Models\Batch;
use App\Models\FinanceTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
    public function __construct(
        protected BatchPolicyService $batchPolicyService,
    ) {
    }

    /**
     * Get Sales Statistics (Total Revenue, Net Profit, Count)
     */
    public function getSalesStats(Carbon $startDate, Carbon $endDate, string $periodKey): array
    {
        $cacheKey = "dashboard_sales_{$periodKey}_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($startDate, $endDate) {
            $salesData = $this->commercialSalesQuery()
                ->whereBetween('sale_date', [$startDate, $endDate])
                ->where('status', 'completed')
                ->selectRaw('COUNT(*) as count, SUM(total) as total_revenue')
                ->first();

            $totalRevenue = $salesData->total_revenue ?? 0;
            $count = $salesData->count ?? 0;

            $cogs = SaleItem::whereHas('sale', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('sale_date', [$startDate, $endDate])
                    ->where('transaction_type', SaleTransactionType::SALE->value)
                    ->where('status', 'completed');
            })->sum('total_cost');

            $grossProfit = $totalRevenue - $cogs;

            return [
                'total_revenue' => (float) $totalRevenue,
                'count' => (int) $count,
                'gross_profit' => (float) $grossProfit,
            ];
        });
    }

    /**
     * Get Cash Flow Statistics (Income, Expense, Net)
     */
    public function getCashFlowStats(Carbon $startDate, Carbon $endDate, string $periodKey): array
    {
        $cacheKey = "dashboard_cashflow_{$periodKey}_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($startDate, $endDate) {
            $totals = FinanceTransaction::join('finance_categories', 'finance_transactions.finance_category_id', '=', 'finance_categories.id')
                ->whereBetween('finance_transactions.transaction_date', [$startDate, $endDate])
                ->selectRaw('finance_categories.type, SUM(finance_transactions.amount) as total')
                ->groupBy('finance_categories.type')
                ->pluck('total', 'finance_categories.type');

            $income = (float) ($totals['income'] ?? 0);
            $expense = (float) ($totals['expense'] ?? 0);

            return [
                'income' => $income,
                'expense' => $expense,
                'net_cash_flow' => $income - $expense,
            ];
        });
    }

    /**
     * Get current inventory valuation based on active batch layers.
     */
    public function getInventoryValuation(): array
    {
        return Cache::remember('dashboard_inventory_valuation', now()->addMinutes(5), function () {
            $costValue = (int) Batch::query()
                ->where('available_quantity', '>', 0)
                ->selectRaw('COALESCE(SUM(available_quantity * unit_cost), 0) as total')
                ->value('total');

            $sellingValue = (int) Batch::query()
                ->join('products', 'products.id', '=', 'batches.product_id')
                ->where('batches.available_quantity', '>', 0)
                ->selectRaw('COALESCE(SUM(batches.available_quantity * COALESCE(batches.selling_price, products.selling_price)), 0) as total')
                ->value('total');

            return [
                'cost_value' => $costValue,
                'selling_value' => $sellingValue,
                'potential_margin' => $sellingValue - $costValue,
            ];
        });
    }

    /**
     * Get Low Stock Products.
     */
    public function getLowStockProducts(int $limit = 5): array
    {
        return Cache::remember('dashboard_low_stock', now()->addMinutes(5), function () use ($limit) {
            return Product::whereColumn('quantity', '<=', 'min_stock')
                ->where('is_active', true)
                ->orderBy('quantity', 'asc')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /**
     * Get batch lifecycle alert statistics.
     */
    public function getBatchAlertStats(): array
    {
        $today = now()->startOfDay();
        $nearExpiryDays = $this->batchPolicyService->nearExpiryThresholdDays();
        $until = $today->copy()->addDays($nearExpiryDays)->endOfDay();
        $cacheKey = "dashboard_batch_alerts_{$today->format('Ymd')}_{$nearExpiryDays}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($today, $until, $nearExpiryDays) {
            $sellableBase = Batch::query()
                ->where('available_quantity', '>', 0)
                ->where('source', '!=', BatchStatus::QUARANTINED->value);

            $expiredCount = (clone $sellableBase)
                ->whereDate('expiry_date', '<', $today)
                ->count();

            $nearExpiryCount = (clone $sellableBase)
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', $until)
                ->count();

            $depletedCount = Batch::query()
                ->where('available_quantity', '<=', 0)
                ->count();

            $zeroCostCount = Batch::query()
                ->where('available_quantity', '>', 0)
                ->where('unit_cost', '=', 0)
                ->count();

            return [
                'expired_count' => $expiredCount,
                'near_expiry_count' => $nearExpiryCount,
                'depleted_count' => $depletedCount,
                'zero_cost_count' => $zeroCostCount,
                'near_expiry_days' => $nearExpiryDays,
            ];
        });
    }

    /**
     * Get the most urgent batches based on expiry date.
     */
    public function getUrgentBatches(int $limit = 5): array
    {
        $today = now()->startOfDay();
        $nearExpiryDays = $this->batchPolicyService->nearExpiryThresholdDays();
        $until = $today->copy()->addDays($nearExpiryDays)->endOfDay();
        $cacheKey = "dashboard_urgent_batches_{$today->format('Ymd')}_{$limit}_{$nearExpiryDays}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($until, $limit) {
            return Batch::query()
                ->with('product:id,name,sku')
                ->where('available_quantity', '>', 0)
                ->where('source', '!=', BatchStatus::QUARANTINED->value)
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', $until)
                ->orderBy('expiry_date')
                ->orderBy('received_at')
                ->limit($limit)
                ->get()
                ->map(function (Batch $batch) {
                    $status = $this->batchPolicyService->getStatus($batch);

                    return [
                        'batch_number' => $batch->batch_number,
                        'product_name' => $batch->product->name ?? 'Unknown Product',
                        'sku' => $batch->product->sku ?? '-',
                        'available_quantity' => $batch->available_quantity,
                        'expiry_date' => $batch->expiry_date?->format('Y-m-d'),
                        'status' => $status->value,
                        'status_label' => $status->label(),
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get top remaining batch valuations.
     */
    public function getTopBatchValuations(int $limit = 5): array
    {
        return Cache::remember("dashboard_batch_valuations_{$limit}", now()->addMinutes(10), function () use ($limit) {
            return Batch::query()
                ->with('product:id,name,sku')
                ->where('available_quantity', '>', 0)
                ->orderByRaw('(available_quantity * unit_cost) DESC')
                ->orderBy('expiry_date')
                ->limit($limit)
                ->get()
                ->map(function (Batch $batch) {
                    $status = $this->batchPolicyService->getStatus($batch);

                    return [
                        'batch_number' => $batch->batch_number,
                        'product_name' => $batch->product->name ?? 'Unknown Product',
                        'sku' => $batch->product->sku ?? '-',
                        'available_quantity' => (int) $batch->available_quantity,
                        'unit_cost' => (int) $batch->unit_cost,
                        'inventory_value' => $this->batchPolicyService->inventoryValue($batch),
                        'status' => $status->value,
                        'status_label' => $status->label(),
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get Top Selling Products.
     */
    public function getTopProducts(Carbon $startDate, Carbon $endDate, int $limit = 5): array
    {
        $cacheKey = "dashboard_top_products_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($startDate, $endDate, $limit) {
            return SaleItem::select('product_id', DB::raw('SUM(quantity) as total_qty'))
                ->whereHas('sale', function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('sale_date', [$startDate, $endDate])
                        ->where('transaction_type', SaleTransactionType::SALE->value)
                        ->where('status', 'completed');
                })
                ->with('product:id,name,sku')
                ->groupBy('product_id')
                ->orderByDesc('total_qty')
                ->limit($limit)
                ->get()
                ->map(function ($item) {
                    return [
                        'product_name' => $item->product->name,
                        'sku' => $item->product->sku,
                        'total_sold' => $item->total_qty,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get Recent Sales.
     */
    public function getRecentSales(int $limit = 5): array
    {
        return Cache::remember('dashboard_recent_sales', now()->addMinutes(1), function () use ($limit) {
            return $this->commercialSalesQuery()
                ->with('customer:id,name')
                ->orderByDesc('sale_date')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /**
     * Get Sales Chart Data (Daily Trend).
     */
    public function getSalesTrend(Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "dashboard_sales_trend_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($startDate, $endDate) {
            $data = $this->commercialSalesQuery()
                ->selectRaw('DATE(sale_date) as date, SUM(total) as total')
                ->whereBetween('sale_date', [$startDate, $endDate])
                ->where('status', 'completed')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->pluck('total', 'date')
                ->toArray();

            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
            $chartData = [];

            foreach ($period as $date) {
                $formattedDate = $date->format('Y-m-d');
                $chartData[$formattedDate] = $data[$formattedDate] ?? 0;
            }

            return $chartData;
        });
    }

    /**
     * Get Cash Flow Chart Data (Income vs Expense).
     */
    public function getCashFlowTrend(Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "dashboard_cashflow_trend_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($startDate, $endDate) {
            $transactions = FinanceTransaction::join('finance_categories', 'finance_transactions.finance_category_id', '=', 'finance_categories.id')
                ->whereBetween('finance_transactions.transaction_date', [$startDate, $endDate])
                ->selectRaw('DATE(finance_transactions.transaction_date) as date, finance_categories.type, SUM(finance_transactions.amount) as total')
                ->groupBy('date', 'finance_categories.type')
                ->get();

            $grouped = [];
            foreach ($transactions as $transaction) {
                $grouped[$transaction->date][$transaction->type] = $transaction->total;
            }

            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
            $incomeData = [];
            $expenseData = [];

            foreach ($period as $date) {
                $formattedDate = $date->format('Y-m-d');
                $incomeData[$formattedDate] = (float) ($grouped[$formattedDate]['income'] ?? 0);
                $expenseData[$formattedDate] = (float) ($grouped[$formattedDate]['expense'] ?? 0);
            }

            return [
                'income' => $incomeData,
                'expense' => $expenseData,
            ];
        });
    }

    /**
     * Get Top Customers by Revenue.
     */
    public function getTopCustomers(Carbon $startDate, Carbon $endDate, int $limit = 5): array
    {
        $cacheKey = "dashboard_top_customers_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($startDate, $endDate, $limit) {
            return $this->commercialSalesQuery()
                ->select('customer_id', DB::raw('SUM(total) as total_spent'))
                ->whereBetween('sale_date', [$startDate, $endDate])
                ->where('status', 'completed')
                ->whereNotNull('customer_id')
                ->with('customer:id,name,phone')
                ->groupBy('customer_id')
                ->orderByDesc('total_spent')
                ->limit($limit)
                ->get()
                ->map(function ($item) {
                    return [
                        'customer_name' => $item->customer->name ?? 'Unknown',
                        'phone' => $item->customer->phone ?? '-',
                        'total_spent' => $item->total_spent,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get Expense Breakdown by Category.
     */
    public function getExpenseBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "dashboard_expense_breakdown_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($startDate, $endDate) {
            return FinanceTransaction::select('finance_category_id', DB::raw('SUM(amount) as total_amount'))
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->whereHas('category', function ($query) {
                    $query->where('type', 'expense');
                })
                ->with('category:id,name,type')
                ->groupBy('finance_category_id')
                ->orderByDesc('total_amount')
                ->get()
                ->map(function ($item) {
                    return [
                        'category_name' => $item->category->name ?? 'Uncategorized',
                        'total_amount' => $item->total_amount,
                    ];
                })
                ->toArray();
        });
    }

    public function getRniOverviewStats(): array
    {
        return Cache::remember('dashboard_rni_overview_stats', now()->addMinutes(5), function () {
            $activeBatches = Batch::query()
                ->with('product')
                ->where('available_quantity', '>', 0)
                ->get();

            $physicalStockQuantity = (int) $activeBatches->sum('available_quantity');
            $usableStockQuantity = (int) $activeBatches
                ->filter(fn (Batch $batch) => $this->batchPolicyService->canBeConsumed($batch))
                ->sum('available_quantity');
            $expiredStockQuantity = (int) $activeBatches
                ->filter(fn (Batch $batch) => $this->batchPolicyService->isExpired($batch))
                ->sum('available_quantity');
            $nearExpiryStockQuantity = (int) $activeBatches
                ->filter(fn (Batch $batch) => $this->batchPolicyService->isNearExpiry($batch))
                ->sum('available_quantity');
            $expiredBatchCount = (int) $activeBatches
                ->filter(fn (Batch $batch) => $this->batchPolicyService->isExpired($batch))
                ->count();
            $nearExpiryBatchCount = (int) $activeBatches
                ->filter(fn (Batch $batch) => $this->batchPolicyService->isNearExpiry($batch))
                ->count();
            $zeroCostBatchCount = (int) $activeBatches->where('unit_cost', 0)->count();
            $currentMonthStart = now()->startOfMonth();
            $currentMonthEnd = now()->endOfMonth();
            $materialUsageThisMonth = (int) SaleItem::query()
                ->whereHas('sale', function (Builder $query) use ($currentMonthStart, $currentMonthEnd) {
                    $query
                        ->where('transaction_type', SaleTransactionType::MATERIAL_USAGE->value)
                        ->whereBetween('sale_date', [$currentMonthStart, $currentMonthEnd]);
                })
                ->sum('quantity');

            return [
                'total_rm' => Product::query()->where('is_active', true)->count(),
                'total_batch' => $activeBatches->count(),
                'total_physical_stock_quantity' => $physicalStockQuantity,
                'total_usable_stock_quantity' => $usableStockQuantity,
                'expired_stock_quantity' => $expiredStockQuantity,
                'near_expiry_stock_quantity' => $nearExpiryStockQuantity,
                'near_expiry' => $nearExpiryBatchCount,
                'expired' => $expiredBatchCount,
                'zero_cost_batch' => $zeroCostBatchCount,
                'zero_cost_batch_count' => $zeroCostBatchCount,
                'material_usage_this_month' => $materialUsageThisMonth,
                'low_stock' => Product::query()
                    ->whereColumn('quantity', '<=', 'min_stock')
                    ->where('is_active', true)
                    ->count(),
            ];
        });
    }

    public function getRecentMaterialUsage(int $limit = 8): array
    {
        return Cache::remember("dashboard_recent_material_usage_{$limit}", now()->addMinutes(1), function () use ($limit) {
            return $this->materialUsageQuery()
                ->with(['creator:id,name', 'issuer:id,name', 'items.product:id,name'])
                ->orderByDesc('usage_date')
                ->orderByDesc('sale_date')
                ->limit($limit)
                ->get()
                ->map(function (Sale $sale) {
                    return [
                        'id' => $sale->id,
                        'usage_number' => $sale->invoice_number,
                        'usage_date' => optional($sale->usage_date ?? $sale->sale_date)?->format('Y-m-d'),
                        'purpose' => $sale->purpose ?? '-',
                        'formula' => $sale->formula ?? '-',
                        'project' => $sale->project ?? '-',
                        'issued_by' => $sale->issuer->name ?? $sale->creator->name ?? '-',
                        'line_count' => $sale->items->count(),
                        'total_qty' => (int) $sale->items->sum('quantity'),
                    ];
                })
                ->toArray();
        });
    }

    public function getTopUsedMaterialsThisMonth(int $limit = 5): array
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        return Cache::remember("dashboard_top_used_materials_{$limit}_{$start->format('Ym')}", now()->addMinutes(5), function () use ($start, $end, $limit) {
            return SaleItem::query()
                ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
                ->whereHas('sale', function (Builder $query) use ($start, $end) {
                    $query
                        ->where('transaction_type', SaleTransactionType::MATERIAL_USAGE->value)
                        ->whereBetween('sale_date', [$start, $end]);
                })
                ->with('product:id,name,sku')
                ->groupBy('product_id')
                ->orderByDesc('total_quantity')
                ->limit($limit)
                ->get()
                ->map(fn (SaleItem $item) => [
                    'product_name' => $item->product?->name ?? 'Unknown Material',
                    'sku' => $item->product?->sku ?? '-',
                    'total_quantity' => (int) $item->total_quantity,
                ])
                ->toArray();
        });
    }

    public function getNearExpiryMaterialRisks(int $limit = 5): array
    {
        $today = now()->startOfDay();
        $until = $today->copy()->addDays($this->batchPolicyService->nearExpiryThresholdDays())->endOfDay();

        return Cache::remember("dashboard_near_expiry_material_risks_{$limit}_{$today->format('Ymd')}", now()->addMinutes(5), function () use ($today, $until, $limit) {
            return Batch::query()
                ->select('product_id')
                ->selectRaw('MIN(expiry_date) as nearest_expiry_date')
                ->selectRaw('SUM(available_quantity) as at_risk_quantity')
                ->with('product:id,name,sku')
                ->where('available_quantity', '>', 0)
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', $until)
                ->groupBy('product_id')
                ->orderBy('nearest_expiry_date')
                ->orderByDesc('at_risk_quantity')
                ->limit($limit)
                ->get()
                ->map(fn (Batch $batch) => [
                    'product_name' => $batch->product?->name ?? 'Unknown Material',
                    'sku' => $batch->product?->sku ?? '-',
                    'nearest_expiry_date' => $batch->nearest_expiry_date
                        ? Carbon::parse($batch->nearest_expiry_date)->format('Y-m-d')
                        : null,
                    'at_risk_quantity' => (int) $batch->at_risk_quantity,
                ])
                ->toArray();
        });
    }

    protected function commercialSalesQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Sale::query()->where('transaction_type', SaleTransactionType::SALE->value);
    }

    protected function materialUsageQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Sale::query()->where('transaction_type', SaleTransactionType::MATERIAL_USAGE->value);
    }
}
