# Transaction CRUD Consistency

Milestone: `v0.4.1-inbound-outbound-crud-consistency-fix`

Status legend:

- `implemented`
- `fixed in this sprint`
- `intentionally unsupported`
- `broken before / fixed now`

## Root Causes Found

- `resources/views/purchases/show.blade.php` had literal Markdown fences and broken Blade structure, which caused the Material Receipt detail route to fail rendering.
- The shared purchase detail view exposed legacy purchase actions in Material Receipt context, including a path to `mark-paid`.
- Purchase and sale status transitions were using stale model state inside shared services, which left duplicate side effects vulnerable under repeated clicks or concurrent retries.
- Shared inbound/outbound views had context drift in labels, buttons, totals, and table headers.
- The Material Receipt index reused the purchase table without context-specific column labels.

## Inbound

### RNI Material Receipt

| Action | Route | Status | Controller / Service / View / Request | Response / Redirect |
| --- | --- | --- | --- | --- |
| Index | `GET material-receipts` | `implemented` | `MaterialReceiptController@index`, `resources/views/material-receipts/index.blade.php`, `PurchaseTable` | HTML view |
| Create | `GET material-receipts/create` | `implemented` | `MaterialReceiptController@create`, `resources/views/material-receipts/create.blade.php`, shared `purchases.form` | HTML view |
| Store | `POST purchases` with `context=material_receipt` | `implemented` | `PurchaseController@store`, `PurchaseService::createPurchase()`, `StorePurchaseRequest`, shared `purchases.form` | Redirects to `material-receipts.show` |
| Show | `GET material-receipts/{purchase}` | `broken before / fixed now` | `MaterialReceiptController@show`, shared `resources/views/purchases/show.blade.php` with material receipt context | HTML view |
| Edit | `GET material-receipts/{purchase}/edit` | `implemented` | `MaterialReceiptController@edit`, `resources/views/material-receipts/edit.blade.php`, shared `purchases.form` | HTML view |
| Update | `PUT/PATCH purchases/{purchase}` with `context=material_receipt` | `implemented` | `PurchaseController@update`, `PurchaseService::updatePurchase()`, `UpdatePurchaseRequest`, shared `purchases.form` | Redirects to `material-receipts.show` |
| Receive / Confirm | `PATCH purchases/{purchase}/received` | `fixed in this sprint` | `PurchaseController@markReceived`, `PurchaseService::markAsReceived()` | Redirects to `material-receipts.show` with success flash |
| Cancel | `PATCH purchases/{purchase}/cancel` | `implemented` | `PurchaseController@cancel`, `PurchaseService::cancelPurchase()` | Redirects to `material-receipts.show` |
| Delete draft | `DELETE purchases/{purchase}` | `fixed in this sprint` | `PurchaseController@destroy`, `PurchaseService::deletePurchase()` | Redirects to `material-receipts.index` |
| Print | none | `intentionally unsupported` | no wrapper route | n/a |
| Paid / finance posting | shared `PATCH purchases/{purchase}/paid` route | `broken before / fixed now` | `PurchaseController@markPaid`, `PurchaseService::markAsPaid()` now blocks Material Receipt context | Redirects back with error, no finance entry |

Rules confirmed:

- Supplier, invoice number, and proof/evidence remain optional in Material Receipt context.
- Stock enters only on receipt confirmation.
- Batch creation and `inventory_logs` creation happen once per confirmed item.
- No finance posting is allowed for Material Receipt.

### Legacy Purchase

| Action | Route | Status | Controller / Service / View / Request | Response / Redirect |
| --- | --- | --- | --- | --- |
| Index | `GET purchases` | `implemented` | `PurchaseController@index`, `resources/views/purchases/index.blade.php`, `PurchaseTable` | HTML view |
| Create | `GET purchases/create` | `implemented` | `PurchaseController@create`, `resources/views/purchases/create.blade.php`, shared `purchases.form` | HTML view |
| Store | `POST purchases` | `implemented` | `PurchaseController@store`, `PurchaseService::createPurchase()`, `StorePurchaseRequest` | Redirects to `purchases.show` |
| Show | `GET purchases/{purchase}` | `broken before / fixed now` | `PurchaseController@show`, shared `resources/views/purchases/show.blade.php` in legacy context | HTML view |
| Edit | `GET purchases/{purchase}/edit` | `implemented` | `PurchaseController@edit`, `resources/views/purchases/edit.blade.php`, shared `purchases.form` | HTML view |
| Update | `PUT/PATCH purchases/{purchase}` | `implemented` | `PurchaseController@update`, `PurchaseService::updatePurchase()`, `UpdatePurchaseRequest` | Redirects to `purchases.show` |
| Mark ordered | `PATCH purchases/{purchase}/ordered` | `implemented` | `PurchaseController@markOrdered`, `PurchaseService::markAsOrdered()` | Redirects to `purchases.show` |
| Receive | `PATCH purchases/{purchase}/received` | `fixed in this sprint` | `PurchaseController@markReceived`, `PurchaseService::markAsReceived()` | Redirects to `purchases.show` with success flash |
| Mark paid | `PATCH purchases/{purchase}/paid` | `fixed in this sprint` | `PurchaseController@markPaid`, `PurchaseService::markAsPaid()`, `FinanceTransactionService::recordExpenseFromPurchase()` | Redirects to `purchases.show` |
| Cancel | `PATCH purchases/{purchase}/cancel` | `implemented` | `PurchaseController@cancel`, `PurchaseService::cancelPurchase()` | Redirects to `purchases.show` |
| Restore draft | `PATCH purchases/{purchase}/restore-draft` | `implemented` | `PurchaseController@restoreToDraft`, `PurchaseService::restoreToDraft()` | Redirects to `purchases.show` |
| Delete draft | `DELETE purchases/{purchase}` | `implemented` | `PurchaseController@destroy`, `PurchaseService::deletePurchase()` | Redirects to `purchases.index` |
| Print | `GET purchases/{purchase}/print` | `implemented` | `PurchaseController@print`, `resources/views/purchases/print.blade.php` | Printable HTML |

Rules confirmed:

- Supplier validation remains strict for legacy purchase create/update.
- Attachment support remains `PDF/JPG/PNG`.
- Stock enters only on receipt.
- Finance expense posts only on `paid`.
- Finance posting is guarded against duplicate repeated transitions by status locking plus `updateOrCreate()`.

## Outbound

### RNI Material Usage

| Action | Route | Status | Controller / Service / View / Request | Response / Redirect |
| --- | --- | --- | --- | --- |
| Index | `GET material-usages` | `implemented` | `MaterialUsageController@index`, `resources/views/material-usages/index.blade.php` | HTML view |
| Create | `GET material-usages/create` | `implemented` | `MaterialUsageController@create`, `resources/views/material-usages/create.blade.php` | HTML view |
| Store / issue | `POST material-usages` | `implemented` | `MaterialUsageController@store`, `SaleService::createSale()`, `BatchService::reserveBatches()`, inline validation in controller | JSON for fetch, redirect for non-JSON |
| Show | `GET material-usages/{sale}` | `implemented` | `MaterialUsageController@show`, shared `resources/views/sales/show.blade.php` in material usage context | HTML view |
| Delete / cancel | `DELETE material-usages/{sale}` | `fixed in this sprint` | `MaterialUsageController@destroy`, `SaleService::cancelSale()` | Redirects to `material-usages.index` |
| Complete | `PATCH material-usages/{sale}/complete` | `fixed in this sprint` | `MaterialUsageController@complete`, `SaleService::completeSale()` | Redirects to `material-usages.show` |
| Restore | `PATCH material-usages/{sale}/restore` | `fixed in this sprint` | `MaterialUsageController@restore`, `SaleService::restoreSale()` | Redirects to `material-usages.show` |
| Print | `GET material-usages/{sale}/print` | `implemented` | `MaterialUsageController@print`, shared `resources/views/sales/print.blade.php` in material usage context | Printable HTML |
| Edit / update | none | `intentionally unsupported` | no route | n/a |

Rules confirmed:

- Uses the shared stock deduction engine.
- FEFO and manual batch selection remain active.
- No finance income is created for Material Usage.
- Shared views now use Material Usage labels and cost-oriented totals instead of sales wording.

### Legacy Sales / POS

| Action | Route | Status | Controller / Service / View / Request | Response / Redirect |
| --- | --- | --- | --- | --- |
| Index | `GET sales` | `implemented` | `SalesController@index`, `resources/views/sales/index.blade.php` | HTML view |
| Create / POS | `GET sales/create` | `implemented` | `SalesController@create`, `resources/views/sales/create.blade.php` | HTML view |
| Store | `POST sales` | `implemented` | `SalesController@store`, `StoreSaleRequest`, `SaleService::createSale()`, `BatchService::reserveBatches()` | JSON for fetch, redirect for non-JSON |
| Show | `GET sales/{sale}` | `implemented` | `SalesController@show`, shared `resources/views/sales/show.blade.php` in legacy sale context | HTML view |
| Delete / cancel | `DELETE sales/{sale}` | `fixed in this sprint` | `SalesController@destroy`, `SaleService::cancelSale()` | Redirects to `sales.index` |
| Complete | `PATCH sales/{sale}/complete` | `fixed in this sprint` | `SalesController@complete`, `SaleService::completeSale()` | Redirects to `sales.show` |
| Restore | `PATCH sales/{sale}/restore` | `fixed in this sprint` | `SalesController@restore`, `SaleService::restoreSale()` | Redirects to `sales.show` |
| Print | `GET sales/{sale}/print` | `implemented` | `SalesController@print`, shared `resources/views/sales/print.blade.php` in legacy sale context | Printable HTML |
| Edit / update | none | `intentionally unsupported` | no route | n/a |

Rules confirmed:

- One-line and multi-line sale flows remain supported.
- Stock deduction and `sale_item_batches` allocation remain one-time per created sale line.
- Finance income posts only for completed legacy sales.
- Shared views now use explicit `Legacy Sale` labels.

## Response Contract Consistency

### Browser form submits

- Shared purchase endpoints return redirects with flash messages and session validation errors.
- Shared transition endpoints now redirect explicitly to the correct Material Receipt, Legacy Purchase, Material Usage, or Legacy Sales page instead of relying on `back()`.

### AJAX / fetch submits

- `POST material-usages` returns JSON with `success`, `message`, and `redirect_url` on success.
- `POST sales` returns JSON with `success`, `message`, `redirect_url`, and `print_url` on success.
- Validation failures continue returning Laravel JSON validation errors for fetch callers.
- Frontend submit buttons remain disabled during processing and are released in `finally` blocks.

## Search Behavior By Context

- Inbound purchase / Material Receipt search uses `scope=procurement` and returns all active materials, including zero-stock materials, newly created materials, and materials without active batches.
- Outbound Material Usage and Legacy Sales search uses the default sale scope and returns only items with usable on-hand stock.

## Duplicate / Idempotency Safety

- `PurchaseService::markAsOrdered()`, `markAsReceived()`, `markAsPaid()`, `cancelPurchase()`, and `restoreToDraft()` now lock the purchase row before status transitions.
- `SaleService::cancelSale()`, `completeSale()`, `restoreSale()`, and `deleteSale()` now lock the sale row before side effects.
- Material Receipt `mark-paid` is explicitly blocked.
- Finance posting remains `updateOrCreate()` based for legacy purchase and legacy sale references.

## Finance Consistency Result

- Material Receipt: no finance posting.
- Material Usage: no finance posting.
- Legacy Purchase: finance expense only on `paid`.
- Legacy Sales / POS: finance income only on `completed`.

## Visibility Consistency Rule

- The `Finance` navigation group is shown only when `module_finance_enabled` is on and the signed-in user is an allowed admin role.
- In the current RNI UAT role model, that means `admin_rni` can see Finance and `formulator` cannot.
- Finance transaction validation now exposes `Date`, `Type`, `Category`, `Source`, `Reference`, `Related Document`, `Amount`, and `Created By` directly in the finance list.

## Refresh Rule

- Dashboard and analytics caches are versioned and invalidated after successful transaction commit for stock, finance, and destructive product/material mutations.
- Cache invalidation is triggered after:
  - material receipt confirmation
  - legacy purchase receipt
  - material usage issue
  - legacy sale / POS completion
  - sale / usage cancellation or restore
  - legacy purchase mark-paid
  - finance transaction create, update, delete, or void
  - product / material soft delete

## v0.4.8.1 Hotfix Addendum

- Root cause confirmed for the hotfix:
  - product/material soft delete did not invalidate the shared dashboard/report cache
  - several current-state batch-backed queries were still counting soft-deleted materials because they did not exclude deleted products
- Current delete guard for materials/products:
  - active stock authority is `SUM(batches.available_quantity)`, not `products.quantity`
  - browser `ProductTable` row and bulk delete actions both call the centralized `ProductService::deleteProduct()` path
  - delete is blocked when active stock is greater than zero, including zero-cost active stock
  - delete is also blocked when `products.quantity` still shows positive stock as a fail-safe against stock/cache drift
  - delete is allowed only after official stock movement flows reduce active stock to zero
- Preserved behavior:
  - Material Receipt, Material Usage, Stock Take, and Opening Stock semantics remain unchanged
  - no finance posting semantics changed
  - product/material delete is not a stock movement and creates no `inventory_logs` row
  - no new stock movement types were introduced
  - historical movement and transaction evidence for deleted materials remains preserved

## Item Code Rule

- UI label: `Item Code`
- Export header: `Item Code`
- `Item Code` is the nullable legacy IERP code stored in `products.item_code_ierp`.
- `SKU` remains the internal RMP code and is displayed separately where the screen or export shows both identifiers.
- If `item_code_ierp` is empty, show `-`.

## Compact Navigation Rule

- The compact top navigation groups the app into `Dashboard`, `Operations`, `Master Data`, `Reports`, and `Administration`.
- Finance remains accessible through its own `Finance` dropdown/menu when `module_finance_enabled` is on and the signed-in user can access finance.
- Legacy routes and role/module guards remain unchanged; only the menu grouping is compacted.

## View / Regression Coverage Added

- Added route render coverage for Material Receipt, Legacy Purchase, Material Usage, and Legacy Sales pages in `tests/Feature/TransactionViewRenderTest.php`.
- Added a guard test that Material Receipt cannot be marked paid in `tests/Feature/PurchaseReceiptWorkflowTest.php`.
