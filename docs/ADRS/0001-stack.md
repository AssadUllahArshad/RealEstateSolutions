# ADR 0001 — Modular Laravel monolith and transactional MySQL

Date: 2026-10-30. Status: architecture implemented for the foundation slice; exact installed dependencies resolved and locally verified as recorded in [foundation evidence](../FOUNDATION_STATUS.md). Business rules, remote CI and Redis runtime verification remain separate gates.

[Index](../README.md) · [Architecture](../ARCHITECTURE.md) · [Discovery](../DISCOVERY.md) · [Phase 1](../PHASE_1_CHECKLIST.md)

## Context

S1 and S2 select a Laravel modular monolith for an internal Pakistani society ERP. The team knows Laravel; transaction safety, scoped authorization and exact finance dominate. S3 requires real MySQL 8.4 integration tests and MySQL-compatible active-allotment uniqueness. U1 initially limited delivery to planning; subsequent user authorization started the foundation slice.

## Decision

Use one Laravel application with the official React/TypeScript/Inertia starter and session authentication, MySQL 8.4 LTS InnoDB for authoritative records, Redis queues/cache, server policies, decimal-safe PKR handling and explicit Asia/Karachi business dates. Target PHP 8.4, Laravel 13, React 19, Inertia 3 and Tailwind 4 subject to actual released compatibility verification. Use the starter's compatible dependency set rather than independently pinning incompatible majors. Runtime/package mismatch blocks scaffolding until resolved; any target change receives a documented ADR update.

Propose Docker Compose local services and Nginx/PHP-FPM production deployment, with separate workers/scheduler. Hosting is D14. Propose DECIMAL(18,2) with decimal-string boundaries and BCMath/verified decimal library; D05 still controls rounding/date behavior. Permission/test/export libraries are not selected until compatible releases are verified.

## Alternatives considered

- Separate Next.js frontend/Node API: adds authentication/deployment surfaces and duplicated business boundaries with no stated MVP need; not selected by sources.
- Microservices: distribute financial transactions and operational complexity prematurely; deferred.
- SQLite-only testing: cannot certify MySQL generated uniqueness, decimal behavior or locking; rejected as integrity evidence.
- Full double-entry accounting, automated messaging and SaaS tenancy: optional product expansions requiring explicit approval; outside this ADR's MVP scope.

## Consequences and verification

One transaction boundary simplifies booking and posting but requires disciplined domain actions and short locks. React receives authorized props rather than bypassing server scope. Operational finance cannot claim statutory accounting statements. MySQL-specific invariants need real DB CI. Exact package versions, official evidence and actual local results now appear in the [foundation report](../FOUNDATION_STATUS.md) and lockfiles. Project-local PHP 8.4.27 and MySQL 8.4.13 bypass the old system runtime without replacing XAMPP. Redis and Docker remain unavailable locally; their runtime checks are still open.

Review this decision if official starter compatibility conflicts with source targets, deployment constraints prevent the chosen runtime, or approved scale needs demonstrate a new boundary. Do not introduce a separate API or multi-tenancy merely to anticipate hypothetical future use.
