# Batch Policy

## Lifecycle

RMP v0.2.0-batch-fefo-stable standardizes batch lifecycle as computed policy states.

- `active`: batch has remaining `available_quantity`, is not quarantined, and is not within the near-expiry window
- `near_expiry`: batch has remaining `available_quantity` and `expiry_date` falls within the configured near-expiry threshold
- `expired`: batch has remaining `available_quantity` and `expiry_date` is before today
- `depleted`: `available_quantity <= 0`
- `quarantined`: reserved for non-sellable operational holds; currently inferred from `source = quarantined`

Status is computed centrally by `App\Services\BatchPolicyService`. No new warehouse or company dimension is introduced.

## Expiry Rules

- `BatchPolicyService::getStatus()` is the source of truth for lifecycle classification
- `BatchPolicyService::isExpired()` blocks sale consumption when expiry is before the current day
- `BatchPolicyService::isNearExpiry()` uses `batch_near_expiry_days`
- default near-expiry threshold is `30` days
- threshold can be overridden through the `settings` table using key `batch_near_expiry_days`
- batches without `expiry_date` remain valid and are classified as `active` unless depleted or quarantined

## FEFO Rules

`App\Services\FefoService` is the dedicated allocation policy engine.

Supported policy names:

- `FEFO`
- `FIFO`
- `MANUAL`

Current active policy behavior:

- auto recommendation uses FEFO ordering
- dated batches are prioritized before non-dated batches
- earlier `expiry_date` wins first
- ties fall back to `received_at`, then `id`
- expired, depleted, and quarantined batches are skipped from automatic sale recommendation

Manual allocation behavior:

- manual allocation remains supported
- selected batch quantities must exactly match the requested sale quantity
- selected quantities must not exceed `available_quantity`
- expired batches are rejected server-side
- depleted batches are rejected server-side
- quarantined batches are rejected server-side

## Consumption Rules

- `batches.available_quantity` remains the operational stock source
- `products.quantity` remains the synchronized cached aggregate of batch availability
- `inventory_logs` remains the audit trail
- negative batch quantities are not allowed
- sale COGS follows the exact consumed batch layers recorded in `sale_item_batches`
- restoring cancelled sales attempts to reapply the original batch layers and will fail if those layers are no longer consumable

## Valuation Rules

Batch valuation remains layer based.

Per batch:

- `inventory_value = available_quantity x unit_cost`

Inventory valuation total:

- `SUM(available_quantity x unit_cost)` across batches

RMP does not convert inventory valuation to a global average valuation model.

`products.purchase_price` may still be synchronized as a cached on-hand average for backward compatibility, but inventory value reporting remains batch-layer based.

## Zero-Cost Rules

Zero-cost batches are valid inventory.

Supported examples:

- supplier samples
- trial materials
- materials already expensed externally
- free-of-charge stock

Allowed values:

- `unit_cost = 0`
- `inventory_value = 0`

Zero-cost batches can still be active, sellable, and included in FEFO allocation if they are otherwise valid.
