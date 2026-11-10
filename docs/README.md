# EstateFlow planning pack

Current legacy compensation and print implementation: [admin-reviewed legacy commission assignment and agent statements](LEGACY_COMMISSION_STATUS.md).

Current agent-compensation implementation: [collection-based commission accrual, reversal and controlled payments](AGENT_COMMISSION_STATUS.md).

Current cancellation-finance implementation: [controlled partial customer refunds and approved deductions](CUSTOMER_REFUND_STATUS.md).

Current purchaser ownership implementation: [controlled purchaser transfers and joint ownership history](PURCHASER_TRANSFER_STATUS.md).

Current role, owner-attribution and interface implementation: [admin/accountant operations, two plot owners, bilingual shell and branded themes](ROLE_OWNERSHIP_LOCALIZATION_STATUS.md).

Current statement implementation: [printable current principal statements](PRINCIPAL_STATEMENT_STATUS.md).

Current collections implementation: [installment allocation and live ledger](INSTALLMENT_LEDGER_STATUS.md).

Current adjustment implementation: [admin-posted manual charges and compensating waivers](MANUAL_CHARGE_STATUS.md).

Current owner-settlement foundation: [owner defaults, plot rate snapshots and booking gate](OWNER_SETTLEMENT_STATUS.md).

Current document implementation: [branded receipts and booking printouts](PRINT_DOCUMENTS_STATUS.md).

Current sales implementation: [final booking and down payment allocation](FINAL_BOOKING_STATUS.md).

Latest company/society identity: [Data Real Estate / Green Vista branding pack](branding/README.md), including recreated SVGs and preserved brochure references. Current finance implementation: [receipt recognition status](RECEIPT_RECOGNITION_STATUS.md).

Latest finance workflows: [installment ledger and approved corrections](INSTALLMENT_LEDGER_STATUS.md), [customer refunds](CUSTOMER_REFUND_STATUS.md), [purchaser transfers](PURCHASER_TRANSFER_STATUS.md), [owner settlement](OWNER_SETTLEMENT_STATUS.md), [agent commissions](AGENT_COMMISSION_STATUS.md), and [office expenses](OFFICE_EXPENSE_STATUS.md). Second-admin provisioning, outbound email setup, and Owner 2 profile configuration are intentionally deferred until the end of the remaining work.

Planning baseline: 2026-10-30. Foundation implementation has started following the user's instruction to proceed. See [actual foundation status](FOUNDATION_STATUS.md); business-policy decisions remain open.

## Reading order

1. [Product requirements](PRODUCT_REQUIREMENTS.md): scope, stories, acceptance criteria and prototype gaps.
2. [Decision questionnaire](FINANCIAL_DECISIONS.md): prioritized choices and consequences.
3. [Architecture](ARCHITECTURE.md), [data model and ERD](DATA_MODEL.md), [access matrix](RBAC_MATRIX.md).
4. [Roadmap](ROADMAP.md), [phase-1 execution checklist](PHASE_1_CHECKLIST.md), [stack ADR](ADRS/0001-stack.md).
5. [Discovery and tooling evidence](DISCOVERY.md).
6. [Single-society scope decision](ADRS/0002-single-society.md) and [inventory catalogue implementation](INVENTORY_STATUS.md).
7. [Reservation execution plan](RESERVATION_EXECUTION_PLAN.md): remaining M2 decisions, occupancy design, race tests and implementation checklist.
8. [Manual-hold implementation](HOLD_STATUS.md): approved operating rules, permissions, exclusivity, hold-state filters and actual validation.
9. [Run and test locally on Windows](LOCAL_TESTING.md): bundled PHP 8.4, Start/Stop launchers and private test credentials.
10. [Interface rebuild and customer workspace](CRM_UI_STATUS.md): prototype-based UI, scoped CRM, manual follow-ups, real dashboard and search.
11. [Sales desk and booking drafts](SALES_DRAFT_STATUS.md): exact proposed quotes, customer/plot selection, snapshots and version history; see [final booking](FINAL_BOOKING_STATUS.md) for confirmation and down-payment allocation.
12. [Accepted operating rules, admin settings and private identity](OPERATING_RULES_STATUS.md): latest user decisions, versioned configuration, CNIC/address and private customer documents.
13. [Role, ownership and localization status](ROLE_OWNERSHIP_LOCALIZATION_STATUS.md): expanded admin/accountant operations, two-owner plot attribution, local accountant access, English/Urdu infrastructure and theme verification.
14. [Manual charge implementation](MANUAL_CHARGE_STATUS.md): approved admin-only entry, immutable records, full waiver, scoped read access and accounting boundaries.
15. [Owner settlement foundation](OWNER_SETTLEMENT_STATUS.md): approved calculation and payout rules, exact plot-rate snapshots, booking gate and next entitlement slice.

## Status vocabulary and authority

Current financial path: account and receipt preparation ([collection preparation](COLLECTION_PREPARATION_STATUS.md)), receipt recognition ([posting controls](RECEIPT_RECOGNITION_STATUS.md)), accepted booking ([final booking](FINAL_BOOKING_STATUS.md)), live payments ([installment ledger](INSTALLMENT_LEDGER_STATUS.md)), and linked refunds/transfers/owner and commission workflows. Each status page records its specific verification and outstanding policy/release gates.

Purchaser increment: [single/joint proposed purchasers](PURCHASERS_STATUS.md), with exact shares, private customer scope and independent revision history. Pricing and installment preparation followed in the terms increment above.

- **AGREED REQUIREMENT** means explicit in the supplied sources or current planning request; it does not signify later business sign-off.
- **TECHNICAL PROPOSAL** means a reviewable implementation design, not an implemented feature.
- **ASSUMPTION** means a planning convenience requiring confirmation before dependent implementation.
- **NEEDS DECISION** means a business policy is unresolved. Decision IDs D01–D15 are maintained in the questionnaire. No unanswered item supplies a default.

The initial request limited discovery to planning. The subsequent instruction to proceed authorizes foundation implementation; it does not approve unanswered business rules. All four original files remain preserved reference inputs. Conflicting prototype behavior never establishes financial policy. Record approvals with decision ID, selected option, examples, approver, date and effective scope; then update linked acceptance criteria and ADRs.

## Source keys

| Key | Supplied source | Use |
|---|---|---|
| S1 | [Development plan](../DEVELOPMENT_PLAN.md) | Journeys, milestones, open questions and MySQL safeguards |
| S2 | [Master prompt](../AI_MASTER_PROMPT.md) | Product, invariants, modules, UI, quality and required documents |
| S3 | [MySQL setup](../MYSQL_SETUP.md) | Database configuration and integrity checks |
| S4 | [Clickable prototype](../index%20%281%29.html) | Screen vocabulary and illustrative interactions only |
| U1 | Current planning request | Planning only; preserve inputs; explicit unresolved policy boundaries |
| U2 | User's 2026-11-02 reservation answers | Manual release, no approval, delegated beneficiary design, authorized-admin-only changes; see D03 and hold status |
| U3 | User's subsequent booking/finance questionnaire answers | Agent finalization, down payment plus documents, admin discounts/correction approval, accountant expenses, configurable owner/commission/plan rules and live office hosting; see current operating rules |

Foundation and the inventory catalogue/export are implemented. The user approved the manual-hold scope on 2026-11-02; see hold status for implementation evidence. Contract/allotment conversion and discount, schedule, owner and commission policies remain separately gated.
