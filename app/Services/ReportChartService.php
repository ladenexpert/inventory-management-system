<?php

namespace App\Services;

class ReportChartService
{
    public function line(string $label, array $series, string $color, string $valueFormat = 'number'): array
    {
        return [
            'type' => 'line',
            'valueFormat' => $valueFormat,
            'labels' => array_keys($series),
            'datasets' => [[
                'label' => $label,
                'data' => array_values($series),
                'borderColor' => $color,
                'backgroundColor' => $this->transparentize($color, 0.18),
                'fill' => true,
                'tension' => 0.28,
                'borderWidth' => 2,
                'pointRadius' => 2,
                'pointHoverRadius' => 4,
            ]],
        ];
    }

    public function bar(string $label, array $series, string $color, string $valueFormat = 'number'): array
    {
        return [
            'type' => 'bar',
            'valueFormat' => $valueFormat,
            'labels' => array_keys($series),
            'datasets' => [[
                'label' => $label,
                'data' => array_values($series),
                'backgroundColor' => $this->transparentize($color, 0.2),
                'borderColor' => $color,
                'borderWidth' => 1,
                'borderRadius' => 6,
                'maxBarThickness' => 42,
            ]],
        ];
    }

    private function transparentize(string $hexColor, float $alpha): string
    {
        $hex = ltrim($hexColor, '#');

        if (strlen($hex) !== 6) {
            return $hexColor;
        }

        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));

        return sprintf('rgba(%d, %d, %d, %.2f)', $red, $green, $blue, $alpha);
    }
}
