# Financial accounts and receipt preparation — 4 November 2026

[Index](README.md) · [Operating rules](OPERATING_RULES_STATUS.md) · [Terms](TERMS_STATUS.md) · [RBAC](RBAC_MATRIX.md)

## Delivered scope and authority

U3 requests all supported payment methods as radio choices and office access by assigned role. This increment implements financial-account setup and **unposted receipt preparation**. It deliberately does not recognize funds: receipt numbering, business-date controls, opening balances, reversals and final recognition rules remain required before affected posting. Saving a report does not create a cash movement, receivable allocation, customer credit, cheque clearance, owner/commission accrual or booking down-payment eligibility.

The Finance navigation group links to receipt preparations for authorized staff and financial accounts for finance-wide staff. PREP-{id} is an internal preparation reference, not an official receipt number or printable proof of payment. All records have the derived status **Prepared · Unposted**; there is no API field or toggle that can turn one into cleared/recognized money.

## Financial accounts

Admins/super_admins with `finance.accounts.manage` create a cash, bank, wallet or other destination with a unique normalized code and recognizable name. Both role and ability are required on writes. Code/type cannot be changed after creation; rename and active/inactive changes require a reason and version. Deactivating an account retains its references and history, prevents subsequent preparation writes targeting it, and never deletes existing reports. No opening balance is assumed to be zero: there is no balance or opening-money field in this increment.

The [account action](../app/Domain/Finance/Accounts.php) locks a fresh actor and the installation before account writes. Creation UUID replay returns the same record; changed payload conflicts. Unique code validation, optimistic updates, no-op handling and append-only before/after audit are transactional. The [responsive account screen](../resources/js/pages/finance/accounts.tsx) includes desktop tables, mobile summary cards, setup form and latest ten revisions. Personal bank/card information and credentials are not requested.

## Receipt preparation workflow

1. Main admin saves accepted methods in Receiving payments settings. Preparation needs a saved policy with the chosen method enabled; unresolved allocation/recognition choices remain visibly incomplete and cannot enable posting.
2. Admin configures an active destination account. Staff explicitly selects the customer, account, exact positive PKR amount and reported Karachi business date. No date or payment method is silently selected.
3. Cash, bank transfer, cheque, mobile wallet and other methods render as radio choices, limited to saved enabled methods. Non-cash methods require an external instrument/transaction reference. A customer-level preparation may optionally reference one of that customer's booking drafts; this is context, not allocation.
4. Save creates PREP-{id} and a revision. Staff may correct an **unposted preparation** with optimistic version protection. The customer is fixed after creation; a different customer requires a separate record. This does not implement cancellation/correction of recognized payments, which require the user's separate admin-finalization workflow.
5. Reopen and review encrypted private reference/notes. Lists show only customer name, reported amount/date, method, destination snapshot and preparation status. No official receipt/recognition or deletion endpoint exists.

The [editor](../resources/js/pages/finance/receipt-edit.tsx) uses labeled controls, setup/policy-change warnings, unsaved-change guards, disabled controls during writes, exact string formatting, missing-posting-settings guidance and latest ten revision summaries. Search is bounded and literal-safe (including 0, % and _); customer selection searches up to thirty names. Optional draft choices show up to one hundred recent applicable references and preserve the saved selection. Existing inactive account labels remain visible while another active destination must be chosen before saving.

## Role scope and privacy

The following **technical role grants for preparation** implement the assigned-role requirement; none grant money posting:

| Role | Account directory/history | Account setup | Receipt preparations |
|---|---|---|---|
| super_admin/admin | Yes | Yes with explicit ability | All customers |
| accountant | Yes | No | All customers, minimal finance context |
| sales_agent | Destination labels in editor only | No | Currently assigned customers only |

`finance.receipts.view_all` additionally requires an admin/super_admin/accountant role. A misgrant to an agent cannot bypass assignment scope. Accountant receipt access does **not** grant CRM directory, CNIC/address, private identity-document, email or phone access: finance lookups serialize customer ID/name only. Current assignment governs every receipt list/detail/write, including records created before reassignment.

External references and verification notes are encrypted at rest, hidden from default model JSON, and exposed only in the scoped detail response. They are never copied into audit history or list/search responses. The creation payload hash uses HMAC with the application encryption key; replay supports configured previous keys. This prevents low-entropy private references becoming an unkeyed hash oracle. Access remains sensitive: live HTTPS, secure sessions, backup encryption and application-key retention/rotation remain deployment gates.

## Schema and transaction guarantees

The additive [migration](../database/migrations/2026_11_04_000300_create_collection_preparation.php) adds `financial_accounts`, `financial_account_events`, `receipt_drafts` and `receipt_draft_events`. It includes restrictive foreign keys, unique account code, unique actor/creation key, unique parent/revision, exact DECIMAL(18,2) amounts and MySQL checks on positive amounts/versions and valid account/method vocabularies. No ledger/balance tables or synthetic business records are introduced in the application database.

The [receipt action](../app/Domain/Finance/ReceiptDrafts.php) locks/rechecks a fresh actor, currently accessible customer, selected account, saved collections policy, optional matching draft and existing preparation. Disabled accounts/methods are rejected; changed policy versions require reload. Existing customer cannot change. Reference/notes normalize before hashing/encryption. Identical create replay returns the original currently accessible report, even if later settings differ; changed content under the same key conflicts. Different-content stale edits cannot overwrite a newer version. Each money change, including one paisa at maximum supported precision, is retained exactly. No-op edits create no audit event. Failures roll back data and audit together, with three deadlock retries.

Account labels are snapshotted when first selected or changed; renaming an account does not silently change the reported destination snapshot on old preparations. Current policy values/version are captured on every meaningful save. History retains exact amount/date/method/account/draft/policy references and changed field names, never private reference/note contents. Reported dates are claims for later verification, not authorization to backdate a ledger entry. Cross-customer draft IDs and fake recognized/cleared status, receipt-number and actor input are rejected.

## Verification and next slice

Eight focused real-MySQL tests passed (149 assertions before the final additional misgrant assertion), covering accounts, role/assignment privacy, exact amount/editing, encrypted fields/HMAC key rotation, repeat submissions, disabled account/method/settings, binding a draft to its customer, counterfeit posting fields, stale updates and audit rollback. Independent-process barrier tests prove distinct MySQL sessions: identical concurrent commands yield one preparation/event and the same result ID; different one-paisa amounts under the same key yield one save and one conflict. These are preparation/idempotency tests, not evidence of ledger-posting correctness. Final regression/browser results are recorded in [operating status](OPERATING_RULES_STATUS.md).

Final regression passed 132 backend tests with one explicit Redis skip (1,380 assertions). PHPStan, Pint, frontend format/lint, TypeScript and production build passed. Both final desktop/mobile finance browser flows passed (1.3 minutes), including account setup, cheque radio selection, exact creation/editing, scoped private detail persistence after reload, history, list privacy and mobile summary cards. Account/receipt screenshots were visually inspected; cramped mobile account columns were replaced with stacked cards and retested. All fifteen migrations rolled back/reapplied only on `estateflow_test`; the application migration preserved existing records. All 226 documentation links resolve and four original source hashes match.

Next planned slice: explicit main-admin receipt posting controls and recognition, with configured official numbering, allowed business dates/period locks, payment-state transitions and controlled reversal/correction workflow. Opening balances and account/method routing must be explicit before related postings. Recognition must be idempotent under actual MySQL races and produce immutable account movements; final booking must consume only eligible verified payment evidence/funds under the shared occupancy lock. Optional formal GL, automated messaging and SaaS remain outside MVP.
