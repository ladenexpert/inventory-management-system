# Module Management

## Goal

Modules can now be enabled or disabled without deleting code. Disabled modules are:

- hidden from navigation
- blocked at guarded routes with a friendly dashboard redirect
- left intact in the codebase for later re-enable

## Current modules

- `module_rni_enabled`
- `module_sales_enabled`
- `module_purchases_enabled`
- `module_finance_enabled`
- `module_reports_enabled`
- `module_users_enabled`
- `module_materials_enabled`

All module settings default to `1` (`enabled`) through `SettingSeeder`.

## Where settings live

Module flags are stored in the existing `settings` table.

Admin path:

- `Settings`

The settings editor now renders `module_*_enabled` values as an `Enabled` / `Disabled` select.

## Route guards

Route protection is enforced by `EnsureModuleEnabled` and the `module:*` middleware alias.

Current guard usage:

- `rni`
  - material usage routes
  - material receipt wrapper routes
- `sales`
  - legacy sales/POS routes
  - customer master page
- `purchases`
  - legacy purchase GET pages
  - supplier master page
- `finance`
  - finance routes
- `reports`
  - report routes
- `users`
  - user management page
- `materials`
  - product, batch, category, unit, and opening stock import pages

## Shared-engine note

`Material Receipt` still reuses the purchase engine under the hood. Because of that:

- legacy purchase screens can be hidden/guarded separately
- shared POST/PATCH purchase endpoints remain available for the material receipt workflow

This avoids breaking RNI receipt processing while still allowing the legacy purchase UI to be disabled.

## Navigation behavior

Disabled modules are removed from both:

- desktop navigation
- mobile navigation

RNI labels remain user-facing:

- `Material Usage`
- `Material Receipt`
- `Materials`
- `Reports`

Legacy labels remain clearly separated:

- `Legacy POS`
- `Legacy Sales`
- `Legacy Purchases`

## Operational recommendation

For pilot UAT:

1. Keep `rni`, `materials`, and `reports` enabled.
2. Keep `sales`, `purchases`, and `finance` enabled if shared flows are still being exercised.
3. Disable legacy-only menus gradually after UAT confirms RNI wrappers cover the needed operations.
