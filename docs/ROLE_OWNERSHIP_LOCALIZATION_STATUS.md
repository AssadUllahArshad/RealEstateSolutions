# Roles, plot ownership and bilingual interface status

Implementation baseline: 2026-11-07.

## Agreed requirements

- Admin and accountant staff may perform the operational customer, follow-up, sales-draft and final-booking work available to sales agents. Existing finance and administrative abilities remain additive.
- Agents may finalize a valid booking without routine admin approval. Existing server-side eligibility, document, down-payment, scope and concurrency checks still apply.
- Each plot belongs to one of two principal owner/admin profiles so staff can distinguish the two owners' inventory.
- The interface supports English and Urdu, Data Real Estate visual identity, and light/dark appearance.

## Implemented behavior

- Admin and accountant roles inherit the current sales-agent ability set during permission seeding. Accountants also receive office-wide customer visibility for shared office work. Sensitive actions still retain their existing role/ability checks: discounts remain admin-controlled, receipt reversals remain admin-controlled, and business settings remain super-admin-controlled.
- Final booking accepts accountant, admin, super-admin and sales-agent actors when the actor also holds `sales.confirm` and passes the same current-scope and transaction invariants.
- `plot_owners` stores at most two named profiles, each linked to a distinct admin or super-admin user. `plots.plot_owner_id` assigns a plot to one profile. Inventory filters, table/grid views, CSV exports and final-booking snapshots expose the owner label.
- Creating owner profiles is an inventory-configuration action. Existing installations can migrate before profiles exist; after the first profile is configured, plot create/update requires an owner assignment.
- Owner attribution is descriptive. It does not calculate a landlord share, entitlement, settlement, commission or payout.
- English/Urdu locale selection is cookie-backed and server validated. Urdu sets `lang="ur"` and right-to-left document direction. The shell, overview, customer directory/editor, sales desk, booking-draft editor, purchaser editor, proposed pricing/installment editor, inventory catalogue/setup, plot-owner setup, plot-hold workflow, final-booking review, financial-account directory/editor, receipt preparation/issuance, issued-receipt actions, receipt list and installment ledger use the translation catalogue; Manrope and Noto Nastaliq Urdu are bundled locally.
- The light palette uses Data Real Estate gold with warm neutrals. Dark mode uses navy/blue tokens. The application uses a code-native Data Real Estate favicon and the actual company/society brand assets in the sign-in and printable views.
- A private local accountant credential is generated at `.runtime/local-accountant-login.txt`; it is excluded from source control and intended only for local testing.

## Security and integrity

- Permission inheritance copies explicit abilities; it does not bypass policies, object scope, document eligibility, database locks or audit events.
- Only an admin or super-admin user can be linked to a plot-owner profile. One admin cannot represent both profiles, and the database enforces the one-to-one link.
- The maximum of two profiles is enforced inside a locked transaction and tested against a third-profile attempt.
- Final bookings snapshot the plot-owner ID and name so later catalogue edits cannot silently rewrite historical attribution.

## Verification

- MySQL feature suite for access, locale, inventory ownership, CRM, holds, final booking, print documents and installment ledger: 58 tests and 899 assertions passed.
- All 19 migrations rolled back and reapplied on an isolated MySQL 8.4 test database.
- TypeScript type checking, formatting/lint, production asset build, Pint and PHPStan passed.
- Browser verification logged in with the generated accountant account, opened the live dashboard, inventory catalogue and new-booking screen, confirmed the owner filter/column, and confirmed dark mode tokens (`#0b1425` background and `#edf3ff` foreground).
- Browser verification confirmed the Urdu locale response, `lang="ur"`, `dir="rtl"`, customer and booking form translations, and no horizontal overflow. It also detected and fixed a cascade-layer conflict so the computed Urdu body font is now `Noto Nastaliq Urdu`; switching back to English restores Manrope.
- Browser verification also covered the live owner filter, hold-state filters and agent/accountant hold form in Urdu. Focused locale, hold and final-booking regression coverage passed with 17 tests and 231 assertions.
- The live accountant receipt workspace was verified in Urdu. Inventory configuration, receipt preparation/posting and installment-ledger regression coverage passed with 31 tests and 435 assertions. The collection privacy test was aligned with the approved accountant shared-office CRM access while retaining private fields outside the ordinary customer serialization.
- The financial-account, receipt-entry and issued-receipt interfaces now translate their core controls, safety guidance, state labels and audit headings. TypeScript checking and the 2,340-module production build pass using the project-local PHP 8.4 runtime.
- The legal-purchaser proposal, proposed pricing/installment schedule and receipt verification/issuance controls now translate their operational fields, warnings and attestations. Fixed-point ownership shares and all existing pricing, posting and recognition rules remain unchanged.
- Branded print views now inherit the selected locale, emit `lang`/`dir` attributes, use the bundled Urdu document font and translate the shared controls, principal statement, booking confirmation and receipt summaries without changing immutable amounts, identifiers or privacy exclusions.
- The shared application shell now uses a branded responsive page-header surface, clearer KPI cards, sticky translucent top navigation, RTL-aware search and sidebar selection, stronger keyboard focus states, and complete Urdu dashboard guidance at desktop and mobile widths.
- Inventory, sales and finance now share 44px form controls, rounded action surfaces, consistent filter toolbars and semantic state badges. Sales drafts use dedicated mobile summary cards, while receipt state color communicates prepared, pending clearance, recognized and reversed states in both light and dark modes.
- Customer management now uses mobile directory cards, grouped contact/identity forms and bilingual profile/follow-up controls. Business-settings sections scroll horizontally on phones, and confirmed bookings use structured purchaser, payment-source and installment panels.
- Super-admin, admin and accountant roles now have an owner operational report with current plot assignment, confirmed booking value, exact allocated down-payment/installment collections and outstanding principal. Historical booking attribution comes from the immutable owner snapshot; unallocated receipt credit is excluded, and the report does not infer settlement, entitlement, commission, profit or a balance sheet.
- Urdu RTL layout now mirrors the complete authenticated shell: desktop sidebar and active border move to the right, main content remains non-overlapping, mobile navigation opens from the right, directional icons mirror, and shared tables, dialogs, dropdowns, search fields, password controls and page-specific timelines use logical start/end alignment.

## Assumptions

- Both principal admins can currently view the whole office inventory. Owner labels and filters distinguish inventory; they do not create row-level isolation.
- The two profiles represent principal owners/admins for inventory attribution. This does not approve any financial ownership percentage.

## Needs decision

1. Supply the two principal owner names and the email/name for each linked admin account. Until then the existing plot remains visibly **Unassigned owner** and no production owner profiles are invented.
2. Confirm whether the two owner/admin users should continue seeing all plots or should be restricted to their own assigned plots. Restriction would affect dashboards, searches, exports, booking access and reports.
3. Confirm the settlement basis and percentage separately. Plot attribution must not be used as an implicit settlement rule.
4. Approve the Urdu terminology for finance, booking, cancellation/correction and legal purchaser screens before those translations are treated as official business wording.

## Next safe implementation slice

Continue with controlled booking correction/cancellation requests and the small-admin-approval workflow already agreed by the user. Refund execution, owner settlement and commission posting remain gated by explicit business rules.

