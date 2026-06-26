<?php

namespace App\Http\Controllers;

use App\Services\StockTakeImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Support\RmpTerminology;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockTakeImportController extends Controller
{
    public function __construct(
        protected StockTakeImportService $stockTakeImportService,
    ) {
    }

    public function index(): View
    {
        return view('stock-take.import', [
            'preview' => session('stock_take_preview'),
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,ods', 'max:20480'],
        ]);

        try {
            $preview = $this->stockTakeImportService->previewFromFile($request->file('file')->getRealPath());

            session([
                'stock_take_preview' => $preview,
                'stock_take_preview_rows' => $preview['rows'],
            ]);

            $message = $preview['errors'] === []
                ? 'Stock take preview generated successfully.'
                : 'Stock take preview generated with validation errors. Please review before applying.';

            return back()->with($preview['errors'] === [] ? 'success' : 'warning', $message);
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to generate stock take preview: ' . $e->getMessage());
        }
    }

    public function apply(Request $request): RedirectResponse
    {
        $rows = session('stock_take_preview_rows', []);
        $preview = session('stock_take_preview');

        if ($rows === [] || !is_array($rows)) {
            return back()->with('error', 'No stock take preview is available to apply.');
        }

        if (!empty($preview['errors'])) {
            return back()->with('error', 'Please resolve preview errors before applying stock take adjustments.');
        }

        try {
            $result = $this->stockTakeImportService->applyPreviewRows($rows, (int) $request->user()->id);

            session()->forget(['stock_take_preview', 'stock_take_preview_rows']);

            return back()->with('success', "Stock take applied. Adjusted rows: {$result['applied_rows']}, skipped rows: {$result['skipped_rows']}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to apply stock take adjustments: ' . $e->getMessage());
        }
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $directory = storage_path('app/temp');

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filePath = $directory . DIRECTORY_SEPARATOR . 'template-stock-take-import-' . Str::random(8) . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues([
            RmpTerminology::ITEM_CODE,
            RmpTerminology::BATCH_NO,
            RmpTerminology::EXPIRY_DATE,
            RmpTerminology::STORAGE_LOCATION,
            RmpTerminology::COUNTED_QTY,
            RmpTerminology::UNIT,
            RmpTerminology::REFERENCE_NUMBER,
            RmpTerminology::NOTES,
        ]));
        $writer->addRow(Row::fromValues([
            'IERP-RM-0001',
            'RM-LOT-001',
            now()->addMonths(6)->format('Y-m-d'),
            'RACK-A1',
            '98',
            'KG',
            'STOCKTAKE-JUN-01',
            'Cycle count adjustment',
        ]));
        $writer->close();

        return response()->download($filePath, 'template-stock-take-import.xlsx')->deleteFileAfterSend(true);
    }
}
