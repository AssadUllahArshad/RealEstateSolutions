# Branded receipt and booking printouts

Follow-up: [installment allocations and linked admin-approved reversals](INSTALLMENT_LEDGER_STATUS.md) reduce receipt credit in print previews. The allocation row label includes bookings and installments. Booking confirmation printouts still show the original schedule; the separate [current principal statement](PRINCIPAL_STATEMENT_STATUS.md) shows live balances and reversal history.

Current implementation: 2026-11-05. Builds on [receipt recognition](RECEIPT_RECOGNITION_STATUS.md), [final booking](FINAL_BOOKING_STATUS.md) and the [brochure-based logo recreations](branding/README.md).

## Staff workflow

Open an issued receipt or confirmed booking and choose **Print / Save PDF**. A separate authenticated preview tab opens with both company/society logos. Choose its print button, then a physical printer or the browser's Save as PDF destination. Printing does not issue a new receipt number, change funds, allocate credit or confirm a new booking.

This uses native browser printing. It does not install a server PDF dependency, store a PDF archive, send documents externally, digitally sign a document or provide a public verification link. Staff should choose appropriate printer/destination and custody for saved customer copies. Automated messaging remains outside MVP.

## Documents and financial meaning

| Document | Included | Deliberately distinct |
| --- | --- | --- |
| Receipt | Official number, current version/state, face amount, accepted date, customer name at issue, method/reference, destination at issue, bank stage, recognized/allocated/unallocated credit | Pending cheque has no usable credit; deposit-recognized cheque explicitly lacks bank clearance; reversed receipt prominently has no valid payment credit and does not represent a cash refund |
| Booking confirmation | Confirmed date/reference, inventory descriptors, net/gross/approved discount, down payment, purchaser names/shares, exact source allocations, original installment schedule and document IDs/types | Original agreed schedule is not a live outstanding statement; confirmation is not a registered title/deed/transfer; no scanned signatures or private documents are reproduced |

Prints carry generation time in Asia/Karachi and identify themselves as current-status copies. An existing paper/PDF copy cannot update after a later CRM change; staff can reopen the authenticated preview to obtain current status. Receipt number/version and generation time provide context. There is no fabricated legal allotment number, signature, stamp, tax invoice or bank verification QR.

[Pkr](../app/Support/Pkr.php) formats decimal strings without floats. Maximum DECIMAL(18,2) precision is retained, including `9,999,999,999,999,999.99`. Credit allocation is the real booking allocation total, not an account opening balance or complete cashbook. Remaining credit is shown only for recognized receipts.

## Branding and historical issuer data

The [shared print layout](../resources/views/print/layout.blade.php) reuses the transparent [company](../public/branding/data-real-estate.svg) and [society](../public/branding/green-vista.svg) SVGs. These remain initial recreations from supplied brochure references, not recovered original artwork.

Future receipt issue snapshots now capture the saved office/society name, office address, phone and email under the existing society lock. Future booking snapshots capture the same contact fields. Only office contact details are printed; private customer CNIC/address are omitted. Historical receipt snapshots lacking issuer details show that those details were not captured. Older booking snapshots with names only continue to print those names without inventing contact details. A later office-profile edit does not rewrite an already issued/confirmed document. Existing business profile names are not silently changed by printing.

The known legacy local profile labels were separately aligned to the user's previously supplied **Data Real Estate / Green Vista Housing Scheme** names through the audited catalogue action. The helper refused other databases or unexpected existing labels. Office contacts and historical records were retained; no financial policy values, balances, receipts or bookings were rewritten. Print preview itself never updates the profile.

## Access, audit and security

[DocumentPrintController](../app/Http/Controllers/DocumentPrintController.php) uses the exact existing receipt/booking scope queries. Assigned agents cannot print another agent's customers. Accountants retain authorized receipt visibility without receiving CRM identity/document access; booking confirmation print requires the existing CRM/sales scope. Guests require sign-in and verification. Missing/unposted receipt IDs and inaccessible records are rejected.

Each successful preview appends a minimal `receipt.print_previewed` or `booking.print_previewed` customer activity with record ID and, for receipts, version/state. This logs a preview request, **not proof that a physical copy was printed**. Audit rows contain no identity values, external reference or private staff notes. The preview reads and audit insert execute within a transaction; printing does not mutate financial records.

Responses are private/no-store, noindex/noarchive, refuse framing and use a restrictive self-only CSP. All dynamic Blade values are escaped. Printouts omit private identity snapshots, encrypted staff verification/reversal notes, request hashes/keys, storage paths and document download links. The standalone page does not serialize an Inertia payload or load external tracking/fonts/scripts. Saved PDF/physical copies are outside web-cache controls.

## Layout and verification

[Print CSS](../public/branding/documents.css) supplies an A4 layout, repeating schedule table headers, row break protection, wrapping long names/references/amounts and signature/date lines. Screen previews are responsive. Print media hides navigation/buttons and app chrome. [Print script](../public/branding/documents.js) opens the browser dialog only on an explicit click.

Initial focused real-MySQL print suite passed: four tests, 59 assertions. Additional accountant/max-precision/long-schedule checks and final static/browser results are recorded below once complete. These are targeted checks for this slice; prior whole-suite evidence remains in [booking status](FINAL_BOOKING_STATUS.md).

Final backend verification: **18 targeted tests passed, 274 assertions**, spanning the expanded five print tests, receipt posting and final booking, including both independent-process MySQL concurrency suites. The 360-installment print test retains every row through December 2057; maximum receipt precision is also asserted in actual rendered HTML. No full-suite rerun is claimed for this print slice. PHPStan level 7, Pint, frontend formatting/lint, TypeScript and production build passed. A missing booking snapshot type declaration found by PHPStan was corrected to match its existing array casts. No new packages or schema migrations were introduced. All four original source hashes still match, and all 279 relative documentation links resolve.

Final browser verification: **four desktop/mobile Chromium scenarios passed (4.6 minutes)**. They exercise real booking confirmation and cheque preparation/issue/clearance/reversal, open authenticated previews through the UI, verify both SVGs loaded, check status/privacy/viewport, invoke the print control with the OS dialog stubbed, and hide controls under print media. Native headless Chromium generated **eight valid PDF files**: booking confirmation plus pending, recognized and reversed receipt copies for both viewports. The PDF header and nontrivial file size are asserted. Desktop/mobile booking previews and the mobile reversed-receipt preview were visually inspected. QA PDFs/screenshots contain only isolated DEMO data and remain test outputs; they are not actual office-issued documents or archived signed originals.

## Remaining release gates

The live installment ledger and linked admin-approved allocation reversals are delivered. Owner-2 setup is waiting for a distinct authorized admin and usable account-verification email. Opening balances/reconciliation, broader cancellation and commission policies, full financial reports and production recovery remain separate release gates.
