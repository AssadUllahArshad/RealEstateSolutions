# Run EstateFlow on this Windows machine

Business settings and private customer identity/documents are now available; see [current implementation](OPERATING_RULES_STATUS.md). The Start launcher sets upload_max_filesize=10M and post_max_size=12M for its PHP web process. Customer document roots are separate for local application, E2E and testing; `CUSTOMER_DOCUMENTS_PATH` can override the dedicated private disk. Keep it outside the public web root and back it up together with encryption keys.

[Index](README.md) · [Project instructions](../README.md) · [Foundation](FOUNDATION_STATUS.md)

## Ready-to-use runtime — 2026-11-03

The application keeps its installed Laravel 13 dependencies and existing lockfiles. This machine already has PHP 8.4.27 at `.runtime/php84/php.exe`; system `php` resolves to XAMPP PHP 8.1, which caused the startup confusion. No Laravel downgrade, XAMPP installation/removal, global PATH change or Windows service change is needed.

Double-click [START_ESTATEFLOW.cmd](../START_ESTATEFLOW.cmd), then visit [local login](http://127.0.0.1:18080/login). Read the private generated `.runtime/local-login.txt` file for your local administrator credentials. The launcher opens the browser by default; `-NoBrowser` suppresses opening it and `-Build` rebuilds the frontend using the correct PHP PATH. Existing compiled assets are ready; PHP/MySQL/Composer/npm packages are not installed by the launcher.

[STOP_ESTATEFLOW.cmd](../STOP_ESTATEFLOW.cmd) stops only its own web process, checking executable path, process ID and start time. It leaves MySQL running rather than terminating database writes. An existing database or occupied port is never replaced, and a conflicting unrelated web server is not stopped. Process state is kept under ignored `.runtime/local-services.json`.

## What startup changes

The [PowerShell launcher](../scripts/local-app.ps1) checks the existing PHP/MySQL files, initialized data directory, environment and vendor dependencies. It starts MySQL bound only to `127.0.0.1:33084` if needed. The [guarded PHP helper](../scripts/local-runtime.php) accepts only the local environment and existing `estateflow` MySQL 8.4 database, applies pending normal migrations and additive role grants, and creates a synthetic local administrator only if its email does not already exist. No society, plot, customer or financial fixture is inserted. Existing accounts/passwords are not reset.

The generated test admin is explicitly verified for local testing so SMTP is unnecessary; the provisioning event records this development channel. The normal staff provisioning command, public registration restrictions, email verification middleware and production auth behavior remain unchanged. Credentials are random and saved only in an ignored local file, never printed in validation output. This is development setup, not a deployment workflow.

Process-local PATH selects PHP 8.4 for PHP/Wayfinder builds; TEMP/TMP select project-local temporary storage because system temporary storage has previously filled. The PHP helper command [php.cmd](../scripts/php.cmd) also selects this runtime and temporary directory. The project PHP CA bundle now resides inside its own runtime directory; existing XAMPP files are not edited.

## Verification

Verified: project PHP reports 8.4.27; actual local startup, test-admin provisioning, Chrome sign-in, verified dashboard access, inventory and society setup navigation passed. Repeated Start retains the existing account/password and reports the server already running. Stop and restart passed while MySQL remained running. The helper refused `APP_ENV=production` before any database operation. PowerShell parsing, PHP syntax, Pint and frontend formatting/lint passed. No framework/package versions changed.

The initial repeat-start check exposed Windows PowerShell's JSON-array enumeration behavior in the process-state reader. Explicit array enumeration and null filtering fixed it; the complete repeat-start/stop/start sequence then passed. Stop also waits for web process exit before returning. This was a launcher issue, not an application login failure.

The app is a local built-in PHP server, not a production Apache/PHP deployment. Redis and remote CI remain separate open gates; the current manual inventory workflow does not need an expiry worker. Full backend/financial suites were not rerun for these launcher-only changes; prior feature evidence remains in the implementation reports.
