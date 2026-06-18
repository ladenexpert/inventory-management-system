# Database Map

## Purpose

This document maps the current database structure based on migrations, models, and observed business logic.

## Schema Timeline

### Base Laravel Tables

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`

### Business Tables Added

Initial business model:

- `customers`
- `suppliers`
- `units`
- `categories`
- `products`
- `purchases`
- `purchase_items`
- `sales`
- `sale_items`
- `finance_categories`
- `finance_transactions`
- `settings`

Later enhancements:

- finance polymorphic source references on `finance_transactions`
- global discount on `sales`
- batch number and expiry date on `purchase_items`
- `batches`
- `inventory_logs`
- `sale_item_batches`
- `sale_items.total_cost`
- legacy batch backfill from `products.quantity`
- `products.item_code_ierp`
- `purchases.purchase_date` converted from `date` to `datetime`

## Major Tables

## 1. `users`

Purpose:

- authentication users
- creator reference for sales, purchases, and finance transactions

Important columns:

- `id`
- `name`
- `username` unique
- `email` unique
- `email_verified_at`
- `password`

Relationships:

- has many `sales` via `created_by`
- implicitly referenced by `purchases.created_by`
- implicitly referenced by `finance_transactions.created_by`

## 2. `customers`

Purpose:

- optional customer identity for sales/POS

Important columns:

- `id`
- `name`
- `email`
- `phone`
- `address`
- `notes`

Relationships:

- has many `sales`

## 3. `suppliers`

Purpose:

- supplier master for purchasing

Important columns:

- `id`
- `name`
- `contact_person`
- `email`
- `phone`
- `address`
- `notes`

Relationships:

- has many `purchases`

## 4. `units`

Purpose:

- product unit-of-measure master

Important columns:

- `id`
- `name` unique
- `symbol` unique

Relationships:

- has many `products`

## 5. `categories`

Purpose:

- product categorization

Important columns:

- `id`
- `name`
- `slug` unique
- `description`

Relationships:

- has many `products`

## 6. `products`

Purpose:

- core inventory master record

Important columns:

- `id`
- `category_id`
- `unit_id`
- `sku` unique
- `item_code_ierp` nullable indexed
- `name`
- `purchase_price`
- `selling_price`
- `quantity`
- `min_stock`
- `is_active`
- `description`
- `notes`

Relationships:

- belongs to `categories`
- belongs to `units`
- has many `purchase_items`
- has many `sale_items`
- has many `batches`
- has many `inventory_logs`

Assessment note:

- `quantity` is a denormalized aggregate that is recalculated from batch availability in `BatchService`

## 7. `purchases`

Purpose:

- purchase header

Important columns:

- `id`
- `invoice_number` nullable unique
- `supplier_id`
- `purchase_date` datetime
- `due_date`
- `total`
- `status`
- `notes`
- `proof_image`
- `created_by`

Relationships:

- belongs to `suppliers`
- belongs to `users` as creator
- has many `purchase_items`
- has many `batches`

Status values observed:

- `draft`
- `ordered`
- `received`
- `paid`
- `cancelled`

## 8. `purchase_items`

Purpose:

- purchase line items

Important columns:

- `id`
- `purchase_id`
- `product_id`
- `batch_number` nullable indexed
- `expiry_date` nullable indexed
- `quantity`
- `unit_price`
- `selling_price`
- `subtotal`

Relationships:

- belongs to `purchases`
- belongs to `products`
- has one `batch`

Assessment note:

- batch metadata is captured first on purchase item, then converted into actual batch rows on receipt

## 9. `sales`

Purpose:

- sales/POS header

Important columns:

- `id`
- `invoice_number` unique
- `customer_id` nullable
- `created_by`
- `sale_date`
- `status`
- `subtotal`
- `global_discount`
- `total_discount`
- `total`
- `cash_received`
- `change`
- `payment_method`
- `notes`

Relationships:

- belongs to `customers`
- belongs to `users` as creator
- has many `sale_items`
- has many `inventory_logs`

Status values observed:

- `pending`
- `completed`
- `cancelled`

## 10. `sale_items`

Purpose:

- sales line items with financial snapshots

Important columns:

- `id`
- `sale_id`
- `product_id`
- `quantity`
- `cost_price`
- `total_cost`
- `unit_price`
- `discount`
- `final_price`
- `subtotal`

Relationships:

- belongs to `sales`
- belongs to `products`
- has many `sale_item_batches`

Assessment note:

- `cost_price` and `total_cost` snapshot cost layers at sale time

## 11. `batches`

Purpose:

- inventory lot/batch layers

Important columns:

- `id`
- `product_id`
- `purchase_id` nullable
- `purchase_item_id` nullable
- `batch_number` unique
- `expiry_date` nullable indexed
- `received_at` nullable indexed
- `unit_cost`
- `selling_price`
- `quantity`
- `available_quantity`
- `source` indexed
- `notes`

Relationships:

- belongs to `products`
- belongs to `purchases`
- belongs to `purchase_items`
- has many `inventory_logs`
- has many `sale_item_batches`

Observed source values:

- `purchase`
- `opening_balance`
- `adjustment_in`
- `legacy_sync`

Assessment note:

- `available_quantity` is the operational stock bucket used during FEFO deduction

## 12. `sale_item_batches`

Purpose:

- many-to-many bridge between sale lines and consumed batches

Important columns:

- `id`
- `sale_item_id`
- `batch_id`
- `quantity`
- `unit_cost`

Constraints:

- unique on `sale_item_id + batch_id`

Relationships:

- belongs to `sale_items`
- belongs to `batches`

## 13. `inventory_logs`

Purpose:

- inventory movement history

Important columns:

- `id`
- `product_id`
- `batch_id` nullable
- `purchase_id` nullable
- `purchase_item_id` nullable
- `sale_id` nullable
- `sale_item_id` nullable
- `movement_type`
- `quantity`
- `quantity_before`
- `quantity_after`
- `notes`

Relationships:

- belongs to `products`
- belongs to `batches`
- belongs to `purchases`
- belongs to `purchase_items`
- belongs to `sales`
- belongs to `sale_items`

Observed movement types:

- `purchase_receive`
- `sale_out`
- `sale_cancel_restore`
- `sale_restore_out`
- `opening_balance` via source-driven log creation
- `adjustment_in` via source-driven log creation
- `adjustment_out`
- `legacy_sync`

Assessment note:

- this table is the best current audit trail for stock changes, but it is not yet the sole source of truth

## 14. `finance_categories`

Purpose:

- classify finance transactions

Important columns:

- `id`
- `name`
- `slug` unique
- `type`
- `description`

Relationships:

- has many `finance_transactions`

Observed type values:

- `income`
- `expense`

## 15. `finance_transactions`

Purpose:

- cash-flow style ledger entries

Important columns:

- `id`
- `code` unique
- `transaction_date`
- `finance_category_id`
- `amount`
- `description`
- `external_reference`
- `created_by`
- `reference_id` nullable
- `reference_type` nullable

Relationships:

- belongs to `finance_categories`
- belongs to `users` as creator
- morphs to source reference

Reference usage observed:

- `reference_type = App\Models\Sale`
- `reference_type = App\Models\Purchase`
- null for manual entries

## 16. `settings`

Purpose:

- simple key-value application settings

Important columns:

- `key` primary key
- `value`

Observed keys from seeder:

- `store_name`
- `store_address`
- `store_phone`
- `opening_balance_date`
- `opening_balance_amount`
- `currency_symbol`
- `currency_position`
- `currency_fraction_digits`
- `currency_thousand_separator`
- `currency_decimal_separator`

## Relationship Diagram In Words

### Inventory Core

`categories` -> `products`  
`units` -> `products`  
`products` -> `batches`  
`products` -> `inventory_logs`

### Purchase Core

`suppliers` -> `purchases`  
`users` -> `purchases.created_by`  
`purchases` -> `purchase_items`  
`purchase_items` -> `products`  
`purchase_items` -> `batches` after receipt

### Sales Core

`customers` -> `sales`  
`users` -> `sales.created_by`  
`sales` -> `sale_items`  
`sale_items` -> `products`  
`sale_items` -> `sale_item_batches`  
`sale_item_batches` -> `batches`

### Finance Core

`finance_categories` -> `finance_transactions`  
`users` -> `finance_transactions.created_by`  
`finance_transactions` -> morph reference to `sales` or `purchases`

## Inventory-Related Structure

Main inventory state lives across these tables:

- `products`
- `batches`
- `inventory_logs`
- `sale_item_batches`

Operational interpretation from code:

- on-hand quantity = sum of `batches.available_quantity`
- `products.quantity` is synchronized from that sum
- COGS for sales comes from batch allocations stored on `sale_item_batches` and copied to `sale_items.total_cost`

## Purchase-Related Structure

Main purchase state lives across:

- `suppliers`
- `purchases`
- `purchase_items`
- `batches`
- `finance_transactions`

Important rule from code:

- stock does not enter inventory at purchase creation time
- stock enters only on `received`
- finance expense is recorded only on `paid`

## POS-Related Structure

Main POS state lives across:

- `customers`
- `sales`
- `sale_items`
- `sale_item_batches`
- `batches`
- `inventory_logs`
- `finance_transactions`

Important rule from code:

- stock is reserved/deducted when sale is created, even if status is `pending`
- finance income is recorded only when status is `completed`

## Finance-Related Structure

Main finance state lives across:

- `finance_categories`
- `finance_transactions`
- `settings`

Important rule from code:

- system-generated ledger rows are source-driven and can be voided by deleting the derived transaction row
- this is a transactional reporting ledger, not a double-entry accounting ledger

## Schema Risks

### 1. Dual Stock Storage

- `products.quantity` duplicates `batches.available_quantity` aggregate
- synchronization bugs could create silent mismatch

### 2. Single-Dimension Inventory

- no `company_id`
- no `warehouse_id`
- no `location_id`

Current schema assumes one global inventory pool.

### 3. Partial Historical Provenance

- pre-batch stock was migrated into synthetic `legacy_sync` batches
- historical lot precision before the batch feature cannot be assumed

### 4. Finance Audit Immutability

- finance rows can be deleted when source is voided
- audit history is not immutable at schema level

### 5. Permission Model Absence

- schema has no roles, permissions, teams, or scoped access tables

## Table Grouping By Domain

### Master Data

- `users`
- `customers`
- `suppliers`
- `units`
- `categories`
- `settings`

### Inventory

- `products`
- `batches`
- `inventory_logs`

### Procurement

- `purchases`
- `purchase_items`

### Sales

- `sales`
- `sale_items`
- `sale_item_batches`

### Finance

- `finance_categories`
- `finance_transactions`

### Framework Runtime

- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`
