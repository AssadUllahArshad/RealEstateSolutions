# Payment plan calculator status

Implemented on 2026-11-10.

## Agreed rules

- The calculator is a preview only. It never saves, books, collects, reserves or approves anything.
- It reuses the shared `InstallmentCalculator` engine and the saved `booking`, `installments` and `commissions` business policies; no financial or calendar defaults are assumed.
- If main admin has not completed booking and installment settings, the form is disabled and the missing fields are listed.
- Discounts are entered explicitly (0 means no discount). A preview cannot approve a discount; only an authorized admin may set one on a saved proposal.
- The commission preview is an estimate only. A `cleared_collections` basis has no single preview amount; a `fixed` basis shows the fixed amount; a `net_contract` percentage basis shows net price × rate with half-up paisa rounding.
- No financial transaction, receipt, booking, ledger entry or audit event is created by calculating a preview.

## Delivered

- `GET /calculator` renders a bilingual preview page for users with the `sales.drafts` ability; `POST /calculator` (throttled) validates input and returns the computed preview.
- Inputs: plot price, discount type (fixed PKR or percentage of price), discount value, planned down payment, plan frequency (full payment or any frequency enabled by main admin), installment count and first due date for recurring plans, or custom dated/amount rows.
- Output: net price, discount, planned down payment, configured minimum, installment balance, commission preview, policy versions and the full installment schedule with the configured rounding, month-end and residual rules.
- Sidebar entry under the sales desk for users with `sales.drafts`.
- Urdu translations for the new screen; English falls back to the key.

## Verification

- MySQL feature tests cover: authorized preview with exact schedule snapshots (2028-01-31 anchor, 2028-02-29 clamp, residual in last installment), percentage discount with fixed commission, `cleared_collections` commission returning no single amount, 403 for users without `sales.drafts`, missing-setup blocking, business-rule violations (discount ≥ gross, down payment below minimum, full payment mismatch) and custom-schedule exact-total enforcement.
- 7 tests, 95 assertions passed. TypeScript and the production asset build passed.

## Boundaries

- No real financial source values have been entered.
- The preview is not a contract, receipt, booking or financial transaction and creates no audit trail.
- Commission preview does not accrue, pay or reverse anything; it only illustrates the configured rule.
- Full reporting, legacy imports/cutover, production recovery rehearsal, the second admin, outbound email and Owner 2 setup remain outstanding or deferred.
