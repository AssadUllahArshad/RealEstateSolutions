# Production validation record

Validated on 2026-11-16 against `https://erp.datarealestate.online` and release `8987676`.

## Corrected defect

The receivables summary selected the booking JSON snapshot from a grouped query. MySQL 8 inferred the primary-key dependency, while production MariaDB 10.11 rejected it under `ONLY_FULL_GROUP_BY`. The query now groups the snapshot explicitly, and `/finance/receivables` plus its export return HTTP 200 for Super Admin and Accountant.

## Live route validation

- Super Admin: 37 workspace/settings pages returned HTTP 200.
- Accountant: every permitted page returned HTTP 200; restricted configuration, team, business-policy, and opening-balance administration pages returned the expected HTTP 403.
- All CSV/XLSX/template routes and the full office export returned HTTP 200. Expected HTTP 429 responses during the first bulk pass were retested individually after the throttle window and passed.
- The last production error is the pre-fix receivables exception at 06:34 UTC. No new application error was recorded during the post-deployment route, export, or UI sweeps.

## Browser validation

The dashboard, inventory, new booking, receivables, financial accounts, business settings, and operations report were tested with a real authenticated production session at 1440x900 and 390x844 in English and Urdu.

- 28 page/locale/viewport combinations returned HTTP 200.
- English used `dir=ltr`; Urdu used `dir=rtl`.
- No horizontal document overflow was detected.
- No page or console error was recorded.

## Build and static checks

- Production frontend build: passed under PHP 8.4.
- Vite Plus formatting/lint: 134 formatted files and 124 linted files passed.
- TypeScript `tsc --noEmit`: passed.
- Laravel Pint: passed.
- PHPStan: passed with zero errors.
- The focused local feature test could not start because the local MySQL connection stalled; the corrected query was therefore exercised directly through production MariaDB by both authorized roles and its export.

## Initialized editable data

- Society: Green Vista Housing Scheme.
- Office: Data Real Estate.
- Business email: Amir Ali's business mailbox.
- Booking documents: signed booking form, CNIC copy, customer photograph, and payment evidence.
- Held plot access: authorized agents may proceed, matching the approved no-wait workflow.
- Installment frequencies: monthly, quarterly, half-yearly, yearly, and custom.
- Collections: cash, bank transfer, cheque, mobile wallet, and other; hybrid allocation; cheques recognized after clearance; excess retained as customer credit.
- Active zero-balance account shells: main cash, main bank, mobile wallet, and other receipts.

No opening balance, transaction, plot, block, price, down-payment threshold, installment date/rounding rule, late fee, owner percentage, owner entitlement trigger, commission rule, receipt series, or legacy cutover date was invented.

## Remaining launch operations

1. Add the three scheduled tasks through StackCP when its scheduler is available.
2. Copy a verified backup off-host and perform a restore drill.
3. Rotate the initially shared mailbox passwords and update the protected SMTP credential.
4. Complete the remaining business-policy decisions before recording real bookings or financial transactions.
