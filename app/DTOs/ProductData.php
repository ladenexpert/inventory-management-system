<?php

namespace App\DTOs;

class ProductData
{
    public function __construct(
        public readonly int $category_id,
        public readonly int $unit_id,
        public readonly ?int $supplier_id,
        public readonly ?string $sku,
        public readonly ?string $item_code_ierp,
        public readonly string $name,
        public readonly ?string $physical_form,
        public readonly ?int $physical_form_id,
        public readonly int $purchase_price,
        public readonly int $selling_price,
        public readonly int $quantity,
        public readonly ?string $opening_batch_number,
        public readonly ?string $opening_expiry_date,
        public readonly ?string $opening_storage_location,
        public readonly ?int $opening_storage_location_id,
        public readonly int $min_stock,
        public readonly bool $is_active,
        public readonly ?string $description,
        public readonly ?string $notes,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            category_id: (int) $data['category_id'],
            unit_id: (int) $data['unit_id'],
            supplier_id: isset($data['supplier_id']) && $data['supplier_id'] !== '' ? (int) $data['supplier_id'] : null,
            sku: !empty($data['sku'] ?? null) ? $data['sku'] : null,
            item_code_ierp: !empty($data['item_code_ierp'] ?? null) ? trim($data['item_code_ierp']) : null,
            name: $data['name'],
            physical_form: !empty($data['physical_form'] ?? null) ? trim((string) $data['physical_form']) : null,
            physical_form_id: isset($data['physical_form_id']) && $data['physical_form_id'] !== '' ? (int) $data['physical_form_id'] : null,
            purchase_price: (int) $data['purchase_price'],
            selling_price: (int) $data['selling_price'],
            quantity: (int) ($data['quantity'] ?? 0),
            opening_batch_number: !empty($data['opening_batch_number'] ?? null) ? trim($data['opening_batch_number']) : null,
            opening_expiry_date: !empty($data['opening_expiry_date'] ?? null) ? $data['opening_expiry_date'] : null,
            opening_storage_location: !empty($data['opening_storage_location'] ?? null) ? trim((string) $data['opening_storage_location']) : null,
            opening_storage_location_id: isset($data['opening_storage_location_id']) && $data['opening_storage_location_id'] !== '' ? (int) $data['opening_storage_location_id'] : null,
            min_stock: (int) ($data['min_stock'] ?? 0),
            is_active: (bool) ($data['is_active'] ?? true),
            description: empty($data['description']) ? null : $data['description'],
            notes: empty($data['notes']) ? null : $data['notes'],
        );
    }

    public function toArray(): array
    {
        return [
            'category_id' => $this->category_id,
            'unit_id' => $this->unit_id,
            'supplier_id' => $this->supplier_id,
            'sku' => $this->sku,
            'item_code_ierp' => $this->item_code_ierp,
            'name' => $this->name,
            'physical_form' => $this->physical_form,
            'physical_form_id' => $this->physical_form_id,
            'purchase_price' => $this->purchase_price,
            'selling_price' => $this->selling_price,
            'quantity' => $this->quantity,
            'opening_batch_number' => $this->opening_batch_number,
            'opening_expiry_date' => $this->opening_expiry_date,
            'opening_storage_location' => $this->opening_storage_location,
            'opening_storage_location_id' => $this->opening_storage_location_id,
            'min_stock' => $this->min_stock,
            'is_active' => $this->is_active,
            'description' => $this->description,
            'notes' => $this->notes,
        ];
    }
}
