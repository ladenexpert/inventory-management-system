# Naming Convention

## User-facing RNI labels

Use these names in RNI-facing navigation, headings, actions, and success/error messages:

- `RNI Stock Out`
- `RNI Stock In`
- `Materials`
- `Reports`
- `Usage History`
- `Material Usage`
- `Material Receipt`
- `Current Inventory`
- `Expiry Report`
- `Storage Locations`

## Legacy commercial labels

Use these names only for the commercial workflow:

- `Legacy Sales`
- `Legacy Purchases`
- `Sales / POS`
- `Finance`
- `Customers`

## Rules

1. Do not label RNI stock-out screens as `Sales`.
2. Do not label RNI stock-in screens as `Purchase` unless the reference is technical and hidden behind an RNI wrapper.
3. Keep route/controller reuse internal; user-facing copy should follow the RNI label set.
4. When a shared engine is reused, prefer wording that matches the active context:
   - `Material Receipt` instead of `Purchase`
   - `Material Usage` instead of `Sale`
5. When legacy access is still needed, prefix it explicitly with `Legacy`.

## Current applied examples

- Navigation now separates:
  - `RNI Stock Out`
  - `RNI Stock In`
  - `Legacy Sales`
  - `Legacy Purchases`
  - `Customers`
- Material receipt forms now prefer:
  - `Raw Material`
  - `Unit Cost`
  - `Reference Price`

## Future naming guardrails

- Any new RNI report should use `material`, `raw material`, `usage`, `receipt`, or `inventory` wording.
- Any commercial-only feature should keep `sales`, `purchase`, or `finance` wording.
- Shared technical route names may remain as-is when changing them would create migration risk; the user-facing label should still be corrected in Blade/Livewire views.
