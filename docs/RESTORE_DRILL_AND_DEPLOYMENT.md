# Restore drill and deployment checklist

Status: draft runbook for M7 readiness. Not executed against production. D14 (hosting, RPO/RTO, retention, restore owner) and D15 (provisioning, MFA) remain open business decisions; this document records the procedure to rehearse once those are agreed.

[Index](README.md) · [Architecture](ARCHITECTURE.md) · [Roadmap](ROADMAP.md) · [MySQL setup](../MYSQL_SETUP.md)

## Scope

This runbook covers:

1. A restore drill: restoring the database, private document storage and encryption keys into an isolated environment, then verifying financial control totals and private access.
2. A deployment checklist: the ordered steps to deploy a new release to a live environment.

It does not cover:

- Choosing a hosting provider, RPO/RTO, retention or restore owner (D14).
- Provisioning staff accounts, MFA or delegated ability sets (D15).
- Staging UAT sign-off (requires named business/finance approvers).
- Production recovery validation (requires a live environment and real data).

## Restore drill

### Preconditions

- An isolated environment (separate host or VM) with no network route to production.
- A current encrypted backup of:
  - the MySQL 8.4 database (`estateflow`),
  - the private document storage root (`CUSTOMER_DOCUMENTS_PATH`),
  - the application encryption keys (`APP_KEY` and any `APP_PREVIOUS_KEYS`),
  - the deployment configuration (`.env` with secrets redacted for the drill log).
- A least-privilege MySQL user for the restore target (not the production application user).
- A named restore owner and a named verifier (finance approver).

### Procedure

1. **Provision the isolated environment.** Install PHP 8.4, MySQL 8.4, Nginx/PHP-FPM (or the agreed stack) and the application code at the release tag. Do not copy production secrets into the drill log.
2. **Restore the database.** Create an empty `estateflow_restore` schema. Restore the backup with `mysql` or `mysqldump --restore`. Verify the schema version matches the release tag (`php artisan migrate:status`).
3. **Restore the private document storage.** Copy the backup of `CUSTOMER_DOCUMENTS_PATH` into the isolated environment. Verify file count and total bytes match the backup manifest.
4. **Restore the encryption keys.** Place `APP_KEY` and `APP_PREVIOUS_KEYS` in the isolated environment's `.env`. Verify a known encrypted field (e.g., a customer CNIC) decrypts correctly.
5. **Verify financial control totals.** As the finance approver, reconcile:
   - total recognized receipts (sum of `payment_receipts.amount` where `state = 'recognized'`),
   - total allocated down payments (sum of `booking_allocations.amount`),
   - total allocated installments (sum of `installment_allocations.amount`),
   - total approved owner payouts (sum of `owner_payouts.amount` where `state = 'approved'`),
   - total approved agent commissions (sum of `commission_payments.amount` where `state = 'approved'`),
   - total approved customer refunds (sum of `customer_refunds.amount` where `state = 'approved'`),
   - total approved office expenses (sum of `office_expenses.amount`),
   - cashbook net movement (sum of `account_movements.amount` signed by `kind`).
   Record the totals and the approver's sign-off.
6. **Verify private access.** Sign in as a synthetic restore-drill user (not a production account). Confirm:
   - the dashboard loads,
   - a customer profile with a private document opens and the document downloads,
   - a confirmed booking's financial summary is visible,
   - an unauthorized role cannot see finance screens.
7. **Verify audit integrity.** Confirm `audit_events`, `booking_events`, `receipt_draft_events`, `payment_receipt_events` and `inventory_events` are present and append-only (no rows with `updated_at` set).
8. **Record the drill.** Write a drill report with: date, environment, backup timestamp, restore duration, control totals, verifier sign-off, any discrepancies and their resolution. File the report in the release evidence pack.

### Pass criteria

- The isolated environment boots and serves the application.
- All financial control totals match the backup source (or discrepancies are explained and signed off).
- Private documents are readable by authorized users and not by unauthorized users.
- Audit tables are append-only and complete.
- The drill report is filed.

### Failure handling

- If the database restore fails, do not proceed. Investigate the backup integrity and the MySQL version match.
- If encryption keys are missing or wrong, private data is unreadable. Do not attempt to brute-force or bypass encryption. Restore the correct keys from the key backup.
- If control totals do not match, do not declare the restore successful. Investigate whether the backup was taken mid-transaction or whether the wrong backup was used.
- If any step fails, the drill is a failure. Record the failure, fix the root cause and re-run the drill.

## Deployment checklist

### Pre-deployment

- [ ] D14 and D15 are agreed and recorded (hosting, RPO/RTO, retention, restore owner, provisioning, MFA).
- [ ] A current encrypted backup exists and its restore has been rehearsed (see restore drill above).
- [ ] The release tag is built and its assets are immutable (`npm run build` output is committed or built in CI).
- [ ] `php artisan migrate:status` shows no pending migrations on the target, or the migration plan is reviewed and backward-compatible.
- [ ] The deployment configuration (`.env`) is reviewed: `APP_DEBUG=false`, `APP_ENV=production`, HTTPS, secure cookies, least-privilege DB credentials, non-public storage, queue/scheduler supervision.
- [ ] The private document storage root is outside the public web root and is backed up together with the database and keys.
- [ ] The deployment window is agreed with the office (no active posting during the window).

### Deployment

1. **Verify backups.** Confirm the latest backup is restorable (spot-check the restore drill report).
2. **Review SQL/locking impact.** For each pending migration, review the SQL for locking impact on large tables. Prefer additive expand/contract changes. Destructive changes require a separate review and a restored-copy rehearsal.
3. **Deploy assets/code.** Pull the release tag. Run `composer install --no-dev --optimize-autoloader`. Run `npm ci && npm run build` (or use the CI-built assets). Run `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`.
4. **Apply migrations.** Run `php artisan migrate --force`. Verify `php artisan migrate:status` shows all migrations applied.
5. **Restart workers.** Restart queue workers and the scheduler. Verify the scheduler heartbeat is fresh.
6. **Run readiness/smoke checks.**
   - `GET /login` returns 200.
   - Sign in as a synthetic smoke-test user (not a production account).
   - The dashboard loads.
   - A read-only finance screen loads (e.g., `/finance/receivables`).
   - A read-only report loads (e.g., `/reports/operations`).
   - `php artisan queue:work --once` processes a test job (if a test job exists).
7. **Verify monitoring.** Confirm failed-job alerts, scheduler heartbeat, DB contention/deadlock alerts, disk growth, backup age and restore evidence are wired to the agreed channel.

### Post-deployment

- [ ] The deployment log is filed (date, release tag, migrations applied, smoke-check results, any issues).
- [ ] The office is notified that the deployment is complete.
- [ ] The first business day after deployment, the finance approver reconciles charges, collections, allocations, unallocated balances, cash movements and payables against the pre-deployment baseline.
- [ ] Any discrepancy is investigated before the next deployment.

### Rollback

- Do not roll back posted finance. Prefer forward correction (a new migration or a corrective entry).
- If the deployment fails before migrations are applied, revert the code and assets to the previous release tag.
- If the deployment fails after migrations are applied, do not drop tables or columns. Restore from the pre-deployment backup into an isolated environment, verify, then decide whether to restore production or apply a forward fix.
- Destructive rollback of posted finance is never permitted.

## Open decisions

- D14: hosting provider, RPO/RTO, retention, restore owner, capacity, MFA requirements.
- D15: staff provisioning, MFA, delegated ability sets, same-person approval exceptions.
- D12: identity retention and erasure/legal hold (affects backup retention).
- D13: legacy cutover reconciliation (affects the restore drill's control totals).

## Boundaries

- This runbook is a draft. It has not been executed against production.
- No production deployment has been attempted.
- No restore drill has been executed against a production backup.
- The control totals listed are the minimum set; the finance approver may add more.
- This document does not replace the named business/finance sign-off required for M7 release.
