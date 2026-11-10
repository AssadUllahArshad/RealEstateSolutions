# Office expense register status

Implemented on 2026-11-08. This module gives super admins, admins and accountants a controlled operational register for verified office spending.

## Delivered

- Immutable `EXP-000001` style expense records with exact two-decimal PKR amounts.
- Explicit active cash, bank, wallet or other financial account selection and an immutable account snapshot.
- Free-text category, payee, external reference, verification note, posting actor and Asia/Karachi business date.
- Idempotent commands: replaying the same command and payload returns the original record; changed payload reuse conflicts.
- Current-date-only posting until a close-period/backdating policy is approved.
- Date filtering, exact period total, desktop/mobile layouts, English/Urdu labels and RTL alignment.
- Accountant and admin posting authority. Sales agents have no expense-register access.
- Unified cashbook integration: expenses appear as outflows beside receipt inflows and accounting reversals, with account/date filters and exact net movement.
- Filter-preserving UTF-8 CSV cashbook export for authorized finance users, including an Excel-compatible byte-order mark and exact decimal amount strings.
- Branded printable expense vouchers with Data Real Estate and Green Vista identity, English/Urdu RTL output, exact PKR values and private no-store security headers.
- A bilingual finance overview with period inflow, reversals, office outflow, net movement, account-level movement and non-monetary work-queue counts; every total is explicitly separated from unconfigured opening balances.

## Financial boundary

The register is an operational cashbook view. It is not a double-entry general ledger, trial balance, profit and loss statement, statutory balance sheet or proof of an external bank balance. The system does not infer opening balances or allow an expense record to alter customer receipts.

## Verification

MySQL feature coverage verifies accountant/admin authority, agent denial, exact amounts, immutable records, idempotent replay, changed-payload conflict, inactive-account rejection, current-date enforcement, filtered exports and protected vouchers. Authenticated browser checks cover admin and accountant access, CSV download and English/Urdu layouts at desktop and mobile widths.

## Still needs a business decision

- Backdating window, accounting-period close and who may reopen a period.
- Expense categories, required evidence by category, approval thresholds and whether a preparer/poster separation is needed.
- Opening balances and reconciliation semantics.
- Cancellation refund amounts, deductions and allocation/reversal order remain unresolved and are not supplied by this module.
