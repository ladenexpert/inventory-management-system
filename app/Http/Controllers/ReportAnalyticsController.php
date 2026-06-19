<?php

namespace App\Http\Controllers;

use App\Services\DashboardStatsService;

class ReportAnalyticsController extends Controller
{
    public function salesAnalysis(DashboardStatsService $service)
    {
        $startDate = now()->subDays(29)->startOfDay();
        $endDate = now()->endOfDay();

        return view('reports.sales-analysis', [
            'stats' => $service->getSalesStats($startDate, $endDate, 'last_30_days'),
            'salesTrend' => $service->getSalesTrend($startDate, $endDate),
            'topCustomers' => $service->getTopCustomers($startDate, $endDate, 10),
            'fastMovingMaterials' => $service->getFastMovingMaterials($startDate, $endDate, 10),
        ]);
    }

    public function purchaseAnalysis(DashboardStatsService $service)
    {
        $startDate = now()->subDays(29)->startOfDay();
        $endDate = now()->endOfDay();

        return view('reports.purchase-analysis', [
            'purchaseTrend' => $service->getPurchaseTrend($startDate, $endDate),
            'inboundTrend' => $service->getInboundTrend($startDate, $endDate),
            'topSuppliers' => $service->getTopSuppliers($startDate, $endDate, 10),
            'businessStats' => $service->getBusinessInsightStats($startDate, $endDate),
        ]);
    }
}
