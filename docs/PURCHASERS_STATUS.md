# Proposed legal purchasers — 4 November 2026

[Planning index](README.md) · [Sales drafts](SALES_DRAFT_STATUS.md) · [Current operating rules](OPERATING_RULES_STATUS.md)

## Authority and delivered behavior

U3/D02 delegated purchaser design; the accepted technical direction is single/joint purchasers with exact shares totaling 100%. U3/D03 permits agents to finalize once payment and document requirements are met. This slice implements **proposed purchasers on a saved draft**, not final booking, ownership transfer or financial liability.

Agents/admins with existing sales, inventory and customer access select 1–20 private customer profiles. Each profile requires CNIC/address; shares are positive decimal strings with at most four decimal places and must total exactly 100.0000 using BCMath. Duplicate customer IDs and duplicate CNICs within the purchaser set are rejected. CNIC uniqueness across all CRM leads is not imposed. Names/identity remain in customer profiles with encrypted CNIC/address; draft rows contain only customer references and shares. The 20-person limit is a technical input bound, not a legal joint-ownership policy.

The [responsive editor](../resources/js/components/draft-purchasers.tsx) shows customer selectors, explicit shares, a live integer-based total, remove/add controls, errors, saved purchaser version and latest ten revision summaries. No ownership split is supplied automatically. The sales contact is explicitly separate from purchasers. Existing customer lookup filters supply selectable records; saved accessible purchasers remain available even outside the first 30 lookup matches. Unsaved changes trigger the existing navigation guard; inputs disable during save. Draft terms and purchaser forms have independent versions so one does not reset the other's unsaved inputs.

## Integrity, access and privacy

The additive [migration](../database/migrations/2026_11_04_000100_create_draft_purchasers.php) adds `booking_drafts.purchasers_version`, `draft_purchasers` and append-only `draft_purchaser_events`. Restrictive FKs preserve references; unique draft/customer and draft/history-version keys prevent duplicates; MySQL checks enforce positive shares ≤100. Cross-row total is enforced by the [transactional action](../app/Domain/Sales/DraftPurchasers.php), not by a per-row constraint.

Saving locks a fresh actor, the draft's CRM contact and all selected customers in ID order, then locks/rechecks the scoped draft. Current permissions/assignment and identity are checked inside the transaction. Version conflicts cannot overwrite changed content. Canonically identical repeated submissions create no duplicate history. Replacement, version increment and before/after ID/share audit are atomic with three deadlock retries. Contact/plot draft revisions are separate; changing a sales contact does not silently replace proposed purchasers.

Reads recheck draft access and separately scope purchaser profiles. A purchaser assigned elsewhere appears as “Customer access changed”, with no customer ID/name/identity values, and preparation returns to “Needs attention”. Duplicate or missing current identity also invalidates preparation. History responses show only revision, actor reference, count and UTC time; stored ID/share history is not sent to the client. All routes use auth, verification, CSRF and write throttling. No new broad role permissions were added.

## Limits and next slice

Recorded identity is not verified identity or consent. Required uploads still need human verification. A final contract must snapshot legal purchaser identity, shares, approved pricing, policy versions and verified documents atomically; it must recheck current assignment, occupancy and payment rules. Current draft links remain editable and are not historical title records. Transfer requests require a later separate audited admin-finalization workflow, preserving original contracts and liabilities.

The subsequent [pricing and installment-plan slice](TERMS_STATUS.md) is now implemented: admin-controlled discounts, monthly/quarterly/other/custom plans, explicitly entered first date, configured month-end/rounding/remainder handling and exact schedule totals. No monetary/date fallback is approved. Receipt recognition and final booking remain subsequent dependent slices.

## Verification

Nine focused MySQL sales tests passed (183 assertions), covering exact joint shares, partial/invalid totals, zero/float/exponent/overprecision inputs, duplicate parties/CNICs, missing identity, customer assignment changes, foreign profiles, changed-content stale edits, identical replay and audit-failure rollback. Updated desktop/mobile sales browser tests both passed (1.9 minutes), exercising purchaser entry, server validation, saving and persisted values alongside draft revision/history, single-editor rendering and client-error checks. Screenshots were inspected at both widths. Final run results and broader regression evidence are recorded in [operating status](OPERATING_RULES_STATUS.md).
