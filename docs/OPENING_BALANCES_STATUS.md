# Opening balances status

Implemented on 2026-11-14.

## Agreed rules

- Opening balances are recorded as of the legacy cutover date, which is configurable in business settings (super_admin only).
- Opening balances are kept separate from live ledgers until explicitly activated. Activation is a separate, audited step.
- Opening balances are immutable once recorded. Corrections are not supported in this slice; use a new opening balance with a different source note if a correction is needed.
- Opening balances are gated on `finance.accounts.manage` and the super_admin/admin role.
- Opening balances are effective on the cutover date only; any other date is rejected.
- Activation records an `opening_balance_activations` row with the actor and a command key. Activation is idempotent (same command key returns the prior activation).
- Activation does not post to the live ledger in this slice. The exact posting rules (rounding, rate source, whether they count toward payouts) need user sign-off before wiring into payout/commission calculations.

## Delivered

- `cutover` business settings section (super_admin only) with a `cutover_date` field.
- `opening_customer_balances`, `opening_owner_balances`, `opening_commission_balances` and `opening_balance_activations` tables (migration `2026_11_14_000200_create_opening_balance_tables.php`).
- `App\Domain\Finance\OpeningBalances` — create customer/owner/commission opening balances (effective on cutover date only) and activate them into the live ledger (idempotent).
- `GET /finance/opening-balances` — bilingual opening balances screen (record and list customer/owner/commission opening balances).
- `POST /finance/opening-balances/customer|owner|commission` — record an opening balance.
- `POST /finance/opening-balances/{kind}/{opening}/activate` — activate an opening balance into the live ledger.
- Sidebar entry under Finance for users with `finance.accounts.manage`.
- Urdu translations for the new screen; English falls back to the key.

## Verification

- MySQL feature tests cover: 403 for users without `finance.accounts.manage`, opening balance requires cutover date, opening customer/owner/commission balances are recorded on the cutover date, activation is recorded, activation is idempotent.
- 7 tests, 19 assertions passed. TypeScript and the production asset build passed. PHPStan passes on the new files. Pint passes on all new/changed files.

## Boundaries

- No real financial source values have been entered.
- Activation does not post to the live ledger in this slice. The exact posting rules (rounding, rate source, whether they count toward payouts) need user sign-off before wiring into payout/commission calculations.
- Opening balances are not a backup/restore mechanism and do not import uploaded files (customer documents).
- Opening balances are immutable once recorded; corrections are not supported in this slice.
