<?php

namespace App\DTOs;

class PurchaseItemData
{
    public function __construct(
        public int $product_id,
        public ?string $batch_number,
        public ?string $expiry_date,
        public ?string $storage_location,
        public ?int $storage_location_id,
        public int $quantity,
        public int $unit_price,
        public ?int $selling_price,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            product_id: (int) $data['product_id'],
            batch_number: !empty($data['batch_number']) ? trim($data['batch_number']) : null,
            expiry_date: !empty($data['expiry_date']) ? $data['expiry_date'] : null,
            storage_location: !empty($data['storage_location']) ? trim((string) $data['storage_location']) : null,
            storage_location_id: isset($data['storage_location_id']) && $data['storage_location_id'] !== '' ? (int) $data['storage_location_id'] : null,
            quantity: (int) $data['quantity'],
            unit_price: (int) $data['unit_price'],
            selling_price: isset($data['selling_price']) && $data['selling_price'] !== '' ? (int) $data['selling_price'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->product_id,
            'batch_number' => $this->batch_number,
            'expiry_date' => $this->expiry_date,
            'storage_location' => $this->storage_location,
            'storage_location_id' => $this->storage_location_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'selling_price' => $this->selling_price,
        ];
    }
}
