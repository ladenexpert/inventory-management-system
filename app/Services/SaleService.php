<?php

namespace App\Services;

use Exception;
use App\Models\Sale;
use App\DTOs\SaleData;
use App\Models\Product;
use App\Models\SaleItem;
use App\Enums\SaleStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleTransactionType;
use App\Exceptions\SaleException;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class SaleService
{
    public function __construct(
        protected FinanceTransactionService $financeService,
        protected BatchService $batchService
    ) {
    }

    /**
     * Create a new sale with items and reserve stock immediately.
     *
     * Pending sales still reserve stock. Completion only controls finance income creation.
     */
    public function createSale(SaleData $data): Sale
    {
        $maxAttempts = 5;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return DB::transaction(function () use ($data) {
                    try {
                        // Lock products for update
                        $productIds = collect($data->items)->pluck('product_id')->sort()->values()->all();

                        $products = Product::whereIn('id', $productIds)
                            ->lockForUpdate()
                            ->get()
                            ->keyBy('id');

                        $sale = Sale::create([
                            'invoice_number' => $this->generateReferenceNumber($data->transaction_type),
                            'transaction_type' => $data->transaction_type,
                            'customer_id' => $data->customer_id,
                            'created_by' => $data->created_by,
                            'sale_date' => $data->sale_date,
                            'usage_date' => $data->usage_date,
                            'status' => $data->status,
                            'payment_method' => $data->payment_method,
                            'purpose' => $data->purpose,
                            'formula' => $data->formula,
                            'project' => $data->project,
                            'requested_by' => $data->requested_by,
                            'issued_by' => $data->issued_by ?? $data->created_by,
                            'notes' => $data->notes,
                            'cash_received' => $data->cash_received,
                            'change' => $data->change,
                            'subtotal' => 0,
                            'global_discount' => $data->global_discount,
                            'total_discount' => 0,
                            'total' => 0,
                        ]);

                        $totalSubtotal = 0;
                        $totalDiscount = 0;
                        $isMaterialUsage = $data->transaction_type === SaleTransactionType::MATERIAL_USAGE;

                        $this->batchService->withinStockMutationScope(function () use ($data, $products, $sale, $isMaterialUsage, &$totalSubtotal, &$totalDiscount) {
                            foreach ($data->items as $itemData) {
                                $product = $products->get($itemData->product_id);

                                if (!$product) {
                                    throw SaleException::productNotFound($itemData->product_id);
                                }

                                $quantity = $itemData->quantity;
                                $discount = $itemData->discount;
                                $allocations = $this->batchService->reserveBatches($product, $quantity, $itemData->batch_allocations);
                                $totalCost = collect($allocations)->sum(fn(array $allocation) => $allocation['quantity'] * $allocation['unit_cost']);
                                $calculatedCostPrice = $quantity > 0 ? (int) round($totalCost / $quantity) : 0;
                                $unitPrice = $isMaterialUsage
                                    ? $calculatedCostPrice
                                    : ($itemData->unit_price > 0 ? $itemData->unit_price : (int) $product->selling_price);

                                if ($discount > $unitPrice) {
                                    throw SaleException::invalidDiscount("Item discount (" . format_money($discount) . ") cannot exceed unit price (" . format_money($unitPrice) . ") for product '{$product->name}'.");
                                }

                                $finalPrice = $unitPrice - $discount;
                                $subtotal = $finalPrice * $quantity;

                                $saleItem = SaleItem::create([
                                    'sale_id' => $sale->id,
                                    'product_id' => $product->id,
                                    'quantity' => $quantity,
                                    'cost_price' => $calculatedCostPrice,
                                    'total_cost' => $totalCost,
                                    'unit_price' => $unitPrice,
                                    'discount' => $discount,
                                    'final_price' => $finalPrice,
                                    'subtotal' => $subtotal,
                                ]);
                                $saleItem->setRelation('product', $product);

                                $this->batchService->recordSaleItemAllocations($sale, $saleItem, $allocations);

                                $totalSubtotal += $subtotal;
                                $totalDiscount += $discount * $quantity;
                            }
                        });

                        if ($data->global_discount > $totalSubtotal) {
                            throw SaleException::invalidDiscount("Global discount (" . format_money($data->global_discount) . ") cannot exceed subtotal (" . format_money($totalSubtotal) . ").");
                        }

                        $total = $totalSubtotal - $data->global_discount;

                        if ($data->status === SaleStatus::COMPLETED) {
                            if ($data->payment_method === \App\Enums\PaymentMethod::CASH && $data->cash_received < $total) {
                                throw SaleException::insufficientPayment($total, $data->cash_received);
                            }
                        }

                        $change = 0;

                        // Calculate change if payment method is cash
                        if ($data->payment_method === \App\Enums\PaymentMethod::CASH && $data->cash_received >= $total) {
                            $change = $data->cash_received - $total;
                        }

                        $sale->update([
                            'subtotal' => $totalSubtotal,
                            'total_discount' => $totalDiscount + $data->global_discount,
                            'global_discount' => $data->global_discount,
                            'total' => $total,
                            'change' => $change,
                        ]);

                        if ($sale->status === SaleStatus::COMPLETED && $sale->transaction_type->createsFinanceIncome()) {
                            $this->financeService->recordIncomeFromSale($sale);
                        }

                        return $sale;

                    } catch (Exception $e) {
                        if ($e instanceof SaleException || ($e instanceof QueryException && $this->isInvoiceNumberCollision($e))) {
                            throw $e;
                        }

                        throw SaleException::creationFailed($e->getMessage(), ['data' => $data]);
                    }
                });
            } catch (QueryException $e) {
                if (!$this->isInvoiceNumberCollision($e)) {
                    throw SaleException::creationFailed($e->getMessage(), ['data' => $data]);
                }

                if ($attempt === $maxAttempts) {
                    throw SaleException::creationFailed('Failed to generate a unique transaction number. Please try again.', ['data' => $data]);
                }

                usleep(50000);
            } catch (SaleException $e) {
                throw $e;
            }
        }

        throw SaleException::creationFailed('Failed to generate a unique transaction number. Please try again.', ['data' => $data]);
    }

    /**
     * Cancel a sale and restore reserved stock.
     *
     * Pending and completed sales both return stock because both states reserve stock.
     */
    public function cancelSale(Sale $sale, ?string $reason = null): Sale
    {
        return DB::transaction(function () use ($sale, $reason) {
            try {
                $lockedSale = Sale::query()
                    ->lockForUpdate()
                    ->with(['items.product', 'items.saleItemBatches.batch'])
                    ->findOrFail($sale->id);

                if ($lockedSale->status === SaleStatus::CANCELLED) {
                    throw SaleException::invalidStatus('cancel', $lockedSale->status->label(), ['id' => $lockedSale->id]);
                }

                if (in_array($lockedSale->status, [SaleStatus::COMPLETED, SaleStatus::PENDING], true)) {
                    $this->batchService->withinStockMutationScope(function () use ($lockedSale) {
                        foreach ($lockedSale->items as $item) {
                            if ($item->product) {
                                $restored = $this->batchService->restoreSaleItemBatches($lockedSale, $item);

                                if (!$restored) {
                                    $this->batchService->restoreSaleItemWithoutRecordedAllocations($lockedSale, $item);
                                }
                            }
                        }
                    });
                }

                $updateData = ['status' => SaleStatus::CANCELLED];

                if ($reason) {
                    $updateData['notes'] = ($lockedSale->notes ? $lockedSale->notes . "\n" : '') . "[Cancelled]: " . $reason;
                }

                $lockedSale->update($updateData);

                $this->financeService->voidTransaction($lockedSale);

                return $lockedSale->refresh();

            } catch (Exception $e) {
                if ($e instanceof SaleException)
                    throw $e;
                throw SaleException::cancellationFailed($e->getMessage(), ['id' => $sale->id]);
            }
        });
    }

    /**
     * Mark a pending sale as completed.
     *
     * Completion does not change stock reservation. It only finalizes finance income.
     */
    public function completeSale(Sale $sale, array $paymentData = []): Sale
    {
        return DB::transaction(function () use ($sale, $paymentData) {
            $lockedSale = Sale::query()
                ->lockForUpdate()
                ->findOrFail($sale->id);

            if ($lockedSale->status !== SaleStatus::PENDING) {
                throw SaleException::invalidStatus('complete', $lockedSale->status->label(), ['id' => $lockedSale->id]);
            }

            $updateData = ['status' => SaleStatus::COMPLETED];

            if (!empty($paymentData)) {
                $updateData['cash_received'] = $paymentData['cash_received'] ?? $lockedSale->cash_received;

                if ($lockedSale->payment_method === PaymentMethod::CASH && $updateData['cash_received'] < $lockedSale->total) {
                    throw SaleException::insufficientPayment($lockedSale->total, $updateData['cash_received']);
                }

                if ($lockedSale->payment_method === PaymentMethod::CASH && $updateData['cash_received'] >= $lockedSale->total) {
                    $updateData['change'] = $updateData['cash_received'] - $lockedSale->total;
                } else {
                    $updateData['change'] = 0;
                }
            }

            $lockedSale->update($updateData);

            if ($lockedSale->transaction_type->createsFinanceIncome()) {
                $this->financeService->recordIncomeFromSale($lockedSale);
            }

            return $lockedSale->refresh();
        });
    }

    /**
     * Restore a cancelled sale to pending and reserve stock again.
     */
    public function restoreSale(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            $lockedSale = Sale::query()
                ->lockForUpdate()
                ->with(['items.product', 'items.saleItemBatches.batch'])
                ->findOrFail($sale->id);

            if ($lockedSale->status !== SaleStatus::CANCELLED) {
                throw SaleException::invalidStatus('restore', $lockedSale->status->label(), ['id' => $lockedSale->id]);
            }

            $this->batchService->withinStockMutationScope(function () use ($lockedSale) {
                foreach ($lockedSale->items as $item) {
                    $product = $item->product()->lockForUpdate()->find($item->product_id);

                    if (!$product) {
                        throw SaleException::productNotFound($item->product_id);
                    }

                    $reapplied = $this->batchService->reapplySaleItemBatches($lockedSale, $item);

                    if (!$reapplied) {
                        $allocations = $this->batchService->reserveBatches($product, $item->quantity);
                        $this->batchService->recordSaleItemAllocations($lockedSale, $item, $allocations);
                    }
                }
            });

            $lockedSale->update(['status' => SaleStatus::PENDING]);

            return $lockedSale->refresh();
        });
    }

    /**
     * Permanently delete a cancelled sale.
     *
     * @param Sale $sale
     * @return void
     * @throws Exception
     */
    public function deleteSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $lockedSale = Sale::query()
                ->lockForUpdate()
                ->findOrFail($sale->id);

            if ($lockedSale->status !== SaleStatus::CANCELLED) {
                throw SaleException::invalidStatus('delete', $lockedSale->status->label(), ['id' => $lockedSale->id]);
            }

            $this->financeService->voidTransaction($lockedSale);

            $lockedSale->items()->delete();
            $lockedSale->delete();
        });
    }

    /**
     * Generate unique invoice number.
     * Format: INV.YYMMDD.0001
     */
    protected function generateReferenceNumber(SaleTransactionType $transactionType): string
    {
        $prefix = $transactionType->referencePrefix() . '.' . date('ymd') . '.';

        $latest = Sale::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        if (!$latest) {
            return $prefix . '0001';
        }

        $lastNumber = (int) substr($latest->invoice_number, -4);
        return $prefix . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    }

    protected function isInvoiceNumberCollision(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'sales.invoice_number')
            || str_contains($message, 'sales_invoice_number_unique')
            || str_contains($message, 'unique constraint failed: sales.invoice_number')
            || str_contains($message, "for key 'sales_invoice_number_unique'");
    }
}
