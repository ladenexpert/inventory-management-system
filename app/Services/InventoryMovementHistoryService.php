<?php

namespace App\Services;

use App\Models\InventoryLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InventoryMovementHistoryService
{
    public function query(array $filters = []): Builder
    {
        return InventoryLog::query()
            ->with([
                'product.unit',
                'batch',
                'purchase.creator',
                'sale.creator',
                'sale.issuer',
            ])
            ->when(!empty($filters['from_date']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from_date']))
            ->when(!empty($filters['to_date']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to_date']))
            ->when(!empty($filters['user_id']), function (Builder $query) use ($filters) {
                $userId = (int) $filters['user_id'];

                $query->where(function (Builder $nested) use ($userId) {
                    $nested
                        ->whereHas('purchase', fn (Builder $purchase) => $purchase->where('created_by', $userId))
                        ->orWhereHas('sale', fn (Builder $sale) => $sale->where('issued_by', $userId)->orWhere('created_by', $userId));
                });
            })
            ->when(!empty($filters['transaction_type']), fn (Builder $query) => $query->where('movement_type', $filters['transaction_type']))
            ->when(!empty($filters['rm_code']), function (Builder $query) use ($filters) {
                $term = $filters['rm_code'];

                $query->whereHas('product', function (Builder $product) use ($term) {
                    $product->where('item_code_ierp', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%");
                });
            })
            ->when(!empty($filters['rm_name']), fn (Builder $query) => $query->whereHas('product', fn (Builder $product) => $product->where('name', 'like', '%' . $filters['rm_name'] . '%')))
            ->when(!empty($filters['lot_number']), fn (Builder $query) => $query->whereHas('batch', fn (Builder $batch) => $batch->where('batch_number', 'like', '%' . $filters['lot_number'] . '%')))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->query($filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function exportRows(array $filters = []): Collection
    {
        return $this->query($filters)->get()->map(fn (InventoryLog $log) => $this->mapRow($log));
    }

    public function mapRow(InventoryLog $log): array
    {
        $userName = $log->sale?->issuer?->name
            ?? $log->sale?->creator?->name
            ?? $log->purchase?->creator?->name
            ?? 'System';

        return [
            'date_time' => $log->created_at?->format('Y-m-d H:i:s') ?? '-',
            'user' => $userName,
            'transaction_type' => $log->movement_type_label,
            'rm_name' => $log->product?->name ?? '-',
            'rm_code' => $log->product?->item_code_ierp ?: ($log->product?->sku ?? '-'),
            'lot_number' => $log->batch?->batch_number ?? '-',
            'quantity' => (int) $log->quantity,
            'remaining_stock' => (int) $log->quantity_after,
            'reference' => $log->purchase?->invoice_number
                ?? $log->sale?->invoice_number
                ?? ($log->batch?->batch_number ? 'Batch ' . $log->batch->batch_number : '-'),
            'notes' => $log->notes ?? '-',
        ];
    }
}
