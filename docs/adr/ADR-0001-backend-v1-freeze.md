# ADR-0001: Backend v1 Freeze

| Field | Value |
|-------|-------|
| **Status** | Draft — to be completed before `git tag v1.0-backend-freeze` |
| **Date** | 2026-07-13 |
| **Scope** | Backend v1 — all domains through Phase 8.7 |

## Context

The Event Platform is a multi-tenant Laravel application for event ticketing in Syria. Buyers pay organizers directly via mobile wallets (ShamCash, Syriatel Cash via API Syria). The platform does **not** hold ticket revenue; it tracks **commission receivable** from organizers.

Backend v1 is considered complete when Phases 8.5.5–8.7 pass, the [Backend Freeze Checklist](../../IMPLEMENTATION_ROADMAP.md#backend-freeze-checklist-إلزامية-قبل-git-tag) is green, and this ADR is marked **Accepted**.

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

### 9. Check-in

- **Decision:** QR scan resolves ticket by serial; check-in is idempotent-aware (reject double check-in, cancelled, invalidated).
- **Audit:** Check-in history recorded; activity log on state change.
- **Consequence:** PWA scanner is a thin client over existing check-in API — no separate mobile app required for MVP.

---

### 10. Authorization

- **Decision:** RBAC via `PermissionService` + policies; super admin bypass for platform operations.
- **Organizer settlement/reports:** `reports.view` permission on venue.
- **Admin financial operations:** super admin only (commission payment recording, admin settlement).
- **Consequence:** Permission slugs are part of the frozen contract.

---

### 11. API conventions

- **Thin controllers** → services → DTOs → resources.
- **OpenAPI** required for all named routes (`OpenApiContractGuardTest`).
- **Architecture guards** block direct model/DB access in controllers.
- **Pagination:** unified contract for all list endpoints.

---

## What is explicitly out of scope for Backend v1

- Hosted payment pages / card processing
- Organizer payouts (platform never holds ticket money)
- Webhook-based payment confirmation (removed in Phase 7.9)
- Native mobile apps (PWA scanner sufficient for MVP)
- Full notification templates admin (email delivery for tickets exists; SMS admin deferred)

---

## Freeze policy (post-tag)

| Allowed | Not allowed without customer-driven ADR |
|---------|----------------------------------------|
| Bug fixes | Domain redesign |
| Small response tweaks | New ledger tables |
| Optional endpoints | Speculative features |

---

## Completion checklist (this ADR)

- [ ] All sections reviewed against actual code
- [ ] Linked commits documented (8.5.1, 8.5.3, 8.5.4, …)
- [ ] Status changed to **Accepted**
- [ ] `git tag v1.0-backend-freeze` created

---

## References

- `IMPLEMENTATION_ROADMAP.md` — §v1
- `blueprint_v1_3.md`
- `PHASE_4_FINAL_ARCHITECTURE_AUDIT.md`
