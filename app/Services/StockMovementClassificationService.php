<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Enums\SaleTransactionType;
use App\Models\Batch;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockMovementClassificationService
{
    public const FAST_MOVING = 'fast_moving';
    public const SLOW_MOVING = 'slow_moving';
    public const DEAD_STOCK = 'dead_stock';
    public const NORMAL_UNCLASSIFIED = 'normal_unclassified';
    public const NO_USAGE_UNCLASSIFIED = 'no_usage_unclassified';

    public function __construct(
        protected BatchPolicyService $batchPolicyService,
    ) {
    }

    public function query(): Builder
    {
        $today = now()->startOfDay();
        $cutoff90 = $today->copy()->subDays(90)->toDateString();
        $cutoff180 = $today->copy()->subDays(180)->toDateString();
        $cutoff365 = $today->copy()->subDays(365)->toDateString();

        $usageMetrics = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->selectRaw('sale_items.product_id')
            ->selectRaw('MAX(DATE(COALESCE(sales.usage_date, sales.sale_date))) as last_usage_date')
            ->selectRaw(
                'SUM(CASE WHEN DATE(COALESCE(sales.usage_date, sales.sale_date)) >= ? THEN sale_items.quantity ELSE 0 END) as usage_qty_90_days',
                [$cutoff90]
            )
            ->selectRaw(
                'SUM(CASE WHEN DATE(COALESCE(sales.usage_date, sales.sale_date)) >= ? THEN sale_items.quantity ELSE 0 END) as usage_qty_180_days',
                [$cutoff180]
            )
            ->selectRaw(
                'SUM(CASE WHEN DATE(COALESCE(sales.usage_date, sales.sale_date)) >= ? THEN sale_items.quantity ELSE 0 END) as usage_qty_365_days',
                [$cutoff365]
            )
            ->where('sales.transaction_type', SaleTransactionType::MATERIAL_USAGE->value)
            ->where('sales.status', '!=', SaleStatus::CANCELLED->value)
            ->groupBy('sale_items.product_id');

        $batchMetrics = DB::table('batches')
            ->leftJoin('storage_locations', 'storage_locations.id', '=', 'batches.storage_location_id')
            ->selectRaw('batches.product_id')
            ->selectRaw('SUM(CASE WHEN batches.available_quantity > 0 THEN 1 ELSE 0 END) as batch_count')
            ->selectRaw('MIN(CASE WHEN batches.available_quantity > 0 THEN DATE(COALESCE(batches.received_at, batches.created_at)) END) as first_stock_date')
            ->selectRaw('MIN(CASE WHEN batches.available_quantity > 0 AND batches.expiry_date IS NOT NULL THEN DATE(batches.expiry_date) END) as earliest_expiry_date')
            ->selectRaw('SUM(CASE WHEN batches.available_quantity > 0 THEN batches.available_quantity * batches.unit_cost ELSE 0 END) as inventory_value')
            ->selectRaw('GROUP_CONCAT(DISTINCT COALESCE(storage_locations.name, batches.storage_location)) as storage_location_summary')
            ->groupBy('batches.product_id');

        return Product::query()
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->leftJoinSub($usageMetrics, 'usage_metrics', fn ($join) => $join->on('usage_metrics.product_id', '=', 'products.id'))
            ->leftJoinSub($batchMetrics, 'batch_metrics', fn ($join) => $join->on('batch_metrics.product_id', '=', 'products.id'))
            ->where('products.is_active', true)
            ->where('products.quantity', '>', 0)
            ->selectRaw('products.id')
            ->selectRaw('products.sku')
            ->selectRaw('products.item_code_ierp')
            ->selectRaw('products.name as product_name')
            ->selectRaw('products.physical_form')
            ->selectRaw('products.quantity as stock_available')
            ->selectRaw('COALESCE(units.symbol, units.name, ?) as unit', ['-'])
            ->selectRaw('usage_metrics.last_usage_date')
            ->selectRaw('COALESCE(usage_metrics.usage_qty_90_days, 0) as usage_qty_90_days')
            ->selectRaw('COALESCE(usage_metrics.usage_qty_180_days, 0) as usage_qty_180_days')
            ->selectRaw('COALESCE(usage_metrics.usage_qty_365_days, 0) as usage_qty_365_days')
            ->selectRaw('COALESCE(batch_metrics.batch_count, 0) as batch_count')
            ->selectRaw('batch_metrics.first_stock_date')
            ->selectRaw('batch_metrics.earliest_expiry_date')
            ->selectRaw('COALESCE(batch_metrics.inventory_value, 0) as inventory_value')
            ->selectRaw('batch_metrics.storage_location_summary')
            ->selectRaw('COALESCE(usage_metrics.last_usage_date, batch_metrics.first_stock_date) as movement_basis_date')
            ->selectRaw(
                "CASE
                    WHEN usage_metrics.last_usage_date IS NOT NULL AND DATE(usage_metrics.last_usage_date) < ? THEN ?
                    WHEN usage_metrics.last_usage_date IS NULL AND batch_metrics.first_stock_date IS NOT NULL AND DATE(batch_metrics.first_stock_date) < ? THEN ?
                    WHEN usage_metrics.last_usage_date IS NOT NULL AND DATE(usage_metrics.last_usage_date) < ? THEN ?
                    WHEN usage_metrics.last_usage_date IS NULL AND batch_metrics.first_stock_date IS NOT NULL AND DATE(batch_metrics.first_stock_date) < ? THEN ?
                    WHEN usage_metrics.last_usage_date IS NOT NULL AND DATE(usage_metrics.last_usage_date) >= ? THEN ?
                    WHEN usage_metrics.last_usage_date IS NULL AND batch_metrics.first_stock_date IS NULL THEN ?
                    ELSE ?
                END as classification",
                [
                    $cutoff365,
                    self::DEAD_STOCK,
                    $cutoff365,
                    self::DEAD_STOCK,
                    $cutoff180,
                    self::SLOW_MOVING,
                    $cutoff180,
                    self::SLOW_MOVING,
                    $cutoff90,
                    self::FAST_MOVING,
                    self::NO_USAGE_UNCLASSIFIED,
                    self::NORMAL_UNCLASSIFIED,
                ]
            )
            ->selectRaw(
                "CASE
                    WHEN usage_metrics.last_usage_date IS NOT NULL AND DATE(usage_metrics.last_usage_date) < ? THEN 1
                    WHEN usage_metrics.last_usage_date IS NULL AND batch_metrics.first_stock_date IS NOT NULL AND DATE(batch_metrics.first_stock_date) < ? THEN 1
                    WHEN usage_metrics.last_usage_date IS NOT NULL AND DATE(usage_metrics.last_usage_date) < ? THEN 2
                    WHEN usage_metrics.last_usage_date IS NULL AND batch_metrics.first_stock_date IS NOT NULL AND DATE(batch_metrics.first_stock_date) < ? THEN 2
                    WHEN usage_metrics.last_usage_date IS NOT NULL AND DATE(usage_metrics.last_usage_date) >= ? THEN 3
                    WHEN usage_metrics.last_usage_date IS NULL AND batch_metrics.first_stock_date IS NULL THEN 5
                    ELSE 4
                END as classification_rank",
                [$cutoff365, $cutoff365, $cutoff180, $cutoff180, $cutoff90]
            );
    }

    public function records(): Collection
    {
        return $this->query()
            ->orderBy('classification_rank')
            ->orderByDesc('stock_available')
            ->orderBy('products.name')
            ->get()
            ->map(fn (object $record) => $this->normalizeRecord($record));
    }

    public function summary(): array
    {
        $records = $this->records();

        return [
            'fast_moving' => $records->where('classification', self::FAST_MOVING)->count(),
            'slow_moving' => $records->where('classification', self::SLOW_MOVING)->count(),
            'dead_stock' => $records->where('classification', self::DEAD_STOCK)->count(),
            'normal_unclassified' => $records->where('classification', self::NORMAL_UNCLASSIFIED)->count(),
            'no_usage_unclassified' => $records->where('classification', self::NO_USAGE_UNCLASSIFIED)->count(),
        ];
    }

    public function chartSummary(): array
    {
        $summary = $this->summary();

        $series = [
            'Fast Moving' => $summary['fast_moving'],
            'Slow Moving' => $summary['slow_moving'],
            'Dead Stock' => $summary['dead_stock'],
        ];

        if ($summary['normal_unclassified'] > 0 || $summary['no_usage_unclassified'] > 0) {
            $series['Normal / Unclassified'] = $summary['normal_unclassified'] + $summary['no_usage_unclassified'];
        }

        return $series;
    }

    public function topByClassification(string $classification, int $limit = 5): array
    {
        return $this->records()
            ->where('classification', $classification)
            ->take($limit)
            ->map(function (array $record) use ($classification) {
                return [
                    'product_name' => $record['product_name'],
                    'item_code' => $record['item_code'],
                    'quantity' => $record['stock_available'],
                    'total_quantity' => match ($classification) {
                        self::FAST_MOVING => $record['usage_qty_90_days'],
                        self::SLOW_MOVING => $record['usage_qty_180_days'],
                        default => $record['usage_qty_365_days'],
                    },
                    'days_since_last_usage' => $record['days_since_last_usage'],
                ];
            })
            ->values()
            ->all();
    }

    public function classificationOptions(): array
    {
        return [
            self::FAST_MOVING => 'Fast Moving',
            self::SLOW_MOVING => 'Slow Moving',
            self::DEAD_STOCK => 'Dead Stock',
            self::NORMAL_UNCLASSIFIED => 'Normal / Unclassified',
            self::NO_USAGE_UNCLASSIFIED => 'No Usage / Unclassified',
        ];
    }

    public function classificationLabel(?string $classification): string
    {
        return $this->classificationOptions()[$classification ?? ''] ?? 'Normal / Unclassified';
    }

    public function physicalFormLabel(?string $physicalForm): string
    {
        return Product::physicalFormOptions()[$physicalForm ?? ''] ?? 'Unspecified';
    }

    public function applyClassificationFilter(Builder $query, string $classification): Builder
    {
        $today = now()->startOfDay();
        $cutoff90 = $today->copy()->subDays(90)->toDateString();
        $cutoff180 = $today->copy()->subDays(180)->toDateString();
        $cutoff365 = $today->copy()->subDays(365)->toDateString();

        return match ($classification) {
            self::FAST_MOVING => $query
                ->whereNotNull('usage_metrics.last_usage_date')
                ->whereDate('usage_metrics.last_usage_date', '>=', $cutoff90),
            self::SLOW_MOVING => $query->where(function (Builder $builder) use ($cutoff180, $cutoff365) {
                $builder
                    ->where(function (Builder $usage) use ($cutoff180, $cutoff365) {
                        $usage
                            ->whereNotNull('usage_metrics.last_usage_date')
                            ->whereDate('usage_metrics.last_usage_date', '<', $cutoff180)
                            ->whereDate('usage_metrics.last_usage_date', '>=', $cutoff365);
                    })
                    ->orWhere(function (Builder $noUsage) use ($cutoff180, $cutoff365) {
                        $noUsage
                            ->whereNull('usage_metrics.last_usage_date')
                            ->whereNotNull('batch_metrics.first_stock_date')
                            ->whereDate('batch_metrics.first_stock_date', '<', $cutoff180)
                            ->whereDate('batch_metrics.first_stock_date', '>=', $cutoff365);
                    });
            }),
            self::DEAD_STOCK => $query->where(function (Builder $builder) use ($cutoff365) {
                $builder
                    ->where(function (Builder $usage) use ($cutoff365) {
                        $usage
                            ->whereNotNull('usage_metrics.last_usage_date')
                            ->whereDate('usage_metrics.last_usage_date', '<', $cutoff365);
                    })
                    ->orWhere(function (Builder $noUsage) use ($cutoff365) {
                        $noUsage
                            ->whereNull('usage_metrics.last_usage_date')
                            ->whereNotNull('batch_metrics.first_stock_date')
                            ->whereDate('batch_metrics.first_stock_date', '<', $cutoff365);
                    });
            }),
            self::NO_USAGE_UNCLASSIFIED => $query
                ->whereNull('usage_metrics.last_usage_date')
                ->whereNull('batch_metrics.first_stock_date'),
            self::NORMAL_UNCLASSIFIED => $query->where(function (Builder $builder) use ($cutoff90, $cutoff180) {
                $builder
                    ->where(function (Builder $usage) use ($cutoff90, $cutoff180) {
                        $usage
                            ->whereNotNull('usage_metrics.last_usage_date')
                            ->whereDate('usage_metrics.last_usage_date', '<', $cutoff90)
                            ->whereDate('usage_metrics.last_usage_date', '>=', $cutoff180);
                    })
                    ->orWhere(function (Builder $noUsage) use ($cutoff180) {
                        $noUsage
                            ->whereNull('usage_metrics.last_usage_date')
                            ->where(function (Builder $basis) use ($cutoff180) {
                                $basis
                                    ->whereNull('batch_metrics.first_stock_date')
                                    ->orWhereDate('batch_metrics.first_stock_date', '>=', $cutoff180);
                            });
                    });
            }),
            default => $query,
        };
    }

    public function lifecycleStatusLabel(?string $earliestExpiryDate): string
    {
        if (!$earliestExpiryDate) {
            return 'No expiry';
        }

        $batch = new Batch([
            'available_quantity' => 1,
            'source' => 'purchase',
            'expiry_date' => Carbon::parse($earliestExpiryDate),
        ]);

        return $this->batchPolicyService->getStatus($batch)->label();
    }

    public function daysSinceMovement(?string $movementBasisDate): ?int
    {
        if (!$movementBasisDate) {
            return null;
        }

        return Carbon::parse($movementBasisDate)->startOfDay()->diffInDays(now()->startOfDay());
    }

    public function normalizeRecord(object $record): array
    {
        $daysSinceMovement = $this->daysSinceMovement($record->movement_basis_date);

        return [
            'id' => (int) $record->id,
            'sku' => $record->sku ?: '-',
            'item_code' => $record->item_code_ierp ?: '-',
            'product_name' => $record->product_name,
            'physical_form' => $record->physical_form,
            'physical_form_label' => $this->physicalFormLabel($record->physical_form),
            'stock_available' => (int) $record->stock_available,
            'unit' => $record->unit ?: '-',
            'last_usage_date' => $record->last_usage_date,
            'movement_basis_date' => $record->movement_basis_date,
            'days_since_last_usage' => $daysSinceMovement,
            'usage_qty_90_days' => (int) $record->usage_qty_90_days,
            'usage_qty_180_days' => (int) $record->usage_qty_180_days,
            'usage_qty_365_days' => (int) $record->usage_qty_365_days,
            'batch_count' => (int) $record->batch_count,
            'earliest_expiry_date' => $record->earliest_expiry_date,
            'storage_location_summary' => $record->storage_location_summary
                ? implode(', ', array_filter(array_map('trim', explode(',', $record->storage_location_summary))))
                : '-',
            'inventory_value' => (int) $record->inventory_value,
            'classification' => $record->classification,
            'classification_label' => $this->classificationLabel($record->classification),
            'classification_rank' => (int) $record->classification_rank,
            'status_label' => $this->lifecycleStatusLabel($record->earliest_expiry_date),
        ];
    }
}
