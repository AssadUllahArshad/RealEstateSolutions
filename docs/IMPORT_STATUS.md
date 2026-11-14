# Import status

Implemented on 2026-11-14.

## Agreed rules

- Imports are dry-run first: every row is validated and row-level errors are shown before anything is saved. A separate confirm step commits a clean dry run.
- Imports create records only. They never post payments, recognize money, allocate installments or change existing bookings.
- Imports respect the same access scope as the underlying modules; the `import.run` ability is granted to super_admin, admin and accountant.
- Duplicate source identifiers do not duplicate records: customers are matched on phone, plot owners on name, plots on block+number, bookings on plot.
- The bookings import creates a confirmed booking with a generated installment schedule (monthly/quarterly/half_yearly/yearly) using the same half-up rounding and residual-last rule as the live calculator. It does not allocate receipts or post entitlement/commission events.
- Import batches are recorded with the actor, template, row count, error count and a payload hash. Committed batches are immutable.

## Delivered

- `import_batches` and `import_rows` tables (migration `2026_11_14_000100_create_import_tables.php`).
- `App\Domain\Import\ImportTemplates` — versioned CSV templates for customers, plot owners, plot inventory and confirmed bookings.
- `App\Domain\Import\ImportService` — dry-run validation (row-level errors) and confirm-to-commit in a transaction.
- `GET /imports` — bilingual import screen (template download, CSV upload, batch history).
- `GET /imports/template/{template}` — downloadable CSV template with example row.
- `POST /imports/dry-run` — parses the uploaded CSV, validates every row, stores the batch and rows, returns row-level errors.
- `GET /imports/{batch}` — batch detail with per-row status and errors.
- `POST /imports/{batch}/commit` — commits a clean dry run (blocked when `error_count > 0`).
- Sidebar entry under Administration for users with `import.run`.
- Urdu translations for the new screen; English falls back to the key.

## Verification

- MySQL feature tests cover: 403 for users without `import.run`, template download streams a UTF-8 BOM CSV with the expected headers, dry-run validates rows and reports errors, commit creates customers from valid rows, commit is rejected when the dry run has errors, inventory import creates plots, bookings import creates a confirmed booking.
- 7 tests, 31 assertions passed. TypeScript and the production asset build passed. PHPStan passes on the new files. Pint passes on all new/changed files.

## Boundaries

- No real financial source values have been entered.
- The bookings import does not allocate receipts, post owner entitlement or commission events, or create installment allocations. Those remain separate, separately gated workflows.
- Opening balances (PR15c) remain outstanding.
- The import is not a backup/restore mechanism and does not import uploaded files (customer documents).
- The bookings import generates a schedule from the supplied frequency/count/first_due_date; it does not read the saved installment policy. The policy is snapshotted as empty in the imported booking's terms.
