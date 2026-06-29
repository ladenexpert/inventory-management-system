<?php

namespace App\Support;

use App\Models\Batch;
use App\Models\User;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class TransactionContext
{
    public const GRAIN_HEADER = 'header';
    public const GRAIN_LINE = 'line';
    public const GRAIN_MOVEMENT = 'movement';
    public const GRAIN_ANALYTICS = 'analytics';

    public const MATERIAL_RECEIPT = 'material_receipt';
    public const LEGACY_PURCHASE = 'legacy_purchase';
    public const MATERIAL_USAGE = 'material_usage';
    public const USAGE_REPORT = 'usage_report';
    public const LEGACY_SALE = 'legacy_sale';
    public const INBOUND_PURCHASE_ANALYSIS = 'inbound_purchase_analysis';
    public const SALES_ANALYSIS = 'sales_analysis';
    public const OPENING_STOCK = 'opening_stock';
    public const STOCK_TAKE_ADJUSTMENT = 'stock_take_adjustment';
    public const STOCK_ADJUSTMENT = 'stock_adjustment';
    public const LEGACY_SYNC = 'legacy_sync';
    public const INVENTORY_MOVEMENT_HISTORY = 'inventory_movement_history';

    private const DEFINITIONS = [
        self::MATERIAL_RECEIPT => [
            'transaction_prefix' => 'MR',
            'ui_grain' => self::GRAIN_HEADER,
            'export_grain' => self::GRAIN_LINE,
            'line_export_source' => 'purchase_items',
            'routes' => ['material-receipts.index', 'material-receipts.show', 'material-receipts.edit'],
            'safe_route_name' => 'material-receipts.show',
            'route_permission_module' => 'material_receipt',
            'export_prefix' => 'material_receipt_lines',
            'labels' => [
                'title' => 'Material Receipt',
                'plural' => 'Material Receipts',
            ],
            'finance_policy' => 'inventory_value_optional',
            'dashboard_policy' => 'operational',
            'role_module' => 'material_receipt',
        ],
        self::LEGACY_PURCHASE => [
            'transaction_prefix' => 'PO',
            'ui_grain' => self::GRAIN_HEADER,
            'export_grain' => self::GRAIN_LINE,
            'line_export_source' => 'purchase_items',
            'routes' => ['purchases.index', 'purchases.show', 'purchases.edit'],
            'safe_route_name' => 'purchases.show',
            'route_permission_module' => 'legacy_purchase',
            'export_prefix' => 'legacy_purchase_lines',
            'labels' => [
                'title' => 'Legacy Purchase',
                'plural' => 'Legacy Purchases',
            ],
            'finance_policy' => 'finance_optional',
            'dashboard_policy' => 'operational',
            'role_module' => 'legacy_purchase',
        ],
        self::MATERIAL_USAGE => [
            'transaction_prefix' => 'MU',
            'ui_grain' => self::GRAIN_HEADER,
            'export_grain' => self::GRAIN_LINE,
            'line_export_source' => 'sale_item_batches',
            'routes' => ['material-usages.index', 'material-usages.show', 'material-usages.print'],
            'safe_route_name' => 'material-usages.show',
            'route_permission_module' => 'material_usage',
            'export_prefix' => 'material_usage_lines',
            'labels' => [
                'title' => 'Material Usage',
                'plural' => 'Material Usages',
            ],
            'finance_policy' => 'excluded',
            'dashboard_policy' => 'operational',
            'role_module' => 'material_usage',
        ],
        self::USAGE_REPORT => [
            'transaction_prefix' => 'MU',
            'ui_grain' => self::GRAIN_LINE,
            'export_grain' => self::GRAIN_LINE,
            'line_export_source' => 'sale_item_batches',
            'routes' => ['reports.usage-history'],
            'safe_route_name' => 'material-usages.show',
            'route_permission_module' => 'material_usage',
            'export_prefix' => 'usage_report',
            'labels' => [
                'title' => 'Usage Report',
                'plural' => 'Usage Report',
            ],
            'finance_policy' => 'excluded',
            'dashboard_policy' => 'hidden',
            'role_module' => 'reports',
        ],
        self::LEGACY_SALE => [
            'transaction_prefix' => 'INV',
            'ui_grain' => self::GRAIN_HEADER,
            'export_grain' => self::GRAIN_LINE,
            'line_export_source' => 'sale_item_batches',
            'routes' => ['sales.index', 'sales.show', 'sales.print'],
            'safe_route_name' => 'sales.show',
            'route_permission_module' => 'legacy_sales',
            'export_prefix' => 'legacy_sales_lines',
            'labels' => [
                'title' => 'Legacy Sale',
                'plural' => 'Legacy Sales',
            ],
            'finance_policy' => 'finance_optional',
            'dashboard_policy' => 'operational',
            'role_module' => 'legacy_sales',
        ],
        self::INBOUND_PURCHASE_ANALYSIS => [
            'transaction_prefix' => 'MR/PO',
            'ui_grain' => self::GRAIN_ANALYTICS,
            'export_grain' => self::GRAIN_ANALYTICS,
            'line_export_source' => null,
            'routes' => ['reports.purchase-analysis'],
            'safe_route_name' => 'reports.purchase-analysis',
            'route_permission_module' => 'legacy_purchase',
            'export_prefix' => 'inbound_purchase_analysis',
            'labels' => [
                'title' => 'Inbound & Purchase Analysis',
                'plural' => 'Inbound & Purchase Analysis',
            ],
            'finance_policy' => 'finance_optional',
            'dashboard_policy' => 'analytics',
            'role_module' => 'reports',
        ],
        self::SALES_ANALYSIS => [
            'transaction_prefix' => 'INV',
            'ui_grain' => self::GRAIN_ANALYTICS,
            'export_grain' => self::GRAIN_ANALYTICS,
            'line_export_source' => null,
            'routes' => ['reports.sales-analysis'],
            'safe_route_name' => 'reports.sales-analysis',
            'route_permission_module' => 'legacy_sales',
            'export_prefix' => 'sales_analysis',
            'labels' => [
                'title' => 'Sales Analysis',
                'plural' => 'Sales Analysis',
            ],
            'finance_policy' => 'finance_optional',
            'dashboard_policy' => 'analytics',
            'role_module' => 'reports',
        ],
        self::OPENING_STOCK => [
            'transaction_prefix' => null,
            'ui_grain' => self::GRAIN_MOVEMENT,
            'export_grain' => self::GRAIN_MOVEMENT,
            'line_export_source' => null,
            'routes' => ['products.import-opening-stock', 'products.import-opening-stock.store', 'products.import-opening-stock.template'],
            'safe_route_name' => 'reports.inventory-movement-history',
            'route_permission_module' => 'reports',
            'export_prefix' => 'opening_stock_import',
            'labels' => [
                'title' => 'Opening Stock',
                'plural' => 'Opening Stock',
            ],
            'finance_policy' => 'excluded',
            'dashboard_policy' => 'operational',
            'role_module' => 'opening_stock',
        ],
        self::STOCK_TAKE_ADJUSTMENT => [
            'transaction_prefix' => 'STK',
            'ui_grain' => self::GRAIN_MOVEMENT,
            'export_grain' => self::GRAIN_MOVEMENT,
            'line_export_source' => null,
            'routes' => [
                'stock-take.index',
                'stock-take.preview',
                'stock-take.show',
                'stock-take.recalculate',
                'stock-take.apply',
                'stock-take.close',
                'stock-take.export',
                'stock-take.template',
            ],
            'safe_route_name' => 'reports.inventory-movement-history',
            'route_permission_module' => 'reports',
            'export_prefix' => 'stock_take_adjustments',
            'labels' => [
                'title' => 'Stock Take Adjustment',
                'plural' => 'Stock Take Adjustments',
            ],
            'finance_policy' => 'excluded',
            'dashboard_policy' => 'operational',
            'role_module' => 'stock_take',
        ],
        self::STOCK_ADJUSTMENT => [
            'transaction_prefix' => 'ADJ',
            'ui_grain' => self::GRAIN_MOVEMENT,
            'export_grain' => self::GRAIN_MOVEMENT,
            'line_export_source' => null,
            'routes' => ['products.index'],
            'safe_route_name' => 'reports.inventory-movement-history',
            'route_permission_module' => 'reports',
            'export_prefix' => 'stock_adjustments',
            'labels' => [
                'title' => 'Stock Adjustment',
                'plural' => 'Stock Adjustments',
            ],
            'finance_policy' => 'excluded',
            'dashboard_policy' => 'operational',
            'role_module' => 'materials',
        ],
        self::LEGACY_SYNC => [
            'transaction_prefix' => null,
            'ui_grain' => self::GRAIN_MOVEMENT,
            'export_grain' => self::GRAIN_MOVEMENT,
            'line_export_source' => null,
            'routes' => ['reports.inventory-movement-history'],
            'safe_route_name' => 'reports.inventory-movement-history',
            'route_permission_module' => 'reports',
            'export_prefix' => 'legacy_sync',
            'labels' => [
                'title' => 'Legacy Sync',
                'plural' => 'Legacy Sync',
            ],
            'finance_policy' => 'excluded',
            'dashboard_policy' => 'operational',
            'role_module' => 'materials',
        ],
        self::INVENTORY_MOVEMENT_HISTORY => [
            'transaction_prefix' => null,
            'ui_grain' => self::GRAIN_MOVEMENT,
            'export_grain' => self::GRAIN_MOVEMENT,
            'line_export_source' => null,
            'routes' => ['reports.inventory-movement-history', 'reports.inventory-movement-history.export'],
            'safe_route_name' => 'reports.inventory-movement-history',
            'route_permission_module' => 'reports',
            'export_prefix' => 'inventory_movement_history',
            'labels' => [
                'title' => 'Inventory Movement History',
                'plural' => 'Inventory Movement History',
            ],
            'finance_policy' => 'excluded',
            'dashboard_policy' => 'hidden',
            'role_module' => 'reports',
        ],
    ];

    public static function definition(string $context): array
    {
        $definition = self::DEFINITIONS[$context]
            ?? throw new InvalidArgumentException("Unsupported transaction context [{$context}].");

        return $definition + [
            'row_grain' => $definition['ui_grain'],
            'finance_relevant' => $definition['finance_policy'] !== 'excluded',
            'dashboard_relevant' => $definition['dashboard_policy'] !== 'hidden',
        ];
    }

    public static function exportFilename(string $context, string $extension): string
    {
        $baseName = self::definition($context)['export_prefix'];

        return "{$baseName}_" . now()->format('Y_m_d') . ".{$extension}";
    }

    public static function applyPurchaseContext(Builder $query, string $context): Builder
    {
        return match ($context) {
            self::MATERIAL_RECEIPT => $query->where(function (Builder $purchaseQuery) {
                $purchaseQuery
                    ->where('entry_context', self::MATERIAL_RECEIPT)
                    ->orWhere(function (Builder $fallbackQuery) {
                        $fallbackQuery
                            ->whereNull('entry_context')
                            ->where('transaction_code', 'like', 'MR.%');
                    });
            }),
            self::LEGACY_PURCHASE => $query->where(function (Builder $purchaseQuery) {
                $purchaseQuery
                    ->where('entry_context', self::LEGACY_PURCHASE)
                    ->orWhere(function (Builder $fallbackQuery) {
                        $fallbackQuery
                            ->whereNull('entry_context')
                            ->where(function (Builder $legacyQuery) {
                                $legacyQuery
                                    ->whereNull('transaction_code')
                                    ->orWhere('transaction_code', 'not like', 'MR.%');
                            });
                    });
            }),
            self::INBOUND_PURCHASE_ANALYSIS => $query->where(function (Builder $purchaseQuery) {
                $purchaseQuery
                    ->where('entry_context', self::MATERIAL_RECEIPT)
                    ->orWhere('entry_context', self::LEGACY_PURCHASE)
                    ->orWhere(function (Builder $fallbackQuery) {
                        $fallbackQuery
                            ->whereNull('entry_context')
                            ->where(function (Builder $legacyQuery) {
                                $legacyQuery
                                    ->whereNull('transaction_code')
                                    ->orWhere('transaction_code', 'like', 'MR.%')
                                    ->orWhere('transaction_code', 'like', 'PO.%');
                            });
                    });
            }),
            default => throw new InvalidArgumentException("Unsupported purchase context [{$context}]."),
        };
    }

    public static function applySaleContext(Builder $query, string $context): Builder
    {
        return match ($context) {
            self::MATERIAL_USAGE => $query->where(function (Builder $saleQuery) {
                $saleQuery
                    ->where('transaction_type', self::MATERIAL_USAGE)
                    ->orWhere(function (Builder $fallbackQuery) {
                        $fallbackQuery
                            ->whereNull('transaction_type')
                            ->where('transaction_code', 'like', 'MU.%');
                    });
            }),
            self::LEGACY_SALE, self::SALES_ANALYSIS => $query->where(function (Builder $saleQuery) {
                $saleQuery
                    ->where('transaction_type', 'sale')
                    ->orWhere(function (Builder $fallbackQuery) {
                        $fallbackQuery
                            ->whereNull('transaction_type')
                            ->where(function (Builder $legacyQuery) {
                                $legacyQuery
                                    ->whereNull('transaction_code')
                                    ->orWhere('transaction_code', 'not like', 'MU.%');
                            });
                    });
            }),
            default => throw new InvalidArgumentException("Unsupported sale context [{$context}]."),
        };
    }

    public static function resolvePurchaseContext(Purchase $purchase): string
    {
        if ($purchase->entry_context === self::MATERIAL_RECEIPT) {
            return self::MATERIAL_RECEIPT;
        }

        if ($purchase->entry_context === self::LEGACY_PURCHASE) {
            return self::LEGACY_PURCHASE;
        }

        if (is_string($purchase->transaction_code) && str_starts_with($purchase->transaction_code, 'MR.')) {
            return self::MATERIAL_RECEIPT;
        }

        return self::LEGACY_PURCHASE;
    }

    public static function resolveSaleContext(Sale $sale): string
    {
        if ($sale->transaction_type?->value === self::MATERIAL_USAGE) {
            return self::MATERIAL_USAGE;
        }

        if ($sale->transaction_type?->value === 'sale') {
            return self::LEGACY_SALE;
        }

        if (is_string($sale->transaction_code) && str_starts_with($sale->transaction_code, 'MU.')) {
            return self::MATERIAL_USAGE;
        }

        return self::LEGACY_SALE;
    }

    public static function resolveContextFromTransactionNumber(?string $transactionNumber): ?string
    {
        if (!is_string($transactionNumber) || trim($transactionNumber) === '' || trim($transactionNumber) === '-') {
            return null;
        }

        $prefix = strtoupper((string) strtok(trim($transactionNumber), '.'));

        return match ($prefix) {
            'MR' => self::MATERIAL_RECEIPT,
            'PO' => self::LEGACY_PURCHASE,
            'MU' => self::MATERIAL_USAGE,
            'INV' => self::LEGACY_SALE,
            'ADJ' => self::STOCK_ADJUSTMENT,
            'STK' => self::STOCK_TAKE_ADJUSTMENT,
            default => null,
        };
    }

    public static function resolveBatchContext(Batch $batch): ?string
    {
        $purchase = $batch->relationLoaded('purchase')
            ? $batch->purchase
            : ($batch->purchase_id ? $batch->purchase()->first() : null);

        if ($purchase) {
            return self::resolvePurchaseContext($purchase);
        }

        $sourceTransactionNumber = self::batchSourceTransactionNumber($batch);
        $transactionContext = self::resolveContextFromTransactionNumber($sourceTransactionNumber);

        if ($transactionContext !== null) {
            return $transactionContext;
        }

        return match ($batch->source) {
            'opening_balance' => self::OPENING_STOCK,
            'adjustment_in' => self::STOCK_ADJUSTMENT,
            'legacy_sync' => self::LEGACY_SYNC,
            default => null,
        };
    }

    public static function resolveBatchTransactionLink(Batch $batch, ?User $user = null): ?array
    {
        $context = self::resolveBatchContext($batch);

        if ($context === null) {
            return null;
        }

        return match ($context) {
            self::MATERIAL_RECEIPT,
            self::LEGACY_PURCHASE => self::resolvePurchaseBatchRoute($batch, $user, $context),
            self::MATERIAL_USAGE,
            self::LEGACY_SALE => self::resolveSaleBatchRoute($batch, $user, $context),
            self::OPENING_STOCK,
            self::STOCK_ADJUSTMENT,
            self::STOCK_TAKE_ADJUSTMENT,
            self::LEGACY_SYNC => self::resolveInventoryMovementRoute($batch, $user, $context),
            default => null,
        };
    }

    public static function label(string $context): string
    {
        return self::definition($context)['labels']['title'];
    }

    public static function labelForBatchSource(?string $source, ?string $transactionNumber = null, ?string $entryContext = null): string
    {
        $context = $entryContext ?: self::resolveContextFromTransactionNumber($transactionNumber);

        if ($context !== null && in_array($context, [self::MATERIAL_RECEIPT, self::LEGACY_PURCHASE, self::MATERIAL_USAGE, self::LEGACY_SALE], true)) {
            return self::label($context);
        }

        return match ($source) {
            'purchase' => 'Inbound Receipt',
            'opening_balance' => self::label(self::OPENING_STOCK),
            'adjustment_in' => self::label(self::STOCK_ADJUSTMENT),
            'legacy_sync' => self::label(self::LEGACY_SYNC),
            'sale_cancel_restore' => 'Cancellation / Restore',
            'quarantined' => 'Quarantined',
            null, '' => '-',
            default => str($source)->headline()->toString(),
        };
    }

    public static function labelForMovementType(?string $movementType): string
    {
        return match ($movementType) {
            'purchase_receive' => 'Purchase / Material Receipt',
            'opening_balance' => self::label(self::OPENING_STOCK),
            'adjustment_in' => 'Manual Stock Adjustment In',
            'adjustment_out' => 'Manual Stock Adjustment Out',
            'stock_take_adjustment_in' => 'Stock Take Adjustment In',
            'stock_take_adjustment_out' => 'Stock Take Adjustment Out',
            'sale_out' => self::label(self::MATERIAL_USAGE),
            'sale_cancel_restore' => 'Cancellation / Restore',
            'sale_restore_out' => 'Restore Reservation',
            'legacy_sync' => 'Legacy Sync',
            'quarantined' => 'Quarantined',
            null, '' => '-',
            default => str($movementType)->headline()->toString(),
        };
    }

    public static function labelForInventoryAdjustmentType(?string $adjustmentType): string
    {
        return match ($adjustmentType) {
            'stock_take_import' => self::label(self::STOCK_TAKE_ADJUSTMENT),
            'manual_stock_adjustment' => self::label(self::STOCK_ADJUSTMENT),
            null, '' => '-',
            default => str($adjustmentType)->headline()->toString(),
        };
    }

    private static function batchSourceTransactionNumber(Batch $batch): ?string
    {
        $transactionNumber = $batch->getAttribute('source_transaction_number')
            ?? $batch->getAttribute('source_transaction_code')
            ?? $batch->getAttribute('transaction_code');

        return is_string($transactionNumber) && trim($transactionNumber) !== ''
            ? $transactionNumber
            : null;
    }

    private static function resolvePurchaseBatchRoute(Batch $batch, ?User $user, string $context): ?array
    {
        if (!$batch->purchase_id || !self::userCan($user, self::definition($context)['route_permission_module'], 'view')) {
            return null;
        }

        return [
            'context' => $context,
            'label' => self::label($context),
            'route' => self::definition($context)['safe_route_name'],
            'parameters' => ['purchase' => $batch->purchase_id],
            'tooltip' => 'View ' . strtolower(self::label($context)),
        ];
    }

    private static function resolveSaleBatchRoute(Batch $batch, ?User $user, string $context): ?array
    {
        $saleId = $batch->getAttribute('source_sale_id');

        if (!$saleId || !self::userCan($user, self::definition($context)['route_permission_module'], 'view')) {
            return null;
        }

        return [
            'context' => $context,
            'label' => self::label($context),
            'route' => self::definition($context)['safe_route_name'],
            'parameters' => ['sale' => $saleId],
            'tooltip' => 'View ' . strtolower(self::label($context)),
        ];
    }

    private static function resolveInventoryMovementRoute(Batch $batch, ?User $user, string $context): ?array
    {
        if (!self::userCan($user, self::definition($context)['route_permission_module'], 'view')) {
            return null;
        }

        $transactionType = match ($context) {
            self::OPENING_STOCK => 'opening_balance',
            self::STOCK_ADJUSTMENT => 'adjustment_in',
            self::STOCK_TAKE_ADJUSTMENT => 'stock_take_adjustment_in',
            self::LEGACY_SYNC => 'legacy_sync',
            default => null,
        };

        return [
            'context' => $context,
            'label' => self::label($context),
            'route' => self::definition($context)['safe_route_name'],
            'parameters' => array_filter([
                'lot_number' => $batch->batch_number ?: null,
                'transaction_type' => $transactionType,
            ], static fn ($value) => $value !== null && $value !== ''),
            'tooltip' => 'View movement history',
        ];
    }

    private static function userCan(?User $user, string $module, string $action): bool
    {
        return $user?->hasPermission($module, $action) ?? false;
    }
}
