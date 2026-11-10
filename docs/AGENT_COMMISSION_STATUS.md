# Agent commission implementation status

[Planning index](README.md) · [Financial decisions](FINANCIAL_DECISIONS.md) · [Roadmap](ROADMAP.md)

Agent commission accrual and payment are implemented using the approved collection basis. A new confirmed booking snapshots the customer’s assigned sales agent, the configured percentage, policy version and full policy values when the main-admin commission settings are `percentage`, `cleared_collections`, `collection_cleared` and `reverse_and_recover`. Missing or incompatible settings create no commission snapshot or accrual; the application does not invent a rate.

Each down-payment or installment allocation creates one idempotent immutable earning event from recognized allocated principal. Exact percentage arithmetic uses four-decimal rates and half-up paisa rounding. Approved customer refunds append a proportional negative commission event; previously paid excess therefore appears as a negative recoverable agent balance rather than rewriting earlier earnings or payments. Approved deductions are excluded because no customer cash was refunded.

Accountants may prepare partial commission payments from active cash, bank or wallet accounts. Admins approve or reject them. Non-cash payments require a reference, pending requests reserve available commission, and changed payload reuse conflicts. Approved payments appear as cashbook outflows and CSV entries. English/Urdu finance screens show agent balances, immutable earning events and payment history.

Verification on PHP 8.4 and MySQL 8.4: commission/refund/final-booking suite passed (7 tests, 106 assertions); PHPStan reported zero errors; 124 frontend files passed formatting and 114 passed lint; the production build transformed 2,349 modules. Migration and live permission seeding completed.

Existing bookings are handled through a separate admin-only legacy assignment. The admin selects one responsible sales agent, enters the exact approved four-decimal rate, an explicit Karachi collection start date, and a required evidence/reason note. Eligible historical allocations on or after that date are backfilled idempotently while the original booking remains immutable. A booking with an existing legacy assignment is locked pending the separate controlled correction workflow; direct overwrite is prohibited.

Approved commission payments now have branded printable vouchers. Each sales agent has a printable period statement reconciling opening balance, immutable earning/reversal events, approved payments and closing payable or recoverable balance. Negative balances are labeled recoverable and automatically offset future earnings before payment approval.

The commission portal filters earning events and payment history by agent and Karachi business-date range. Its reconciliation CSV applies the same filters, exports all matching earning and reversal events plus approved payments, and streams large exports without buffering the full result set. Spreadsheet-formula-like agent names are neutralized in the CSV. The activity panel shows each event's business date, and an empty result has a translated Urdu state.
