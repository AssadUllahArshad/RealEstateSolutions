# Final booking and down payment allocation

Follow-up: [branded receipt and booking printouts](PRINT_DOCUMENTS_STATUS.md) are now implemented. The earlier print gap below is superseded by that report.

Implemented 2026-11-04. This supersedes the final-booking gap in [receipt recognition status](RECEIPT_RECOGNITION_STATUS.md). Company/society presentation follows the [branding pack](branding/README.md).

## Authority and boundaries

The user's approved operating rules permit agents to finalize bookings after both down payment and documents are satisfied. `sales.confirm` is explicitly granted to sales agents, admin and super admin; the domain also checks the actual role. An accountant cannot confirm by receiving an accidentally assigned permission. Customer scope remains current assignment for agents and existing authorized admin scope.

This slice confirms a booking and its agreed schedule. It does not register legal title, issue a governmental transfer, collect future installments, calculate late charges, recognize landowner/commission liabilities, refund cash or implement cancellation. Optional double-entry GL, automated messaging and SaaS tenancy remain outside MVP.

## Staff workflow

1. Save the sales proposal, legal purchasers and complete private identities. Purchaser shares must total exactly 100% and CNICs must be distinct.
2. Save current pricing and payment terms, using explicit configured minimum down payment, frequency, rounding and date rules. Admin discount authorization is retained from the existing terms workflow.
3. Upload the primary contact's configured document checklist, including a signed booking form. Staff must actually inspect identity, consent, signatures and payment evidence; merely possessing an uploaded document is not automated document verification. Joint purchasers each require complete distinct identity profiles; this slice retains the existing primary-contact document checklist and does not invent a separate joint-signature checklist.
4. Have authorized finance staff recognize the customer's receipts. Pending/reversed receipts cannot fund a booking. A deposit-recognized cheque follows the explicitly captured collection policy; clearance is not silently imposed as a different rule.
5. Open **Review & confirm booking** from the proposal. Select recognized receipts belonging to the sales contact and enter exact PKR amounts from each. The allocation must equal the entire saved *planned down payment*, not merely the configured minimum. Funds from other customer profiles, including joint purchasers, require a future authorized payer/credit transfer workflow rather than implicit cross-customer use.
6. Check the explicit verification attestation and confirm. The server records today's Asia/Karachi business date; no booking backdating rule is invented. Remaining receipt credit stays unallocated. A distinct confirmation record shows the agreed price, allocated down payment, purchaser shares and schedule after reload.

These allocation boundaries are technical proposals implemented to preserve existing terms and prevent accidental customer-credit transfers. They introduce no monetary defaults. Later installment allocation must honor the configured oldest-first/manual/hybrid rule; this slice allocates only the named booking down payment.

## Integrity and concurrency

[FinalBookings](../app/Domain/Sales/FinalBookings.php) validates fresh role, customer scope, draft/purchaser/terms versions, current booking/installment policy versions, identity, document checklist and current plot occupancy inside one MySQL transaction. It locks the installation society, customer, plot, proposal, purchasers and selected receipts. Current locking reads recheck existing bookings and prior allocations after waiting, avoiding stale repeatable-read snapshots. Amounts use DECIMAL and BCMath strings throughout.

[Migration](../database/migrations/2026_11_04_000500_create_final_bookings.php) adds unique proposal and plot booking constraints, positive exact financial constraints, a confirmation command key unique per actor, immutable event history and unique booking/receipt allocation pairs. Confirmation replay returns the same booking; changed payload with the same key conflicts. Replay hashes support current/previous app keys. Audits, allocations, consumed reservation and booking commit together or all roll back.

Reservation/blocked plot writers share the existing plot lock and now reject booked plots. A reservation allowed by current configured hold access is consumed into the booking; an admin block prevents confirmation. The manual release policy for ordinary reservations remains in place. Confirmed booking occupancy is never released by the hold-release endpoint. Available inventory excludes bookings and has a separate Booked filter/status. Catalogue edits to booked plots are blocked; block/size labels captured in the contract remain frozen snapshots.

The proposal, purchaser list and terms mutation endpoints reject updates after confirmation. Confirmed bookings and booking events reject model update/delete operations. Allocations are append-only through this slice's domain API. Receipt reversal rejects *any* booking allocation, including partially allocated receipts, until a linked admin correction/unallocation workflow exists. This deliberately avoids silently removing the down payment supporting an active booking.

## Privacy and UI

Confirmed terms, purchaser names/shares, inventory descriptors, policy snapshots and document IDs/types are captured. CNIC/address snapshots are separately encrypted at rest and hidden from ordinary model serialization. Request keys/hashes are also hidden. Receipt details and finance verification notes are not copied into the contract screen. Current primary-contact scope gates access to the confirmed record. HTTPS, existing encrypted Inertia history, private document storage and encrypted-backup key retention remain deployment requirements.

The [confirmation screen](../resources/js/pages/sales/confirm.tsx) requires explicit receipt selection, manually entered amounts and an unchecked attestation. It does not sum money with floating point. The server is the authority for exact totals and available credit. The [confirmed record](../resources/js/pages/sales/booked.tsx) uses the paired-logo header and responsive amount/purchaser/schedule cards. This is an internal screen; formal signed/printable contract and receipt templates are the next slice.

## Verification

Five focused real-MySQL tests passed with 98 assertions before the final presentation-only sales-list changes. They exercise agent confirmation, immutable snapshot privacy, exact partial-receipt allocation, replay/conflict, source freeze, hold restrictions, allocated-receipt reversal guard, scope/role/attestation, insufficient funds, missing documents, stale terms and complete audit rollback. Independent worker processes with distinct MySQL connection IDs verify replay and competing claims on the same receipt, including one winner when only one further down payment can be funded.

PHPStan passed at level 7. Frontend lint/format, TypeScript and production build passed before the final list-label changes; final regression/browser evidence will be appended after verification. No whole-suite green claim is implied by these intermediate results.

Final follow-up so far: full backend run reported 145 tests, 143 passed, one explicit Redis skip and one obsolete expectation that the payment checklist was still `pending`. The checklist now offers allocation as an action. Both assertions were updated, and the isolated retry plus the added joint-purchaser test passed (two tests, 30 assertions). The latter also verifies that pending receipt state cannot fund a booking, exact joint shares/private snapshots are retained, confirmed terms reject edits and the booking model rejects mutation. The entire full suite was not rerun after that expectation fix.

Frontend formatting/lint (100 files), TypeScript and final production build passed. All seventeen migrations rolled back/reapplied successfully only on the isolated test database. The application database received the additive booking migration and role grants through the guarded local helper; no actual business bookings, receipts, policies or profile names were changed. Documentation verification resolved 263 relative links. Original planning/prototype inputs remain preserved.

Final browser verification: desktop and mobile both passed (51.9 seconds). Each agent flow rejects an inexact allocation, confirms with the exact down payment, displays the permanent purchaser/payment/schedule record after reload, and redirects frozen proposal/terms URLs to that record. Both logos are visible, no client errors were recorded and the viewport overflow assertion passed. The mobile confirmed-record screenshot was visually inspected. An earlier attempt stopped because the fixture guard assumed an `e2e` environment name; the existing environment is `local` on `estateflow_test`. The corrected helper retains exact local host/port/database guards and loaded only synthetic records.

## Next slice

Add branded printable issued receipts and booking confirmations with clear current state, exact amounts, signed-document references and controlled print-only data. Follow with later installment collections, agent correction/cancellation requests finalized by admin, linked accounting corrections/refunds and ownership transfers. Opening balances, expenses, owner settlement execution, commissions and production backup/restore validation remain separate release gates.
