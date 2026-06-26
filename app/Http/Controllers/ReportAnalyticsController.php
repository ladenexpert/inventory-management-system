<?php

namespace App\Http\Controllers;

use App\Services\DashboardStatsService;
use App\Services\ReportChartService;
use App\Services\StockMovementClassificationService;
use App\Support\RmpTerminology;
use App\Support\TransactionContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportAnalyticsController extends Controller
{
    public function salesAnalysis(DashboardStatsService $service, ReportChartService $charts, Request $request)
    {
        $startDate = now()->subDays(29)->startOfDay();
        $endDate = now()->endOfDay();
        $canViewSalesFinancials = $request->user()?->canAccessFinance() ?? false;

        return view('reports.sales-analysis', [
            'stats' => $service->getSalesStats($startDate, $endDate, 'last_30_days'),
            'canViewSalesFinancials' => $canViewSalesFinancials,
            'salesTrendChart' => $canViewSalesFinancials
                ? $charts->line('Sales Revenue', $service->getSalesTrend($startDate, $endDate), '#1d4ed8', 'currency')
                : null,
            'topCustomers' => $canViewSalesFinancials
                ? $service->getTopCustomers($startDate, $endDate, 10)
                : [],
        ]);
    }

    public function exportSalesAnalysis(Request $request, DashboardStatsService $service, string $format): BinaryFileResponse
    {
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);

        $rows = $service->getSalesAnalysisRows(
            now()->subDays(29)->startOfDay(),
            now()->endOfDay(),
        );
        $canViewSalesFinancials = $request->user()?->canAccessFinance() ?? false;

        $filePath = $this->prepareExportFile("sales-analysis-{$format}", $format);
        $writer = $format === 'csv' ? new CsvWriter() : new XlsxWriter();

        $headers = [
            'Date',
            RmpTerminology::TRANSACTION_NUMBER,
            RmpTerminology::REFERENCE_NUMBER,
            'SKU',
            RmpTerminology::ITEM_CODE,
            RmpTerminology::MATERIAL_NAME,
            RmpTerminology::BATCH_NO,
            RmpTerminology::EXPIRY_DATE,
            RmpTerminology::STORAGE_LOCATION,
            'Quantity',
            RmpTerminology::UNIT,
            'Customer',
            RmpTerminology::STATUS,
            'Context',
        ];

        if ($canViewSalesFinancials) {
            $headers = array_merge($headers, [
                'Revenue',
                'Sale Total',
            ]);
        }

        $headers[] = 'Created By';

        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $row) {
            $values = [
                $row['date'],
                $row['transaction_number'],
                $row['reference'] ?? '-',
                $row['sku'],
                $row['item_code_ierp'],
                $row['product_name'],
                $row['batch_number'],
                $row['expiry_date'],
                $row['storage_location'],
                $row['quantity'],
                $row['unit'],
                $row['customer'],
                $row['status'],
                $row['context_label'] ?? TransactionContext::label(TransactionContext::LEGACY_SALE),
            ];

            if ($canViewSalesFinancials) {
                $values[] = $row['line_revenue'];
                $values[] = $row['sale_total'];
            }

            $values[] = $row['created_by'];

            $writer->addRow(Row::fromValues($values));
        }

        $writer->close();

        return response()
            ->download($filePath, TransactionContext::exportFilename(TransactionContext::SALES_ANALYSIS, $format))
            ->deleteFileAfterSend(true);
    }

    public function purchaseAnalysis(DashboardStatsService $service, ReportChartService $charts, Request $request)
    {
        $startDate = now()->subDays(29)->startOfDay();
        $endDate = now()->endOfDay();
        $canViewPurchaseFinancials = $request->user()?->canAccessFinance() ?? false;
        $canViewInventoryValue = ($request->user()?->canViewInventoryValue() ?? false) || $canViewPurchaseFinancials;

        return view('reports.purchase-analysis', [
            'purchaseTrendChart' => $canViewPurchaseFinancials
                ? $charts->line('Purchase Total', $service->getPurchaseTrend($startDate, $endDate), '#b45309', 'currency')
                : null,
            'inboundTrendChart' => $charts->bar('Inbound Units', $service->getInboundTrend($startDate, $endDate), '#0f766e'),
            'topSuppliers' => $canViewPurchaseFinancials
                ? $service->getTopSuppliers($startDate, $endDate, 10)
                : [],
            'businessStats' => $service->getBusinessInsightStats($startDate, $endDate),
            'canViewPurchaseFinancials' => $canViewPurchaseFinancials,
            'canViewInventoryValue' => $canViewInventoryValue,
        ]);
    }

    public function exportPurchaseAnalysis(Request $request, DashboardStatsService $service, string $format): BinaryFileResponse
    {
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);

        $rows = $service->getPurchaseAnalysisRows(
            now()->subDays(29)->startOfDay(),
            now()->endOfDay(),
        );
        $canViewPurchaseFinancials = $request->user()?->canAccessFinance() ?? false;

        $filePath = $this->prepareExportFile("purchase-analysis-{$format}", $format);
        $writer = $format === 'csv' ? new CsvWriter() : new XlsxWriter();

        $headers = [
            'Date',
            RmpTerminology::TRANSACTION_NUMBER,
            RmpTerminology::REFERENCE_NUMBER,
            'Supplier',
            'SKU',
            RmpTerminology::ITEM_CODE,
            RmpTerminology::MATERIAL_NAME,
            RmpTerminology::BATCH_NO,
            RmpTerminology::EXPIRY_DATE,
            RmpTerminology::STORAGE_LOCATION,
            'Quantity',
            RmpTerminology::UNIT,
            RmpTerminology::STATUS,
            'Context',
        ];

        if ($canViewPurchaseFinancials) {
            $headers = array_merge($headers, [
                'Line Amount',
                'Purchase Total',
            ]);
        }

        $headers[] = 'Created By';

        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $row) {
            $values = [
                $row['date'],
                $row['transaction_number'],
                $row['reference'],
                $row['supplier'],
                $row['sku'],
                $row['item_code_ierp'],
                $row['product_name'],
                $row['batch_number'],
                $row['expiry_date'],
                $row['storage_location'],
                $row['quantity'],
                $row['unit'],
                $row['status'],
                $row['context_label'],
            ];

            if ($canViewPurchaseFinancials) {
                $values[] = $row['line_amount'];
                $values[] = $row['purchase_total'];
            }

            $values[] = $row['created_by'];

            $writer->addRow(Row::fromValues($values));
        }

        $writer->close();

        return response()
            ->download($filePath, TransactionContext::exportFilename(TransactionContext::INBOUND_PURCHASE_ANALYSIS, $format))
            ->deleteFileAfterSend(true);
    }

    public function stockMovementClassification(
        StockMovementClassificationService $classificationService,
        ReportChartService $charts
    ) {
        $summary = $classificationService->summary();

        return view('reports.stock-movement-classification', [
            'summary' => $summary,
            'classificationChart' => $charts->bar(
                'Material Count',
                $classificationService->chartSummary(),
                '#0f766e'
            ),
            'hasUnclassifiedMaterials' => ($summary['normal_unclassified'] + $summary['no_usage_unclassified']) > 0,
        ]);
    }

    private function prepareExportFile(string $baseName, string $format): string
    {
        $directory = storage_path('app/temp');

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        return $directory . DIRECTORY_SEPARATOR . $baseName . '-' . Str::random(8) . '.' . $format;
    }
}
