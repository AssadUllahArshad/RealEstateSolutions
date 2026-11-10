# EstateFlow — Implementation Plan

## Decision
Use one Laravel 13 modular-monolith application with React 19/TypeScript, Inertia.js 3, Tailwind, shadcn/ui, MySQL 8.4 LTS (InnoDB), Redis, server-side authorization, automated tests, and Docker Compose. Verify compatible package versions when scaffolding. Avoid separate Next.js/Node API for first release.

## Why
The team already knows Laravel. Inertia enables React without adding separate API authentication, deployment, and duplication of business logic. Strong transactional controls and financial correctness matter more than frontend/backend language uniformity.

## MVP user journeys
1. Admin defines society, block, plot sizes and plots.
2. Agent searches available plots and creates a customer or selects an authorized customer.
3. Agent reserves a plot (with expiry/approval rules decided by client).
4. Authorized user approves booking terms/discount and generates contract installment schedule.
5. Accountant collects a payment and allocates receipt to due installments.
6. Customer statement shows agreed charges, allocated payments, balance and due dates.
7. Admin sees inventory, collection and overdue reports; financial events have traceable actor/time.

## Milestones and release gates
**0. Scope and domain rules:** Approve ERD, booking lifecycle, permissions, receipt/discount/owner/commission policies. Gate: ambiguous financial rules documented, no fabricated defaults.
**1. Foundation:** Starter kit, authentication, roles/policies, base layout, CI/test suite, MySQL 8.4, demo seeders. Gate: authenticated navigation and server authorization tests pass.
**2. Inventory:** Configurable sizes, blocks, unique plot numbers per block, status transitions, history and filtering. Gate: no two active allotments under concurrent submissions.
**3. Customers and bookings:** Agent scope, identity docs, bookings, approvals, discounts and snapshotted plans. Gate: contract math is deterministic and history preserved.
**4. Installments and collections:** Due schedule, receipts, allocations, balances, reversals and statements. Gate: duplicate requests cannot produce duplicate receipts; balances reconcile.
**5. Operations finance:** Cash/bank book, expenses, owner agreement/settlements, referral commission lifecycle and payout approval. Gate: every money movement traceable with no silent edits.
**6. Reporting and follow-up:** Dashboard, aging report, reminders, CSV/PDF exports, calculator. Gate: totals tie to source transactions and date filters.
**7. Hardening/deployment:** Load/security tests, backup/restore, audit checks, staging UAT and documented runbook. Gate: business UAT sign-off.

## Suggested initial permission matrix
- Main Admin: configuration, user/role management, all society and financial records.
- Sub Admin: configurable broad operational scope, restricted role assignment and financial overrides by policy.
- Sales Agent: authorized customer and booking operations; no access to unrelated customers, owner balances or privileged finance.
- Accountant: receipts, allocations, statements and payouts subject to approval; cannot reassign super-admin permissions.
All decisions are enforced by server policies and queried data scope, not UI hiding alone.

## Initial database entity groups
Inventory: societies, blocks, plot_sizes, plots, plot_status_history, pricing.
CRM: users, customers, customer documents, agent assignments.
Sales: reservations, bookings, allotments, adjustments, approvals, plan templates, booking plans.
Collections: installments, receipts, receipt allocations, reversals.
Finance: financial accounts, cash transactions, expenses, owner agreements, owner entitlements and settlements.
Referrals: sources, booking referrals, rules, commission accrual and payments.
Platform: permissions, audit logs, reminders, attachments.

## Open questions requiring client confirmation
1. Is this only one society or multiple independent societies? Could data be separated by branch/office?
2. Can one plot have joint owners and multiple linked purchasers? Are transfers, cancellations and resales required?
3. What is the plot status/hold expiry and booking approval workflow?
4. Are plan schedules monthly only? Any fees, balloon payments, late penalties, grace periods or holiday rules?
5. When are rebates allowed and who can approve different amounts?
6. How are payments matched to installments (oldest-first automatic or accountant-selected)? What about advance payments and refunds?
7. Does 'balance sheet' mean operational customer balances or statutory double-entry accounts and financial statements?
8. How is the owner's entitlement computed, and when is it due? How do owner receipts, refunds and reversals work?
9. When is commission earned: on booking, down payment, or collected amount? Who approves payout and handles cancellations?
10. What customer identification information and documents are legally/operationally necessary? Who may access them?
11. Which reminder channels are in scope? Who sends messages, and what opt-in/consent process applies?
12. Are there legacy Excel files to import? What reports and print formats are mandatory?

## Functional definition of done
User story includes backend authorization; validation; tests; transactional persistence; audit/history if financial; robust empty/loading/error UI; migration + seeders; query performance review; reviewed acceptance cases; documentation.

## Build discipline for AI-assisted development
- One phase and vertical slice at a time.
- Review migration SQL and accounting calculations before applying.
- Make the agent execute and report test results, not merely assert success.
- Require clearly labeled assumptions and blockers.
- Commit after passing milestones.
- Review security and accounting logic manually; do not trust generated code uncritically.

## MySQL database implementation decisions
- Engine/version: MySQL 8.4 LTS / InnoDB for all business tables, utf8mb4 and strict SQL mode.
- Local Docker services: Laravel app (or host PHP tooling), MySQL, Redis; persistent MySQL volume, health checks and environment-only credentials.
- Laravel `.env`: `DB_CONNECTION=mysql`, `DB_HOST=mysql` (when Laravel runs inside Compose, otherwise `127.0.0.1`), `DB_PORT=3306`, `DB_DATABASE=estateflow`, `DB_USERNAME=estateflow`, and a secret `DB_PASSWORD`.
- Use migrations with foreign keys, indexes, decimal currency columns and tested rollback paths.
- For exclusive active allotments, MySQL does not support PostgreSQL-style filtered unique indexes. Specify and test a generated-column + UNIQUE-index approach (or alternative formally justified database invariant), combined with row locks and transactions.
- Exercise concurrency, idempotency, deadlocks/retries, payment allocation limits and reversal behavior against real MySQL in CI.
- No schema operations or SQL syntax that presumes PostgreSQL. Never use floating point for money.
- Secure DB credentials, least-privilege user, daily backup policy and restore drills before launch.
