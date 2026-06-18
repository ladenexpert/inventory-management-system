<?php

namespace App\DTOs;

readonly class SaleItemData
{
    /**
     * @param array<array{batch_id: int, quantity: int}>|null $batch_allocations Manual batch allocations (null = auto FEFO)
     */
    public function __construct(
        public int $product_id,
        public int $quantity,
        public int $unit_price, // Enforce integer for currency (e.g. Rupiah via casts)
        public int $discount = 0,
        public ?array $batch_allocations = null, // null = auto FEFO, array = manual
        public ?int $cost_price = null, // Calculated cost price per unit
        public ?int $total_cost = null, // Calculated total cost for quantity
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            product_id: (int) $data['product_id'],
            quantity: (int) $data['quantity'],
            unit_price: (int) $data['unit_price'],
            discount: (int) ($data['discount'] ?? 0),
            batch_allocations: isset($data['batch_allocations']) ? (array) $data['batch_allocations'] : null,
            cost_price: isset($data['cost_price']) ? (int) $data['cost_price'] : null,
            total_cost: isset($data['total_cost']) ? (int) $data['total_cost'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
            'batch_allocations' => $this->batch_allocations,
            'cost_price' => $this->cost_price,
            'total_cost' => $this->total_cost,
        ];
    }
}
