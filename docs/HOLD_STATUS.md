# Manual plot holds — implementation status

[Index](README.md) · [Decisions](FINANCIAL_DECISIONS.md) · [Execution design](RESERVATION_EXECUTION_PLAN.md) · [Catalogue](INVENTORY_STATUS.md)

## Approved scope — 2026-11-02

The product owner selected manual release, no approval step and authorized-admin-only changes, and delegated the beneficiary choice. Agents and admins can immediately place a staff-attributed internal hold with a required purpose. This allows site visits/internal inventory control before CRM exists, avoids collecting unapproved customer identity fields and creates no placeholder customers. Customer binding is deferred to the approved CRM/booking slice; an internal hold is not proof of purchaser entitlement.

Only an authorized admin/super_admin can release holds or create/release blocks. A held/blocked plot must be released before any replacement hold/block. No automatic expiry, duration, extension, pending approval, allotment, contract, payment or financial entitlement is introduced. Allotment conversion remains a separate decision gate. Operating staff should review old holds manually; there is no age-based automatic release.

## Implementation

- Additive migration [plot holds](../database/migrations/2026_11_02_000100_create_plot_holds.php) creates `plot_holds` and `inventory_commands`. A unique stored generated active plot key covers both reservations and blocks; released rows retain history. CHECKs restrict states/kinds and require complete release metadata. FKs restrict deletion of referenced users/plots/results.
- [Holds action](../app/Domain/Inventory/Holds.php) locks the plot first and rechecks the current actor and abilities. It never acquires the catalogue's society mutex, avoiding reversed lock order with catalogue edits. Different plots can proceed independently. Reservation/block/release, history audit and idempotency result commit atomically with bounded deadlock retries.
- An actor-scoped UUID binds a normalized operation/plot/hold/reason hash to its result. Same-key replay causes no second effect; changed details conflict. A stale release targets its original hold ID and cannot release a later holder. Releasing an already-released record is an audited-history no-op. No command cleanup/retention job is installed; preserve keys for the current lifetime pending an approved retention policy.
- [Controller](../app/Http/Controllers/PlotHoldController.php) validates inputs, requires authentication/verified email via routes and provides conflict feedback. No posted actor, expiry, customer or arbitrary state controls authority. Form conflicts redirect with errors so Inertia refreshes current state; the domain class throws an explicit conflict exception for other callers. Current scope does not expose a public API.
- [Hold screen](../resources/js/pages/inventory/hold.tsx) shows the current state, purpose, staff attribution, paginated history and explicit actions; table/grid links reflect Held/Blocked/No active hold. Only names are loaded for staff relations, with no staff emails or private customer documents. Purpose/release text is visible to inventory-authorized staff; UI instructs users not to enter CNIC/contact/private details. This is an operational internal view within the approved single-society installation, not customer-level CRM access.
- Sales agents gain only inventory view/reservation permissions; admins gain view/reservation/release/block. Catalogue/configuration/export grants stay separate. Release/block require an admin role as well as the permission. Rerunning the additive role seeder does not replace staff role assignments.

The catalogue CSV remains a catalogue export and does not claim a certified occupancy snapshot. Future allotments must share this occupancy authority and may require a reviewed schema evolution; a separate allotment-only unique key cannot exclude these holds by itself.

## Validation

| Check | Actual result |
|---|---|
| `php artisan test` on MySQL 8.4 | 86 passed, one explicit Redis skip; 525 assertions, 87 tests total |
| Additional release-audit rollback test | Passed separately after the regression run; four assertions verify rollback retains occupancy and creates no release audit/command |
| Independent-process MySQL races | Hold/hold and hold/block: one winner, one conflict. Same-key retries: one hold/event. Different plot: completes while the first plot remains locked |
| PHPStan | Passed, zero errors |
| Pint | Passed; final rerun used project-local TEMP/TMP after system temporary storage filled |
| Frontend format/lint, TypeScript and production build | Passed on the final UI, including the semantic markup adjustment |
| Guarded migration rollback/reapply | All eight migrations passed against disposable `estateflow_test`; application database not rolled back |
| Browser | Six authentication/access/recovery scenarios passed. Corrected inventory scenarios passed on desktop and mobile (2 tests, 2.8 minutes), including agent hold, admin release/block/release, agent restrictions and CSV download |
| Original source files / documentation links | All four SHA256 values unchanged; documentation links valid |
| Read-only runtime doctor | PHP 8.4.27, MySQL 8.4.13, InnoDB, strict SQL, UTC session and Asia/Karachi business timezone passed; Redis explicitly skipped |
| Real Redis, remote CI, Compose and production restore | Not executed; prior gates remain open |

The regression result predates the one additional rollback test, so it is not presented as an 88-test full-suite run. Tests cover invalid release metadata, forbidden actor/state/expiry/customer fields, revoked access, admin role plus ability enforcement, literal conflicts, exact occupancy state, changed-payload replay, stale release and audit-failure rollback. Catalogue test expectations were updated to reflect the approved new agent read permission; configuration/export/edit privileges were not broadened.

An initial HTTP test hit the existing stale Vite manifest because the new page had not yet been built. A production asset build is required before server-rendered tests reference that page. Static analysis also required replacing mixed-ID `findOrFail` lookups with single-result `whereKey(...)->firstOrFail()` queries. Neither failure is counted as a passed check.

The first extended desktop browser scenario successfully created the agent's hold, then failed because the test captured the catalogue URL before Inertia finished navigation and sent the admin to that old URL. The corrected test explicitly waits for the plot hold URL before capturing it. The same issue was avoided by stopping the redundant in-progress mobile scenario and rerunning the affected inventory file on both viewports. Six authentication/recovery baseline scenarios had already passed; their checks were not repeated. Final inventory results follow below.

The corrected two-scenario run completed successfully on the final built UI. Both screenshots were captured; desktop and mobile hold screens were visually inspected for readable state, action labels, purpose, attribution and release history. No placeholder purchaser or sale status is displayed. All eight browser scenarios have passed across the baseline run and targeted inventory rerun; this is not described as a single uninterrupted eight-test pass.

## Hold-state discovery follow-up — 2026-11-02

Added the `hold` inventory filter: `reservation` (Held), `block` (Blocked) and `none` (No active hold or block), with an empty value showing all states. It composes with existing search/block/size/sort and survives grid/table navigation. Released history never makes a plot appear held. The catalogue CSV uses the same validated query; it still does not include private hold purposes or claim a point-in-time occupancy report. Unknown state values are rejected, including prototype sale labels.

Targeted backend validation: 24 catalogue/hold tests passed on actual MySQL 8.4 with 275 assertions. PHPStan passed with zero errors; Pint, frontend formatting/lint, TypeScript and production build passed. Both extended desktop/mobile browser scenarios passed (2 tests, 1.5 minutes): held/blocked lookup and return to the no-active-hold filter after release, alongside the existing creation, role restriction and CSV download flow. Original source hashes remain unchanged and documentation links are valid. This is a targeted continuation, not a rerun of every backend or financial test. It adds no schema or permission changes.

## Installation and remaining gates

Apply migrations and rerun `RolePermissionSeeder` using the existing project-local runtime. The migration adds tables and leaves existing catalogue records intact. Back up any populated deployment before schema changes. Rollback drops hold/command history, so validate rollback only in the isolated disposable test schema; do not roll back live occupancy data.

No real customer or application demo records are seeded by this slice. Redis, remote CI, production recovery and D15 broader access-policy gates remain open. Manual holds do not depend on an expiry worker. No deployment or package installation is part of this change.
