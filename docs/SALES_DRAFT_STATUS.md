# Sales desk: booking drafts

Implemented 2026-11-03 following the instruction to continue implementation. This is a non-binding proposal workspace; final booking/allotment and financial posting remain separate. [Index](README.md) · [Requirements](PRODUCT_REQUIREMENTS.md) · [Decisions](FINANCIAL_DECISIONS.md) · [CRM](CRM_UI_STATUS.md) · [Holds](HOLD_STATUS.md).

## Authority and boundaries

Current terms addition: [proposed pricing and installment schedules](TERMS_STATUS.md) are implemented with admin discount controls and explicit policy snapshots. Final contract execution, verified collections and allocation remain pending.

Current addition: [single/joint proposed purchasers](PURCHASERS_STATUS.md) are implemented on saved drafts, including exact shares and independent history. References below to future legal-party work concern final contractual snapshots, verification and transfers.

**Agreed requirements:** S1/S2 require customer-to-plot sales workflows, PKR precision, server authorization and auditability. S4 supplies the visual reference. D01 selects one configurable society/office per installation. The user authorized continued implementation; original inputs remain preserved.

**Technical implementation:** a draft connects an existing customer contact and plot with a positive proposed PKR price and optional notes. It has a creator, actor-scoped creation UUID/hash, integer version, catalogue price snapshot and append-only version events. A contact is not a legal purchaser declaration. Multiple drafts may reference one plot; no exclusivity is claimed. `DRAFT-{id}` is an internal reference, not approved booking/receipt numbering.

**Updated authority:** The subsequent U3 response authorizes agent final booking with down payment plus documents, admin-controlled discounts and correction approval, accountant expenses and configurable financial rules. The user delegated purchaser design; single/joint shares and separate audited transfers are the selected technical direction. See [current operating rules and delivered configuration/identity](OPERATING_RULES_STATUS.md). This draft implementation remains non-binding. Legal-party, contract, receipt and settlement engines still need implementation and configured examples; optional GL, automated messaging and SaaS remain excluded.

## Staff workflow

1. Add a contact and catalogue plot in the existing workspaces.
2. Open **Sales desk → New booking draft**, select the customer contact and plot, enter a full proposed price and optional notes, then save.
3. Selectors show up to 30 matches. Expand **Find a different customer or plot** to narrow by name/plot number before editing. Searches reload the form; unsaved changes trigger the normal navigation warning. Existing selections remain available beyond the first result set.
4. Review current plot hold context and its manual-hold link. Creating a draft does not change hold state, allocate a plot, approve a discount or post money.
5. Reopen to revise terms. Version history retains exact quote/snapshot values and actor references. Stale versions conflict. Notes remain current-only and are not copied into history.
6. The overview displays a scoped draft count and sales-desk link. Search the paginated list by customer name or plot number; history is also paginated.

There is no final confirmation/payment action in this slice and no generated balance, receivable, owner entitlement or commission.

## Integrity, access and privacy

### Booking preparation update — 4 November 2026

Saved proposals now include a responsive seven-step [preparation panel](../resources/js/components/booking-preparation.tsx), powered by a read-only [server assessment](../app/Domain/Sales/BookingPreparation.php). It checks CNIC/address presence, configured document types, current plot hold access and booking-settings completeness. It exposes status text rather than private identity values. Current customer assignment and sales/inventory abilities govern access. Links open customer or plot review. Editor inputs are disabled during saving to prevent typing being lost when the saved version replaces the form.

Traceability: U3 requires documents and down payment, permits agent final booking, reserves discounts for admin and delegates purchaser design. Upload presence does not prove authenticity, signatures or cleared funds. Legal purchasers, verified receipts and final contract/installment plans remain explicitly pending. No finalization button, financial posting, allocation or fabricated completion percentage is introduced. Assessment reflects saved data at the displayed UTC time; future finalization must recheck rules under the common plot occupancy lock and snapshot the applied policy version. No migration or production business-data changes were needed.

Update validation: six focused MySQL sales tests passed (109 assertions), covering assignment scope, private-data minimization, missing setup, document presence and payment remaining pending despite uploaded evidence. Frontend lint/format, TypeScript and production build passed. Current browser/static-analysis results are recorded in the operating status.

The additive [migration](../database/migrations/2026_11_03_000200_create_booking_drafts.php) creates `booking_drafts` and `booking_draft_events`: restrictive FKs, positive quote/version checks, unique actor/creation key and unique draft/version events. Existing customer, plot and hold rows are retained. Money uses MySQL `DECIMAL(18,2)`, validated decimal-string input, BCMath normalization and string-based UI formatting.

The [domain action](../app/Domain/Sales/BookingDrafts.php) locks a fresh actor, selected scoped customer, plot and existing draft in one transaction with bounded deadlock retries. It rechecks abilities/current assignment. Identical creation replay returns the original visible record; changed payload conflicts. History failure rolls back the draft. No-op edits create no event. The snapshot is captured on initial selection/changing the plot; unrelated edits never silently reprice it. History is append-only through the application model, not database-administrator tamper resistance.

`sales.drafts` is granted to super_admin/admin/sales_agent; reads/writes also require `customers.view` and `inventory.view`. Visibility follows current customer assignment unless `customers.view_all` is granted. Reassignment changes access immediately, retaining creator/history. Accountant receives no grant. Seed changes are additive. Creation keys/hashes are hidden from JSON. No identity, banking or contact snapshots enter history; avoid sensitive information in notes. Retention/document rules remain D12.

The [controller](../app/Http/Controllers/BookingDraftController.php) implements bounded lookups and paginated lists/history, with existing auth/verification/CSRF and write throttles. The [list](../resources/js/pages/sales/index.tsx) and [editor](../resources/js/pages/sales/edit.tsx) reuse office components, responsive layouts, labelled native inputs, errors and unsaved-change protection. No dependency or framework change was needed.

## Verification and release gates

The five focused MySQL draft tests passed (87 assertions), covering precision/snapshot stability, replay/changed payload, assignment scope/reassignment, stale versions/no-op, invalid values/tampering, permission denial, literal search and audit rollback. Guarded rollback/reapply of all ten migrations passed only on disposable `estateflow_test`; application migration was additive and existing user data preserved.

The complete backend suite passed: **103 tests total, 102 passed, one explicit Redis skip; 879 assertions**. PHPStan reported zero errors; Pint, frontend formatting/lint, TypeScript and the production build passed. Both new desktop/mobile sales scenarios passed (2.0 minutes), covering creation, revision, snapshot display, unchanged hold context, both historical prices, list search and viewport overflow. The mobile screenshot was visually inspected. Actual local-app sign-in, overview sales link, list and editor passed a separate Chrome smoke check without creating business records. Original source hashes match their recorded discovery values.

The final complete Chrome browser suite passed **all 12 scenarios in 5.4 minutes**, across desktop and mobile: authentication, keyboard/access recovery, CRM/follow-ups/search, inventory/manual holds and sales draft creation/revision/history/search. Desktop and mobile draft screenshots were visually inspected. All 155 relative documentation links resolve. The application is left running on `http://127.0.0.1:18080`, with the sales desk at `/sales`.

Redis, remote CI, deployment/restore, approved contract lifecycle and financial concurrency/idempotency remain separate release gates. Existing hold races cover occupancy; draft tests do not prove future allotment/payment correctness. Early verification caught an unbuilt new-page manifest and a malformed update fixture carrying a creation-only field; assets were built and the fixture corrected before the final passing runs.
