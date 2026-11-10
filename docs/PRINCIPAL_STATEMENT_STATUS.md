# Current principal statements

Implemented 2026-11-05. Builds on the [live ledger](INSTALLMENT_LEDGER_STATUS.md) and [branded print documents](PRINT_DOCUMENTS_STATUS.md).

Open a booking's **Payments & ledger**, then **Print statement / Save PDF**. The private standalone document uses both approved brand recreations and the issuer profile captured at booking confirmation. Native browser printing supports paper or Save as PDF.

The statement includes contract price, paid principal including the down payment, remaining and overdue principal, every scheduled installment's current paid/remaining amounts, down-payment receipt sources, and the complete installment allocation history ordered by ledger version. Allocation dates are explicitly distinguished from receipt recognition dates. All calculations reuse the exact-decimal ledger service; no independent float calculations or new financial movements are introduced.

Statements represent the current booking principal, not a historical date filter or a consolidated customer account. Unapplied credit, charges and cash refunds are explicitly excluded. Admin-approved linked installment-allocation reversals appear as adjustments and update the live paid, remaining and overdue principal. No automatic fees or invented financial settings are enabled.

Assigned agents use their existing customer scope. Accountants/admins use finance scope without receiving CRM identity permissions. Statements omit CNIC, customer private address, document paths and private payment notes; Blade escapes names. Responses use the existing no-store, no-index, CSP and frame-protection headers. Previewing records a minimal audit event with booking ID and ledger version. No public statement link, archived signed PDF or public verification service is introduced.

## Verification

- Ten targeted MySQL feature tests passed (147 assertions), including print and ledger regressions. The new test checks changing balances after allocation, accountant access without CRM permission, unrelated-agent denial, unauthorized-user denial, privacy, headers and no extra financial writes when printing repeatedly.
- PHPStan and Pint passed; frontend lint/format, TypeScript and production build passed.
- Desktop and mobile Chrome end-to-end scenarios passed (2 tests, 1.7 minutes): agent confirmation, finance allocation, reload, branded statement with PKR 875.00 remaining, print-button invocation, PDF generation and viewport overflow checks. Both statement PDFs and both original booking confirmation PDFs were generated and validated. Desktop and mobile statement screenshots were inspected.

## Related workflow and remaining scope

Linked installment-allocation corrections require an accountant request and separate admin approval. Original allocations remain unchanged; approved reversals are included in current ledger statements and reallocation is a separate transaction. The broader booking-cancellation and contract-schedule adjustment policies remain separate decisions.
