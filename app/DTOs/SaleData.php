<?php

namespace App\DTOs;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\SaleTransactionType;
use Carbon\Carbon;

readonly class SaleData
{
    /**
     * @param SaleItemData[] $items
     */
    public function __construct(
        public Carbon $sale_date,
        public PaymentMethod $payment_method,
        public int $created_by,
        public array $items,
        public SaleTransactionType $transaction_type = SaleTransactionType::SALE,
        public ?int $customer_id = null,
        public SaleStatus $status = SaleStatus::COMPLETED,
        public ?Carbon $usage_date = null,
        public ?string $purpose = null,
        public ?string $formula = null,
        public ?int $team_id = null,
        public ?string $invoice_number = null,
        public ?string $project = null,
        public ?string $requested_by = null,
        public ?int $issued_by = null,
        public ?string $notes = null,
        public int $cash_received = 0,
        public int $change = 0,
        public int $global_discount = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            sale_date: Carbon::parse($data['sale_date']),
            payment_method: PaymentMethod::from($data['payment_method']),
            created_by: (int) $data['created_by'],
            items: array_map(fn($item) => SaleItemData::fromArray($item), $data['items']),
            transaction_type: isset($data['transaction_type']) ? SaleTransactionType::from($data['transaction_type']) : SaleTransactionType::SALE,
            customer_id: isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            status: isset($data['status']) ? SaleStatus::from($data['status']) : SaleStatus::COMPLETED,
            usage_date: !empty($data['usage_date']) ? Carbon::parse($data['usage_date']) : null,
            purpose: $data['purpose'] ?? null,
            formula: $data['formula'] ?? null,
            team_id: isset($data['team_id']) && $data['team_id'] !== '' ? (int) $data['team_id'] : null,
            invoice_number: !empty($data['invoice_number']) ? trim((string) $data['invoice_number']) : null,
            project: $data['project'] ?? null,
            requested_by: $data['requested_by'] ?? null,
            issued_by: isset($data['issued_by']) ? (int) $data['issued_by'] : null,
            notes: $data['notes'] ?? null,
            cash_received: (int) ($data['cash_received'] ?? 0),
            change: (int) ($data['change'] ?? 0),
            global_discount: (int) ($data['global_discount'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'sale_date' => $this->sale_date->toDateTimeString(),
            'payment_method' => $this->payment_method->value,
            'created_by' => $this->created_by,
            'items' => array_map(fn($item) => $item->toArray(), $this->items),
            'transaction_type' => $this->transaction_type->value,
            'customer_id' => $this->customer_id,
            'status' => $this->status->value,
            'usage_date' => $this->usage_date?->toDateTimeString(),
            'purpose' => $this->purpose,
            'formula' => $this->formula,
            'team_id' => $this->team_id,
            'invoice_number' => $this->invoice_number,
            'project' => $this->project,
            'requested_by' => $this->requested_by,
            'issued_by' => $this->issued_by,
            'notes' => $this->notes,
            'cash_received' => $this->cash_received,
            'change' => $this->change,
            'global_discount' => $this->global_discount,
        ];
    }
}
