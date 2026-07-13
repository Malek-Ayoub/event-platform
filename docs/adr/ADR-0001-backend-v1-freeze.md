# ADR-0001: Backend v1 Freeze

| Field | Value |
|-------|-------|
| **Status** | Accepted |
| **Date** | 2026-07-13 |
| **Scope** | Backend v1 — all domains through Phase 8.7 |
| **Tag** | `v1.0-backend-freeze` (Batch 10.2) |

## Context

The Event Platform is a multi-tenant Laravel application for event ticketing in Syria. Buyers pay organizers directly via mobile wallets (ShamCash, Syriatel Cash via API Syria). The platform does **not** hold ticket revenue; it tracks **commission receivable** from organizers.

Backend v1 is complete when Phases 8.5.5–8.7 pass, the [Backend Freeze Checklist](../../IMPLEMENTATION_ROADMAP.md#backend-freeze-checklist-إلزامية-قبل-git-tag) is green, and this ADR is **Accepted**.

## Decision summary

The following decisions are **frozen** at v1. Changes require a new ADR and should only be driven by a real customer need — not speculative refactoring.

---

### 1. Multi-tenant isolation

- **Decision:** Subdomain-based tenant resolution (`{venue}.base_domain`) with `BelongsToVenue` global scope on all `venue_id` tables.
- **Tenant context:** `TenantContextInterface` bound per request; explicit `withoutGlobalScope` only in cross-tenant admin reads.
- **Consequence:** Every query in tenant APIs must assume scope is active; tests use `bindTenant()` + `withTenantHost()`.

---

### 2. Outbox pattern

- **Decision:** Domain side-effects that must survive process crashes are written to `outbox_events` **inside the same database transaction** as the aggregate change.
- **Consumers:** Separate worker processes events asynchronously (`commission.recorded`, `commission.adjusted`, `commission.payment_recorded`, ticket artifacts, etc.).
- **Worker:** `php artisan outbox:process` (batch loop or `--once`); must be scheduled in production.
- **Consequence:** No direct email/PDF/ledger writes from controllers; services publish via `OutboxService`.

---

### 3. Transaction boundary

- **Decision:** All multi-step writes use `TransactionRunner` — not `DB::transaction()` in controllers or models.
- **Consequence:** Architecture guard tests enforce this.

---

### 4. Ticket identity & issuance

- **Decision:** Tickets are issued atomically with orders via `OrderService` / `TicketSerialService` using pessimistic locking on `ticket_serial_counters` and `ticket_types.quantity_sold`.
- **Serial format:** Venue-scoped counters per event; tickets are immutable after issuance (status transitions only).
- **Consequence:** No optimistic locking on `orders` or `tickets`.

---

### 5. Snapshot / artifact pipeline

- **Decision:** QR, PDF, and email content are generated from **immutable ticket snapshots** stored at issuance time — not live re-queries of mutable event data.
- **Pipeline:** Outbox event → worker → QR generation → PDF → email delivery.
- **Consequence:** Changing event branding does not retroactively alter issued tickets.

---

### 6. Manual payment verification (API Syria)

- **Decision:** No hosted checkout or webhooks for payment completion. Flow is:
  1. `POST /payments` → payment instructions (wallet account).
  2. Buyer transfers manually.
  3. `POST /payments/{id}/verify` → gateway verifies transaction number via API Syria HTTP.
- **Payment accounts:** Per-venue `payment_accounts` resolved by `PaymentAccountResolver` (organizer wallet numbers).
- **Consequence:** `PaymentGateway` interface is verify-only; initiation returns instructions, not a redirect URL.

---

### 7. Commission & financial ledger

- **Decision:** Platform tracks **commission receivable only** — not organizer revenue, not payouts.
- **Ledger table:** `settlement_entries` (append-only).
- **Entry types:**

  | Type | Direction | Meaning |
  |------|-----------|---------|
  | `commission_due` | credit | Sale commission owed |
  | `commission_adjustment` | debit | Refund adjustment |
  | `commission_paid` | debit | Manual payment received |

- **Outstanding formula (source of truth):**
  ```
  SUM(commission_due) - SUM(commission_adjustment) - SUM(commission_paid)
  ```
- **`balance_after`:** Display snapshot only — not used for business logic in read APIs.
- **Manual payments:** `commission_payments` + `CommissionPaymentService::recordPayment()` writes matching ledger row.
- **Consequence:** Refunds do not create ledger rows directly; `commission.adjusted` outbox consumer writes `commission_adjustment`.

---

### 8. Settlement read model (Phase 8.5.4)

- **Decision:** `SettlementSummaryService` is the single source for summary math (organizer + admin).
- **Data sources:**
  - Gross sales → `orders`
  - Tickets sold → `tickets`
  - Refunds → `refunds`
  - Outstanding → `settlement_entries` sums
- **API envelope (stable):**
  ```json
  { "data": { "summary": {}, "entries": [], "payments": [], "meta": {} } }
  ```
- **Date filters:** `?from=` / `?to=` on all settlement and report endpoints.
- **Consequence:** Controllers do not compute financial numbers inline.

---

### 9. Reports composition layer (Phase 8.5.5)

- **Decision:** Financial and operational reports are **read-only composition** over existing tables — no new domain writes.
- **Organizer:** `GET /api/tenant/organizer/reports?from=&to=&event_id=` — `reports.view` or super admin.
- **Admin:** `GET /api/admin/reports?from=&to=&limit=` — super admin only.
- **Query services:** `PlatformRevenueQuery`, `CommissionReportQuery`, `TopVenuesQuery`, `TopEventsQuery`, `PaymentMethodReportQuery`, `RefundReportQuery` orchestrated by `OrganizerReportService` / `AdminReportService`.
- **Consequence:** Report math reuses `SettlementSummaryService` and `SettlementDateRange`; architecture guards block write-side service dependencies.

---

### 10. Dashboard composition layers (Phases 8.6–8.7)

- **Decision:** Organizer and admin home screens each use **one primary read endpoint** — no new domain, migrations, or writes.
- **Organizer:** `GET /api/tenant/organizer/dashboard` — KPIs, today, upcoming events, latest orders/check-ins, commission snapshot.
- **Admin:** `GET /api/admin/dashboard` — platform KPIs, today, top venues/events (`limit=5`), latest orders/payments/check-ins, three alert widgets.
- **Implementation:** `OrganizerDashboardService` / `AdminDashboardService` as orchestrators only; dedicated `*Query` classes use `DB::table()` joins with fixed `LIMIT` — no Eloquent N+1.
- **Empty state:** Financial values `0.00`, lists `[]`, never `null`.
- **Consequence:** Dashboard APIs are projections; changes to underlying report queries propagate automatically.

---

### 11. Check-in

- **Decision:** QR scan resolves ticket by serial; check-in is idempotent-aware (reject double check-in, cancelled, invalidated).
- **Audit:** Check-in history recorded; activity log on state change.
- **Consequence:** PWA scanner is a thin client over existing check-in API — no separate mobile app required for MVP.

---

### 12. Authorization

- **Decision:** RBAC via `PermissionService` + policies; super admin bypass for platform operations.
- **Organizer settlement/reports/dashboard:** `reports.view` permission on venue (or super admin).
- **Admin financial operations:** super admin only (commission payment recording, admin settlement, admin reports, admin dashboard).
- **Consequence:** Permission slugs are part of the frozen contract.

---

### 13. API conventions

- **Thin controllers** → services → DTOs → resources.
- **OpenAPI** required for all named routes (`OpenApiContractGuardTest`).
- **Architecture guards** block direct model/DB access in controllers and write-side dependencies in read layers.
- **Pagination:** unified contract for all list endpoints; dashboard “latest” widgets use fixed limits (5).

---

## What is explicitly out of scope for Backend v1

- Hosted payment pages / card processing
- Organizer payouts (platform never holds ticket money)
- Webhook-based payment confirmation (removed in Phase 7.9)
- Native mobile apps (PWA scanner sufficient for MVP)
- Full notification templates admin (email delivery for tickets exists; SMS admin deferred)
- Frontend (Phase 9)

---

## Freeze policy (post-tag)

| Allowed | Not allowed without customer-driven ADR |
|---------|----------------------------------------|
| Bug fixes | Domain redesign |
| Small response tweaks | New ledger tables |
| Optional endpoints | Speculative features |
| Read-only composition layers | New migrations for domain |

---

## Linked commits (Backend v1 core)

| Phase | Commit | Summary |
|-------|--------|---------|
| 8.5.4 | `d1fab6a` | Settlement read APIs |
| 8.5.5.1 | `d76986e` | Organizer reports |
| 8.5.5.2 | `ca6fc9d` | Admin reports |
| 8.5.5.3 | `d70b58a` | Reports closed (OpenAPI, guards) |
| 8.6.1 | `468672b` | Organizer dashboard |
| 8.7 | `2c3af7a` | Admin dashboard |
| 10.1 | *(this audit)* | Backend freeze audit + Pint |

---

## Completion checklist (this ADR)

- [x] All sections reviewed against actual code
- [x] Linked commits documented
- [x] Status changed to **Accepted**
- [x] `git tag v1.0-backend-freeze` created (Batch 10.2)

---

## References

- `IMPLEMENTATION_ROADMAP.md` — §v1
- `blueprint_v1_3.md`
- `PHASE_4_FINAL_ARCHITECTURE_AUDIT.md`
