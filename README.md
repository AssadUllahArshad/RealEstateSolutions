# EstateFlow

Internal housing society ERP for Pakistan. Initial currency PKR; business dates Asia/Karachi; event timestamps UTC.

The foundation provides official-starter session authentication, optional two-factor authentication, a responsive workspace, explicit abilities, protected team views and audited role changes. The inventory catalogue supports one configurable society/office per installation, custom blocks/sizes, filters and a schematic grid. CRM, manual follow-ups and non-binding booking drafts are implemented; final allotment and financial modules remain planned. No financial policy has been inferred from the prototype.

Recovery screens now explain restricted access, missing pages, expired sessions and rate limits. Production server-error screens show a safe request reference instead of internal exception details. MySQL connections explicitly use UTC while business dates remain Asia/Karachi.

- [Planning pack](docs/README.md)
- [Foundation implementation and verification](docs/FOUNDATION_STATUS.md)
- [Outstanding business decisions](docs/FINANCIAL_DECISIONS.md)
- [Inventory catalogue and accepted scope](docs/INVENTORY_STATUS.md)
- [Phase-1 checklist](docs/PHASE_1_CHECKLIST.md)
- [Redesigned interface, customers and follow-ups](docs/CRM_UI_STATUS.md)
- [Sales desk and booking drafts](docs/SALES_DRAFT_STATUS.md)
- [Single/joint proposed purchasers and share validation](docs/PURCHASERS_STATUS.md)
- [Proposed pricing, admin discounts and installment schedules](docs/TERMS_STATUS.md)
- [Financial accounts and unposted receipt preparation](docs/COLLECTION_PREPARATION_STATUS.md)
- [Receipt numbering, recognition, cheque clearance and accounting reversal](docs/RECEIPT_RECOGNITION_STATUS.md)
- [Accepted operating rules, business settings and private customer identity](docs/OPERATING_RULES_STATUS.md)

The dashboard now displays real operational counts, block distribution, follow-up tasks and recent activity. Use **Customers** to add contacts and schedule internal follow-ups; admins can assign records to staff and agents see only their assignments. The header search finds authorized plots/customers. The navy/blue application shell, inventory dialogs and sign-in screens follow the supplied prototype's visual direction. Booking terms, identity documents and financial posting are still separate implementation gates.

## Runtime and dependencies

Main admins can now use **Business settings** for booking requirements, plan/date/rounding options, collection rules, late fees, owner percentages and commissions. Unset values remain unconfigured. Customer profiles support encrypted CNIC/address and private photo/document upload/download. These additions prepare the agreed agent booking and admin correction workflows; they do not yet post bookings, payments, expenses or settlements.

Use **Sales desk** to save non-binding customer/plot proposals with exact PKR quotes, catalogue snapshots and version history. Drafts leave holds, ownership and financial balances unchanged. Final booking/allotment and payment rules remain separate decisions.

PHP 8.4 with pdo_mysql, bcmath, mbstring, fileinfo, openssl, curl and zip; Composer 2; Node 22.19+ within supported package engines; MySQL 8.4 InnoDB; Redis for queue/cache integration. Exact installed versions are in the lockfiles and foundation report. Do not use system XAMPP PHP 8.1 or MariaDB as a substitute.

On this Windows workspace, portable PHP and MySQL are in ignored `.runtime/`; XAMPP was not replaced. Use `scripts\php.cmd` from the project root to select portable PHP. Composer must also use this runtime explicitly, for example `scripts\php.cmd C:\composer\composer.phar install`. Portable runtimes and local secrets are not distributed with source code.

## Local start

### This Windows machine: double-click start

PHP 8.4.27 and MySQL 8.4.13 are already installed inside this project's ignored `.runtime` directory. You can keep the existing XAMPP installations and system PHP 8.1.

1. Double-click [START_ESTATEFLOW.cmd](START_ESTATEFLOW.cmd). It uses project PHP 8.4, starts the existing local MySQL database if needed, applies pending migrations/role grants and starts the app at [http://127.0.0.1:18080/login](http://127.0.0.1:18080/login). It never initializes/replaces a database.
2. Open the private `.runtime/local-login.txt` file for your local test email/password. The launcher creates a verified local-only test administrator once, records provisioning, and retains any existing account/password on subsequent starts. This helper refuses non-local environments or databases other than `estateflow` at `127.0.0.1:33084`; it does not change production authentication or existing staff verification.
3. Use [STOP_ESTATEFLOW.cmd](STOP_ESTATEFLOW.cmd) to stop the managed web server. MySQL stays running to avoid force-terminating a database. Neither script changes XAMPP or Windows services.

To rebuild frontend changes with the correct PHP on PATH, run `START_ESTATEFLOW.cmd -Build`. Build failure stops startup. No Node dev server or Apache is needed when using the built assets. Logs are `.runtime/local-web.log`, `.runtime/local-web-errors.log` and `.runtime/local-mysql.log`; credentials/runtime logs must stay private. PHP's CA bundle is copied into `.runtime/php84/cacert.pem`, so this runtime no longer depends on the XAMPP CA path.

### Manual setup / other machines

1. Copy `.env.example` to `.env` on a fresh checkout and supply local secrets. The existing workspace already has an ignored, generated local environment; do not overwrite it.
2. Start MySQL/Redis with `docker compose up -d` after setting `MYSQL_ROOT_PASSWORD` and `DB_PASSWORD` in the local environment. Compose binds services to loopback only. Host PHP uses `DB_HOST=127.0.0.1`; a containerized app would use `mysql` and `redis` service names. The current machine uses isolated portable MySQL on **33084**; its local `.env` already selects that port. Redis is not locally verified; the current local cache uses files while queues remain configured for Redis.
3. Install locked dependencies with compatible PHP/Composer and `npm.cmd ci`; generate an application key on a fresh environment only; run `scripts\php.cmd artisan migrate --seed` against the intended local database.
4. Run `npm.cmd run build` with portable PHP on PATH (Wayfinder invokes PHP), or run the frontend dev server separately.
5. Start `scripts\php.cmd artisan serve --host=127.0.0.1 --port=18080` and visit [the local sign-in page](http://127.0.0.1:18080/login).

For this workspace, restart portable MySQL in a separate terminal if needed:

```powershell
& .runtime/mysql-8.4.13-winx64/bin/mysqld.exe --no-defaults --basedir="D:/Personal/Projects/RealState/.runtime/mysql-8.4.13-winx64" --datadir="D:/Personal/Projects/RealState/.runtime/mysql-data" --bind-address=127.0.0.1 --port=33084 --mysqlx=OFF --skip-name-resolve
```

Do not reinitialize existing data. No Windows service was installed. The generated initialization SQL is private runtime material, not an application migration or deployment script.

## Staff access

Public registration and staff self-deletion are disabled. Provision an account from a trusted operator console:

```text
scripts\php.cmd artisan estateflow:staff admin@example.test --name="Office Admin" --role=super_admin
```

The command prompts for a password and records provisioning; it never prints the password. Email verification is required. Local mail uses the log transport: follow the verification URL in the private local Laravel log, or configure a development mailbox. Production provisioning/MFA and delegated permissions still require D15 approval. No live staff or preset production password is seeded.

Main admin receives explicit staff administration, catalogue/configuration/export and manual-hold abilities. Agents receive inventory view and immediate hold placement; authorized admins can release/block. Other financial abilities remain gated. Role changes require password confirmation, are audited and cannot target the acting user's own role.

## Society and inventory setup

After applying migrations and rerunning the role seeder, main admin can open `/inventory/setup` to enter the society/office profile, add or rename blocks, and define any positive plot size in marla. Then use `/inventory` to add/edit plots, optionally enter a catalogue list price, search/filter/sort, or switch to the schematic grid. No society name, blocks or sizes are assumed for the application database. Another society can configure its own installation; multiple societies/branches cannot be inserted into the same installation.

Plot numbers are unique within each block. Prices use exact decimal strings; changing a catalogue price does not approve a sale. Agents and admins can open a plot's hold screen and immediately place an internal manual hold with a purpose. Filter inventory by Held, Blocked or No active hold or block; catalogue CSV exports respect the same filters. Only authorized admins can release or block; an existing occupancy must be released before replacement. There is no expiry or approval queue. Holds are staff-attributed and create no customer booking, allotment or financial entitlement. Catalogue and hold changes retain actor/history with no delete endpoint. See [manual-hold status](docs/HOLD_STATUS.md).

## Verification

Run the read-only service check with `scripts\php.cmd artisan estateflow:doctor`. It validates runtime extensions, actual MySQL version/settings and Redis connectivity without printing connection credentials or changing records. `--skip-redis` explicitly omits Redis and reports it as skipped; it does not certify queue delivery. The default check currently fails on this machine because Redis is unreachable.

Configure an isolated **estateflow_test** MySQL 8.4 database with its own limited user in ignored `.env.testing`. The test harness refuses a different database name, engine/version or environment before migrations. Tests can recreate tables in this test database; never point it at operational data.

```text
scripts\php.cmd artisan test
scripts\php.cmd vendor/bin/pint --test
scripts\php.cmd vendor/bin/phpstan analyse --no-progress
npm.cmd run types:check
npm.cmd run check
npm.cmd run build
```

See the foundation and inventory reports for actual results and browser commands. Real Redis integration requires `RUN_REDIS_TESTS=true` and an isolated service; its absence is an explicit skip, not a pass. CI defines MySQL 8.4, Redis and browser checks, but has not run remotely. Duplicate-plot MySQL concurrency is tested now; booking, receipt-idempotency and financial tests will accompany their modules.

When using file cache for browser tests, give `.env.e2e` distinct `CACHE_FILE_PATH` and `CACHE_LOCK_PATH` directories under `storage/framework/cache/e2e/`. Do not share the local application's permission cache across databases. Backend tests use the array cache; the production proposal remains Redis.

## Attribution

Based on the official [Laravel React starter](https://github.com/laravel/react-starter-kit), commit `98d9b821baba4f4cc4120c96d35971366cec53e6`. The upstream starter is MIT licensed. Original supplied requirements and prototype remain unchanged.
#   R e a l E s t a t e S o l u t i o n s  
 