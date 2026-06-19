<?php

namespace App\Http\Controllers;

use App\Services\MasterDataImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MasterDataImportController extends Controller
{
    public function __construct(
        protected MasterDataImportService $importService,
    ) {
    }

    public function show(string $resource): View
    {
        return view('master-imports.show', [
            'resource' => $resource,
            'definition' => $this->importService->definition($resource),
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $definition = $this->importService->definition($resource);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,ods', 'max:20480'],
        ]);

        try {
            $result = $this->importService->importFromFile($resource, $request->file('file')->getRealPath());
            $label = Str::lower($definition['label']);

            if ($result['failed_rows'] > 0 && $result['created_rows'] > 0) {
                return back()
                    ->with('warning', "Import {$label} completed with issues. Created: {$result['created_rows']}, failed: {$result['failed_rows']}.")
                    ->with('import_report', $result);
            }

            if ($result['failed_rows'] > 0) {
                return back()
                    ->with('error', "Import {$label} failed. Failed rows: {$result['failed_rows']}.")
                    ->with('import_report', $result);
            }

            return back()
                ->with('success', "Import {$label} completed. Created rows: {$result['created_rows']}.")
                ->with('import_report', $result);
        } catch (\Throwable $e) {
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function downloadTemplate(string $resource): BinaryFileResponse
    {
        $definition = $this->importService->definition($resource);
        $filePath = $this->importService->buildTemplateFile($resource);
        $downloadName = 'template-' . Str::slug($definition['label']) . '.xlsx';

        return response()->download($filePath, $downloadName)->deleteFileAfterSend(true);
    }
}
