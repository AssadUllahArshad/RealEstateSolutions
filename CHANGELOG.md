# Changelog

- Added owner default and per-plot rate snapshots, immutable collection-linked owner entitlement events, exact half-up paisa calculations, final-booking rate gating, and entitlement totals in bilingual owner reports.
- Added accountant-prepared, admin-authorized partial owner payouts with immutable rejection, cash/bank/wallet evidence rules, available-balance controls, bilingual UI, and approved cashbook outflows.
- Added branded printable owner settlement statements with exact opening/activity/closing reconciliation, optional date filters, a separate pending section, and masked non-cash references.

- Added controlled booking correction and cancellation requests: staff submit with reasons, admins approve or reject once, and original contracts, allocations, receipts, and financial movements remain preserved.
- Added accountant/admin office-expense posting with immutable numbered records, account snapshots, PKR totals, date filters, Urdu UI and an explicit cashbook-only boundary.
- Replaced the receipt-only movement list with a unified cashbook of receipt inflows, accounting reversals and office-expense outflows, including filtered totals and Urdu/mobile support.
- Added access-controlled, filter-preserving UTF-8 cashbook CSV exports and branded bilingual expense vouchers with exact PKR rendering and private print security headers.
- Added a bilingual finance overview for exact period movement, account-level activity and receipt work queues without implying opening balances, bank reconciliation or general-ledger reporting.
- Added a scoped bilingual receivables worklist with exact MySQL schedule/allocation projection, overdue and due-today totals, customer/plot search and direct booking-ledger access without calculating unapproved charges.
- Added filter-preserving receivables CSV export with scoped rows, exact PKR strings, Karachi as-of dates, UTF-8 BOM and spreadsheet-formula protection.
- Added immutable admin-entered booking charges with manual-policy enforcement, exact PKR totals, assignment-scoped visibility and compensating admin waivers; charges remain separate from principal, receipts and cashbook movements.

## 2026-11-07 — Shared office roles, plot owners and bilingual theme

- Expanded admin/accountant operations to the current agent CRM, hold, sales and final-booking workflow while retaining admin-only discount/reversal and super-admin-only settings controls.
- Added exactly two principal plot-owner profiles linked to distinct admin users, plot assignment/filtering/exports and immutable booking owner snapshots without inventing settlement percentages.
- Added a private local accountant account, English/Urdu locale infrastructure with RTL support, bundled Manrope/Noto Nastaliq fonts, Data Real Estate light colors, navy dark mode and a branded favicon.
- Verified the live accountant dashboard, inventory and booking screens plus MySQL access/concurrency suites. See [role, ownership and localization status](docs/ROLE_OWNERSHIP_LOCALIZATION_STATUS.md).
- Expanded Urdu coverage through the customer directory/editor, sales desk and booking-draft editor, and fixed the font cascade so Urdu renders with the bundled Noto Nastaliq font while English retains Manrope.
- Added Urdu inventory catalogue filters, owner attribution, shared hold badges/pagination, complete manual-hold actions and final-booking confirmation guidance without changing booking or finance policy.
- Added Urdu society/block/size setup, two-owner administration, customer receipt listing and installment-ledger terminology. Updated the finance privacy regression to reflect the approved accountant shared-office CRM access.
- Added Urdu financial-account setup, receipt preparation and issued-receipt actions, including accounting safety guidance, clearance/reversal labels and audit headings; verified TypeScript and the production asset build with the project-local PHP runtime.
- Added Urdu purchaser proposals, pricing/installment preparation and receipt verification/issuance controls while preserving fixed-point shares, configured schedules, posting gates and money-recognition rules.
- Added locale-aware RTL branded principal statements, booking confirmations and receipt print views with the bundled Urdu font while preserving exact PKR values, immutable snapshots and private-field exclusions.
- Refined the shared shell and dashboard with branded page headers, stronger KPI hierarchy, sticky navigation, RTL-aware search/sidebar states, accessible focus styling and complete Urdu dashboard guidance across desktop and mobile layouts.
- Standardized inventory, sales and finance controls with larger rounded fields, clearer focus and dark-mode surfaces, compact toolbars and denser tables; added responsive sales-draft cards and state-specific receipt badges for prepared, pending, recognized and reversed money states.
- Added responsive customer-directory cards, grouped customer identity/contact forms, bilingual customer profile and follow-up controls, phone-friendly business-settings navigation, and a clearer confirmed-booking financial summary.
- Added permission-gated bilingual owner operational reports for current inventory assignment, confirmed contract value, exact allocated collections and outstanding principal, with owner/date filters and immutable booking-owner attribution; unallocated credit and settlement entitlement remain excluded.
- Corrected Urdu RTL layout across the authenticated application: the sidebar now anchors on the right, main content occupies the matching left area, mobile navigation opens from the right, tables/headings align to the logical start, and shared search, dialog, password, dropdown, timeline and icon spacing use direction-aware positions.

## 2026-11-05 — Current principal statements

- Added private branded statements from the live booking ledger with exact paid, remaining and overdue principal, installment balances and complete allocation history.
- Reused finance/agent access scope, print security headers and minimal preview audits; excluded private identities and unapplied credit. See [statement status](docs/PRINCIPAL_STATEMENT_STATUS.md).

## 2026-11-05 — Installment allocations and live ledger

- Added scoped paid/remaining/overdue principal and installment statuses, finance-only allocation forms and source history.
- Added atomic revisioned allocation batches, exact oldest-first/manual/hybrid application, replay/stale checks and independent-session MySQL competition tests.
- Unified receipt credit across final booking, installments, reversal protection and print previews. No new cash movement, late fee or monetary policy default is created. See [ledger status](docs/INSTALLMENT_LEDGER_STATUS.md).

## 2026-11-05 — Branded print previews

- Added authenticated, no-store receipt and booking confirmation printouts using both company/society logos, exact PKR strings and native browser Print / Save PDF controls.
- Added explicit pending/deposit-recognized/reversed receipt status, original booking schedule/evidence references, private-field exclusion and minimal preview audit events.
- Captured office contact snapshots for future issue/confirmation; preserved historical records without inventing issuer details. See [print status](docs/PRINT_DOCUMENTS_STATUS.md).

## 2026-11-04 — Agent final booking and exact down payment allocation

- Added confirmation review, explicit recognized-credit selection, staff attestation and permanent agreed contract/purchaser/document snapshots.
- Added atomic booking/allocation/audit writes, idempotent confirmation and booked plot exclusivity across holds and proposals.
- Froze confirmed proposal edits and blocked reversal of receipts funding confirmed bookings. Added Booked inventory state/filter and confirmed sales records.
- Added MySQL independent-connection credit/replay tests; see [final booking status](docs/FINAL_BOOKING_STATUS.md) for verification and remaining print/correction workflows.

## 2026-11-04 — Configured receipt recognition

- Added explicit main-admin posting controls, permanent number sequences, date/routing/reference checks and accountant/admin verification.
- Added immutable receipt credits, distinct cheque recognition/clearance states, frozen source preparations and admin accounting reversal with no refund execution.
- Added scoped issued-receipt/audit/movement screens and actual MySQL posting races. See [receipt recognition status](docs/RECEIPT_RECOGNITION_STATUS.md). Final booking and installment allocation remain separate.

## 2026-11-04 — Accounts and unposted receipt preparation

- Added admin financial-account setup/history, active destinations and a Finance navigation group.
- Added scoped customer receipt preparation with payment-method radios, exact amounts, private encrypted references/notes, policy/account snapshots and revision history.
- Added concurrent replay/conflict and finance-privacy tests. No recognized money, opening balance or official receipt is created. See [collection preparation status](docs/COLLECTION_PREPARATION_STATUS.md).

## 2026-11-04 — Proposed pricing and installment preparation

- Added exact fixed-PKR admin discounts, configured down-payment checks and monthly/quarterly/half-yearly/yearly/custom/full-payment proposals.
- Added deterministic dates, explicit rounding/remainder rules, policy snapshots, stale-edit protection and atomic terms revision history.
- Added a responsive pricing/schedule screen linked from saved drafts and preparation cards. See [terms implementation](docs/TERMS_STATUS.md). No financial posting or final allocation is introduced.

## 2026-11-04 — Proposed legal purchasers

- Added single/joint purchaser entry for saved drafts with exact positive shares totaling 100%, private customer identity checks and duplicate-party protection.
- Added independent purchaser revisions, atomic change history and current-assignment privacy. Updated responsive sales preparation and customer review warnings.
- See [purchaser implementation status](docs/PURCHASERS_STATUS.md); final booking, transfers and receipt posting remain separate workflows.

## 2026-11-04 — Sales preparation and responsive guidance

- Added a server-derived booking preparation checklist for saved proposals, with customer/plot review links and clear missing-setup/action/pending states.
- Kept private identity values out of checklist responses and separated uploaded payment evidence from verified money.
- Improved responsive sales guidance and prevented in-flight edits from being lost during draft saves. See [sales status](docs/SALES_DRAFT_STATUS.md).

## 2026-11-03 — Operating rules, business settings and private identity

- Recorded the user's booking, approval, expense, owner/commission and hosting answers, with delegated joint-purchaser design and explicit remaining rule inputs.
- Added six main-admin business-policy sections with exact rates, incomplete-setup visibility, stale-edit protection and transactional version history.
- Added encrypted customer CNIC/address and private, scoped photograph/document upload/download with validation, audit and failed-write file cleanup. See [operating rules status](docs/OPERATING_RULES_STATUS.md).

## 2026-11-03 — Sales desk and booking drafts

- Added customer/plot booking drafts, exact proposed PKR quotes, catalogue snapshots and visible version history.
- Added current-assignment scope, creation replay, stale-version conflicts and atomic history; drafts do not allocate plots or post money.
- See [sales draft status](docs/SALES_DRAFT_STATUS.md) for validation and remaining decisions.

## 2026-11-03 — Prototype-aligned workspace and CRM

- Rebuilt the navy/blue shell, sign-in layout, live operational dashboard and inventory presentation; added functional header search and edit dialogs.
- Added scoped customer create/edit/assignment, profiles/history, and manual follow-up scheduling/completion with due-state filters.
- Added transactional creation replay/history controls, server-side access scoping and MySQL/browser coverage. See [CRM/UI status](docs/CRM_UI_STATUS.md) for actual evidence and unimplemented financial modules.

## 2026-11-03 — Windows local testing launcher

- Added double-click Start/Stop scripts using the existing project-local PHP 8.4/MySQL runtimes, private one-time test-admin provisioning and guarded local-only startup.
- Verified sign-in, protected inventory/setup screens and repeat Start/Stop/restart. Kept existing XAMPP installations, framework versions, dependencies and system PATH unchanged. See [local testing](docs/LOCAL_TESTING.md).

## 2026-11-02 — Manual inventory holds

- Added current hold-state filters shared by inventory views and CSV export; released history is excluded from active-held/blocked results.

- Agents/admins can confirm internal manual holds immediately, with staff attribution and a required purpose. No expiry or approval queue.
- Authorized admins alone can release or block. Shared database uniqueness prevents overlapping holds/blocks; atomic audit and idempotency protect retries and stale releases.
- Added current hold links, paginated history, real MySQL race tests and agent/admin desktop/mobile workflow coverage. See [hold status](docs/HOLD_STATUS.md) for actual validation and remaining gates.

## 2026-10-31 — Inventory CSV export

- Added permission-controlled filtered catalogue CSV downloads with exact decimal prices, bounded batches, spreadsheet formula protection and desktop/mobile download coverage.
- No reservation, allocation or financial posting rules added by that slice. See [inventory status](docs/INVENTORY_STATUS.md).

## 2026-10-31 — Recovery and service checks

- Accepted one configurable society/office per installation and added society profile, custom blocks/sizes, plot catalogue, exact list prices, table/grid browsing, filters and audit history. Reservations/allotments remain disabled pending lifecycle decisions.
- Added MySQL singleton/uniqueness/positive-value constraints, explicit inventory abilities and a coordinated two-process duplicate-plot test. Isolated browser file-cache paths from the application's cache.

- Added standalone recovery pages for access denial, missing pages, expired sessions and rate limits, plus safe production server errors; JSON contracts, HTTP status and retry headers are preserved.
- Made MySQL session timezone explicitly UTC and verified it on MySQL 8.4, with Asia/Karachi retained for business dates.
- Added a read-only `estateflow:doctor` command with explicit Redis skip/failure reporting and no connection-secret output.
- Expanded backend and browser coverage for recovery and diagnostics. Inventory scope/lifecycle decisions remain pending; no business schema or financial policies changed.

## 2026-10-30 — Foundation slice

- Preserved four supplied inputs and completed the linked planning pack.
- Added official Laravel React/Inertia starter, locked dependencies, isolated project-local PHP 8.4 and MySQL 8.4 runtimes, and local/test environment separation.
- Added staff-only sign-in, optional 2FA, responsive EstateFlow workspace, protected team views, explicit foundation abilities and transactional audited role changes.
- Added safe request correlation, Karachi business clock, retained staff attribution, trusted-console provisioning and guarded synthetic browser fixtures.
- Added MySQL integration tests, browser smoke coverage, Redis probe test and CI/service definitions. Actual results and remaining gates are tracked in [foundation status](docs/FOUNDATION_STATUS.md).
- Inventory, finance, GL, messaging and SaaS remain outside this foundation implementation.
