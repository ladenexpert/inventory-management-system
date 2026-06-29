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
- current-state dashboard, inventory, expiry, and batch monitoring surfaces must exclude soft-deleted materials from that active operational count

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
- `stock_take_adjustment_in`
- `stock_take_adjustment_out`
- `purchase_receive`
- `sale_out`
- `sale_cancel_restore`
- `sale_restore_out`
- `legacy_sync`

## Inventory Movement Rules

### Material / product delete

- Material/product delete is a master-data lifecycle action, not an inventory movement.
- Material/product delete must not create an `inventory_logs` row.
- Material/product delete must not auto-create a stock adjustment.
- Material/product delete is blocked while active stock exists, using `SUM(batches.available_quantity)` as the authority.
- Material/product delete also fails safe when `products.quantity` still shows positive stock, even if batch sums appear zero, so stock/cache drift cannot bypass the delete guard.
- Stock must be reduced to zero first through official stock movement flows such as Stock Take, Manual Adjustment, or Material Usage before soft delete is allowed.
- After active stock reaches zero, soft delete remains allowed and historical stock-affecting movement rows remain visible.

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

### Stock Take adjustment

- Stock Take stores a row-level `system_qty` snapshot, `counted_qty`, and derived `variance_qty`
- Stock Take in v0.4.8 reconciles existing matched batches only
- Stock Take never creates a new batch in this milestone
- Before posting, the current `batches.available_quantity` must still match the reviewed snapshot
- If the current quantity changed after review, posting is blocked and the session must be recalculated
- plus variance writes `stock_take_adjustment_in`
- minus variance writes `stock_take_adjustment_out`
- zero variance writes no movement but keeps Stock Take evidence
- Stock Take remains excluded from purchase, sales, usage, and finance side effects

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

Automatic reservation is now centralized through `App\Services\FefoService`.

Automatic reservation follows current batch ordering:

1. batches with expiry first
2. earliest `expiry_date`
3. earliest `received_at`
4. lowest `id`

Automatic FEFO skips batches that are:

- expired
- depleted
- quarantined

### Manual allocation

Manual batch allocation is allowed, but:

- selected quantities must exactly match the requested sale quantity
- selected batch quantities cannot exceed `available_quantity`
- expired batches are blocked server-side for manual allocation
- depleted batches are blocked server-side for manual allocation
- quarantined batches are blocked server-side for manual allocation

Frontend warnings are not the only protection.

## Batch Lifecycle And Valuation

Batch lifecycle is centralized through `App\Services\BatchPolicyService`.

Computed lifecycle states:

- `active`
- `near_expiry`
- `expired`
- `depleted`
- `quarantined`

Default near-expiry threshold:

- `30` days
- configurable through setting key `batch_near_expiry_days`

Batch valuation rule:

- `inventory_value = available_quantity x unit_cost`
- zero-cost batches are valid and remain sellable when otherwise eligible
- Stock Take may use existing batch `unit_cost` only for admin-visible reporting such as adjustment value
- Stock Take does not overwrite `batch.unit_cost`
- `products.purchase_price` remains a derived average-cost cache and is not the authoritative Stock Take posting cost

## Synchronization Rule

All stock-changing operations must use the shared product sync routine after batch changes:

- recalculate `products.quantity`
- optionally recalculate product average purchase price from remaining batch valuation
- treat sync as a one-way cache update from `batches.available_quantity` to `products.quantity`
- update `products.quantity` quietly so the cache refresh does not trigger another stock sync pass
- synchronize once per affected product at the end of the transaction stock movement scope when possible
- write `inventory_logs` after the batch movement and product cache sync are finalized for that scope

This avoids drifting aggregate stock, recursive sync loops, and repeated aggregate queries inside a single transaction flow.

## Dashboard And Report Refresh

- Dashboard and report aggregates may be cached, but cached keys must be invalidated after successful stock, finance, or destructive product mutations.
- The cache invalidation point is registered after successful transaction commit when a database transaction is active.
- Zero-stock product/material soft delete must invalidate dashboard/report aggregates even though historical batch and movement evidence remains preserved.
- This keeps:
  - current inventory
  - inventory movement history
  - dashboard stock metrics
  - purchase analysis
  - sales analysis
  - finance cash-flow metrics
  immediately consistent after the source transaction is committed.

## Known Limitations

- `products.quantity` still duplicates batch totals by design
- inventory logs are an audit trail, not yet the only ledger authority
- the current schema is still single-company and single-warehouse
- batch policy and FEFO recommendation are now centralized service rules while preserving the existing stock authority
- finance ledger is derivative from sales and purchases, not full double-entry accounting
