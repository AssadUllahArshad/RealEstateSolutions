# Ability and access proposal

Current role expansion (2026-11-07): admin and accountant inherit the current sales-agent operational abilities. Accountant also receives office-wide customer visibility and can prepare/confirm bookings subject to the same scope, evidence, recognized-credit, locking and audit checks. This does not grant accountant discount changes, receipt reversal, business-setting changes, inventory configuration/export, user administration or plot release/block. See [role and ownership status](ROLE_OWNERSHIP_LOCALIZATION_STATUS.md).

Installment ledger (2026-11-05): finance receipt visibility controls scoped ledger reads. `finance.allocations.post` plus actual accountant/admin/super_admin role authorizes writes. Assigned sales agents read their customers' balances; misgranting the post ability does not grant agent posting. Accountants use minimal finance customer context without CRM identity/document access. See [ledger status](INSTALLMENT_LEDGER_STATUS.md).

Current booking authority (updated 2026-11-07): `sales.confirm` plus actual sales_agent/accountant/admin/super_admin role authorizes final booking within current customer scope. No routine admin approval is added. Confirmed record reads require `sales.drafts` and current CRM scope. Receipt posting remains accountant/admin with `finance.receipts.post`; reversal requires admin role and `finance.receipts.reverse`, and allocated receipts are blocked until linked correction is implemented. See [receipt controls](RECEIPT_RECOGNITION_STATUS.md), [booking scope](FINAL_BOOKING_STATUS.md) and [current role expansion](ROLE_OWNERSHIP_LOCALIZATION_STATUS.md).

Finance preparation (2026-11-04): admin/super_admin manage accounts with `finance.accounts.manage` plus role check; accountant may view setup/history. All four staff roles receive explicit receipt-view/preparation abilities. Finance-wide receipt/customer-ID/name scope requires `finance.receipts.view_all` plus admin/super_admin/accountant role; sales agents remain assignment-scoped even if this ability is misgranted. Accountant receives no CRM identity/document grant. None of these abilities authorize posting, recognition, official receipt issuance, reversal or balance changes. See [scope table and privacy](COLLECTION_PREPARATION_STATUS.md).

Proposed terms addition (2026-11-04): current sales-draft access permits schedule preparation. Changing/removing/rebasing discounts additionally requires `sales.discounts` **and** admin/super_admin role, both checked on a fresh actor inside the transaction. The new ability is seeded only to those two roles. Agents may retain existing authorized discount terms on the same gross price; accountant remains denied. No financial posting or final contract authority is introduced. See [terms status](TERMS_STATUS.md).

Proposed purchaser entry (2026-11-04) reuses the sales-draft abilities below. Draft contact and every selected purchaser must be currently accessible. Foreign/reassigned purchaser identities are hidden even when the draft contact remains visible. Fresh actor/assignment checks run under locks on writes. No accountant grant or transfer/finalization authority is introduced. See [purchaser status](PURCHASERS_STATUS.md).

Current addition: [operating rules](OPERATING_RULES_STATUS.md) record the user's agent-finalization/admin-discount/admin-correction/accountant-expense directions; these future action grants are not yet posting endpoints. Implemented `business-settings.manage` additionally requires the super_admin role on every request. Customer identity and document routes reuse current-assignment CRM scoping and `customers.manage` for writes; ordinary directory/search serialization omits CNIC/address and disk paths.

Implemented sales-draft addition (2026-11-03): `sales.drafts` is granted to super_admin/admin/sales_agent and also requires `customers.view` and `inventory.view`. Visibility/editing follows current customer assignment unless `customers.view_all` is granted. Accountant has no grant. Final bookings, discounts, allotments and finance are separate. See [sales draft status](SALES_DRAFT_STATUS.md).

[Index](README.md) · [Requirements](PRODUCT_REQUIREMENTS.md) · [Decisions](FINANCIAL_DECISIONS.md)

AGREED: roles super_admin, admin, accountant, sales_agent; explicit server-side abilities; agents limited to authorized customers/bookings; permission changes audited (S1/S2). The grants below are **TECHNICAL PROPOSALS** requiring review under D01/D15. No role name alone authorizes a money override.

Implemented abilities include `users.view`, `access.manage`, `inventory.view`, `inventory.export`, `inventory.manage` and `configuration.manage` for super_admin. Under the user's manual-hold and role-expansion decisions, sales_agent and accountant receive `inventory.view` and `inventory.reservation`; admin and super_admin receive those plus `inventory.release` and `inventory.block`. Release/block require both explicit permission and an admin/super_admin role. Accountant and agent cannot edit catalogue/configuration, export, release or block. The approved inventory scope is one society/office per installation (D01); there are no tenant/branch memberships. Role changes require recent password confirmation and cannot target the acting administrator. D15 still gates broader production access-policy sign-off.

Legend: C = controls permission assignment; G = eligible only when explicitly granted; O = assigned/authorized records only; — = no proposed grant. All access is further limited by approved society/branch scope. Super admin has global administration but financial posting still requires valid terms, approvals and invariants.

| Ability | super_admin | admin | accountant | sales_agent |
|---|---|---|---|---|
| users.provision / roles.assign | C | — | — | — |
| configuration.manage | G | G | — | — |
| inventory.view | G | G | G | G |
| inventory.manage / block / release | G | G | — | — |
| customers.view | G | G | G | O |
| customers.create / update | G | G | G | O |
| customers.assign_agent | G | G | — | — |
| identity.view_full / documents.download | G | G | G | O + explicit sensitive grant |
| reservations.create / bookings.submit | G | G | G | O |
| bookings.approve / allotments.activate | G | G | — | — |
| discounts.request | G | G | G | O |
| discounts.approve / contract.adjust | G | G | G | — |
| receipts.post / allocations.manage | G | G | G | — |
| receipts.reverse / refunds.approve | G | G | G | — |
| expenses.create / payouts.request | G | G | G | — |
| payouts.approve / payouts.post | G | G | G | — |
| owner.agreements.manage / settlements.post | G | G | G | — |
| commissions.view / approve / pay | G | G | G | — |
| statements.view | G | G | G | O |
| reports.finance / exports.finance | G | G | G | — |
| reports.inventory / sales | G | G | G | O for sales |
| reminders.manage / calculator.preview | G | G | G | O for customer notes |
| audit.view | G | G | G for authorized finance | O activity only |

Each slash-separated ability is independently assignable, especially approval versus posting. G does not approve thresholds, self-approval or unrestricted sensitive data access. D04/D08/D09 decide monetary authority; D15 decides separation of duties and emergency access. Default-deny unspecified abilities; permission catalog and grants are versioned seed/configuration reviewed before production.

## Enforcement and acceptance tests

Implemented CRM (2026-11-03): admin/super_admin receive `customers.view`, `customers.manage`, `customers.view_all`, `customers.assign`; sales_agent receives view/manage for current assigned records only. Accountants remain ungranted. Assignment controls are server-validated; a hidden selector never establishes authority. Follow-up access follows current customer assignment, including dashboard and search scope. This does not authorize identity documents or financial statements.

Implemented catalogue export requires **both** `inventory.view` and `inventory.export`. Only `super_admin` receives export from the additive foundation seeder; a read-only catalogue grant does not permit bulk download. This is an implementation grant, not approval of the future financial export matrix above.

- Lists/search use scoped queries before pagination; detail routes authorize loaded objects; writes verify scope from server context rather than posted society IDs. Apply equivalent checks to exports, downloads, background jobs and dashboard aggregates.
- Test every sensitive ability for allowed, denied, unauthenticated, foreign object, foreign scope, revoked membership and tampered actor/approval fields. Agents cannot infer other customers through counts, autocomplete or attachment URLs.
- Team UI cannot grant permissions unavailable to the actor; no mass assignment of roles. Log before/after ability identifiers and actor, avoiding raw PII.
- Reassignment preserves history and immediately changes operational access. Existing finance attribution remains stable. D01/D12 define how shared assignments and historical staff access work.
- Post/approve/reverse checks run again at action time. A valid session or hidden button never substitutes for policy evaluation.
