# Account opening and bank reconciliation status

Implemented on 2026-11-10.

## Agreed rules

- Each financial account has one immutable opening-balance record. Any correction is an append-only, linked adjustment with a reason.
- Opening balances are effective at the start of their Karachi business date. Negative openings are allowed only for bank overdrafts.
- Reconciliation is limited to bank accounts. Finance staff manually enter statement details and match every posted cashbook entry exactly, one-to-one.
- An accountant prepares the reconciliation; a different admin or super-admin must review and finalize or reject it.
- Statement opening and closing balances must equal the corresponding book balances, with no variance adjustment or unmatched lines.
- Prepared/finalized periods cannot overlap. Finalized periods block subsequent cashbook postings dated within the period. There is no reopening workflow in this version.
- Real opening amounts must be entered only from verified bank statements or physical cash counts.

## Delivered

- Account details show effective opening balance, linked corrections and current book balance.
- Reconciliation supports bank/date filtering, opening and closing totals, manual line matching, review notes and decision history.
- Cashbook entries include recognized receipts and reversals, expenses, approved refunds, owner payouts and commission payments.
- Finance overview period totals and per-account net movements include each of those posted cashbook sources; only approved payout/refund/commission records count as outflows.
- Opening/correction and reconciliation actions are transactional and replay-safe. Opening entries, corrections and statement lines are immutable; decisions retain maker/checker identities.
- Cashbook, overview and account-directory text distinguishes period movement from an account balance and a formal financial statement.

## Verification

- MySQL feature tests cover bank-only negative openings, immutable/idempotent corrections, start/end-of-day balance math, exact match rejection, maker/checker enforcement, prepared-period overlap, finalized-period posting rejection, and refund/payout/commission reporting.
- Focused finance regression tests passed: 14 tests, 233 assertions. Focused charge/installment tests passed: 10 tests, 247 assertions.
- PHPStan and Pint passed. TypeScript, targeted frontend checks and production asset build passed.

## Boundaries

- No real financial source values have been entered.
- Exact-match v1 intentionally cannot represent uncleared timing differences, unmatched bank-only activity or adjustments.
- This is an operational cashbook/reconciliation workflow, not a double-entry general ledger, trial balance, profit-and-loss statement or statutory balance sheet.
- Full reporting, legacy imports/cutover, production recovery rehearsal, the second admin, outbound email and Owner 2 setup remain outstanding or deferred.
