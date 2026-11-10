# Foundation implementation and evidence

Baseline: 2026-10-30; updated 2026-10-31. [Planning index](README.md) · [Local setup](../README.md) · [Decision register](FINANCIAL_DECISIONS.md)

The user's instruction to proceed authorized the foundation slice. Subsequent single-society clarification authorized the [inventory catalogue slice](INVENTORY_STATUS.md). Original planning-only restrictions remain historical context. This is not full ERP completion or a production release.

## Implemented

- Official Laravel React starter copied into the project without replacing supplied files or planning documents. Upstream commit: `98d9b821baba4f4cc4120c96d35971366cec53e6`.
- Session authentication, CSRF, login throttling, reset/verification flows and optional 2FA retained. Public registration and passkeys removed through the official starter's feature selector. Staff self-deletion disabled to retain attribution. No production user/password seeded.
- EstateFlow sign-in, responsive protected workspace, Karachi business date, PKR context and honest empty states. Planned modules show no invented financial totals. Team directory is paginated and restricted; own staff profile is accessible while unrelated staff records are denied without ability.
- Mobile navigation closes its drawer after page changes; browser testing exposed and verified this accessibility/visibility requirement. See final browser results below for execution status.
- Four role names created. Foundation abilities `users.view` and `access.manage` are now joined by `inventory.view`, `inventory.manage` and `configuration.manage` in the inventory slice; main admin gets these explicit abilities. No super-admin bypass of future business rules. Other roles receive no implied business permissions. These assignments do not approve the full proposed RBAC matrix or D15 production policy.
- Audited transactional role changes with target/actor authorization, password confirmation, request throttling, allowed role validation and self-change prevention. Role mutation is serialized; authorization is rechecked under the transaction. Failed audit rolls back role mutation. Repeat assignment is a no-op. Trusted-console staff provisioning requires a prompted password and leaves email verification pending.
- Append-only application audit model, request correlation IDs without raw personal values, UTC event handling and injected Karachi business clock.
- MySQL-specific integration harness refuses engines/versions other than MySQL 8.4 and any database other than `estateflow_test`. Separate generated local/test credentials and schemas; no SQLite fallback. Redis probe job is retry-safe and has an opt-in real-worker integration test.
- Compose definition for MySQL 8.4 and Redis, persistent volumes and loopback ports; CI definition for PHP 8.4, MySQL, Redis, frontend checks and desktop/mobile Playwright. Git was not initialized and no remote workflow was run.

## Verified dependency/runtime evidence

| Component | Installed/resolved | Evidence |
|---|---|---|
| Portable PHP | 8.4.27 NTS x64 | Official archive checked against published SHA-256; CLI and platform checks executed |
| Laravel | 13.39.0 | Composer lock and executed application/tests |
| Fortify / server Inertia | 1.41.0 / 3.5.2 | Composer lock |
| Permission package / Redis client | Spatie 8.3.2 / Predis 3.7.0 | Composer resolved with real PHP 8.4; access tests executed |
| React / client Inertia | 19.3.1 / 3.9.1 | npm lock; successful build/typecheck |
| TypeScript / Tailwind / Vite toolchain | 5.9.3 / 4.3.3 / Vite Plus 1.4.0 | Official starter retains its Vite alias rather than manually mixing package majors |
| Node / npm | 22.19.0 / 10.9.3 | Existing tools; no system update performed |
| Portable MySQL | 8.4.13 x64 | Official HTTPS archive and published checksum; actual server version and MySQL-backed tests |
| Redis | Not locally available | Predis installed and CI/Compose defined; real Redis delivery remains unverified locally |

Sources checked: [official starter manifests](https://github.com/laravel/react-starter-kit/tree/98d9b821baba4f4cc4120c96d35971366cec53e6), [Laravel 13 installation](https://laravel.com/docs/13.x/installation), [official PHP Windows releases](https://windows.php.net/downloads/releases/releases.json), [official MySQL 8.4 downloads](https://dev.mysql.com/downloads/mysql/8.4.html). Lockfiles, not floating upstream main, define this project's dependencies. PHP requirement is explicitly ^8.4 with bcmath and pdo_mysql. No ignored platform requirements or fake Composer platform version was used.

## Local environment boundaries

Portable tools live in ignored `.runtime/`; system PATH/XAMPP configuration was not changed. Portable MySQL runs on loopback port 33084 with its own data directory, separate from XAMPP MariaDB 10.4. It was initialized as a new directory, then secured by initialization SQL before accepting application use. Root is not the application's account. This developer account can migrate its own schema; production still requires distinct application/migration privileges.

The current local cache uses files because Redis is unavailable; `.env.example` and CI retain the intended Redis configuration. This is an explicit local limitation, not a replacement for the agreed Redis architecture. Composer/npm caches and temporary browser material are project-local where configured. Chromium download failed due temporary-disk space; installed Chrome is the local browser fallback. No existing listener on port 8000 was stopped; browser tests use 18084, and documented manual development uses 18080.

## Actual verification

The first 2026-10-31 continuation added recovery screens (403/404/419/429 and production 500/503), preserved JSON error contracts and retry headers, explicitly set MySQL sessions to UTC, and introduced `estateflow:doctor`. The subsequent user clarification resolved D01 as one configurable society/office per installation. The inventory slice adds one catalogue migration and three explicit abilities; it introduces no financial or reservation default. Latest inventory evidence is recorded separately in [inventory status](INVENTORY_STATUS.md); the table below retains foundation-slice results.

| Command/check | Result |
|---|---|
| `php composer.phar install` / compatible package additions | Passed; dependencies and lockfiles created |
| `composer check-platform-reqs` using portable PHP | Passed |
| `npm.cmd install` | Passed; installer reported no known advisories at that time |
| `php artisan migrate --seed` on local MySQL 8.4 | Passed after correcting seeder cache invalidation |
| `php artisan test` | 62 passed, 1 skipped (63 total); 283 assertions, on MySQL 8.4 |
| `php vendor/bin/phpstan analyse --no-progress` | Passed; zero errors |
| `php vendor/bin/pint --test` | Passed after formatting |
| `npm.cmd run types:check` | Passed |
| `npm.cmd run check` | Non-mutating formatting/lint check passed |
| `npm.cmd run build` | Passed |
| Compose and workflow YAML parsing | Passed syntax parsing; services/workflow not executed locally |
| Playwright desktop/mobile | 6 passed on installed Chrome, including access-denied recovery, missing pages and actual CSRF/session-expiry responses |
| MySQL migration rollback/reapply | Passed for all six migrations on guarded, disposable estateflow_test only |
| Remote CI / Compose / real Redis delivery | Not run locally; explicit remaining gates |
| `php artisan estateflow:doctor --skip-redis` | Passed all selected PHP/MySQL checks; Redis explicitly skipped |
| `php artisan estateflow:doctor` | Returned failure because actual Redis connectivity failed; all PHP/MySQL checks passed |

Tests cover auth, verification, reset, 2FA, profile settings, internal provisioning, positive/negative abilities, foreign staff IDs, self-promotion denial, tampered role/actor data, recent-password enforcement, permission revocation, role audit rollback, MySQL engine/strictness, Karachi midnight and a replayed queue-handler unit scenario. Business booking races, receipt idempotency and finance do not exist yet and have not been certified. CI's actual Redis worker test is distinct from replaying a handler with an in-memory test cache.

Additional recovery tests verify guest 404 rendering without authenticated layout assumptions, genuine Inertia error-navigation responses, secret-free production error props, status/Retry-After preservation, JSON clients retaining their contract, actual UTC database sessions, and diagnostics failure reporting without connection-detail leakage. The diagnostic Redis-failure test uses a mock for error-path coverage; it is not a substitute for real Redis integration.

The expanded desktop/mobile browser suite passed all six checks against synthetic staff in the isolated test database. Missing-page screenshots were captured and the desktop layout was visually inspected. Browser tests verified that a real login POST without a CSRF token returns the custom 419 Inertia page and that an unauthorized agent can return from the 403 screen to the workspace. The original four supplied file hashes still match, and all documentation links resolve. The continuation changes no schema and introduces no reservation/financial rules.

The final formatting/lint process initially could not start because the sandbox setup-log disk was full. It was rerun through the approved external command path with TEMP/TMP set to the project-local temporary directory; formatting and lint passed. This resource limitation does not indicate a successful Redis or remote CI check; those gates remain open.

One concurrent browser/formatter run exhausted local formatter worker memory. A subsequent sequential formatter/lint/type/build run passed; this was an execution-resource issue, not an ignored lint failure. Initial browser timing failures were traced to successful login redirects that exceeded the default five-second assertion window on this development server; web assertions now wait up to 15 seconds without arbitrary sleeps. Mobile testing then found a real persistent-drawer issue; closing the drawer on Inertia navigation fixed it, and all four desktop/mobile checks passed on the final build. The mobile workspace screenshot was visually inspected for readability and layout. Original source hashes and all 13 documentation-file links were checked successfully.

## Browser reproduction

Run backend tests first; they may rebuild `estateflow_test`. Then prepare `.env.e2e` using the same dedicated test DB credentials, an application key, `APP_NAME=EstateFlow`, `APP_URL=http://127.0.0.1:18084`, database sessions and file cache. Keep it ignored. Never run browser fixtures against the local operational schema.

Set a synthetic `E2E_PASSWORD` in the current process (at least 12 characters); run `php artisan db:seed --env=e2e --class=BrowserTestSeeder` and `npm.cmd run test:browser` with compatible PHP on PATH. The fixture seeder refuses a non-test database. On this machine set `PLAYWRIGHT_CHANNEL=chrome` and project-local TEMP/TMP; on CI install Playwright Chromium. No password belongs in committed test source or summaries. Test traces/screenshots are ignored and treated as private test material.

## Remaining gates and next slice

M1 is not declared release-complete until real Redis delivery, remote CI/Compose execution and D15 production access policy are reviewed. MySQL restore/recovery and production hosting remain later release gates. Do not deploy these developer settings. D01 is resolved and the configurable inventory catalogue is implemented. D03 still blocks operational availability/occupancy migrations; D02 and financial policies gate sales and finance. No GL, automated messaging or SaaS scaffolding has been introduced.

## Source preservation

All four source SHA-256 values still match the planning baseline in [discovery](DISCOVERY.md), including after formatting commands. No supplied source was modified. New application code, tooling definitions, lockfiles and documentation are the only intended changes.
