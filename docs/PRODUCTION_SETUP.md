# EstateFlow production setup

Status: repository automation is prepared but deliberately disabled until the EstateFlow site, document root, database and GitHub production secrets exist. There is no staging environment. Every successful `main` CI run becomes eligible for direct production deployment after `PRODUCTION_DEPLOY_ENABLED` is set to `true`.

[Planning index](README.md) · [Deployment and restore runbook](RESTORE_DRILL_AND_DEPLOYMENT.md) · [Architecture](ARCHITECTURE.md) · [Staff provisioning](STAFF_PROVISIONING.md)

## Verified on 2026-11-15

- SSH alias: `o3hosting` (the earlier `o2hosting` spelling is not configured).
- Host: StackCP shared hosting account reached through o3host.
- PHP: `/usr/bin/php84` is PHP 8.4.27 and includes BCMath, cURL, DOM, Fileinfo, Intl, Mbstring, OpenSSL, PDO MySQL, XML and ZIP. The default `/usr/bin/php` is PHP 8.0.30 and must not run EstateFlow.
- Composer 2.10.3, Git 2.52.0 and `mysqldump` are available.
- The database client identifies as MariaDB 10.11.19. The actual EstateFlow database engine and SQL modes remain unverified until its database is created. CI continues to certify MySQL 8.4.
- `public_html` currently contains a different `blog-site` repository with uncommitted changes. It is not an EstateFlow target and must be preserved.
- No cron entries were returned for the SSH account.

## Required hosting layout

Create a separate site or subdomain for EstateFlow in StackCP. Point its document root to `<estateflow-deploy-root>/current/public`. The deployment root must not be the existing `public_html`.

```text
estateflow/
├── .estateflow-production
├── backups/
├── current -> releases/<commit-sha>
├── releases/
└── shared/
    ├── .env
    └── storage/
```

The marker file prevents the activation script from operating on an accidental path. `shared/.env`, `shared/storage` and `backups` must be mode 700/600 as appropriate and unavailable through the web server.

## GitHub production configuration

Create a GitHub Environment named `production`. Add these environment secrets:

| Name | Value/source |
|---|---|
| `PRODUCTION_SSH_HOST` | Real SSH hostname, currently `ssh.gb.stackcp.com` |
| `PRODUCTION_SSH_PORT` | SSH port, normally `22` |
| `PRODUCTION_SSH_USER` | Hosting SSH user shown in StackCP |
| `PRODUCTION_SSH_PRIVATE_KEY` | A dedicated deploy private key; add only its public key to the host |
| `PRODUCTION_SSH_KNOWN_HOSTS` | Output from a separately verified host-key check |
| `PRODUCTION_DEPLOY_ROOT` | Absolute EstateFlow path, separate from the existing blog site |

Add these environment variables:

| Name | Value |
|---|---|
| `PRODUCTION_PHP_BIN` | `/usr/bin/php84` |
| `PRODUCTION_HEALTH_URL` | `https://<estateflow-domain>/up` |
| `PRODUCTION_DEPLOY_ENABLED` | Keep `false` until the first manual setup and smoke check pass; then set `true` |

The [production workflow](../.github/workflows/deploy-production.yml) builds assets and production Composer dependencies in GitHub Actions, uploads an immutable commit release, creates a database/document/key backup, enters maintenance mode, runs migrations, caches Laravel configuration, atomically changes `current`, and checks `/up`. It retains five code releases. Backup retention is handled separately by the daily backup job and must retain 30 days.

## Production `.env` checklist

Create `shared/.env` directly on the server. Never copy it into Git or CI output.

- `APP_NAME=EstateFlow`, `APP_ENV=production`, `APP_DEBUG=false` and the HTTPS `APP_URL`.
- A generated `APP_KEY` stored in the separate key backup.
- Asia/Karachi application business timezone.
- `DB_CONNECTION=mysql` with the actual StackCP host/database/user/password and a database-limited user.
- Secure, HTTP-only, same-site session cookies.
- Database queue/cache/session drivers unless Redis is later verified.
- `CUSTOMER_DOCUMENTS_PATH=<deploy-root>/shared/storage/app/customer-documents`.
- SMTP host, port, encryption, username, password and sender address.
- `LOG_LEVEL=warning` after launch diagnostics are complete.

Run `composer check-platform-reqs` and `php artisan estateflow:check-services` with `/usr/bin/php84`. Confirm the connection reports the expected database server and strict SQL behavior. MariaDB production use requires the finance, idempotency, locking and concurrency suites to pass against a temporary database on the same server before enabling deployment.

## Scheduler and queues

Add these StackCP cron entries after the deploy root is known:

```cron
* * * * * /usr/bin/php84 <deploy-root>/current/artisan schedule:run >> /dev/null 2>&1
* * * * * /usr/bin/php84 <deploy-root>/current/artisan queue:work database --stop-when-empty --max-time=50 --tries=3 >> /dev/null 2>&1
```

Shared hosting does not guarantee a supervised long-running worker, so the queue command is deliberately bounded.

## Information still needed from the owner

1. The production EstateFlow hostname, for example `erp.datarealestate.online`, and confirmation that it is a separate StackCP site with SSL.
2. The absolute document-root/deployment path created for that site, separate from the current blog `public_html`.
3. A new EstateFlow database name, username and password. Enter secrets through the hosting/GitHub secret interfaces, not chat.
4. Confirmation whether StackCP labels the new database MySQL or MariaDB, plus its server version.
5. Creation of the planned mailboxes and SMTP settings: `amir.ali@datarealestate.online`, `raja.irshad@datarealestate.online`, and `tabish.hafeez@datarealestate.online`.
6. A dedicated GitHub Actions deploy key, or permission to generate one and add only its public half to StackCP.
7. GitHub repository/environment access to create the secrets and variables, or the owner can enter their values directly.

## First-launch gate

Do not enable automatic deployment until repository checks pass, the production-compatible database suite passes, DNS/SSL and document root are verified, `shared/.env` is complete, a first backup is downloaded off-host, the scheduler is installed, and Amir Ali plus Tabish Hafeez complete the production smoke checklist. Real office transactions start only after those checks are recorded.
