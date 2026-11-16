# EstateFlow production setup

Status: the first production release is installed, but public launch and automated deployment remain disabled until the StackCP site runs PHP 8.4, its SSL certificate is issued, and the final HTTPS smoke test passes. There is no staging environment. Every successful `main` CI run becomes eligible for direct production deployment after `PRODUCTION_DEPLOY_ENABLED` is set to `true`.

[Planning index](README.md) · [Deployment and restore runbook](RESTORE_DRILL_AND_DEPLOYMENT.md) · [Architecture](ARCHITECTURE.md) · [Staff provisioning](STAFF_PROVISIONING.md)

## Verified on 2026-11-16

- SSH alias: `o3hosting` (the earlier `o2hosting` spelling is not configured).
- Host: StackCP shared hosting account reached through o3host.
- PHP: `/usr/bin/php84` is PHP 8.4.27 and includes BCMath, cURL, DOM, Fileinfo, Intl, Mbstring, OpenSSL, PDO MySQL, XML and ZIP. The default `/usr/bin/php` is PHP 8.0.30 and must not run EstateFlow.
- Composer 2.10.3, Git 2.52.0 and `mysqldump` are available.
- Production uses MariaDB 10.11.19. Migrations, receipt posting, idempotency and six locking/concurrency scenarios passed against a disposable database on the same server. CI continues to certify MySQL 8.4 as the preferred baseline.
- The CLI release runtime is PHP 8.4.27. The EstateFlow public `.htaccess` selects the host's `x-httpd-php84` handler using o3host's documented per-directory syntax; verify the web response after every public-file deployment.
- Release `811a64b` is active. The clean production schema, three confirmed staff identities, two plot-owner profiles and pre-deploy backups are installed.
- Production mail uses authenticated SMTPS through `smtp.stackmail.com:465` with Amir Ali's business mailbox as the system sender. The SMTP verification message was accepted on 2026-11-16; rotate the initially supplied mailbox passwords after launch.
- `public_html` currently contains a different `blog-site` repository with uncommitted changes. It is not an EstateFlow target and must be preserved.
- No cron entries were returned for the SSH account.

## Required hosting layout

The EstateFlow subdomain uses a real public directory at `/home/sites/23b/7/7bfd37bd3c/datarealestate/erp`. Immutable private releases live at `/home/sites/23b/7/7bfd37bd3c/datarealestate/estateflow`; activation synchronizes only Laravel's public files into the public directory. The existing `public_html` is not an EstateFlow target.

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
| `PRODUCTION_DEPLOY_ROOT` | Private application path, currently `/home/sites/23b/7/7bfd37bd3c/datarealestate/estateflow` |
| `PRODUCTION_WEB_ROOT` | StackCP public path, currently `/home/sites/23b/7/7bfd37bd3c/datarealestate/erp` |

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

Install these StackCP cron entries:

```cron
* * * * * /usr/bin/php84 <deploy-root>/current/artisan schedule:run >> /dev/null 2>&1
* * * * * /usr/bin/php84 <deploy-root>/current/artisan queue:work database --stop-when-empty --max-time=50 --tries=3 >> /dev/null 2>&1
15 2 * * * <deploy-root>/current/deploy/daily-backup.sh <deploy-root> /usr/bin/php84 30 >> <deploy-root>/shared/storage/logs/backup.log 2>&1
```

Shared hosting does not guarantee a supervised long-running worker, so the queue command is deliberately bounded.

## Information still needed from the owner

1. Rotate all mailbox passwords that were previously shared or stored in plaintext, then update the protected production environment with the new system-sender password.
2. Add the documented GitHub `production` environment secrets and variables. Keep `PRODUCTION_DEPLOY_ENABLED=false` until the first off-host backup copy passes.
3. Add the documented scheduled tasks through StackCP; this hosting account is denied access to `crontab` over SSH.

## First-launch gate

Do not enable automatic deployment until repository checks pass, the production-compatible database suite passes, DNS/SSL and document root are verified, `shared/.env` is complete, a first backup is downloaded off-host, the scheduler is installed, and Amir Ali plus Tabish Hafeez complete the production smoke checklist. Real office transactions start only after those checks are recorded.
