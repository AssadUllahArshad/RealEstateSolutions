# EstateFlow — MySQL setup and safeguards

Use MySQL 8.4 LTS with InnoDB and Laravel Eloquent. The `.env` sample below applies to an application running in Docker Compose alongside a service named `mysql`. Change `DB_HOST=127.0.0.1` when Laravel runs on the host and port 3306 is published.

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=estateflow
DB_USERNAME=estateflow
DB_PASSWORD=replace_with_locally_generated_secret
```

Never commit an actual `.env` or credentials. Require PDO MySQL extension (`pdo_mysql`). Create a least-privileged application database user. Use strict mode, utf8mb4 and foreign keys. Persist data using a named Docker volume.

## Data integrity
- Use database transactions plus row-level locking (`lockForUpdate`) for reservation/allotment, receipt, allocation and payout state transitions.
- Protect against multiple **active** allotments on a plot with a MySQL-compatible DB uniqueness technique, e.g. a generated nullable active_plot_id column indexed UNIQUE; ensure inactive records generate NULL. Test correctness against actual MySQL 8.4 and valid status transitions.
- Never infer MySQL has PostgreSQL filtered/partial unique indexes.
- Implement idempotency for receipt creation and important posting actions; store a unique idempotency key per scope.
- Store money in fixed precision DECIMAL(18,2) or integer minor units and perform exact decimal arithmetic.
- Handle deadlock retries with limited attempts and safe business idempotency.
- Verify daily backups via restore test; enable TLS for remote DB connections.

## Verification tasks for the coding assistant
1. Confirm compatible MySQL 8.4 docker image and PHP pdo_mysql extension.
2. Configure `.env.example`, Docker Compose health checks and persisted volume.
3. Run `php artisan migrate` against MySQL, not SQLite.
4. Add MySQL-backed tests for duplicate allotments, concurrent booking, duplicate receipts, payment allocations, and reversal integrity.
5. Check migrations and SQL for portability issues and indexes.

See `AI_MASTER_PROMPT.md` and `DEVELOPMENT_PLAN.md`.
