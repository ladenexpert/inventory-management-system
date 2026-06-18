# Changelog

## v0.2.0-batch-fefo-stable

- standardized computed batch lifecycle states: `active`, `near_expiry`, `expired`, `depleted`, and `quarantined`
- added `BatchPolicyService` for centralized expiry, sellability, and batch valuation rules
- added `FefoService` for future-ready FEFO/FIFO/manual batch recommendation and validation
- tightened sale allocation validation so expired, depleted, and invalid manual selections are rejected server-side
- preserved zero-cost batches as valid operational inventory
- exposed batch lifecycle and valuation signals in batch monitoring, dashboard metrics, and POS batch selection
- documented batch policy and updated inventory ledger rules
- added regression coverage for FEFO expiry skipping, manual allocation validation, zero-cost batches, and batch valuation behavior
