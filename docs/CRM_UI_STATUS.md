# Customer workspace and interface rebuild — 2026-11-03

[Index](README.md) · [Requirements](PRODUCT_REQUIREMENTS.md) · [Scope and decisions](FINANCIAL_DECISIONS.md) · [Manual holds](HOLD_STATUS.md)

## Delivered scope

Following the user's feedback that the starter interface did not reflect the supplied prototype, the application now uses S4's navy navigation, blue actions, light office canvas, compact metrics, panels and table treatment. The original prototype remains untouched. The dashboard's staged marketing/placeholder cards have been replaced by real queries: plot totals, active holds, blocks, scoped customer count, open/overdue manual follow-ups, block distribution and recent inventory activity. No financial totals or invented trends are shown.

The shell has grouped operations/administration navigation, working plot/customer search, Karachi business date, a consistent brand and mobile navigation. The sign-in/reset/verification layouts share a navy brand panel on desktop and a compact mobile layout. Inventory uses the same visual system, colored hold badges and accessible create/edit dialogs instead of inserting the edit form into the middle of the catalogue. Unsaved customer/plot edits require confirmation before closing a dialog or leaving the page; untouched dialogs close directly.

Functional remaining implementation started with CRM and manual follow-ups:

- Customer directory with contact search, staff filtering, pagination, create dialog and profile links.
- Customer create/edit with name, optional phone/email, admin-controlled staff assignment and activity history. No customer deletion endpoint.
- Customer profiles with contact actions, staff attribution, follow-up scheduling/completion and a paginated follow-up history.
- Follow-up desk with open/today/overdue/completed filters, scoped customer links and Karachi business-date handling. No messages are sent.
- Workspace search for plot numbers and customer names, restricted to the caller's authorized records, with a maximum of 20 results per group.
- Dashboard shortcuts lead to actual implemented workflows, rather than disabled/fake modules.

## Permission and privacy boundary

`customers.view` and `customers.manage` are granted to admin, super_admin and sales_agent. Admin/super_admin additionally receive `customers.view_all` and `customers.assign`. Agents can create records for themselves and access/update only their current assignments. Lists, profiles, global search, dashboard counts/tasks, follow-up lists and completion all enforce that boundary server-side. Reassignment changes access immediately; creator/completer attribution remains. Accountants receive no CRM grant in this slice.

Contact fields are the minimal base from S1/S2/S4, not a full identity/KYC implementation. CNIC, identity attachments, joint purchasers, transfers, identity matching/retention and legal documents remain D02/D12 decisions. Do not put identity numbers in notes. No contact uniqueness assumption or automatic merging is introduced. Follow-ups follow the customer's current assignment, and completion preserves the actor/time. Internal notes are not an outbound messaging feature.

## Schema and integrity

The additive [customer workspace migration](../database/migrations/2026_11_03_000100_create_customer_workspace.php) adds `customers`, `customer_activities` and `follow_ups`, with indexed assignment/name and due/completion queries and restrictive foreign keys. It changes no existing society, plot, hold or financial rows. Normal migrations and additive role grants were applied to the known local application database; its existing user-entered society/block details were retained. Browser fixtures remain isolated to `estateflow_test`.

Customer creation uses an actor-scoped creation UUID and payload hash. Repeat identical submissions return the original record; changed payloads conflict; keys/hashes are hidden from JSON. Creation and updates lock the actor and relevant customer in a transaction, recheck permissions and atomically append history. History stores changed field names and assignment IDs, not raw contact snapshots. An audit outage rolls back creation. Completion locks the customer then follow-up, rechecks current assignment and creates at most one completion event. Follow-up creation is a normal validated form operation; no financial idempotency guarantee is claimed for it.

The [CRM domain action](../app/Domain/CRM/Customers.php), [customer controller](../app/Http/Controllers/CustomerController.php) and [follow-up controller](../app/Http/Controllers/FollowUpController.php) contain the server operations. The full future financial ERD remains a proposal; these tables do not imply implementation of contracts, installment schedules or customer financial statements.

## Verification

Final full backend run: **98 tests, 97 passed, one explicit Redis skip; 790 assertions**. PHPStan passed with zero errors. Pint, frontend formatting/lint, TypeScript and production build passed. All ten desktop/mobile browser scenarios passed in 2.9 minutes, covering authentication/recovery, existing inventory/holds and the new customer create/edit/follow-up/search workflow. After the final layout/navigation/accessibility refinements, both targeted mobile scenarios passed again (2 tests, 1.8 minutes).

The initial search assertion expected one name match for `0` while both fixtures had phone numbers containing `0`; the application correctly searches both names and contacts. Correcting the fixture yielded nine passing CRM tests (190 assertions). A later full run exposed an intermittent readiness timeout in the original duplicate-plot harness. Its worker waited 20 seconds although the parent allowed 25 seconds for worker readiness. Worker/parent/process budgets now use 60/45/90 seconds respectively, with an early-exit diagnostic; database barrier coordination and the one-winner/one-row/audit assertions are unchanged. The isolated race passed before that harness correction, and the complete final suite passed afterward. These budgets measure test bootstrap coordination, not application response-time targets.

CRM coverage includes customer scope across list/detail/edit/search/dashboard, agent assignment tampering, admin reassignment, ungranted-role denial, creation replay/changed-payload conflict, no raw contact audit snapshots, audit rollback, manual follow-up scope, repeat completion, stale assignment rejection, invalid dates and Karachi midnight/today/overdue boundaries. Screenshot checks inspect the real local dashboard; no real customer fixtures are inserted into that database.

Guarded rollback/reapply of all nine migrations passed only on the disposable test schema. Desktop and mobile customer profiles were visually inspected. The mobile follow-up form is now placed before the non-interactive activity timeline, long page headings can wrap, header search occupies a usable mobile row, nested routes retain sidebar context, and Radix plot dialogs retain their generated accessible title association. Redis, remote CI and production recovery remain unverified gates.

## Remaining implementation

Subsequent slice: [sales desk booking drafts](SALES_DRAFT_STATUS.md) now implements non-binding proposals, snapshots and version history. Final booking/allotment and financial gates below remain unresolved.

This is the interface/CRM/manual-task slice, not the entire ERP. Booking terms and allotment conversion remain gated by D02/D03/D04; schedule/calendar/rounding by D05/D06; receipts/allocations/refunds by D07/D08; expenses/payout approval by D09; owner/commission accrual by D10/D11; full identity/documents by D12. No source prototype percentage, float computation, booked/sold label or mock balance sheet was promoted into an approved financial policy. Optional GL, automated messaging and SaaS remain outside MVP absent approval.
