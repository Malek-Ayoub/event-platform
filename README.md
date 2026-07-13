# Event Platform

Multi-tenant event ticketing backend for Syria. Buyers pay organizers directly via mobile wallets; the platform tracks commission receivable, issues tickets, and provides settlement, reporting, and dashboard APIs.

**Backend Status: Frozen (v1)** — tagged `v1.0-backend-freeze`. New backend features are limited to bug fixes, security, performance, or customer-driven changes. Frontend work continues in a separate repository.

## Current Status

| Area | Status |
|------|--------|
| Backend | v1 Feature Freeze |
| Frontend | Planned (`event-platform-frontend`) |
| Production | Pending |

## Architecture Overview

- **Multi-tenancy:** Subdomain-based venue resolution (`{venue}.base_domain`) with `BelongsToVenue` global scope on tenant tables.
- **Payments:** Manual wallet transfer + API Syria verification (no hosted checkout, no webhooks).
- **Orders & inventory:** Atomic order reservation and ticket issuance with pessimistic locking on serial counters and ticket type capacity.
- **Outbox:** Domain side-effects (commission ledger, QR, PDF, email) written in the same transaction as aggregate changes; processed by `php artisan outbox:process`.
- **Ticketing:** Immutable ticket snapshots drive QR, PDF, and email artifacts.
- **Check-in:** QR scan resolves ticket serial; idempotent check-in with audit trail.
- **Settlement:** Append-only `settlement_entries` ledger; manual `commission_payments` for amounts received outside the platform.
- **Read APIs:** Reports and dashboards are composition layers over existing tables — no new domain writes.

See `docs/adr/ADR-0001-backend-v1-freeze.md` for frozen architectural decisions.

## Requirements

- PHP 8.3+
- Composer 2.x
- SQLite (local development) or MySQL 8+ / PostgreSQL (production)
- Node.js 20+ (Vite asset tooling only; backend API is standalone)

## Installation

```bash
git clone <repository-url> event-platform
cd event-platform

composer install
cp .env.example .env
php artisan key:generate

# Local SQLite (default)
touch database/database.sqlite
php artisan migrate --seed

# Or configure MySQL in .env, then:
# php artisan migrate --seed
```

Configure tenant base domain in `.env`:

```env
TENANCY_BASE_DOMAIN=localhost
```

## Queue Worker

Outbox consumers and async jobs use the database queue driver by default (`QUEUE_CONNECTION=database`).

Run a worker in development:

```bash
php artisan queue:work
```

Process outbox events (required for commission ledger, ticket QR/PDF/email):

```bash
php artisan outbox:process
# or single batch:
php artisan outbox:process --once
```

## Scheduler

Schedule outbox processing in production (example — add to your server's cron invoking `schedule:run`, or run `outbox:process` directly):

```bash
* * * * * cd /path/to/event-platform && php artisan outbox:process --once >> /dev/null 2>&1
```

Laravel's `routes/console.php` does not register scheduled tasks by default; production cron must invoke `outbox:process` explicitly.

## Storage

Ticket artifacts (QR images, PDFs) are stored on the configured filesystem disk (`FILESYSTEM_DISK=local` by default). Ensure `storage/app` is writable and backed up in production.

```bash
php artisan storage:link
```

## Mail

Default mailer is `log` for local development. Set SMTP (or another transport) in `.env` for production:

```env
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
```

Ticket and order emails are dispatched via the outbox pipeline after payment verification.

## API Documentation

OpenAPI spec is generated with L5-Swagger:

```bash
php artisan l5-swagger:generate
```

- JSON spec: `storage/api-docs/api-docs.json`
- Swagger UI: `/api/documentation` (when enabled)

Key endpoints:

| Audience | Examples |
|----------|----------|
| Tenant (organizer) | `POST /api/tenant/orders`, `POST /api/tenant/payments/{id}/verify`, `GET /api/tenant/organizer/dashboard` |
| Platform (admin) | `GET /api/admin/reports`, `GET /api/admin/dashboard`, `POST /api/admin/commission-payments` |

Authentication uses Laravel Sanctum bearer tokens.

## Testing

```bash
php artisan test
```

Architecture guards and OpenAPI contract tests:

```bash
php artisan test --filter=Architecture
php artisan test --filter=OpenApiContract
```

Code style:

```bash
vendor/bin/pint
```

## License

MIT
