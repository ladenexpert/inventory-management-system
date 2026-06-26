<?php

namespace Tests\Unit;

use App\Services\ReportChartService;
use Tests\TestCase;

class ReportChartServiceTest extends TestCase
{
    public function test_line_chart_payload_preserves_labels_series_and_value_format(): void
    {
        $service = app(ReportChartService::class);

        $chart = $service->line('Inbound Units', [
            '2026-06-01' => 4,
            '2026-06-02' => 7,
        ], '#0f766e');

        $this->assertSame('line', $chart['type']);
        $this->assertSame('number', $chart['valueFormat']);
        $this->assertSame(['2026-06-01', '2026-06-02'], $chart['labels']);
        $this->assertSame([4, 7], $chart['datasets'][0]['data']);
        $this->assertSame('Inbound Units', $chart['datasets'][0]['label']);
    }

    public function test_bar_chart_payload_supports_currency_format(): void
    {
        $service = app(ReportChartService::class);

        $chart = $service->bar('Purchase Total', [
            'Fast Moving' => 2,
            'Slow Moving' => 1,
        ], '#b45309', 'currency');

        $this->assertSame('bar', $chart['type']);
        $this->assertSame('currency', $chart['valueFormat']);
        $this->assertSame([2, 1], $chart['datasets'][0]['data']);
    }
}
