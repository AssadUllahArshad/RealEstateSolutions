# Export status

Implemented on 2026-11-13.

## Agreed rules

- Exports are read-only. They never save, book, collect, reserve, approve or post anything.
- Every list/report export streams a UTF-8 BOM CSV with spreadsheet-formula protection (cells starting with `=`, `+`, `-`, `@` or control characters are prefixed with `'`).
- Every export also supports `?format=xlsx` (Excel via PhpSpreadsheet) with the same rows and headers.
- Exports respect the same access scope as the underlying list/report; no export widens visibility.
- The full data export is super_admin only and streams a single zip of CSVs (one per table). Framework-internal tables (`password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`) are excluded.
- Exports are throttled (`throttle:10,1`; full export `throttle:5,1`) and send `Cache-Control: private, no-store` and `X-Content-Type-Options: nosniff`.

## Delivered

- Shared `App\Domain\Export\CsvExport` helper: `stream()` (CSV) and `xlsx()` (Excel), both with formula-injection protection and private/no-store headers.
- `?format=csv|xlsx` export endpoints added to: customers, follow-ups, sales drafts, manual charges, receipts, cashbook movements, financial accounts, bank reconciliation, office expenses, owner payouts, customer refunds, agent commissions, receivables, inventory, operational reports, owner reports, team.
- `GET /exports/full` (super_admin only) streams a zip of CSVs for every application table, excluding framework-internal tables. The export is logged (`Log::info`) with the actor id and table count.
- Shared `ExportMenu` component (CSV/Excel dropdown) wired into the list pages; full data export button on the business-settings page (super_admin only).
- Urdu translations for the new export labels; English falls back to the key.

## Verification

- MySQL feature tests cover: full export is super_admin only (403 for sales_agent), zip contains `customers.csv`/`bookings.csv`/`users.csv`, excludes `sessions.csv`/`password_reset_tokens.csv`/`migrations.csv`, and the customers CSV contains the seeded fixture with a UTF-8 BOM.
- 2 tests, 18 assertions passed. Operational report tests remain green (3 tests, 71 assertions). TypeScript and the production asset build passed. PHPStan reports only the one pre-existing `BrowserBookingSeeder` error (present on HEAD). Pint passes on all new/changed files.

## Boundaries

- No real financial source values have been entered.
- Exports are not contracts, receipts, bookings or financial transactions and create no audit trail (the full export logs a single line; no per-row audit).
- Import (PR15b) and opening balances (PR15c) remain outstanding.
- The full export is a point-in-time snapshot of the live database; it is not a backup/restore mechanism and does not include uploaded files (customer documents).
