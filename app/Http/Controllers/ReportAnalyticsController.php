<?php

namespace App\Http\Controllers;

use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function exportSalesAnalysis(Request $request, DashboardStatsService $service, string $format): BinaryFileResponse
    {
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);

        $rows = $service->getSalesAnalysisRows(
            now()->subDays(29)->startOfDay(),
            now()->endOfDay(),
        );

        $filePath = $this->prepareExportFile("sales-analysis-{$format}", $format);
        $writer = $format === 'csv' ? new CsvWriter() : new XlsxWriter();

        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues([
            'Date',
            'Invoice Number',
            'SKU',
            'Item Code IERP',
            'Material / Product Name',
            'Batch / Lot Number',
            'Expiry Date',
            'Storage Location',
            'Quantity',
            'Unit',
            'Customer',
            'Status',
            'Revenue',
            'Sale Total',
            'Created By',
        ]));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues([
                $row['date'],
                $row['invoice_number'],
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
                $row['line_revenue'],
                $row['sale_total'],
                $row['created_by'],
            ]));
        }

        $writer->close();

        return response()
            ->download($filePath, 'sales-analysis.' . $format)
            ->deleteFileAfterSend(true);
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

    public function exportPurchaseAnalysis(Request $request, DashboardStatsService $service, string $format): BinaryFileResponse
    {
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);

        $rows = $service->getPurchaseAnalysisRows(
            now()->subDays(29)->startOfDay(),
            now()->endOfDay(),
        );

        $filePath = $this->prepareExportFile("purchase-analysis-{$format}", $format);
        $writer = $format === 'csv' ? new CsvWriter() : new XlsxWriter();

        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues([
            'Date',
            'Reference',
            'Supplier',
            'SKU',
            'Item Code IERP',
            'Material / Product Name',
            'Batch / Lot Number',
            'Expiry Date',
            'Storage Location',
            'Quantity',
            'Unit',
            'Status',
            'Line Amount',
            'Purchase Total',
            'Created By',
        ]));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues([
                $row['date'],
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
                $row['line_amount'],
                $row['purchase_total'],
                $row['created_by'],
            ]));
        }

        $writer->close();

        return response()
            ->download($filePath, 'purchase-analysis.' . $format)
            ->deleteFileAfterSend(true);
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
