<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

trait ReadsDownloadedSpreadsheet
{
    protected function downloadedSpreadsheetRows(TestResponse $response): array
    {
        $file = $response->baseResponse->getFile();

        $this->assertNotNull($file);

        $reader = ReaderFactory::createFromFileByMimeType($file->getPathname());
        $reader->open($file->getPathname());

        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }
}
