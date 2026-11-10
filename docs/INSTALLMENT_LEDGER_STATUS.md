# Installment allocation and live contract ledger

Implemented 2026-11-05 following the user's instruction to continue. Builds on [final booking](FINAL_BOOKING_STATUS.md), [receipt recognition](RECEIPT_RECOGNITION_STATUS.md) and [print documents](PRINT_DOCUMENTS_STATUS.md).

## Staff workflow and authority

Open a confirmed booking and select **Payments & ledger**. Finance staff can also open a customer's booking ledgers from an issued receipt. Assigned agents can read their customers' ledgers; accountant/admin finance roles use the existing scoped minimal finance customer lookup. Accountants receive no CRM identity/document permission.

Only `finance.allocations.post` **and** an actual accountant/admin/super_admin role can apply funds. A misgranted permission does not let an agent post allocations. New receipts still use the existing preparation/recognition workflow: this allocation step consumes recognized credit and creates no new receipt, account movement or cash collection.

Select a receipt and enter the exact amount to apply. Confirm the customer, receipt and installment choice. The system uses today's Asia/Karachi business date; allocations cannot be backdated through this slice. The current posting date must be open. Current collection policy version and ledger version are rechecked before writing.

| Saved rule | Behavior |
| --- | --- |
| Oldest first | Apply the entered amount to unpaid entries in due-date/sequence order, splitting exactly where needed |
| Manual | Staff enters amounts against individual unpaid installments; their sum must equal the receipt allocation |
| Hybrid | Staff chooses oldest-first or manual for this action |

Technical behavior: oldest-first includes future unpaid scheduled installments after earlier ones, so an explicitly entered payment can pay ahead. It does not automatically consume all customer credit or grant a prepayment discount. This is an implementation proposal for applying the approved collection rule, not a newly approved monetary default. Only the entered amount is allocated, and excess receipt credit remains unallocated. Amounts above the remaining contract principal are rejected. Future restriction of advance payments would require an explicit business-setting decision.

## Financial meaning

- Paid principal = original allocated down payment + later installment allocations.
- Remaining principal = sum of unpaid original scheduled amounts.
- Overdue principal = unpaid amounts with due date **before** today's Karachi business date. Due today is distinct from overdue.
- These figures exclude late charges, refunds, contract adjustments, landowner entitlements and commission liabilities. Saved late-fee settings do not automatically levy a fee here.
- Original agreed schedules remain immutable. The live ledger derives paid/remaining/status from allocation rows; it does not rewrite installment dates, prices or earlier print copies.

All amounts remain DECIMAL(18,2)/BCMath strings. The UI formats exact strings and does not sum with floats. Full-payment bookings have no installment schedule to allocate against.

## Integrity and cross-module protection

[Migration](../database/migrations/2026_11_05_000100_create_installment_allocations.php) adds `installment_batches` (actor, receipt, booking, date, exact amount, policy snapshot, version and replay command) and positive sequence-level `installment_allocations`. Composite foreign keys bind every child to the same booking/receipt as its batch. Unique booking/revision and actor/command keys prevent duplicate facts.

[InstallmentLedger](../app/Domain/Finance/InstallmentLedger.php) refreshes actor authorization and takes the existing society mutex, booking, policy and receipt locks. Current locking reads check the ledger revision, original schedule entry balance, recognized receipt movement and remaining customer credit. Batches, rows and a minimal customer activity audit commit together or roll back. Command replay returns the existing batch, and changed payload with the same key conflicts. Current and previous app keys support replay hashes.

[ReceiptCredit](../app/Domain/Finance/ReceiptCredit.php) is the shared exact-credit source for final booking, later allocations, previews and receipt printing. Both down payments and installment allocations reduce available receipt funds. No workflow can reuse funds already consumed by the other workflow. Any consumed receipt is protected from standalone accounting reversal, even if it funded installments only. Corrections use the linked workflow below; no source allocation is edited or deleted.

## Linked allocation corrections

An accountant with allocation-posting authority can request a correction against a specific installment allocation. An admin or super-admin separately approves or rejects it; the requester cannot approve their own request. Requests are idempotent and reserve their amount against refundable credit while pending.

Approval preserves the original allocation row and records an immutable correction decision on the open Karachi posting date. The correction reduces paid principal and restores the corresponding recognized receipt credit. It also appends negative owner-entitlement and agent-commission events at the original batch's saved rates. Reallocation is a separate allocation command; neither request nor approval silently reallocates the restored credit.

The append-only allocation API does not replace privileged database administration controls. Production credentials, access controls, backup/restore and financial reconciliation remain release requirements.

## UI, privacy and scope

[Live ledger](../resources/js/pages/finance/ledger.tsx) shows exact paid, remaining and overdue principal, per-installment statuses, source credit choices and the most recent 20 allocation batches. The schedule is bounded by the existing 360-installment contract limit. Form identity includes booking, ledger revision and policy version, so another booking's stale inputs cannot carry across. Required attestations start unchecked. Empty-credit/setup messages and actionable conflicts are displayed.

Ledger responses contain minimal booking/customer/plot context, amounts, dates and source receipt numbers. They omit CNIC/address snapshots, private verification notes and document paths. Receipt print previews now include both down-payment and installment consumption. Confirmed booking printouts continue to show the original agreed schedule and explicitly are not live outstanding statements. The separately scoped, printable current customer statement uses live allocations and omits private identity and evidence data.

## Verification and release status

PHPStan level 7 passed. **23 targeted real-MySQL tests passed, 341 assertions**, covering the existing receipt, booking and print suites plus new allocation tests. Coverage includes exact partial allocation, replay/conflict, manual/hybrid rules, stale policies/ledger versions, finance-only roles, accountant visibility without CRM access, due-today/overdue boundaries, installment-only reversal protection, and complete audit-failure rollback. Two coordinated independent MySQL connections compete for the same credit/revision; exactly one wins and its command replays without duplicating rows or movements.

The bilingual receivables worklist now projects every confirmed installment schedule with MySQL `JSON_TABLE`, joins exact allocation totals, and provides database-wide overdue/due/outstanding filtering, search, totals and pagination. It reports principal only and does not infer late charges, refunds, commissions or owner entitlements.

The same scoped projection powers a filter-preserving UTF-8 CSV export. It retains exact decimal strings, includes the Karachi as-of date, prefixes potentially executable customer/plot spreadsheet cells, is rate limited, and carries private no-store and nosniff response headers.

All eighteen migrations rolled back/reapplied successfully on `estateflow_test` only. The real local application received an additive migration and role grants; no actual receipts, bookings, allocations or financial policy defaults were created by testing. The first UI assertion ran before the new Vite page existed; the production build now includes the ledger. Frontend lint/format, TypeScript and production build passed before final layout wrapping refinements; final browser/build evidence follows after completion.

Final follow-up: frontend lint/format (102 files), TypeScript and production build passed with wrapping and empty/setup messages. Both desktop/mobile Chromium booking-to-ledger scenarios passed (1.4 minutes): agent read-only access, finance sign-in, exact allocation, reload persistence, original booking printing and viewport/client-error checks. The mobile live-ledger screenshot was visually inspected. The ledger return link uses the finance receipt list, which accountants can access without CRM privileges. Documentation links and original source hashes are checked separately; no full-backend-suite rerun is claimed for this slice.

Final packaging: the build after the return-link tidy stopped without a diagnostic inside the sandbox; retrying that build outside the sandbox with project-local D: caches/temp succeeded (28 seconds). Final lint and TypeScript passed. All 293 relative documentation links resolve, and the original four supplied hashes remain unchanged. No unrelated files, system configuration or application financial records were removed to complete verification.

Allocation-correction follow-up (2026-11-10): focused MySQL ledger and owner-report tests passed (**8 tests, 128 assertions**), including receipt-credit restoration, preserved source allocations, owner and commission offsets, requester/approver separation, matching decision replay, changed-payload conflict, owner report totals and later separate reallocation. The full MySQL backend suite passed before the final decision-replay hardening and additional report assertions (**192 tests, 1 skipped, 2,389 assertions**). Focused refund, correction and owner-payout regression tests passed (**10 tests, 166 assertions**) before that hardening. PHPStan, TypeScript, frontend lint and production build passed. Workspace-wide Pint still reports formatter changes in legacy files.

## Next implementation

Next, owner-2 setup remains blocked until a distinct second admin can verify access and set a password; the current local mailer is log-only, so no account has been created. Late-fee execution, opening balances/reconciliation, broader commission clawback policy, full reporting and live deployment/restore gates remain open. Optional GL, automated messaging and SaaS tenancy remain outside MVP absent approval.
