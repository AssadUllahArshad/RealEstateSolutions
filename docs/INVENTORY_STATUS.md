# Inventory catalogue slice

Date: 2026-10-31. [Index](README.md) · [Accepted scope](ADRS/0002-single-society.md) · [Remaining decisions](FINANCIAL_DECISIONS.md)

## Implemented boundary

One society/office per installation, with editable profile/contact details, arbitrary block codes/names, positive custom plot-size labels and decimal marla areas, and plot catalogue entries. Another society uses its own installation/database and configures its own catalogue. No hardcoded society name, blocks or size options are seeded in the application database.

Screens: `/inventory/setup` manages the profile, blocks and sizes; `/inventory` lists, filters, sorts and paginates plots with table/schematic-grid views and recent catalogue activity. Authorized staff can add/edit plots and optionally record a list price. Forms retain validation errors and warn before discarding unsaved edits. No master-record deletion endpoint is provided.

Sale availability is deliberately absent from the schema and UI state machine. No plot is automatically available, reserved, allotted, booked or sold. The page explains that catalogue entries do not imply operational availability. D03 is still required for reservations/expiry/allotment transitions and their occupancy constraint. D02 and financial policies remain unresolved.

## Schema and correctness

Migration `2026_10_31_000100_create_inventory_catalogue` adds societies, blocks, plot_sizes, plots and inventory_events. A MySQL CHECK limits the profile to ID 1; a null initial name represents incomplete setup, not a fictitious society. Blocks reference that profile. Unique block codes and size labels are case-insensitive under the configured collation. Plot numbers are unique per block. Foreign keys prevent dangling references; positive-amount CHECKs protect size and optional price columns.

Prices are DECIMAL(18,2), accepted/serialized as decimal strings and displayed with string grouping rather than JS Number conversion. They are catalogue references, never approved contract terms. Areas are DECIMAL(8,2) marla; no unapproved marla-to-square-feet conversion is implemented. Identifiers accept society-specific formats within documented length limits.

Mutations use a transaction and a common society-row lock, fresh authorization and append-only application audit snapshots. Retrying a deadlock uses a fresh model clone. Duplicate index errors become validation conflicts. Repeating an unchanged update produces no additional event. Catalogue updates remain auditable; accepted sales contracts must snapshot catalogue terms in their later module.

The global catalogue lock is a deliberate simple correctness tradeoff for this office-scale setup slice. It is not a proposed global lock for future collections or reservation throughput. Evaluate representative data and concurrent usage before import/performance commitments.

## Authorization and privacy

`inventory.view` controls browsing; `inventory.manage` controls plot writes; `configuration.manage` controls society/block/size setup. All are initially granted to main admin only. No privilege is inferred from admin/accountant/agent role names. Requests cannot set society IDs, actors or lifecycle status. Recent-activity responses omit stored before/after snapshots and company contact fields; the protected setup page exposes office contact details. There is no tenant selector or UI role switching.

## Verification

Backend suite: **74 passed, 1 skipped** (75 total, 424 assertions), including real MySQL constraints and two independent PHP/MySQL writers synchronized at a coordination barrier. The concurrent duplicate-plot test proves one successful creation, one conflict, one plot row and one creation event. It does not claim active-allotment concurrency, which is unimplemented.

Other tests cover singleton enforcement, custom/fractional sizes, identifier normalization, same number in different blocks, amount precision/overflow, missing references, tampered actors/status, read-only access, filters/pagination/grid props, update history, no-op updates and audit-failure rollback. The Redis worker remains the explicit skip.

Browser testing exposed an empty-filter serialization bug: PHP's empty array became a JS array, allowing its inherited `sort` method to masquerade as a selected filter. The controller now always returns an associative object with explicit filter defaults. Regression tests verify the defaults and require exactly one matching record before switching to the grid. A literal `0` search is also covered. Local development-server assertions allow 30 seconds after traces showed a successful login plus dashboard load narrowly exceeding the earlier 15-second window; this is not a performance/SLO certification.

PHP static analysis, frontend formatting/lint, TypeScript checks and production build passed. The new migration and role grants were applied only to the known project-local application database; its profile remains blank until configured. All eight desktop/mobile browser scenarios passed on the corrected build. Rollback and reapply of all seven migrations passed against the guarded disposable test database, including the populated catalogue tables. All four supplied source hashes still match and all 15 documentation files have valid relative links.

| Check | Actual result |
|---|---|
| `php artisan test` | 74 passed, 1 explicit Redis skip; 424 assertions |
| Real MySQL independent-writer test | One create, one conflict; one plot and one creation event |
| `php vendor/bin/phpstan analyse --no-progress` | Passed, zero errors |
| `npm.cmd run types:check` | Passed |
| Frontend formatting/lint and production build | Passed |
| `npm.cmd run test:browser` | 8 passed: authentication/recovery plus society setup, custom sizes/blocks, plot create/edit, exact prices, one-result filtering and grid on desktop/mobile |
| Guarded test migration rollback/reapply | Passed for seven migrations; application database not rolled back |
| Real Redis / remote CI / Compose runtime | Not executed; existing release gates remain open |

Desktop/mobile inventory screenshots were captured; the mobile grid was visually inspected for readable names, exact amounts and absence of unintended availability badges. The initial mobile filter failure is retained above as rationale for its regression coverage, rather than hidden by a timeout change. The subsequent complete eight-scenario run passed.

Query review: FK and `(block_id, number)` indexes support parent lookups and within-block uniqueness; `(plot_size_id, number)` supports size filtering; recent activity uses primary-key ordering. Broad substring search and global price ordering require representative capacity profiling before large-import or latency commitments under D14. No performance target is claimed from this small test dataset.

## Migration/deployment use

Apply the new migration and rerun RolePermissionSeeder on a development installation before accessing the screens. Existing staff assignments are retained; main admin gains the explicit catalogue abilities. The new profile remains blank until configured. Do not run browser fixture seeders on the application database. Browser fixtures must use the isolated estateflow_test database, and file-cache data/lock paths are separated from the local application to avoid sharing permission caches across schemas.

Rollback drops the new inventory catalogue and audit tables, so it is destructive once real catalogue data exists. Use rollback only on the disposable test schema during validation; review/backup and forward migration strategy are required for a populated deployment. No existing financial tables or records are modified by this migration.

## Next slice

Update 2026-11-02: the user selected manual-release holds without approval and authorized-admin-only changes. See [manual-hold implementation](HOLD_STATUS.md) for current behavior and validation. Earlier D03/availability statements in this catalogue report describe the state before that slice; allotment/contract conversion remains gated.

### Filtered CSV export (2026-10-31)

The catalogue now has a streamed CSV download through `GET /inventory/export`. It shares the table's validated filters and stable sort and includes all matching records rather than only the visible page. The download contains block code/name, plot number, size label, exact area and exact PKR list price; it excludes contact details and audit snapshots. Catalogue records still convey no reservation or sale availability. This implements the export portion of S2 inventory import/export; import remains unimplemented.

Both `inventory.view` and the separate `inventory.export` permission are required. The additive role seeder grants export only to the existing main administrator role. Existing installations must rerun that seeder; staff assignments are retained. Downloads are rate limited to ten per minute and marked private/no-store. CSV quoting uses an explicit empty escape parameter; potentially executable spreadsheet text receives an apostrophe prefix. CSV contains exact decimal text, but spreadsheet applications may still coerce long numbers; import price columns as text when exact precision matters.

Rows are read in batches of 500. This bounds application memory but does not promise a point-in-time database snapshot across batches: concurrent catalogue edits may alter the result. A financially certified export and large-volume capacity guarantees remain outside this slice.

Validation: 13 catalogue tests passed on actual MySQL 8.4 (143 assertions), covering export authorization, invalid sorting, all 26 filtered rows beyond page size, descending ordering, exact 16-digit PKR amounts and formula escaping. TypeScript, frontend formatting/lint and production build passed. The first build picked up system PHP 8.1; rerunning with the project PHP 8.4 PATH passed. The project-local MySQL instance was restarted before database checks. Both desktop and mobile browser scenarios passed, including successful CSV downloads (2 tests, 1.9 minutes). Static analysis initially flagged mixing decimal fields with text sanitization; the sanitizer now handles text only and decimal fields pass through unchanged. Reservation expiry and approval questions remain unanswered; no reservation behavior was added.

Agree D03 lifecycle vocabulary, reservation expiry/release/extension and approval behavior before adding operational availability. Do not infer a duration or approved booking state from the prototype. D15 production delegation and Redis/remote CI gates remain open.

Final PHPStan analysis passed with zero errors; Pint passed. The full backend suite was not rerun for this export slice; the earlier 74-pass/one-skip full-suite result above is historical.
