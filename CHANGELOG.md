# Changelog

## v0.3.0-rni-pilot-ready

- added RNI-focused `Material Usage` workflow on top of the existing stock deduction engine
- added usage metadata fields: `usage_date`, `purpose`, `formula`, `project`, `requested_by`, `issued_by`, and typed internal usage transactions
- preserved FEFO allocation, batch validation, inventory ledger behavior, and zero-cost batch support
- exposed `Material Receipt` terminology and wrappers for the existing receiving workflow
- added exportable RNI report pages for current inventory, usage history, and expiry monitoring
- refocused the dashboard on RNI operating metrics and recent material usage activity
- added minimal role separation with `Admin RNI` and `Formulator`
- added `docs/RNI_PILOT_GUIDE.md`
- added pilot coverage for material usage, reports, roles, and dashboard metrics

## v0.2.0-batch-fefo-stable

- standardized computed batch lifecycle states: `active`, `near_expiry`, `expired`, `depleted`, and `quarantined`
- added `BatchPolicyService` for centralized expiry, sellability, and batch valuation rules
- added `FefoService` for future-ready FEFO/FIFO/manual batch recommendation and validation
- tightened sale allocation validation so expired, depleted, and invalid manual selections are rejected server-side
- preserved zero-cost batches as valid operational inventory
- exposed batch lifecycle and valuation signals in batch monitoring, dashboard metrics, and POS batch selection
- documented batch policy and updated inventory ledger rules
- added regression coverage for FEFO expiry skipping, manual allocation validation, zero-cost batches, and batch valuation behavior
