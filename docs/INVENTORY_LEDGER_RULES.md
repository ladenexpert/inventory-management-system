# Inventory Ledger Rules

## Current Stock Authority

The current stock authority in RMP is intentionally hybrid but explicit:

- `batches.available_quantity` is the operational stock source
- `products.quantity` is a cached aggregate synchronized from batch availability
- `inventory_logs` is the audit trail for stock-affecting events

This sprint does **not** turn `inventory_logs` into the only source of truth. It stabilizes the existing model so future work can build on consistent rules.

## Core Rules

### 1. Operational stock source

Available stock for selling and reservation is determined from active batch balances:

- `SUM(batches.available_quantity)` per product

### 2. Cached aggregate

`products.quantity` must always match:

- `SUM(batches.available_quantity)`

`products.quantity` exists as a fast aggregate and reporting convenience. It is not the primary operational authority.

### 3. Audit trail

Every stock-changing operation must create an `inventory_logs` row.

Expected movement types:

- `opening_balance`
- `adjustment_in`
- `adjustment_out`
- `purchase_receive`
- `sale_out`
- `sale_cancel_restore`
- `sale_restore_out`
- `legacy_sync`

## Inventory Movement Rules

### Opening balance

- Product creation with an initial quantity creates a batch
- Batch source: `opening_balance`
- Product aggregate is synchronized from the created batch

### Adjustment in

- Increasing product quantity from the product form creates a new inbound batch
- Batch source: `adjustment_in`

### Adjustment out

- Decreasing product quantity consumes from existing batches
- Product aggregate is synchronized after deduction

### Purchase receive

- Purchase creation does not change stock
- Purchase receive creates one batch per purchase item
- Batch source: `purchase`
- Product aggregate is synchronized after receipt

### Sale out

- Sale creation reserves stock immediately
- Reservation consumes `batches.available_quantity`
- `sale_item_batches` stores the exact consumed layers
- Product aggregate is synchronized after reservation

### Sale cancel restore

- Cancelling a pending or completed sale restores stock
- Restored quantities return to the original recorded batch allocations when available
- If historical allocation records are missing, the system restores stock through a fallback inbound batch so batch authority is preserved

### Sale restore out

- Restoring a cancelled sale to pending reserves stock again
- If original allocation records exist, they are reused
- If original allocation records are missing, the system re-reserves current stock using the normal batch reservation path

### Legacy sync

- Legacy aggregate stock can be materialized into batches using `legacy_sync`
- This supports compatibility with stock that existed before batch tracking became operational

## Pending Sale Behavior

Pending sale behavior is intentional and must be preserved for now:

- `pending` sale reserves stock
- `completed` sale creates finance income
- `cancelled` sale restores stock

This means stock availability decreases as soon as a pending sale is created, even before finance income is recorded.

## FEFO And Manual Allocation Rules

### Auto FEFO

Automatic reservation follows current batch ordering:

1. batches with expiry first
2. earliest `expiry_date`
3. earliest `received_at`
4. lowest `id`

### Manual allocation

Manual batch allocation is allowed, but:

- selected quantities must exactly match the requested sale quantity
- selected batch quantities cannot exceed `available_quantity`
- expired batches are blocked server-side for manual allocation

Frontend warnings are not the only protection.

## Synchronization Rule

All stock-changing operations must use the shared product sync routine after batch changes:

- recalculate `products.quantity`
- optionally recalculate product average purchase price from remaining batch valuation

This avoids drifting aggregate stock and duplicate sync logic across services.

## Known Limitations

- `products.quantity` still duplicates batch totals by design
- inventory logs are an audit trail, not yet the only ledger authority
- the current schema is still single-company and single-warehouse
- auto FEFO behavior remains compatibility-oriented and is not yet a full policy engine
- finance ledger is derivative from sales and purchases, not full double-entry accounting
