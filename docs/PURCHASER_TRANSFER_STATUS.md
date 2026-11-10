# Purchaser transfer implementation status

[Planning index](README.md) · [Product requirements](PRODUCT_REQUIREMENTS.md) · [Data model](DATA_MODEL.md) · [RBAC](RBAC_MATRIX.md)

## Delivered behavior

The controlled purchaser-transfer workflow is implemented for confirmed bookings. Agents, accountants and admins may prepare a request; an admin or super admin makes the final decision. Approval uses the current Asia/Karachi business date as the effective date.

The original booking, its purchaser snapshot, price, down payment, receipts, allocations and installment schedule remain immutable. Each request snapshots the outgoing parties, incoming parties and exact four-decimal shares, remaining scheduled principal, documents, reason and actor. An approved event becomes the active legal ownership view. Earlier purchasers remain historical parties and prior documents remain preserved.

Incoming joint-purchaser shares must total exactly `100.0000%`. Each incoming purchaser requires a distinct CNIC, address, CNIC copy and photograph. Each outgoing purchaser requires a CNIC copy. A signed transfer form from an active outgoing purchaser is mandatory; payment evidence is optional when applicable. Reusing a command key with the same payload returns the original request, while changed payload conflicts.

The unpaid installment schedule stays on the same booking and therefore carries forward without regeneration. After approval, recognized receipt credit belonging to any active incoming purchaser can be allocated to that ledger. A transfer fee is never generated automatically; authorized staff use the existing manual-charge workflow when a separately approved fee applies.

## Storage and access

- `booking_transfers` is the immutable request/decision header with public and encrypted private snapshots, document references, outstanding balance, idempotency data and decision metadata.
- `booking_transfer_parties` preserves normalized incoming customer/share references for traceability.
- `booking_events` records request and decision events.
- `signed_transfer_form` is an allowed private customer-document kind.
- Transfer decisions recheck that active ownership still equals the request's outgoing snapshot, preventing stale approval after another ownership change.

The transfer workspace is linked from each confirmed booking and supports English/Urdu, RTL layout, joint-party editing, readiness indicators and admin decision history.

## Verification evidence

Verified on the bundled PHP 8.4 runtime and MySQL 8.4 InnoDB on 2026-11-09:

- focused transfer/change/ledger suite: 9 tests, 128 assertions passed;
- PHPStan: zero errors;
- frontend format/lint: 122 formatted files and 112 linted files passed;
- production asset build: 2,347 modules transformed successfully;
- live migration and role-permission seed completed.

The tests cover exact joint shares, complete identity documents, duplicate command replay, agent/admin separation, effective approval, preserved booking/allocation rows, active-party projection and carried outstanding principal.

## Remaining related work

Cancellation refund disbursement and proportional owner-entitlement reversal are implemented in the separate [customer-refund workflow](CUSTOMER_REFUND_STATUS.md). A purchaser transfer itself only changes the active legal parties and carries the existing schedule; it does not move cash or create an entitlement reversal. Commission triggers on transfers remain unresolved and outside this slice. Production restore drills and full end-to-end UAT remain release gates.
