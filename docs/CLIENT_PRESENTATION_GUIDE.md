# EstateFlow — client presentation guide

## What EstateFlow is

EstateFlow is Data Real Estate's bilingual office ERP for Green Vista Housing Scheme. It brings plot inventory, customers, bookings, installments, receipts, expenses, owner settlements, agent commissions and reports into one secure system. Amounts use PKR and business dates use Asia/Karachi.

## Best presentation order

1. Sign in and switch between English/Urdu and light/dark mode.
2. Show **Overview**, then find a plot in **Plot inventory**.
3. Open/create a **Customer**, add identity documents and a follow-up.
4. Use **Sales desk** to prepare terms and confirm a booking against cleared funds.
5. Show **Customer receipts**, the installment **Ledger** and printable documents.
6. Finish with finance controls, owner/agent reports, role access and business settings.

## Main pages

| Page | What to demonstrate |
|---|---|
| **Login & security** | Verified staff sign-in, password recovery, email verification, password confirmation and optional two-factor authentication. |
| **Overview** | Role-aware KPIs, available/held/booked plots, customers, drafts, follow-ups, block availability and recent activity. |
| **Global search** | Find plots or customers quickly and open the relevant record. |
| **Plot inventory** | Search/filter plots by society data, block, size, status and owner; view live availability and export it. |
| **Plot hold** | Place a manual hold for a customer, view its history and release it without waiting for admin approval. |
| **Society setup** | Configure society/office, blocks, plot sizes, plots, two owner profiles and assign every plot to its owner/admin. |
| **Customers** | Create, search, assign and export customer records with CNIC, phone, email, address and photo. Agents see assigned customers; authorized office roles can see all. |
| **Customer profile** | View contact details, activity history, bookings, private documents and follow-ups in one workspace. |
| **Follow-ups** | Track open/completed customer calls and tasks with responsible staff and due dates. |
| **Sales desk** | Create/search/export booking drafts and continue each proposal through customer, plot, purchaser, terms and confirmation steps. |
| **Booking draft** | Select the sales contact and plot, save the quoted price and maintain a complete revision history. |
| **Purchasers** | Record one or more legal purchasers, validate their identity data and require ownership shares to total 100%. |
| **Booking terms** | Build monthly, quarterly, half-yearly, yearly or custom installment schedules using approved business rules and exact decimal calculations. |
| **Booking confirmation** | Check required documents and down payment, allocate recognized receipts, freeze the agreed terms and book the plot atomically. |
| **Booked contract** | Review the confirmed contract, purchasers, balances, transactions and history; print the booking and customer statement. |
| **Transfer & corrections** | Request purchaser transfers, booking corrections or cancellation; an authorized admin records the final decision. |
| **Payment plan calculator** | Preview price, down payment, installments, owner value and commission without creating a booking or financial entry. |
| **Owner reports** | Compare plots, confirmed principal, collections and settlement position for Amir Ali's and Raja Irshad's assigned inventory. |
| **Operational reports** | Review inventory, bookings, collections, receivables aging, owner payouts and commissions for a selected period. |

## Finance pages

| Page | What to demonstrate |
|---|---|
| **Customer receipts** | Prepare cash, bank transfer, cheque, mobile-wallet or other receipts; issue an official branded receipt after controlled recognition. |
| **Posted receipt** | View recognition/clearance state, linked allocations and audit history; print the official receipt. |
| **Receivables** | See confirmed principal due by customer/booking with due and aging filters, then export the worklist. |
| **Installment ledger** | Follow every due installment, allocation, outstanding amount and approved correction with a full audit trail. |
| **Finance overview** | Monitor recognized collections and finance work waiting for action. |
| **Cashbook** | Review immutable cash movements created by controlled finance workflows; this is an operational cashbook, not a balance sheet. |
| **Financial accounts** | Manage cash, bank, wallet and other accounts, balances and account-level movement history. |
| **Opening balances** | Enter legacy customer, owner and commission balances at an approved cutover date, then activate them under control. |
| **Bank reconciliation** | Prepare statement reconciliation and let an authorized admin approve it. |
| **Office expenses** | Accountant records categorized expenses against a financial account and prints the expense voucher. |
| **Manual charges** | Admin adds an approved booking charge or compensating waiver without rewriting historical money records. |
| **Customer refunds** | Accountant prepares a refund with approved deductions; admin approves/rejects it and the system prints the result. |
| **Owner payouts** | Prepare and approve owner settlements using each plot's saved owner/rate snapshot and print the payout record. |
| **Agent commissions** | Calculate collection-based commission, handle reversals, prepare/approve payments, support controlled legacy assignments and print agent statements. |

## Administration and personal settings

| Page | What to demonstrate |
|---|---|
| **Business settings** | Main admin controls booking documents, installment rules, payment methods, late-charge rules, owner defaults, commissions, numbering and other effective business policies. |
| **Import data** | Download templates, dry-run and validate customer, owner, inventory or booking CSV files before committing them. |
| **Team & access** | View staff and assign Super Admin, Admin, Accountant or Sales Agent roles with password confirmation and an audit trail. |
| **Profile** | Each user updates their own name, phone and email details. |
| **Security** | Change password, enable two-factor authentication and review account protection. |
| **Appearance & language** | Use responsive light/dark themes and complete English LTR or Urdu RTL presentation. |

## Roles to explain

- **Super Admin:** complete system, policy, staff and security control.
- **Admin:** full agent workflow plus inventory management and operational approvals.
- **Accountant:** full agent/booking workflow plus receipts, accounts, reconciliation preparation, expenses, refunds, payouts and commission preparation.
- **Sales Agent:** assigned customers, plot holds, booking workflow, documents, follow-ups and receipt preparation.

Sensitive approvals remain separated: discounts, reversals, corrections, refunds, payouts and commission decisions require the appropriate admin permission.

## Reliability and presentation closing

Every important booking and finance action is permission checked, audited, idempotent and protected against conflicting updates. The interface is responsive, bilingual and branded for Data Real Estate and Green Vista; receipts, contracts, statements and vouchers are printable. Production runs securely at **https://erp.datarealestate.online** with automated CI checks, a verified backup process and a prepared direct-production deployment workflow.
