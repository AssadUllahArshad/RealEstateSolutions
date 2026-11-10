# Discovery record

[Planning index](README.md) · [Stack decision](ADRS/0001-stack.md) · [Execution checklist](PHASE_1_CHECKLIST.md)

## Inspection evidence — 2026-10-30

This section records the initial planning-only inspection. Subsequent authorized implementation installed project-local tooling and application dependencies; see [foundation execution evidence](FOUNDATION_STATUS.md) for the current state. The original findings below are retained as historical evidence.

All four supplied files were read completely. Markdown was read in bounded sections; the long, minified prototype was reread as bounded character chunks to avoid truncation. No application scaffold, dependency manifests, lockfiles or Git metadata were present. `git rev-parse --is-inside-work-tree` reported that this is not a Git repository. No applicable AGENTS.md was found in the project or its ancestor directories through the drive root. No system configuration, package installation, scaffold or database connection was performed.

| Probe | Observed result | Consequence |
|---|---|---|
| `php --version` | PHP 8.1.25 CLI, XAMPP | Does not meet proposed PHP 8.4 target; do not scaffold using current PATH runtime |
| `php -m` | pdo_mysql, bcmath, mbstring, openssl, fileinfo, curl, zip, sodium present | Useful extensions exist on 8.1 only; recheck selected runtime and web/worker SAPIs |
| `composer --version` | 2.8.8; uses PHP 8.1.25 | Resolver will evaluate old runtime unless intentionally corrected later |
| `node --version` | v22.19.0 | Availability proven; compatibility with selected Vite/starter still unverified |
| `npm --version` | PowerShell blocks npm.ps1 by execution policy | No policy change needed for discovery |
| `npm.cmd --version` | 10.9.3 | Working Windows command entry point |
| `git --version` | 2.49.0.windows.1 | Git available; repository initialization is future work |
| MySQL, Redis, Docker command discovery | Not found on PATH | Does not prove absent from machine; no server/service/version or container availability established |

## Compatibility verification remains open

Laravel 13, PHP 8.4, React 19, Inertia 3 and Tailwind 4 are source targets, not a verified compatible package set. No registry metadata, current official starter manifest, Composer resolution, npm resolution, Docker image or runtime integration was verified in this planning pass. Record exact versions and dated official references before installation; never force incompatible dependencies or spoof Composer platform requirements to hide a runtime mismatch.

## Prototype observations

S4 has 16 screens: dashboard, plot inventory, block map, customers, sales, installments, reminders, payments, ledger, owner settlements, commissions, plans, calculator, team, settings. Its block map is a schematic grid, not GIS. Browser data, role switching, hardcoded business date and seeded identity information are demo mechanisms. Schedule generation reads mutable templates and uses JavaScript month rollover; these are unsuitable contract rules. Some seeded plot statuses have no matching sale; seed consistency must be rebuilt. The demo validates plot numbers globally, while S1 specifies uniqueness per block. Its generic payment form and purpose-text matching do not establish formal receipt, expense or settlement relationships.

## Preservation and verification

Only new planning documents are intended deliverables. No application tests are runnable because there is no application. Document checks cover required files, relative links and source preservation; planned runtime, concurrency and financial tests must not be reported as passed.

Final check found ten planning Markdown documents. Original source byte lengths match initial inspection (7,033; 15,111; 2,092; 44,301 respectively for S1–S4). No write operation targeted a supplied source. Final SHA-256 values, recorded for subsequent implementation preservation checks:

| Source | SHA-256 |
|---|---|
| S1 | `18DB8D4FC6D5B9800B84976458BECE139058A1C7E3558813061F4F965D207CE1` |
| S2 | `C7A2E48D72505F5CB19380980307F0F741FB323EF6E436925E36A8D526EAD9CB` |
| S3 | `03A205FE4639B0CD86778F7638DC7D8E47C0747EA9AC59C2588A0A487CD49091` |
| S4 | `819E8B5353009A5D1730E9A750BDE6147E0B9FFAB140F92AAC6C042B0191CB37` |
