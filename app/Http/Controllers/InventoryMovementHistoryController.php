<?php

namespace App\Http\Controllers;

use App\Models\InventoryLog;
use App\Models\User;
use App\Services\InventoryMovementHistoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventoryMovementHistoryController extends Controller
{
    public function __construct(
        protected InventoryMovementHistoryService $service
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $rows = $this->service
            ->paginate($filters)
            ->through(fn ($log) => $this->service->mapRow($log));

        return view('reports.inventory-movement-history', [
            'filters' => $filters,
            'rows' => $rows,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'transactionTypes' => InventoryLog::movementTypeOptions(),
        ]);
    }

    public function export(Request $request, string $format): BinaryFileResponse
    {
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);

        $directory = storage_path('app/temp');

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $extension = $format === 'csv' ? 'csv' : 'xlsx';
        $filePath = $directory . DIRECTORY_SEPARATOR . 'inventory-movement-history-' . Str::random(8) . '.' . $extension;
        $writer = $format === 'csv' ? new CsvWriter() : new XlsxWriter();

        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues([
            'Date & Time',
            'User',
            'Transaction Type',
            'Material / Product Name',
            'SKU',
            'Item Code IERP',
            'Lot Number',
            'Expiry Date',
            'Storage Location',
            'Quantity',
            'Unit',
            'Remaining Stock',
            'Reference',
            'Notes',
        ]));

        foreach ($this->service->exportRows($this->filters($request)) as $row) {
            $writer->addRow(Row::fromValues([
                $row['date_time'],
                $row['user'],
                $row['transaction_type'],
                $row['material_name'],
                $row['sku'],
                $row['item_code_ierp'],
                $row['lot_number'],
                $row['expiry_date'],
                $row['storage_location'],
                $row['quantity'],
                $row['unit'],
                $row['remaining_stock'],
                $row['reference'],
                $row['notes'],
            ]));
        }

        $writer->close();

        return response()
            ->download($filePath, 'inventory-movement-history.' . $extension)
            ->deleteFileAfterSend(true);
    }

    private function filters(Request $request): array
    {
        return $request->only([
            'from_date',
            'to_date',
            'user_id',
            'transaction_type',
            'rm_code',
            'rm_name',
            'lot_number',
        ]);
    }
}
