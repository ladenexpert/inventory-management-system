<?php

namespace App\Http\Controllers;

use App\Models\StockTakeSession;
use App\Services\StockTakeImportService;
use App\Support\RmpTerminology;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockTakeImportController extends Controller
{
    public function __construct(
        protected StockTakeImportService $stockTakeImportService,
    ) {
    }

    public function index(): View
    {
        $sessions = StockTakeSession::query()
            ->with(['importedByUser', 'reviewedByUser', 'postedByUser', 'closedByUser'])
            ->latest('id')
            ->paginate(10);

        return view('stock-take.import', [
            'sessions' => $sessions,
        ]);
    }

    public function show(StockTakeSession $stockTakeSession): View
    {
        $session = $this->stockTakeImportService->loadSession($stockTakeSession);
        $rows = $session->rows()->with(['batch.product.unit', 'product.unit', 'inventoryAdjustment'])->paginate(50);

        return view('stock-take.show', [
            'session' => $session,
            'rows' => $rows,
            'summary' => $this->stockTakeImportService->summarizeSessionRows($session->rows),
            'canViewValuation' => $this->canViewValuation(),
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,ods', 'max:20480'],
        ]);

        try {
            $session = $this->stockTakeImportService->createSessionFromFile(
                $request->file('file')->getRealPath(),
                (int) $request->user()->id,
            );

            $summary = $this->stockTakeImportService->summarizeSessionRows($session->rows);
            $message = $summary['error_rows'] === 0
                ? 'Stock take preview created successfully. Review the session before posting.'
                : 'Stock take preview created with row issues. Review the session, fix the file if needed, then continue.';

            return redirect()
                ->route('stock-take.show', $session)
                ->with($summary['error_rows'] === 0 ? 'success' : 'warning', $message);
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to create the stock take preview. Please review the file and try again. ' . $e->getMessage());
        }
    }

    public function recalculate(Request $request, StockTakeSession $stockTakeSession): RedirectResponse
    {
        try {
            $session = $this->stockTakeImportService->recalculateSession(
                $stockTakeSession,
                (int) $request->user()->id,
            );

            return redirect()
                ->route('stock-take.show', $session)
                ->with('success', 'System quantities and variances were recalculated from the latest batch stock.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to recalculate the stock take session. ' . $e->getMessage());
        }
    }

    public function apply(Request $request, StockTakeSession $stockTakeSession): RedirectResponse
    {
        try {
            $result = $this->stockTakeImportService->postSession(
                $stockTakeSession,
                (int) $request->user()->id,
            );

            $flash = match ($result['status']) {
                'posted' => 'success',
                'stale' => 'warning',
                default => 'error',
            };

            return redirect()
                ->route('stock-take.show', $result['session'])
                ->with($flash, $result['message']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to post the stock take session. ' . $e->getMessage());
        }
    }

    public function close(Request $request, StockTakeSession $stockTakeSession): RedirectResponse
    {
        try {
            $session = $this->stockTakeImportService->closeSession(
                $stockTakeSession,
                (int) $request->user()->id,
            );

            return redirect()
                ->route('stock-take.show', $session)
                ->with('success', 'Stock take session closed successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to close the stock take session. ' . $e->getMessage());
        }
    }

    public function export(StockTakeSession $stockTakeSession, string $format): BinaryFileResponse
    {
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);

        $directory = storage_path('app/temp');

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $extension = $format === 'csv' ? 'csv' : 'xlsx';
        $filePath = $directory . DIRECTORY_SEPARATOR . 'stock-take-session-' . Str::random(8) . '.' . $extension;
        $writer = $format === 'csv' ? new CsvWriter() : new XlsxWriter();
        $includeValuation = $this->canViewValuation();
        $rows = $this->stockTakeImportService->exportRows(
            $this->stockTakeImportService->loadSession($stockTakeSession),
            $includeValuation,
        );

        $headers = [
            'Row',
            RmpTerminology::SKU,
            RmpTerminology::ITEM_CODE,
            'Material',
            RmpTerminology::BATCH_NO,
            'System Qty',
            RmpTerminology::COUNTED_QTY,
            'Variance Qty',
            RmpTerminology::EXPIRY_DATE,
            RmpTerminology::STORAGE_LOCATION,
            RmpTerminology::REFERENCE_NUMBER,
            RmpTerminology::STATUS,
            RmpTerminology::NOTES,
            'Error Message',
        ];

        if ($includeValuation) {
            $headers = array_merge($headers, [
                'Unit Cost',
                'Adjustment Value',
                RmpTerminology::INVENTORY_VALUE,
                'Average Cost',
            ]);
        }

        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $row) {
            $values = [
                $row['row_number'],
                $row['sku'],
                $row['item_code'],
                $row['material_name'],
                $row['batch_number'],
                $row['system_qty'],
                $row['counted_qty'],
                $row['variance_qty'],
                $row['expiry_date'],
                $row['storage_location'],
                $row['reference'],
                Str::headline((string) $row['status']),
                $row['notes'],
                $row['error_message'],
            ];

            if ($includeValuation) {
                $values = array_merge($values, [
                    $row['unit_cost'],
                    $row['adjustment_value'],
                    $row['inventory_value'],
                    $row['average_cost'],
                ]);
            }

            $writer->addRow(Row::fromValues($values));
        }

        $writer->close();

        return response()
            ->download($filePath, "stock_take_session_{$stockTakeSession->session_code}.{$extension}")
            ->deleteFileAfterSend(true);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $directory = storage_path('app/temp');

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filePath = $directory . DIRECTORY_SEPARATOR . 'template-stock-take-import-' . Str::random(8) . '.xlsx';

        $writer = new XlsxWriter();
        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues([
            RmpTerminology::SKU,
            RmpTerminology::ITEM_CODE,
            'Material',
            RmpTerminology::BATCH_NO,
            'Expiry',
            RmpTerminology::STORAGE_LOCATION,
            RmpTerminology::COUNTED_QTY,
            RmpTerminology::REFERENCE_NUMBER,
            RmpTerminology::NOTES,
        ]));
        $writer->addRow(Row::fromValues([
            '# Required: SKU, Batch No, Counted Qty. Optional: Item Code, Material, Expiry, Storage Location, Reference Number, Notes.',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ]));
        $writer->addRow(Row::fromValues([
            'SKU-RM-0001',
            '',
            'Example Material',
            'RM-LOT-001',
            now()->addMonths(6)->format('Y-m-d'),
            'RACK-A1',
            '98',
            'STOCKTAKE-JUN-01',
            'Cycle count adjustment',
        ]));
        $writer->close();

        return response()->download($filePath, 'template-stock-take-import.xlsx')->deleteFileAfterSend(true);
    }

    private function canViewValuation(): bool
    {
        $user = auth()->user();

        return ($user?->canViewInventoryValue() ?? false)
            || ($user?->canAccessFinance() ?? false);
    }
}
