# Owner settlement implementation status

Implementation baseline: 2026-11-09.

## Approved rules

- Entitlement is calculated from recognized customer money allocated to confirmed booking principal.
- An owner profile carries an optional default percentage. Assigning an owner to a plot copies that default unless an authorized admin enters a plot-specific rate.
- The plot rate is preserved in the immutable final-booking snapshot.
- Calculation uses exact PKR paisa with half-up rounding.
- Office expenses and other deductions remain separate.
- Refunded eligible collections reverse entitlement proportionally with an audit record.
- Partial payouts are allowed up to the available entitlement balance.
- An accountant prepares a payout and an admin authorizes it before cash or bank posting.
- If an approved refund reverses more entitlement than remains unpaid, future entitlements automatically offset the negative balance; there is no separate cash-repayment workflow.

## Delivered in this increment

- Exact decimal owner-default and plot-snapshot fields with MySQL range constraints from 0 through 100 percent.
- Admin setup fields for an owner default and per-plot override.
- Authorized admins can update an owner's display name and default share with an audit event; existing plot-specific rate snapshots remain unchanged.
- Blank plot rate copies the selected owner default.
- Changing a profile default does not silently alter existing plot snapshots.
- A plot assigned to an owner cannot proceed to final booking while its owner rate is unset.
- Final booking captures the plot percentage with the owner and plot details.
- English and Urdu/RTL responsive fields.
- Immutable entitlement events for every eligible down-payment allocation and installment-allocation batch.
- Exact half-up calculation from the final booking's rate snapshot, with source-level duplicate protection.
- Owner reports now show event-backed entitlement totals per owner and per booking.
- Accountants can prepare exact partial payouts up to entitlement remaining after approved and pending amounts.
- Admins approve or reject prepared requests; rejected records remain immutable and must be reprepared.
- Approval records the payout as a cash, bank or wallet cashbook outflow. Non-cash payouts require an external reference.
- Maker/checker permissions prevent admins from preparing and accountants from approving.
- Branded printable owner statements reconcile opening payable, entitlement activity, approved payouts and closing payable, with optional date filters.
- Pending payout requests appear separately and do not reduce the official closing balance; non-cash references are masked to their final four characters.
- Negative owner balances are labeled recoverable in the payout portal and printable statements. Payout preparation stays unavailable until future entitlements offset the recovery balance.

Amir Ali and Raja Irshad each have a 50% owner default and are linked to their separate main-administrator accounts. Existing plots remain with their explicitly assigned owner; creating Raja's profile did not reassign inventory. The current local mailer remains log-only, so production verification/reset links still depend on creating the approved business mailboxes and configuring SMTP. Plot-specific rates remain immutable snapshots and are not changed when a profile default changes.

## Remaining setup

Setup update 2026-11-15: Raja Irshad is the distinct second main administrator and Owner 2 with the approved 50% default. Amir Ali is Owner 1 with a 50% default. Every new plot requires an explicit owner assignment; the defaults do not divide an individual plot automatically. Production mailbox delivery remains to be configured and verified.
