# Customer cancellation refund status

[Planning index](README.md) · [Financial decisions](FINANCIAL_DECISIONS.md) · [Roadmap](ROADMAP.md)

The cancellation-refund workflow is implemented. An approved cancellation is a mandatory prerequisite. Accountants prepare partial refunds from any active cash, bank or wallet account; admins approve or reject them. Bank and wallet refunds require a reference. Refunds use the current Asia/Karachi business date.

Each immutable request separates the actual cash refund from an itemized approved deduction. Refund plus deduction cannot exceed recognized credit allocated to the booking after earlier approved or pending requests. Duplicate command replay is idempotent and changed payload reuse conflicts. Approval adds only the cash refund to the cashbook. Deductions consume customer refundable credit without creating a false cash movement.

Owner entitlement reverses proportionally from the actual cash refund using the booking rate and half-up paisa rounding. The reversal is an immutable negative entitlement event; approved deductions are excluded. Refund rows retain the booking/account snapshot, verification note, actors, decision and exact amounts.

Final verification on bundled PHP 8.4: the full application suite passed with 189 tests, 1 skipped and 2,366 assertions; PHPStan reported zero errors, TypeScript passed, frontend formatting/lint passed, and the production build succeeded.

Approved refunds have branded printable vouchers with the cash-refund versus non-cash-deduction breakdown, pre-request refundable balance, account/reference, preparer, approver, verification evidence and acknowledgement fields. Prepared or rejected refunds cannot be printed as authorized vouchers.

Negative owner balances are recovered only by netting future entitlements, as selected by the user; no separate cash-repayment workflow is provided. Remaining related decisions include commission clawback rules. Production recovery validation and UAT are still release gates.
