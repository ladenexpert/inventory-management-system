<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use Illuminate\Http\RedirectResponse;
use OpenSpout\Writer\XLSX\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Services\ProductOpeningStockImportService;
use App\Support\RmpTerminology;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductOpeningStockImportController extends Controller
{
    public function __construct(
        protected ProductOpeningStockImportService $importService
    ) {
    }

    public function index(): View
    {
        return view('products.import-opening-stock');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,ods', 'max:20480'],
        ]);

        try {
            $result = $this->importService->importFromFile($request->file('file')->getRealPath());

            if ($result['failed_rows'] > 0 && $result['created_rows'] > 0) {
                return back()
                    ->with('warning', "Import selesai. Berhasil: {$result['created_rows']}, Gagal: {$result['failed_rows']}.")
                    ->with('import_report', $result);
            }

            if ($result['failed_rows'] > 0) {
                return back()
                    ->with('error', "Import gagal. Total baris gagal: {$result['failed_rows']}.")
                    ->with('import_report', $result);
            }

            return back()
                ->with('success', "Import stok awal berhasil. Total product dibuat: {$result['created_rows']}.")
                ->with('import_report', $result);
        } catch (\Throwable $e) {
            return back()->with('error', 'Import gagal: ' . $e->getMessage());
        }
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $directory = storage_path('app/temp');

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filePath = $directory . DIRECTORY_SEPARATOR . 'template-import-stok-awal-' . Str::random(8) . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues([
            'SKU',
            RmpTerminology::ITEM_CODE,
            RmpTerminology::MATERIAL_NAME,
            'Category',
            RmpTerminology::UNIT,
            RmpTerminology::PHYSICAL_FORM,
            'Supplier',
            'Purchase Price',
            'Selling Price',
            'Opening Qty',
            'Opening Batch No',
            'Opening Expiry Date',
            RmpTerminology::STORAGE_LOCATION,
            'Min Stock',
            RmpTerminology::STATUS,
            'Description',
            RmpTerminology::NOTES,
        ]));
        $writer->addRow(Row::fromValues([
            '# contoh',
            'IERP-0001',
            'Paracetamol 500mg',
            'Medicine',
            'PCS',
            'powder',
            'PT Sample Ingredient',
            '1200',
            '1500',
            '100',
            'OB-PARA-001',
            now()->addMonths(6)->format('Y-m-d'),
            'Rack A-01',
            '10',
            '1',
            'Tablet 10 strip',
            'Migrasi stok awal',
        ]));
        $writer->addRow(Row::fromValues([
            '# baris yang diawali # akan diabaikan saat import',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ]));
        $writer->close();

        return response()->download($filePath, 'template-import-stok-awal.xlsx')->deleteFileAfterSend(true);
    }
}
