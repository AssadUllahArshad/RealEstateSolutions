# Phase-1 execution checklist

[Index](README.md) · [Discovery](DISCOVERY.md) · [Architecture](ARCHITECTURE.md) · [Roadmap](ROADMAP.md)

Status: foundation slice implemented locally following authorization to proceed. See [execution evidence](FOUNDATION_STATUS.md) for checks actually run and the remaining Redis/CI/release gates. The checklist below remains the full M1 acceptance contract; unchecked items must not be inferred complete from source files alone.

## Smallest safe foundation slice

Goal: staff authenticate into a responsive protected shell backed by a tested MySQL application, with explicit server abilities and reproducible CI. No reservation, collection, owner or commission posting is included. Review [RBAC](RBAC_MATRIX.md) and D15 before releasing staff access; D01 is required before business scope migrations, but not for a generic authentication harness.

### 1. Preserve and verify prerequisites

- [x] Record checksums of the four supplied inputs and retain the planning pack.
- [ ] Recheck applicable instructions and repository status; agree repository initialization/remote separately from installation if needed.
- [x] Verify current official Laravel/starter documentation and released manifests. Dated evidence and exact installed versions are linked from the stack ADR to the foundation report.
- [x] Resolve compatible local PHP 8.4 without replacing XAMPP; verify CLI/Composer and required extensions. Production PHP-FPM/worker verification remains a deployment gate.
- [x] Verify the starter React/TypeScript/Inertia/Vite/Tailwind set, permission package, test runner and Playwright. Export/chart additions deferred.
- [ ] Confirm available MySQL 8.4/Redis services and supported Compose setup or documented host-service alternative. Verify image tags/digests and health checks before use. Current PATH does not expose Docker/MySQL/Redis.
- [x] Use npm.cmd on current Windows shell; no execution policy change made.

### 2. Establish application and local services

- [x] Scaffold the official React starter from the recorded upstream commit, preserving all supplied inputs.
- [x] Create lockfiles and pass real Composer platform checks without ignore-platform-reqs. Git initialization/commit is not performed.
- [ ] Configure local MySQL/Redis health checks and persistent development volumes; publish only needed local ports. DB_HOST is service name inside Compose or 127.0.0.1 for host PHP.
- [x] Supply local/test environment examples, ignored generated secrets, strict MySQL/InnoDB and separate schema-scoped users. Production requires a separate migration identity.
- [x] Set business clock to Asia/Karachi and UTC event storage; verify midnight behavior. No prototype fixed date.

### 3. Build the foundation vertical slice

- [ ] Configure internal staff provisioning after D15; verify session login/logout, CSRF, secure cookie and throttling behavior. Review starter registration exposure.
- [x] Define explicit foundation abilities/policies and audited role assignment without money overrides.
- [x] Create responsive shell, navigation, account menu and genuine empty workspace; no fabricated finance cards or prototype role selector.
- [x] Add guarded synthetic browser-test DEMO users with only foundation role definitions; production grants still await D15.
- [ ] Configure Redis queue and one harmless idempotent test job; do not store authoritative money in Redis.
- [x] Add request correlation without client-supplied IDs/PII and audit events for access changes.

### 4. Execute evidence and close the gate

- [x] Run fresh migrations and schema checks on MySQL 8.4; all six migration rollback/reapply paths passed on the guarded disposable schema.
- [x] Run backend auth, role-change, object-access, tampered-input and revocation tests on MySQL 8.4.
- [x] Run actual lint, TypeScript/PHP static checks and production build; commands/results recorded in foundation evidence.
- [x] Run browser login/logout/protected-navigation and mobile/keyboard checks; 6 passed after the recovery-screen continuation, including mobile navigation and actual CSRF/session-expiry recovery.
- [ ] CI provisions MySQL 8.4 and Redis, installs locked dependencies and runs those same checks without production secrets.
- [x] Report actual commands/results, versions, migrations, screens and remaining gaps in the foundation report; unimplemented finance/concurrency tests remain explicitly unverified.
- [ ] Review remaining M1 release gates (real Redis, remote CI/Compose and D15). D01 and manual-hold rules under D03 are accepted; catalogue and manual holds are implemented. Contract/allotment transitions remain separately gated. See [hold status](HOLD_STATUS.md).

## Compatibility evidence after local installation

| Component | Source target | Evidence required | Current status |
|---|---|---|---|
| PHP / Laravel | 8.4 / 13 | Official constraints and chosen runtime output | Portable PHP 8.4.27 / Laravel 13.39.0 verified |
| Official React starter | React 19 / Inertia 3 / TS | Exact commit and locked manifests | Commit and installed set recorded in foundation report |
| Vite / Tailwind / UI | Compatible starter set / Tailwind 4 | Peer resolution; production build | Starter Vite Plus 1.4.0 / Tailwind 4.3.3; build passed |
| Permission package | Compatible maintained release | Constraints and policy tests | Spatie 8.3.2; access tests passed |
| MySQL / Redis | 8.4 InnoDB / compatible Redis | Services and actual integration tests | Portable MySQL 8.4.13 verified; Redis integration remains open |
| Tests / exports / charts | Suitable maintained libraries | Runtime constraints; only add when needed | PHPUnit and Playwright installed; export/chart packages deferred |
