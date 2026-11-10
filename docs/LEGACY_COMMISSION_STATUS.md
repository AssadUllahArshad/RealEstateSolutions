# Legacy commission assignment status

[Commission implementation](AGENT_COMMISSION_STATUS.md) · [Planning index](README.md)

Admins can attach commission rules to confirmed bookings that predate booking-level commission snapshots. The append-only assignment records one responsible sales agent, an exact approved percentage, an explicit Asia/Karachi eligible-collection start date, the approving actor and supporting reason. It never updates the immutable booking row.

Down-payment allocations use the linked receipt recognition date; installment allocations use their saved business date. Only sources on or after the approved start date are backfilled. Source uniqueness makes replay safe. Future eligible allocations and refunds use the active legacy assignment automatically.

Direct reassignment is blocked. A main administrator can use the controlled correction workflow to replace the responsible agent, exact rate and effective date. The original assignment is marked superseded and remains immutable. Two compensating events reverse the booking's current commission entitlement from the prior agent and establish the recalculated entitlement for the corrected agent. Original allocation, refund and payment records are never rewritten.

Approved payments remain with the agent who received them. If a correction reverses more earned commission than that agent still has payable, their balance becomes recoverable and offsets future earnings. The replacement agent receives the recalculated payable amount from eligible allocations and approved cash refunds on or after the corrected date. A booking with unexplained non-zero event balances across multiple agents is stopped for finance review rather than corrected automatically.

Branded commission payment vouchers and agent period statements are implemented. Statements show earned and reversed events, approved payments, opening and closing balances, and identify negative balances as recoverable from the agent through future commission offsets.
