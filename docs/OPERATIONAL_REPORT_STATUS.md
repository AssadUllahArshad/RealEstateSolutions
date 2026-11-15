# Operational reports status

Implemented on 2026-11-12.

## Agreed rules

- The report is read-only. It never saves, books, collects, reserves, approves or posts anything.
- Inventory is current (as of now). Bookings, collections, owner payouts and agent commissions use the selected period (`from`/`to`, default month-to-date). Receivables aging uses the `as_of` date (default today).
- Collections include only recognized receipts. Allocated collections are exact recognized receipt credit allocated to confirmed booking down payments or installments; unallocated receipt credit is excluded.
- Receivables aging buckets remaining scheduled principal by due date relative to `as_of`: Current (due on/after as-of), 1–30, 31–60, 61–90 and 90+ days overdue.
- Owner payouts and agent commissions show approved payments in the period and outstanding balances (lifetime entitlement/earnings minus approved payments).
- The report does not calculate owner entitlement, settlement, commission, profit or a formal balance sheet.
- Access is gated on the `reports.operations.view` ability, granted to super_admin, admin and accountant.

## Delivered

- `GET /reports/operations` renders a bilingual operational report for users with `reports.operations.view`; `GET /reports/operations/export` (throttled) streams a UTF-8 BOM CSV with the same totals.
- Sections: inventory (total/available/held/booked, by block, by size, by owner), bookings (count, contract value, down payment), collections (recognized count/amount, reversals, allocated to down payments/installments, unallocated), receivables aging (as-of buckets), owner payouts (period approved total, outstanding per owner), agent commissions (period approved total, outstanding per agent).
- Filters: `from`, `to` (period) and `as_of` (aging). Invalid dates are rejected.
- Sidebar entry under the reports section for users with `reports.operations.view`.
- Urdu translations for the new screen; English falls back to the key.
- Shared `App\Domain\Finance\Receivables` service extracted from `ReceivablesController` (`query()`, `summary()`, `installments()`, `decimal()`); the receivables worklist and export now use it unchanged.

## Verification

- MySQL feature tests cover: 403 for users without `reports.operations.view`, exact totals against fixtures (inventory, bookings, collections, aging, owner payouts, commissions), aging buckets shifting with `as_of`, CSV export matching displayed totals with formula-injection protection, and invalid filter rejection.
- 3 tests, 71 assertions passed. Receivables tests remain green (2 tests, 82 assertions). TypeScript and the production asset build passed. PHPStan and Pint passed.

## Boundaries

- No real financial source values have been entered.
- The report is not a contract, receipt, booking or financial transaction and creates no audit trail.
- Owner entitlement, settlement, commission, profit and a formal balance sheet remain separate, separately gated work.
- Legacy cutover, outbound mailbox/SMTP setup and production recovery rehearsal remain outstanding. The second admin, Owner 2 and direct-production automation are now implemented or prepared; see [production setup](PRODUCTION_SETUP.md).
