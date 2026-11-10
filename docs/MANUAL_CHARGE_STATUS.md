# Manual charge implementation status

Implementation baseline: 2026-11-08.

## Agreed operating rule

The user selected **manual charge entry by an authorized admin**. EstateFlow does not calculate or post an automatic late-payment penalty. Each charge requires an exact PKR amount, a reason, an internal verification note, confirmation, and the current Asia/Karachi business date.

This decision is also recorded in [Financial decisions](FINANCIAL_DECISIONS.md). It supersedes earlier proposed automatic fixed or percentage late-fee behavior unless a later approved policy changes it.
Business settings now offer only disabled or manual entry; proposed automatic fee modes are not configurable.

## Delivered behavior

- Authorized admins can post individually approved charges against bookings they can access.
- Charge numbers are allocated under a database lock as `CHG-######` and remain unique.
- Command keys and request hashes make retries idempotent and reject a reused key with different content.
- Amounts use exact decimal arithmetic; floats are not used for financial values.
- Original charge rows are immutable.
- Authorized admins can record exact partial or full waivers with a reason. Every waiver is an immutable compensating record; the original charge stays visible.
- Recognized receipt credit is applied to contract principal first. A remaining payment amount may be split across manual charges explicitly selected by the authorized allocator; unused receipt credit remains available.
- Charge payments reduce charge balances only. They do not alter principal, owner entitlements, commissions, cashbook movements, or principal-only receivables.
- Receipt reversal compensates its charge allocations only when no principal allocation from that receipt remains. Installment allocations use the existing admin-approved correction workflow; booking/down-payment allocations have no correction workflow.
- Accountants and sales agents can view only records allowed by their existing booking scope. They cannot post or waive charges.
- English and Urdu/RTL views are available in the existing responsive finance shell.
- Filters cover booking and active/settled/waived state.

## Explicit accounting boundary

A manual charge does not change contract principal or the installment schedule, recognize a receipt, create a cashbook movement, or automatically become overdue based on dates. Allocation only applies already-recognized receipt credit, and charges are settled after principal. Partial waivers are supported but cannot exceed the remaining charge balance.

## Verification evidence

- Focused backend coverage includes role scope, policy gating, idempotent posting and waivers, exact partial-waiver limits, principal-first split payments, receipt reversal and immutable records.
- PHPStan passed with zero errors.
- Pint passed.
- Frontend checks passed across 120 files.
- Production asset build passed.
- The live local MySQL database has the charge tables, permissions, and the approved `manual` charge policy.
- Live browser checks passed for admin manage access, accountant read-only access, English layout, Urdu RTL layout, and 390-pixel mobile overflow.

## Remaining boundary

There is no standalone charge-payment correction workflow. Receipt reversal compensates all charge allocations on that receipt only when no principal allocation from it remains; installment corrections require the existing admin-approval process, while booking/down-payment allocations have no correction workflow. No automatic charge aging or automatic fee calculation is enabled.

Charge allocation now follows the approved D06 choices recorded in [Financial decisions](FINANCIAL_DECISIONS.md); any charge portion is explicitly assigned by the authorized allocator rather than silently ordered by the system.
