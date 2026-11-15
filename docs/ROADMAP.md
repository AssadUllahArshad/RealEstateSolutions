# Delivery roadmap and release gates

Agent-compensation follow-up (updated 2026-11-15): [collection-based agent commission](AGENT_COMMISSION_STATUS.md) is implemented with booking policy snapshots, immutable earnings, proportional refund reversal, accountant preparation, admin authorization, cashbook integration, legacy booking assignment and printable agent statements. A departed agent's negative balance remains recoverable until a separately approved main-admin settlement or waiver workflow is implemented.

Cancellation-finance follow-up (2026-11-09): [controlled customer refunds](CUSTOMER_REFUND_STATUS.md) are implemented with accountant preparation, admin authorization, partial payments, approved deductions, cashbook outflows and proportional owner-entitlement reversal. Approved refund vouchers and net-future-entitlement recovery for negative owner balances are also delivered. Production recovery validation and UAT remain release gates.

Allocation-correction follow-up (2026-11-09): linked installment allocations can be reversed by an accountant request and a separate admin/super-admin decision. Approval preserves the source allocation, restores receipt credit, and records proportional owner-entitlement and commission offsets at the original saved rates. Reallocation is a separate transaction.

Purchaser-transfer follow-up (2026-11-09): [controlled purchaser transfers](PURCHASER_TRANSFER_STATUS.md) are implemented with exact joint shares, complete identity evidence, immutable historical parties, admin approval and installment carryover.

Ledger follow-up (2026-11-05): [installment allocation, the live principal ledger, linked admin-finalized corrections and printable current customer statements](INSTALLMENT_LEDGER_STATUS.md) are implemented. Older collection/ledger gaps below are superseded.

Print follow-up (2026-11-05): [branded receipt/booking printouts](PRINT_DOCUMENTS_STATUS.md), later installment allocation, linked admin corrections and the live customer ledger are implemented, including current customer statements. Earlier print gaps below are historical.

Latest increments include [receipt recognition](RECEIPT_RECOGNITION_STATUS.md), [final booking/down payment allocation](FINAL_BOOKING_STATUS.md), controlled booking changes, the [office expense register](OFFICE_EXPENSE_STATUS.md), [account opening balances and bank reconciliation](ACCOUNT_OPENING_RECONCILIATION_STATUS.md), the [payment plan calculator](CALCULATOR_STATUS.md) and [operational reports](OPERATIONAL_REPORT_STATUS.md). Staff/Owner 2 setup and the initial new-work-first cutover policy are resolved. Remaining release gates are mailbox/SMTP setup, production PHP/database compatibility, CI/CD secret configuration, a clean production backup and restore rehearsal, direct-production smoke/UAT evidence, legal review of the seven-year retention policy and production recovery validation.

Historical operational deferral (2026-11-10; superseded on 2026-11-15): second-admin provisioning, outbound email setup and Owner 2 were originally deferred. The confirmed staff and Owner 2 identities are now configured locally; real mailbox creation and verified SMTP delivery remain unfinished.

Staff identity update (2026-11-15): Amir Ali and Raja Irshad are both main administrators; Tabish Hafeez is the accountant. Their confirmed phone numbers are stored only in authorized environments, while proposed `datarealestate.online` mailbox names are recorded in the [staff provisioning plan](STAFF_PROVISIONING.md). Raja Irshad is Owner 2 with a 50% default. Mailbox creation and SMTP verification remain release inputs.

2026-11-04 increment (historical; superseded by the current slice reports above): private identity/documents, proposed purchaser shares and [proposed pricing/schedules](TERMS_STATUS.md) were delivered, followed by [accounts and unposted receipt preparation](COLLECTION_PREPARATION_STATUS.md). The recognized collections and controlled booking gaps named here have since been implemented. Preparation race tests prove duplicate-command safety, not account-posting correctness.

2026-11-03 implementation update: the [sales desk](SALES_DRAFT_STATUS.md) adds non-binding draft quotes/snapshots/history alongside [CRM](CRM_UI_STATUS.md). This is partial M3 delivery; private documents, legal purchasers, approved contracts and financial milestones remain open. Draft validation does not satisfy the accepted-contract release gate below.

[Index](README.md) · [Requirements](PRODUCT_REQUIREMENTS.md) · [Decisions](FINANCIAL_DECISIONS.md) · [Phase 1](PHASE_1_CHECKLIST.md)

Sequence follows S1/S2. No duration or release date is committed: staffing, approved rules, capacity and deployment target are unknown. Each milestone yields a reviewable vertical slice and evidence, not just a screen. Foundation can proceed separately from financial policy resolution once implementation is requested.

| Milestone | Deliverable / requirement IDs | Dependencies | Exit gate |
|---|---|---|---|
| M0 Discovery | This pack, source traceability, ERD, access and decision register | Supplied inputs | Planning artifacts reviewed; decisions assigned owners; unresolved items remain visible rather than fabricated |
| M1 Foundation | Verified runtime/lockfiles, official auth, protected shell, abilities, MySQL/Redis harness, CI; PR01 | Version evidence, D15 for access release | Actual build/type/lint and auth/policy tests pass; MySQL connection/migrations verified; DEMO seeds isolated |
| M2 Inventory | Society/block/size/plot setup, views, history and exclusive occupancy; PR02,PR04 | M1; D01,D03 | Two-session races and DB constraints prove one winner; invalid transitions fail atomically |
| M3 CRM and terms | Customers, private docs, assignment, booking approval, snapshots; PR03,PR05 | M2; D02,D04,D12 | IDOR and upload tests pass; catalog edits cannot mutate accepted contract; approved examples reconcile |
| M4 Collections | Schedule, receipts, allocations, reversal, statement/print; PR06–PR08 | M3; D05–D08 | MySQL races/idempotency and signed financial fixtures pass; statement/cashbook controls reconcile |
| M5 Office finance | Accounts, openings, expenses, owner and commission lifecycle; PR09–PR11 | M4; D09–D11,D13 openings | Every accrual/payment traceable; duplicate payouts prevented; refund/clawback examples pass |
| M6 Operations | Manual reminders, dashboards, aging, reports, calculator, import/export; PR12–PR15 | Relevant M2–M5 data; D06,D12,D13 | Filter/as-of totals match source events; exports and notes respect access; legacy cutover reconciled if required |
| M7 Readiness | Direct-production cutover, restore drill, security/performance evidence, runbooks | M1–M6; D14 | CI passes; production compatibility proven; backup captured; named business/finance smoke checks pass; recovery targets demonstrated; no unresolved release-blocking rule |

Current progress: foundation and recovery screens are implemented; the first M2 catalogue slice follows accepted D01 (one configurable society/office per installation). Profile, blocks, sizes, plot setup/browsing and duplicate-plot MySQL concurrency are implemented. M2's reservation/occupancy exit gate is still open under D03; catalogue uniqueness must not be confused with active-allotment safety. See [inventory execution evidence](INVENTORY_STATUS.md).

Modules depending on unapproved policies remain gated; completion of unrelated slices does not authorize their defaults. Optional GL, messaging and SaaS require separate scope decisions, ADRs and estimates.

2026-11-03 continuation: the [interface/CRM slice](CRM_UI_STATUS.md) starts M3's contact management and M6's manual follow-ups/operational dashboard. Minimal contact records, assignment scoping, tasks and search are implemented; full identity documents, booking/contract terms and financial reporting remain gated. The interface now follows the supplied prototype rather than a staged starter dashboard.

The [reservation execution plan](RESERVATION_EXECUTION_PLAN.md) decomposes the remaining M2 gate into policy questions, transaction/locking design, proposed files and concrete acceptance tests. Filtered catalogue CSV export is implemented ahead of broader M6 reporting; it does not imply completion of imports or financial reports.

2026-11-13 continuation: the [export slice](EXPORT_STATUS.md) adds CSV/Excel exports for every list/report and a super_admin-only full data export (zip of CSVs). Import (PR15b) and opening balances (PR15c) remain outstanding.

2026-11-14 continuation: the [import slice](IMPORT_STATUS.md) adds a dry-run CSV import for customers, plot owners, plot inventory and confirmed bookings. Opening balances (PR15c) remain outstanding.

2026-11-14 continuation: the [opening balances slice](OPENING_BALANCES_STATUS.md) adds a configurable legacy cutover date and opening customer/owner/commission balance records. Activation does not post to the live ledger in this slice; the exact posting rules need user sign-off before wiring into payout/commission calculations.

2026-11-15 continuation: the [restore drill and deployment checklist](RESTORE_DRILL_AND_DEPLOYMENT.md) defines the M7 direct-production procedure. D14 and D15 are resolved. The procedure has not yet been executed against production; server runtime compatibility, CI/CD secrets, mailbox delivery and recovery evidence remain open gates.

On 2026-11-02 the user selected immediate staff-attributed manual holds and authorized-admin-only release/block. The [hold slice](HOLD_STATUS.md) implements this subset without an approval queue, expiry worker or customer placeholder. M2's final allotment/booking conversion gate remains open; do not mark the entire milestone complete from hold tests alone.

## Concrete verification plan

Tests listed here are planned, not executed. All integrity tests use actual MySQL 8.4 InnoDB in CI, not an SQLite substitute. Unit tests may avoid a database for pure decimal/date functions.

| Test group | Method and expected assertion | Release gate |
|---|---|---|
| Schema | Fresh migrate plus rollback/reapply in disposable MySQL; verify engine, strict mode, utf8mb4, FKs, generated uniqueness, CHECK behavior and decimal limits | M1/M2 |
| Plot race | Two separate DB connections/processes synchronized at a barrier attempt reservation/allotment on same plot; commit both; exactly one exclusive active occupancy and at most one active allotment | M2 |
| State/expiry | Hold conversion races with expiry/release; blocked plot rejected; history and occupancy agree; same transition retried once only | M2 |
| Receipt idempotency | Parallel same-key same-payload requests yield one receipt, reference, cash effect and audit result; changed payload with same key conflicts; crash before commit leaves no partial effects | M4 |
| Allocation race | Separate transactions allocate the final receipt availability or installment remainder concurrently; sum never exceeds either limit; cross-booking/scope allocations fail | M4 |
| Deadlock/retry | Controlled opposite lock contention validates bounded retry and stable key; exhausted retry returns actionable error; no duplicate receipt/movement | M4 |
| Finance arithmetic | Signed examples cover fractional percentage, non-divisible schedules, approved residual, full-payment/partial/advance cases and decimal overflow; exact sums without tolerance fudge | M3/M4 |
| Dates | Approved month-end/leap-day, grace/holiday and Karachi midnight cases; UTC event and business-date filters agree; no JS rollover assumptions | M4 |
| Reversal | Full/partial as approved; repeat/concurrent reversal, allocation unwind, refund, cheque bounce and audit failure rollback; original immutable and controls reconcile | M4 |
| Entitlement/commission | Signed trigger/base snapshots, repeated source events, payout race, cancellation/refund after settlement and approved clawback; no unearned payable | M5 |
| Authorization | Every sensitive endpoint: unauthorized roles, foreign IDs/scope, changed membership, exports/jobs/downloads; sensitive fields absent from unrelated Inertia props | Each slice |
| Browser | Login, plot search/conflict, assigned customer, approval, receipt, statement and reversal; mobile/keyboard, validation, retry and unsaved edits | M1–M7 |
| Reports/import | As-of fixtures before/after reversal, opening movements, agent/block/size filters, export equality, dry-run import, duplicate replay and spreadsheet injection protection | M6 |
| Recovery/load | Restore DB+files+keys into an isolated temporary environment; verify financial control totals and private access; measure representative concurrency/latency budgets | M7 |

Concurrency harness must use genuinely separate sessions with coordinated barriers and committed setup. A test wrapped in one framework transaction or sequential HTTP calls cannot prove races. Capture final row counts, sums and error classifications; repeat controlled schedules, not timing-only sleeps. No test may connect to production.

## Per-slice definition of done

Acceptance criteria and unresolved decisions linked; migration/SQL review; server policies and validation; atomic actions with audit where needed; meaningful tests executed with command, environment and outcome; accessible empty/loading/error UI; DEMO seed data; query/index review; updated documentation; manual QA walkthrough; known limitations. Commit only after checks when requested/authorized in implementation. Do not claim tests from checklists as actual results.

## Release operations

The approved path deploys directly to production, so the release checklist requires stronger pre-deployment gates: reviewed schema plan, current recoverable backup, immutable CI-built assets, maintenance mode, scheduler health and read-only auth/finance smoke checks. Recovery rehearsal uses an isolated local or temporary environment and proves the agreed RPO/RTO, including who can restore keys and verify financial balances. Daily backup existence alone is insufficient. Production UAT covers end-to-end plot-to-statement plus correction, owner and commission paths using controlled records. The finance approver reconciles charges, collections, allocations, unallocated balances, cash movements and payables before normal office use begins.
