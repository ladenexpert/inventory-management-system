# Module Management

## Goal

Modules can now be enabled or disabled without deleting code. Disabled modules are:

- hidden from navigation
- blocked at guarded routes with a friendly dashboard redirect
- left intact in the codebase for later re-enable

This sits alongside the v0.4.5 role-permission layer:

- module flags decide whether a capability is globally enabled
- role permissions decide whether a signed-in user can see or execute that capability
- sensitive finance and inventory value data still require permission even when the parent module is enabled

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

The current grouped navigation is:

- `Dashboard`
- `Operations`
- `Master Data`
- `Reports`
- `Administration`

RNI labels remain user-facing:

- `Material Usage`
- `Material Receipt`
- `Materials`
- `Reports`
- `RNI Operations`
- `Business Insights`

Legacy labels remain clearly separated:

- `Legacy Sales`
- `Legacy Purchases`

Import-related visibility notes:

- master data import routes are only exposed from the relevant master data pages
- opening stock import remains under `Materials`
- disabling `materials`, `sales`, or `purchases` still hides the related master pages that expose those import entry points

Role-permission visibility notes:

- `Admin RNI` keeps full access by default
- `Formulator` is read-only / export-only for RNI monitoring
- `RM Desk` can create usage and cancel or restore only their own usage
- `Finance` and `Inventory Value` surfaces stay hidden unless the role has the matching permission

## Operational recommendation

For pilot UAT:

1. Keep `rni`, `materials`, and `reports` enabled.
2. Keep `sales`, `purchases`, and `finance` enabled if shared flows are still being exercised.
3. Disable legacy-only menus gradually after UAT confirms RNI wrappers cover the needed operations.
