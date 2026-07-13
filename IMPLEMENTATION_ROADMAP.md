# خارطة طريق التنفيذ — منصة تذاكر الفعاليات متعددة المستأجرين (Laravel 12)

> **الحالة:** المعمارية v1.3 مقفلة ونهائية. النواة الخلفية (Domain) مكتملة تقريبًا — **الأولوية الحالية:** إنهاء Backend v1 ثم الانتقال إلى Frontend v1. راجع **§v1** أدناه قبل أي تنفيذ جديد.
>
> **المرجع المعماري الوحيد:** `blueprint_v1_3.md` (وما يشير إليه من v1.0–v1.2). كل اسم جدول/عمود/Enum/علاقة/تدفق مذكور هنا مأخوذ حرفيًا من المعمارية المقفلة.
>
> **قرارات معمارية مجمّعة:** `docs/adr/ADR-0001-backend-v1-freeze.md` (تُستكمل قبل Tag `v1.0-backend-freeze`).

---

## §v1 — خارطة الطريق الرسمية (Backend v1 → Frontend v1)

> **هذا القسم هو المرجع التشغيلي لـ Cursor والفريق.** لا تُضاف ميزات Domain جديدة إلا إذا فرضها عميل فعلي. بعد `v1.0-backend-freeze` يُعتبر المشروع **Frontend v1** — وليس "تكملة Backend".

### الحالة الحالية (يوليو 2026)

| المجال | الحالة |
|---|---|
| Multi-tenant, Events, Venues, Ticket Types, Orders | ✅ |
| Manual Payment Verification (API Syria), Outbox, Idempotency | ✅ |
| Ticket Issuance, QR, PDF, Email | ✅ |
| Check-in | ✅ |
| Commission Ledger + Settlement Read APIs (8.5.1–8.5.4) | ✅ |
| Reports (8.5.5) | ⏳ |
| Organizer Dashboard API (8.6) | ⏳ |
| Admin Dashboard API (8.7) | ⏳ |
| Frontend | ⏳ |
| Production | ⏳ |

### الترتيب النهائي للتنفيذ

```text
0. ✅ تحديث IMPLEMENTATION_ROADMAP.md
1. ✅ إنشاء docs/adr/
2. ✅ 8.5.5 Reports
   - ✅ 8.5.5.1 Organizer Reports
   - ✅ 8.5.5.2 Admin Reports
   - ✅ 8.5.5.3 OpenAPI + Architecture Guards + Tests
3. ⏳ 8.6 Organizer Dashboard API
4. ⏳ 8.7 Admin Dashboard API
5. ⏳ Backend Freeze Checklist
6. ⏳ ADR-0001 (استكمال الوثيقة)
7. ⏳ git tag v1.0-backend-freeze
8. ⏳ Frontend v1
```

### Backend v1 — ما تبقى

```text
Backend v1
├── Phase 8.5.5 Reports
│   ├── 8.5.5.1 Organizer Reports (Sales, Revenue, Attendance, Commission)
│   ├── 8.5.5.2 Admin Reports (Platform Revenue, Monthly Commission, Top Venues/Events, Payment Methods, Refunds)
│   └── 8.5.5.3 Export + OpenAPI + Tests + Architecture Guards
├── Phase 8.6 Organizer Dashboard
│   └── GET /api/tenant/organizer/dashboard (endpoint رئيسي واحد)
├── Phase 8.7 Admin Dashboard
│   └── KPIs + آخر الأحداث + المدفوعات + Top Venues/Events + تنبيهات
├── Backend Freeze Checklist
├── ADR-0001 Backend v1 Freeze (استكمال)
└── v1.0-backend-freeze (Git Tag)
```

#### Phase 8.5.5 — Reports (Done عندما…)

- جميع التقارير تعمل بـ `from` / `to`.
- **لا** كتابة في قاعدة البيانات (قراءة فقط).
- البيانات من الخدمات/الجداول الحالية (`SettlementSummaryService`, `orders`, `tickets`, `settlement_entries`, `commission_payments`, …).
- اختبارات تغطي صحة الأرقام للفترات الزمنية المختلفة.
- كل دفعة = commit مستقل (8.5.5.1 → 8.5.5.2 → 8.5.5.3).

#### Phase 8.6 — Organizer Dashboard (Done عندما…)

- `GET /api/tenant/organizer/dashboard` — endpoint رئيسي يغذي الشاشة دون 10 طلبات.
- Endpoints فرعية فقط عند الحاجة.
- لا N+1؛ pagination للقوائم.

**شكل الاستجابة المستهدف:**

```json
{
  "today": { "sales": "...", "tickets_sold": 0, "check_ins": 0 },
  "overview": { "gross_sales": "...", "net_revenue": "...", "remaining_tickets": 0, "pending_commission": "..." },
  "recent_orders": [],
  "recent_check_ins": [],
  "top_events": []
}
```

#### Phase 8.7 — Admin Dashboard (Done عندما…)

- KPIs، آخر الأحداث، آخر المدفوعات، أعلى المنظمين/الفعاليات.
- تنبيهات (Outstanding Commission، Events Today، …).
- لا N+1؛ pagination للقوائم.

### سياسة Freeze Backend v1

| مسموح بعد الـ Tag | ممنوع (إلا بطلب عميل فعلي) |
|---|---|
| Bug fixes | إعادة تصميم Domain |
| تحسينات صغيرة | جداول / Ledger جديد |
| تعديل Response بسيط | ميزات "للمستقبل" |

### Backend Freeze Checklist (إلزامية قبل `git tag`)

```text
Domain
☑ Payments
☑ Orders
☑ Ticketing
☑ Check-in
☑ Settlement

Architecture
☐ Architecture Guards
☐ ADR-0001 محدثة
☐ OpenAPI محدثة

Quality
☐ جميع الاختبارات ناجحة
☐ Laravel Pint
☐ لا TODO/FIXME حرجة
☐ (اختياري) PHPStan/Psalm

Performance
☐ لا N+1 في Dashboard APIs
☐ Pagination لكل القوائم
☐ Indexes للجداول الجديدة

Security
☐ Authorization
☐ Rate Limiting
☐ Validation
☐ Audit Logs

Operations
☐ Queue
☐ Scheduler
☐ Mail
☐ Storage
```

عند اكتمال القائمة → `git tag v1.0-backend-freeze` → `git push origin v1.0-backend-freeze`.

### Frontend v1 (مشروع مستقل بعد الـ Freeze)

```text
Frontend v1
├── Sprint 1 — Public Website (Landing, Event List, Event Details)
├── Sprint 2 — Checkout (Checkout, Payment, Ticket Download)
├── Sprint 3 — Organizer Dashboard
├── Sprint 4 — Admin Dashboard
└── Sprint 5 — Scanner PWA (Scan QR, Result states)
```

**Organizer Panel (هدف):** Dashboard, Events, Ticket Types, Orders, Customers, Check-ins, Reports, Settlement, Settings.

**Admin Panel (هدف):** Dashboard, Users, Venues, Events, Payments, Settlement, Analytics, Logs, Permissions.

**ملاحظة:** يمكن البدء بتصميم الواجهات بالتوازي (Mock data) — لا تنتظر اكتمال كل الـ APIs.

### Production (بعد Frontend)

```text
Production
├── Docker
├── Queue / Horizon / Supervisor / Redis
├── S3 + CDN
├── Monitoring + Logging + Backup
├── Rate Limiting + Security
├── CI/CD + VPS + SSL
└── Pilot Event (أول فعالية حقيقية)
```

### Pilot Event — اختبار القبول (قبل أول عميل)

دورة كاملة **بدون تدخل يدوي في DB:**

1. إنشاء Venue → 2. Event → 3. Ticket Types → 4. شراء تذكرة → 5. التحقق من الدفع → 6. إصدار التذكرة → 7. QR → 8. PDF → 9. Email → 10. تنزيل التذكرة → 11. Check-in → 12. Settlement → 13. تسجيل استلام العمولة → 14. Reports.

### Commits مكتملة (Backend — مرجع)

| Commit | المرحلة |
|---|---|
| `8de9868` | 8.5.1 — Commission receivable ledger |
| `1a45b86` | 8.5.3 — Manual commission payments |
| `d1fab6a` | 8.5.4 — Settlement read APIs |

---

## 0. جرد الكيانات المقفلة (Reference Inventory)

الجداول التالية مأخوذة من المعمارية المقفلة (v1.0–v1.3). **ممنوع** إضافة أو إعادة تسمية أو حذف أي منها.

### الجداول

| المجموعة | الجداول |
|---|---|
| **Tenancy & Users** | `venues`, `venue_user` (pivot, يحمل `role`), `users` |
| **Authentication** | `personal_access_tokens`, `sessions`, `password_reset_tokens`, `api_clients` |
| **Authorization** | `permissions`, `role_permissions`, `user_permissions` |
| **Events** | `categories`, `events`, `ticket_types` |
| **Seating & Reservations** | `zones`, `venue_tables`, `table_seats`, `reservations` |
| **Orders & Payments** | `orders`, `tickets`, `payment_transactions`, `refunds`, `commissions`, `commission_adjustments`, `ticket_serial_counters` |
| **Discounts** | `coupons`, `promo_codes` |
| **Products** | `products`, `product_variants` |
| **Financial Settings** | `tax_rates`, `platform_settings` |
| **Communications** | `notifications`, `email_templates`, `sms_templates` |
| **Infrastructure / Audit** | `webhook_logs`, `activity_logs`, `outbox_events`, `media`, `documents` |

### الأدوار (Roles — مقفلة)
- `users.is_super_admin = true` → **Super Admin** (مستوى المنصة).
- `venue_user.role` → **owner** / **staff** (مستوى المنشأة).
- **Customer** → مستخدم عادي (بدون سجل `venue_user` إداري).

### الـ Enums (مقفلة — لا تُعدَّل)
- `outbox_events.status`: `pending`, `processing`, `sent`, `failed`.
- `webhook_logs.status`: (كما في v1.1، بإضافة `verified`/`failed` كقيم تشغيلية موثقة في §56 — دون تغيير التعريف السابق).
- `orders.status`, `refunds.status` (`processed`)، `tickets` (حالة الـ check-in)، Event Lifecycle statuses — **كما وُثّقت في v1.0–v1.2 دون تغيير**.

### التدفقات المقفلة (Locked Flows — تُنفَّذ حرفيًا)
1. **Payment Flow** — Checkout → `orders` → `payment_transactions` → `commissions`.
2. **Reservation Flow** — `zones` → `venue_tables` → `table_seats` → `reservations`.
3. **Check-in Flow** — `tickets` → serial via `ticket_serial_counters` → check-in.
4. **Event Lifecycle** — حالات الفعالية كما في v1.0.
5. **Refund Flow** — `refunds` → `commission_adjustments` (v1.3 §52).
6. **Webhook Flow** — Verify Signature → Store → Idempotency → Process → Payment Update (v1.3 §51, §56).
7. **Outbox Flow** — كتابة `outbox_events` داخل الـ Transaction ثم Worker منفصل (v1.3 §57).

### الآليات المقفلة للتزامن
- **Pessimistic Locking** (`lockForUpdate`): `ticket_types.quantity_sold`, `ticket_serial_counters` (v1.0).
- **Optimistic Locking** (عمود `version`, default=1): `events`, `ticket_types`, `venues`, `platform_settings`, `tax_rates` (v1.3 §58).
- **Global Scope** `BelongsToVenue` لكل جدول يحمل `venue_id` (v1.0).

---

## 1. الترتيب العام للتنفيذ (Global Implementation Order)

> **تحديث Post-Phase 3:** بعد اكتمال المصادقة (Phase 3)، أُعيد تجميع المراحل 4–23 في **6 مراحل تنفيذية** (4–9) لتفادي بناء APIs قبل اكتمال النماذج والصلاحيات. التفاصيل الكاملة في **§1.1**. أقسام Phase 6–23 في §2 تبقى مرجعًا granular للمحتوى المعماري المقفل — راجع **§1.2** لجدول الربط.

| # | Phase | العنوان | الحالة |
|---|---|---|---|
| 1 | **Infrastructure** | إعداد المشروع، الحزم، config، الطبقات الأساسية | ✅ |
| 2 | **Core Traits & Tenancy Foundation** | `BelongsToVenue`, `TenantContext`, middleware subdomain/api_client | ✅ |
| 3 | **Authentication** | Sanctum + sessions + password reset + Auth layer | ✅ |
| 4 | **Domain Models & Authorization** | كل Eloquent Models + علاقات + Policies + RBAC كامل | ✅ |
| 5 | **Domain Services** | `TransactionRunner`, Services, Actions, DTOs (§5.1–§5.9) — **لا** Repository/CQRS/ES | ✅ |
| 6 | **APIs (Business Controllers)** | Events, Ticket Types, Orders, Reservations, Products, Coupons, … | ✅ 6.1–6.8 |
| 7 | **Payments** | Gateway, Manual Wallet Transfer Verification, Refunds, Commission Adjustments | ✅ 7.1–7.9 |
| 8 | **Notifications** | Email, SMS, Templates, Outbox Worker | — |
| 9 | **Production Hardening** | Performance, Queues, Monitoring, Security, Load Testing | — |

### §1.1 — التسلسل المحدّث (4–9) — التفاصيل

#### Phase 4 — Domain Models & Authorization ⏳

**الهدف:** طبقة Domain كاملة + RBAC قبل أي منطق أعمال أو Controllers.

**تُنفَّذ على 5 دفعات (4.1 → 4.5)** — كل دفعة تنتهي باختبارات خضراء قبل التالية.

---

##### Phase 4.1 — RBAC Foundation ⏳

| العنصر | المحتوى |
|---|---|
| **Models** | `Permission`, `RolePermission`, `UserPermission` |
| **Service** | `PermissionService` (grant/revoke/sync/can) |
| **Policies** | `UserPermissionPolicy` (§54) |
| **Gates** | Super Admin bypass + permission slugs |
| **Tests** | Owner / Staff / Customer / Super Admin + escalation §54 |

**معايير الجودة (كل دفعات Phase 4):**
- `$fillable` صريح — **لا** `$guarded = []`
- `$casts` صريحة
- علاقات مع return types (`BelongsTo`, `HasMany`, …)
- **لا** business logic داخل Models
- Traits موجودة فقط عند الحاجة (`BelongsToVenue`, `HasOptimisticLock`, …)
- لا تعطيل Global Scope إلا صراحةً

**Exit criteria 4.1:** جميع اختبارات الصلاحيات الأساسية ✅

---

#### Domain Invariants (معمارية — ليست Validation / Policy / Service)

قواعد اتساق tenant-scoped يجب احترامها في Models (علاقات)، Phase 5 Services، و Phase 6 APIs:

| Invariant | القاعدة |
|---|---|
| Event ↔ Venue | `Event.venue_id` لا يمكن أن يختلف عن `Category.venue_id` عند الربط |
| TicketType ↔ Event | `TicketType.venue_id` يجب أن يساوي `Event.venue_id` |
| Seating hierarchy | `Zone` / `VenueTable` / `TableSeat` / `Reservation` — كل `venue_id` متسق عبر السلسلة |
| Product ↔ Event | عند `Product.event_id` غير null، `Product.venue_id` = `Event.venue_id` |
| ProductVariant ↔ Product | `ProductVariant.venue_id` = `Product.venue_id` |
| Order ↔ Ticket *(Phase 4.4)* | `Order.venue_id` = `Ticket.venue_id` = `TicketType.venue_id` |
| Payment ↔ Order *(Phase 7)* | `PaymentTransaction.venue_id` = `Order.venue_id` |
| Commission ↔ Order *(Phase 4.4)* | `Commission.order_id` unique — عمولة واحدة لكل Order؛ التعديلات عبر `commission_adjustments` فقط |
| Refund ↔ CommissionAdjustment *(Phase 4.4)* | `refund_id` unique على `commission_adjustments` — تعديل عمولة واحد لكل Refund |

**Phase 4.4 — علاقات Event:** استخدم `hasManyThrough` لـ `Event::tickets()` و `Event::orders()` حيث يناسب — لا queries يدوية.

**Seating Policies (مؤجّلة):** `ZonePolicy`, `VenueTablePolicy`, `TableSeatPolicy` — صلاحية `seating.manage` (Phase 4.3/4.4 أو Architecture Review 4.5).

---

#### Future Domain Value Objects *(Phase 5+ — لا تُنفَّذ في Phase 4)*

**الهدف:** منع **Primitive Obsession** قبل بناء Orders & Payments Services. في Phase 4 تبقى الأعمدة `decimal`/`string` مع `$casts` و Enums؛ الـ Value Objects تُ introducer في Phase 5 عند Services/DTOs.

| Value Object | يحل محل | استخدام متوقع |
|---|---|---|
| `Money` | `decimal` amounts (`total`, `subtotal`, `amount`, …) | Orders, Payments, Refunds, Commissions |
| `CommissionRate` | `decimal rate` (مثل `0.01`, `commission_rate`) | Commissions, Venues, Platform settings |
| `Percentage` | نسب خصم (`discount_value` عند `percent`) | Coupons, PromoCodes, TaxRates |
| `EmailAddress` | `customer_email`, `users.email` | Orders, Auth |
| `PhoneNumber` | `customer_phone`, `reservations.customer_phone` | Orders, Reservations |
| `TicketSerial` | `tickets.serial` | Tickets, TicketSerialCounter |
| `QRCode` | `qr_code_path` / مسار التخزين | Tickets |
| `ApiKey` | `api_clients.api_key` | API Clients |
| `ProviderTransactionId` | `payment_transactions.provider_transaction_id` | Payments, Webhooks, Idempotency |

**قواعد:**
- **Phase 4 Models:** `$casts` + Enums فقط — **لا** Value Objects داخل Eloquent attributes بعد.
- **Phase 5 Services:** حوّل من/إلى primitives عند حدود الـ Service (DTO in/out).
- **لا Magic Strings** للحالات — Enums موجودة/جديدة (`OrderStatus`, `PaymentTransactionStatus`, …).

---

#### Domain Read Models *(Phase 5+ — DTOs / Query Objects — لا Models في DB)*

**الهدف:** قراءة بيانات معقدة (تقارير، dashboards، timelines) **بدون** تحميل علاقات ثقيلة على Eloquent Models و**بدون** منطق تقارير داخل Models.

| Read Model | الغرض المتوقع |
|---|---|
| `OrderSummary` | ملخص طلب (مبالغ، حالة، عدد tickets) لعرض القائمة/التفاصيل |
| `EventSalesSummary` | مبيعات حدث (tickets sold، revenue) — aggregates |
| `VenueDashboardMetrics` | KPIs للمالك (orders، revenue، check-ins) |
| `PaymentTimeline` | تسلسل زمني لعمليات الدفع/الاسترجاع لطلب *(Phase 7)* |
| `TicketStatistics` | valid/used/cancelled counts per event |

**قواعد:**
- **ليست** جداول ولا Eloquent Models — `DTO` أو `Query Object` في `app/Domain/ReadModels/` أو `app/Queries/`.
- **Phase 5 Services** تُرجع Read Models؛ **Phase 6 APIs** تُحوّلها إلى Resources.
- **Phase 4 Models** تبقى **Anemic** — لا `$appends` للتقارير، لا accessors تُحمّل علاقات.

---

##### Phase 4.2 — Event Domain

`Category`, `Event`, `TicketType`, `Zone`, `VenueTable`, `TableSeat`, `Reservation` + علاقات + casts + scopes + policies

---

##### Phase 4.3 — Commerce Domain

`Product`, `ProductVariant`, `Coupon`, `PromoCode`, `TaxRate` + policies

---

##### Phase 4.4 — Orders & Payments Models *(أهم دفعة Domain — تُنفَّذ على دفعتين)*

**النطاق:** models + factories + policies + tests — **بدون** Services، Controllers، Migrations، أو Schema changes.

**⚠️ قبل أي علاقة:** راجع Migration الفعلي — **لا تفترض** cardinalities من المنطق فقط.

---

###### Phase 4.4a — Orders & Tickets *(المجموعة الأولى)*

| Model | Traits | ملاحظات Schema |
|---|---|---|
| `Order` | `BelongsToVenue` | **لا** `HasOptimisticLock`، **لا** `SoftDeletes`؛ `timestamps` |
| `Ticket` | `BelongsToVenue` | **لا** `HasOptimisticLock`، **لا** `SoftDeletes` |
| `TicketSerialCounter` | `BelongsToVenue` | unique `(venue_id, event_id)` |

**علاقات إلزامية (من Schema):**

| Model | علاقات |
|---|---|
| `Order` | `belongsTo` Venue, Event, User (customer), Coupon?, PromoCode? — `hasMany` Ticket, PaymentTransaction, Refund — `hasOne` Commission |
| `Ticket` | `belongsTo` Venue, Event, Order, TicketType, User? (checked_in_by) |
| `TicketSerialCounter` | `belongsTo` Venue, Event |
| `Event` *(تكميل)* | `hasMany` Order, Ticket |
| `Reservation` *(تكميل 4.2)* | `belongsTo` Order? — `order_id` nullable |
| `TicketType` *(تكميل)* | `hasMany` Ticket |

**Enums:** `OrderStatus`, `TicketStatus`

**Policies:** `OrderPolicy` → `orders.manage`

**جودة Models (4.4a — إلزامي):**
- **Anemic Models** — لا business logic داخل Models؛ لا accessors تحسب منطق أعمال.
- **لا Lazy Loading غير مقصود** — لا accessor يستدعي `$this->relation` أو `load()`.
- **Typed Relations** — `BelongsTo`, `HasMany`, `HasOne` مع return types صريحة.
- **لا `$appends`** إلا إذا كان الحقل مشتقًا من أعمدة **نفس السجل** فقط (بدون queries).
- **اختبارات:** علاقات، casts، domain constraints، policies، + `OrderDomainModelQualityTest`.

**Exit criteria 4.4a:** اختبارات علاقات + policies + domain constraints (انظر §4.4 Tests) ✅

---

###### Phase 4.4b — Payments & Commissions *(المجموعة الثانية — commit منفصل)*

| Model | Traits | ملاحظات Schema |
|---|---|---|
| `PaymentTransaction` | `BelongsToVenue` | **لا** `HasOptimisticLock`، **لا** `SoftDeletes` |
| `Refund` | `BelongsToVenue` | **لا** `HasOptimisticLock`، **لا** `SoftDeletes` |
| `Commission` | `BelongsToVenue` | **لا** `HasOptimisticLock`، **لا** `SoftDeletes`؛ **`created_at` فقط** (لا `updated_at`) |
| `CommissionAdjustment` | `BelongsToVenue` | **Append-only:** **`created_at` فقط** — لا updates |

**علاقات إلزامية (من Schema — لا hasOne حيث Schema = hasMany):**

| Model | علاقات | ⚠️ Schema-driven |
|---|---|---|
| `PaymentTransaction` | `belongsTo` Venue, Order | `Order` → **`hasMany`** PaymentTransaction *(إعادة محاولة/فشل/دفع متعدد)* — **ليس** hasOne |
| `Refund` | `belongsTo` Venue, Order, PaymentTransaction? | `Order` → **`hasMany`** Refund — `PaymentTransaction` → **`hasMany`** Refund |
| `Commission` | `belongsTo` Venue, Order | `Order` → **`hasOne`** Commission (`order_id` **unique**) |
| `CommissionAdjustment` | `belongsTo` Venue, Commission, Refund | `Commission` → **`hasMany`** — `Refund` → **`hasOne`** (`refund_id` **unique**) |

**Enums:** `PaymentTransactionStatus`, `RefundStatus`, `CommissionStatus`

**Policies:** `PaymentTransactionPolicy`, `RefundPolicy` → `refunds.process` / `orders.manage` حسب §RBAC — `Commission` read عبر Owner/Staff defaults

**Commission / CommissionAdjustment — Append-only في Models:**
- `Commission`: `public const UPDATED_AT = null;` — `$timestamps = true` مع `created_at` فقط
- `CommissionAdjustment`: نفس النمط — **لا** `$fillable` update paths في tests

**Commission (Ledger):** Model **Anemic** — لا `markPaid()` / `cancel()` / `reverse()`؛ منطق الحالة في `CommissionService` (Phase 5).

**Refund:** Aggregate مستقل — **لا** `$order->refund()` على Model.

**Exit criteria 4.4b:** اختبارات علاقات + append-only + `FinancialDomainArchitectureTest` ✅

---

###### Phase 4.4 — اختبارات Domain Constraints *(إلزامية بجانب Policies)*

| السيناريو | التحقق |
|---|---|
| **لا HasOptimisticLock** | `Order`, `Ticket`, `PaymentTransaction`, `Refund`, `Commission`, `CommissionAdjustment` — لا trait، لا عمود `version` |
| **BelongsToVenue** | كل الجداول أعلاه + `TicketSerialCounter` — global scope + `venue_id` في `$fillable` |
| **لا SoftDeletes** | كل جداول Orders/Payments/Commissions — لا trait؛ `assertNotSoftDeletable` |
| **Commission append-only** | `Commission` بدون `updated_at`؛ لا `update()` في tests إلا عبر explicit columns إن وُجدت لاحقًا في Services |
| **CommissionAdjustment append-only** | `created_at` فقط؛ test يؤكد عدم وجود `updated_at` في schema/model |
| **PaymentTransaction cardinality** | Order له `hasMany` — factory ينشئ 2+ transactions لنفس Order في test |
| **Refund ↔ PaymentTransaction** | Refund بـ `payment_transaction_id` nullable؛ test لكلتا الحالتين |
| **Cross-tenant** | Policies + BelongsToVenueScope لكل model |

**تسليم:** **Commit/PR منفصل** لـ 4.4a ثم 4.4b — لا دمج المجموعتين في commit واحد إن أمكن.

---

##### Phase 4.5 — Infrastructure Models + Review

`Notification`, `EmailTemplate`, `SmsTemplate`, `WebhookLog`, `ActivityLog`, `OutboxEvent`, `Media`, `Document`, `PlatformSetting`

ثم **Architecture Review داخلي** قبل Phase 5:
- علاقات ناقصة
- N+1 في العلاقات الأساسية
- Policies مربوطة بكل Models
- Tenant Scope على كل model مستأجر
- جميع الاختبارات ✅

---

**Prerequisites:** Phase 1–3 ✅، Migrations ✅، Tenant middleware ✅ (Phase 2).

**Models موجودة (Phase 3):** `User`, `Venue`, `VenueUser`, `ApiClient`

**لا يتضمن Phase 4:** business Controllers، business Services (Orders, Events, …)

---

#### Phase 5 — Domain Services

**الهدف:** منطق الأعمال في **Services / Actions / DTOs** — بدون HTTP layer.

**Prerequisites:** Phase 4 مكتمل ✅ + مراجعة `PHASE_4_FINAL_ARCHITECTURE_AUDIT.md` + الالتزام بقواعد **§5.1–§5.9** أدناه.

**Phase 5 Gates — ✅ معتمد (98–99% — توثيق مقفل):**

| Gate | § |
|------|---|
| `TransactionRunner` (لا `DB::transaction` مباشرة) | §5.1 |
| Aggregate Roots + Children | §5.2 |
| Service Ownership + Cannot Modify | §5.3 |
| No Model Events | §5.4 |
| Outbox-first (Transaction = data + ActivityLog + Outbox) | §5.5 |
| ActivityLog / Outbox write ownership | §5.6 |
| PlatformSetting single writer | §5.7 |
| Execution batches 5.1–5.6 | §5.8 |
| Service Architecture Guard Tests | §5.9 |

**ما لا يُضاف في Phase 5 (يُمنع — تعقيد بلا فائدة حالية):**
- Repository Pattern كطبقة إلزامية
- CQRS
- Event Sourcing
- DDD الكامل (Aggregates كـ classes منفصلة عن Eloquent)
- Domain Events **داخل** Eloquent Models
- Specifications معقدة
- Command Bus / Mediator Pattern
- Domain Objects / Value Objects **داخل** Eloquent Models (VOs عند **حدود** Service/DTO فقط — راجع Future Domain Value Objects)

**ما يُضاف:**
- Services + Actions + DTOs domain
- `TransactionRunner` لكل تدفق يعدّل state (§5.1)
- Pessimistic / optimistic locks حيث يلزم (§58)
- `ActivityLogService` + `OutboxService` (§5.5–§5.6)
- Read Models / Query Objects حيث يلزم (راجع Domain Read Models)
- **Service Architecture Guard Tests** في نهاية Phase 5 (§5.9)

---

##### §5.1 — TransactionRunner (إلزامي — لا `DB::transaction` مباشرة)

**لا يجوز** استدعاء `DB::transaction(...)` **مباشرة** داخل Domain Services.

**كل Service** يعدّل حالة قاعدة البيانات يمر عبر **`TransactionRunner`**:

```
TransactionRunner
    ↓
OrderService / PaymentService / CommissionService / …
```

```php
return $this->transactionRunner->run(function () {
    // business logic + all persistence
});
```

**فوائد:** Retry، deadlock retry، logging، metrics — في **مكان واحد** لاحقًا.

**قواعد:**
- **لا** أكثر من عملية حفظ (`save()`, `update()`, `delete()`, `create()`) **خارج** `TransactionRunner::run()`.
- `lockForUpdate()` / optimistic `version` ضمن نفس الـ run.
- **يُطبَّق إلزامًا على:** `OrderService`, `PaymentService`, `RefundService`, `CommissionService`, وجميع Domain Services.

**Architecture Guard (§5.9):** لا `DB::transaction` في `app/Services/**` — فقط داخل `TransactionRunner`.

---

##### §5.2 — Aggregate Roots & Boundaries (جدول رسمي)

**جدول Aggregate Boundaries** — أي مساهم يبدأ من هنا. عمود **Children** يحدد ما **لا يُعدَّل** إلا عبر Root:

| Aggregate | Root | Children |
|---|---|---|
| **RBAC** | `User` | `UserPermission`, `RolePermission` |
| **Events** | `Event` | `Category`, `TicketType`, `Zone`, `VenueTable`, `TableSeat`, `Reservation` |
| **Commerce** | `Product` | `ProductVariant`, `Coupon`, `PromoCode` |
| **Orders** | `Order` | `Ticket`, `TicketSerialCounter` |
| **Payments** | `PaymentTransaction` | — *(قراءة `Refund` فقط؛ Refund aggregate منفصل)* |
| **Refunds** | `Refund` | — *(`CommissionAdjustment` عبر Commission flow §52)* |
| **Commission** | `Commission` | `CommissionAdjustment` |
| **Venues** | `Venue` | `VenueUser`, `ApiClient` |
| **Financial Settings** | `TaxRate` | — |
| **Platform** | `PlatformSetting` | — |
| **Notifications** *(Phase 8)* | `Notification` | `EmailTemplate`, `SmsTemplate` |

**قواعد Aggregate:**
- Services **لا تعدّل Children** إلا عبر **Root Service** للنطاق.
- قراءة Models خارج النطاق **مسموحة** (validation, pricing) — **تعديلها ممنوع**.
- مثال: `OrderService` **يقرأ** `Event`, `TicketType`, `Coupon` — **لا يعدّلها**.

---

##### §5.3 — Service Ownership (Owns + Cannot Modify)

**جدول Service Ownership** — يمنع تداخل الخدمات مع مرور الوقت:

| Service | Owns | Cannot Modify |
|---|---|---|
| `OrderService` | `Order`, `Ticket`, `TicketSerialCounter` | `PaymentTransaction`, `Refund`, `Commission` |
| `TicketService` | `Ticket` *(via `OrderService` orchestration)* | `PaymentTransaction`, `Order` *(status transitions عبر OrderService)* |
| `TicketSerialService` | `TicketSerialCounter` *(via `OrderService`)* | أي aggregate آخر |
| `PaymentService` | `PaymentTransaction` | `Order` *(status — عبر orchestration)*, `Commission` |
| `RefundService` | `Refund` | `Commission`, `Order` |
| `CommissionService` | `Commission`, `CommissionAdjustment` | `Order`, `Refund`, `PaymentTransaction` |
| `EventService` | `Event`, `Category`, `TicketType`, `Zone`, `VenueTable`, `TableSeat` | `Order`, `PaymentTransaction` |
| `ReservationService` | `Reservation` | `Order`, `Ticket` |
| `ProductService` | `Product`, `ProductVariant`, `Coupon`, `PromoCode` | `Order`, `PaymentTransaction` |
| `TaxRateService` | `TaxRate` | أي aggregate آخر |
| `PlatformSettingService` | `PlatformSetting` | **أي Model آخر** |
| `VenueService` | `Venue`, `ApiClient` | Orders, Payments, … |
| `PermissionService` | `UserPermission` | `RolePermission` *(seed/sync فقط)* |
| `ActivityLogService` | `ActivityLog` *(append insert)* | أي Model |
| `OutboxService` | `OutboxEvent` *(append insert)* | أي Model |
| `NotificationService` *(Phase 8)* | `Notification` | — *(consumer فقط من Outbox)* |
| `WebhookService` *(Phase 7)* | `WebhookLog` | `PaymentTransaction` *(via PaymentService)* |

**Orchestration:** إذا احتاج `OrderService` commission — يستدعي `CommissionService` ضمن **نفس** `TransactionRunner::run()` — **لا** يكتب في `commissions` مباشرة.

---

##### §5.4 — Domain Events: من Services فقط (لا Models)

**Phase 4 قرار مقفل:** Models **Anemic** — **لا** `boot()`, `booted()`, `creating()`, `created()`, `updating()`, `updated()`, `deleting()`, `deleted()`.

**Phase 5 يحافظ على ذلك:**
- **ممنوع** `$dispatchesEvents` على Models.
- **لا** Observers للأعمال في Phase 5 (Observers §59 → Phase 8).
- Eloquent **للـ persistence + relations + scopes** فقط.

---

##### §5.5 — Outbox Pattern (إلزامي)

**أي Service يغيّر حالة النظام** (خصوصًا مالي/طلبات) يجب أن يكتب **داخل نفس `TransactionRunner::run()`:**

1. **البيانات** (Order, Ticket, Payment, …)
2. **`ActivityLog`** (via `ActivityLogService`)
3. **`OutboxEvent`** (via `OutboxService`)

```
TransactionRunner::run(
    OrderService
        → create Order
        → create Tickets
        → ActivityLogService::record(...)
        → OutboxService::record(...)
)
        ↓ (async — Phase 8 Worker)
    Notification → Email → SMS → Webhook
```

**ممنوع:**

```
OrderService → NotificationService   // ❌
PaymentService → Mail::send()        // ❌
```

**Architecture Guard (§5.9):** لا استدعاء `NotificationService` / `Mail` / `Sms` من Services التي تعدّل بيانات مالية — `OutboxEvent` فقط.

---

##### §5.6 — ActivityLog & Outbox Ownership (قاعدة رسمية)

> **Only Domain Services may create `ActivityLog` and `OutboxEvent` records. Models, Policies, Controllers, Observers, and Form Requests must never write to these tables directly.**

**تفاصيل:**
- Domain Services تستخدم **`ActivityLogService`** و **`OutboxService`** فقط — لا `ActivityLog::create()` مباشرة.
- **ممنوع** من: Models, Policies, Controllers, Observers, Form Requests, Jobs *(إلا Outbox Worker consumer — Phase 8)*.
- **الهدف:** مركزية Audit + Outbox — منع تشتت التسجيل.

---

##### §5.7 — PlatformSetting: Service writer واحد

`PlatformSetting` يستخدم `HasOptimisticLock` (§58).

**قواعد:**
- **كل** التعديل عبر `PlatformSettingService` فقط.
- **ممنوع** `PlatformSetting::save()` / `update()` من Controllers, Services أخرى، **وحتى اختبارات Services الأخرى** — استخدم `PlatformSettingService` أو factory للقراءة فقط.

---

##### §5.8 — ترتيب تنفيذ Phase 5 (_batches)

| Batch | المكونات | ملاحظة |
|---|---|---|
| **5.1** | `TransactionRunner`, `ActivityLogService`, `OutboxService` | Foundation — **قبل أي business service** |
| **5.2** | `TicketSerialService`, `TicketService`, `OrderService` | Serial **قبل** Order (dependency) |
| **5.3** | `PaymentService`, `RefundService` | |
| **5.4** | `CommissionService` | |
| **5.5** | `PlatformSettingService` | |
| **5.6** | Architecture Review + **Service Architecture Guard Tests** | §5.9 |

*(Event, Product, Reservation, TaxRate, Venue Services — parallel بعد 5.1 حسب §1.2 granular)*

كل batch ينتهي باختبارات خضراء قبل التالي.

---

##### §5.3.1 — Payment Domain Invariants (Phase 5.3+)

**لا Partial Payments في v1.3:**
- كل `PaymentTransaction` يجب أن يطابق `order.total` بالكامل.
- `PaymentService::completePayment()` يحوّل `Order` من `pending` → `paid` **فقط** عند اكتمال الدفع الكامل.
- **مستقبلًا** (إن دُعمت دفعات جزئية/عربون): لا يُحدَّث `Order.status = paid` إلا عندما `SUM(completed payments) >= order.total` — **خارج نطاق v1.3**.

**Single Source of Truth — حالة الدفع:**
- `PaymentTransaction.status` هو **مصدر الحقيقة الوحيد** لحالة الدفع.
- `Order.status` (paid/failed/refunded) **حالة مشتقة** (derived business state) — تُحدَّث **فقط** عبر `PaymentService` / `RefundService` orchestration، **وليس** العكس.
- **ممنوع** تحديث `Order → paid` مع بقاء `PaymentTransaction → failed`.

**Refund invariant (يُفرض في `RefundService` فقط):**
- `SUM(refunds WHERE status IN (pending, processed)) <= order.total` لكل طلب.
- لا يعتمد على Controller — اختبار إلزامي: `$100` → refund `$80` → refund `$50` **مرفوض**.

**Outbox envelope (§5.1+):** كل رسالة self-contained:
```json
{
  "aggregate": "order",
  "aggregate_id": 1,
  "event": "order.paid",
  "version": 1,
  "occurred_at": "2026-07-08T12:00:00+00:00",
  "payload": {}
}
```

**Commission decoupling:** `PaymentService` **لا** يستدعي `CommissionService`. التدفق:
```
payment.completed (Outbox) → Worker (Phase 8) → CommissionService::recordCommission()
```
أو استدعاء صريح ضمن orchestration layer — **ليس** داخل `PaymentService`.

---

##### §5.9 — Service Architecture Guard Tests (نهاية Phase 5)

**مماثل لـ `FinancialDomainArchitectureTest`** — يتحقق من:

| Rule | Guard |
|---|---|
| Transactions | جميع Domain Services تستخدم `TransactionRunner` — لا `DB::transaction()` مباشرة في `app/Services/**` |
| Aggregate boundaries | Services لا تستدعي `save()`/`update()` على Models خارج **Owns** (§5.3) |
| Outbox-first | لا `NotificationService` / `Mail` / `Sms` من financial/order services |
| Audit/Outbox | `ActivityLog` / `OutboxEvent` تُنشأ فقط عبر `ActivityLogService` / `OutboxService` |
| PlatformSetting | لا `PlatformSetting::save()` خارج `PlatformSettingService` |

**ملف مقترح:** `tests/Unit/Services/ServiceArchitectureGuardTest.php`

---

**Policies مؤجّلة (Technical Debt — لا تمنع Phase 5):**

| Policy | ملاحظة |
|---|---|
| `MediaPolicy`, `DocumentPolicy` | Phase 21 |
| `TicketPolicy`, `ApiClientPolicy` | Phase 6 |
| `ZonePolicy`, `VenueTablePolicy`, `TableSeatPolicy` | قبل APIs الحجوزات (Phase 6) |

**Phase 5 Readiness:** **✅ 98–99% — معتمد** — Aggregate Boundaries + Service Ownership موثّقان في §5.2–§5.3. **لا مانع** من بدء Batch 5.1.

---

#### Phase 6 — APIs (Business Controllers)

**الهدف:** HTTP layer فقط — Policies جاهزة مسبقًا. **Architecture Freeze** على طبقة Services (Phase 5) — Controllers تستدعي Services فقط.

**Prerequisites:** Phase 5 مكتمل ✅

---

##### §6.1 — Thin Controllers (قاعدة رسمية)

```
Controller
    ↓
FormRequest (validation + authorization hooks)
    ↓
DTO (from validated input)
    ↓
Service (TransactionRunner + business logic)
    ↓
API Resource (response shaping)
```

**ممنوع داخل Controller:**
- أي Query معقد (`Model::query()->where…`)
- أي Business Logic
- `DB::transaction()`
- `ActivityLog` / `OutboxEvent` (مباشرة أو عبر Services غير Domain)
- Policy bypass (`Gate::before`, `withoutMiddleware`, …)

---

##### §6.2 — API Resources فقط

- **لا** إرجاع Eloquent Models مباشرة (`return $order` ❌).
- **نعم** `OrderResource`, `OrderCollection`, `EventResource`, …
- Resources تُشكّل الـ public contract — تغيير Schema الداخلي لا يكسر API.

---

##### §6.3 — Form Requests إلزامية

كل endpoint يمر عبر `*Request` مخصص (`CreateOrderRequest`, `CompletePaymentRequest`, …).
**ممنوع** `$request->validate([...])` داخل Controller.
**ممنوع** إعادة استخدام Request واحدة لعدة عمليات مختلفة.

---

##### §6.4 — Controller Ownership

كل Controller يملك **Aggregate / Domain واحد** فقط — لا orchestration بين Aggregates.

| Controller | يملك |
|---|---|
| `AuthController` / `PasswordController` | Authentication فقط |
| `EventController` | Event Aggregate فقط (`Event`, `Category`, `TicketType` ضمن نفس الـ aggregate) |
| `OrderController` | Order Aggregate فقط |
| `PaymentController` | Payment فقط |
| `RefundController` | Refund فقط |
| `PlatformSettingController` | Platform Settings فقط |

**قاعدة:**

> لا يجوز لأي Controller استدعاء أكثر من **Domain Service واحد**، باستثناء خدمات البنية التحتية (Authorization hooks, Validation, Pagination helpers).

**ممنوع في Controller:**
- `createOrder()`, `purchase()`, `reserve()`, `pay()` داخل `EventController`
- استدعاء `OrderService` من `EventController` (أو العكس)

---

##### §6.5 — API Resource Ownership

كل Resource يمثل **Entity / Read Model واحد** — Mapping فقط، لا منطق.

| Resource | يمثل |
|---|---|
| `EventResource` | `Event` فقط |
| `CategoryResource` | `Category` فقط |
| `TicketTypeResource` | `TicketType` فقط |
| `OrderResource` | `Order` فقط |
| `PaymentTransactionResource` | `PaymentTransaction` فقط |
| `RefundResource` | `Refund` فقط |
| `AuthenticatedUserResource` / `CurrentUserResource` | `User` (سياق Auth) |
| `ApiTokenResource` | `TokenResultDTO` (session response) |

**قاعدة:**

> لا يجوز للـ Resource تنفيذ Queries، أو تحميل Relations (`$this->load(...)`), أو حساب Business Values (totals, commissions, availability).

Resources تستقبل Read Models / DTOs / Models **محمّلة مسبقًا من Service** — وتُشكّل الـ public contract فقط.

---

##### §6.6 — Immutable DTOs

**قاعدة رسمية لجميع DTOs (Phase 3+):**

```
DTOs immutable

readonly properties only

No methods except constructors / static factories (fromArray, …)
```

- **ممنوع:** setters، mutators، business methods، `toArray()` يُعيد حقولًا حساسة (passwords, tokens).
- **نعم:** `readonly class`, `fromArray()`, constructor, `toArray()` للـ safe fields فقط.

> DTOs ليست Models مصغّرة — هي **input/output contracts** بين HTTP layer و Services.

---

##### §6.7 — Unified Pagination Contract

جميع list endpoints تستخدم نفس شكل الـ pagination عبر `ApiResponse::paginated()`:

```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 500,
    "last_page": 25,
    "from": 1,
    "to": 20,
    "path": "https://..."
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

**لا** pagination shapes مخصصة per-controller.

---

##### §6.8 — ترتيب تنفيذ Phase 6 (_batches)

| Batch | المحتوى | الحالة |
|---|---|---|
| **6.1** | API Infrastructure (`BaseApiController`, `ApiResponse`, exception handler, `ApiResource`, pagination, DTO mapping) | ✅ |
| **6.2** | Authentication APIs (تنسيق Phase 3 بصيغتها النهائية) | ✅ |
| **6.3** | Event APIs — **§6.11** | ✅ |
| **6.4** | Commerce APIs | ✅ |
| **6.5** | Order APIs | ✅ |
| **6.6** | Payment APIs | ✅ |
| **6.7** | Platform APIs | ✅ |
| **6.8** | OpenAPI/Swagger + **`ControllerArchitectureGuardTest`** + **`OpenApiContractGuardTest`** | ✅ |

كل batch ينتهي باختبارات خضراء قبل التالي.

---

##### §6.9 — Controller Architecture Guard (Phase 6.8)

**ملف:** `tests/Feature/Architecture/ControllerArchitectureGuardTest.php`

| Rule | Guard |
|---|---|
| Controller لا يستدعي Model مباشرة (`Model::query()`, `Model::create()`, `->save()`, …) | ✅ |
| Controller لا يستخدم `DB` Facade (`DB::transaction()`, `DB::table()`, …) | ✅ |
| Controller لا يستخدم `DB::transaction()` | ✅ |
| Controller لا ينشئ `ActivityLog` / `OutboxEvent` | ✅ |
| Controller لا يعيد Model مباشرة | ✅ |
| Controller methods تستخدم FormRequest (حيث ينطبق) | ✅ |
| Controller methods تعيد Resource / ApiResponse | ✅ |
| Controller يرث `BaseApiController` | ✅ |
| Controller لا يستخدم `response()->json()` مباشرة | ✅ |

**تدريجي (Phase 6.2+):** `AuthControllerArchitectureTest` — يُدمج في Guard الكامل عند 6.8.

**يكمل** `ServiceArchitectureGuardTest` (Phase 5.6).

---

##### §6.10 — Payment SSOT (Guard extension — Phase 6.8)

`ControllerArchitectureGuardTest` + `ServiceArchitectureGuardTest` يمنعان `Order.status = paid` خارج `PaymentService`.

---

##### §6.12 — OpenAPI Contract Projection (Phase 6.8)

**مصدر الحقيقة (SSOT) للعقد:**

```
FormRequest + ApiResource + DTO
```

**OpenAPI (`app/OpenApi/`) = Projection فقط** — ليس SSOT. أي تغيير في Validation أو Resource **يجب** أن يُحدَّث معه الـ projection في **نفس الـ commit**.

**Exit Criteria (6.8):**

| # | Criterion |
|---|---|
| 1 | `php artisan l5-swagger:generate` يعمل بدون أخطاء |
| 2 | كل named routes في `routes/api.php` + `routes/tenant.php` موثّقة (`operationId` = route name) |
| 3 | لا schemas يتيمة (orphan) — كل schema مُشار إليها من paths |
| 4 | أمثلة request/response للعمليات الأساسية: Auth, Events, Orders, Payments |
| 5 | `OpenApiContractGuardTest` + `ControllerArchitectureGuardTest` خضراء |

**OpenAPI Contract Sync Checklist (مراجعة PR):**

- [ ] هل تغيّر `FormRequest::rules()`؟ → حدّث `app/OpenApi/Schemas/Requests/*` المقابل
- [ ] هل تغيّر `ApiResource::toArray()`؟ → حدّث `app/OpenApi/Schemas/Resources/*` المقابل
- [ ] هل أُضيف route جديد؟ → أضف path projection في `app/OpenApi/Paths/*`
- [ ] شغّل `php artisan l5-swagger:generate` و `php artisan test --filter=OpenApiContractGuardTest`

**ملفات:**

| Layer | Path |
|---|---|
| Root metadata | `app/OpenApi/OpenApiSpec.php` |
| Contract registry | `app/OpenApi/OpenApiContractRegistry.php` |
| Request projections | `app/OpenApi/Schemas/Requests/` (mirror `FormRequest`) |
| Resource projections | `app/OpenApi/Schemas/Resources/` (mirror `ApiResource`) |
| Path projections | `app/OpenApi/Paths/` (mirror `routes/api.php` + `routes/tenant.php`) |
| Guard test | `tests/Feature/Architecture/OpenApiContractGuardTest.php` |

**Package:** `darkaonline/l5-swagger ^11.1` — UI at `/api/documentation`.

---

##### §6.11 — Phase 6.3 Event APIs (نطاق مقفل)

**Prerequisites:** Phase 6.1 ✅, Phase 6.2 ✅, Event models + policies (Phase 4.2).

**EventController يدير فقط:**

```
Event
Category
TicketType
```

(ضمن Event Aggregate — لا cross-aggregate endpoints.)

**لا يُضاف في EventController / Event batch:**

- Order endpoints
- Payment endpoints
- Reservation endpoints
- Ticket issuance / check-in endpoints

**EventService boundaries (قبل كتابة Controller):**

```
EventService

✓ create event
✓ update event
✓ publish event
✓ archive event
✓ category assignment
✓ ticket types (CRUD ضمن aggregate)

✗ reservations
✗ orders
✗ payments
✗ commissions
✗ notifications
```

**Exit Criteria (6.3):**
- `EventController` يرث `BaseApiController` + Controller Ownership (§6.4)
- FormRequest → DTO → `EventService` → Resource → `ApiResponse`
- Pagination عبر §6.7
- Feature tests + `EventControllerArchitectureTest` (تدريجي)

---

**محتوى APIs (§2 granular):**
- Controllers, Form Requests, API Resources, Routes.
- Events, Ticket Types, Orders, Reservations, Products, Coupons, Categories, Venues admin, Check-in, …
- **لا** Payment webhooks هنا — Phase 7.

---

#### Phase 7 — Payment Gateway & Webhooks

**Prerequisites:** Phase 5 ✅ (PaymentService, RefundService, CommissionService), Phase 6 ✅ (Payment APIs).

**الهدف:** تكامل مزودي الدفع الخارجيين (ShamCash, Syriatel Cash, …) دون تلويث طبقة الأعمال. Phase 7 هي **أكثر مرحلة حساسية لسلامة البيانات** — الحدود تُثبَّت **قبل** كتابة أي كود.

**المبدأ الحاكم:**

```
Gateway / Webhook Layer  →  Integration (لا DB mutations مباشرة)
PaymentService / RefundService  →  Domain state (SSOT داخلي)
ActivityLog + Outbox  →  عبر TransactionRunner فقط (Phase 5)
```

> **⚠️ تحديث معماري (Amendment — بناءً على وثائق API Syria الرسمية):**
> نموذج الدفع الفعلي (Payment Initiation) تغيّر من **Hosted Checkout + Redirect + Webhooks** إلى **Manual Wallet Transfer + Transaction Verification**. البنود §7.1–§7.7 أدناه تبقى **مرجعًا تاريخيًا** (توثّق كيف بُني Batch 7.1–7.5 فعليًا وتم إنجازه واختباره)، لكنها **لا تصف مسار الدفع الحالي بعد الآن**. المرجع المعماري الحاكم لمسار الدفع من الآن فصاعدًا هو **§7.9**. لا تُعاد كتابة §7.1–§7.7 — تُترك كسجل تاريخي (traceability)، وتُشار إليها بـ "(legacy / superseded by §7.9)" حيث يلزم.

##### §7.0 — ترتيب تنفيذ Phase 7 (_batches)

| Batch | المحتوى | يعتمد على | الحالة |
|---|---|---|---|
| **7.1** | Payment Gateway Abstractions (Interfaces + DTOs + Registry) | — | ✅ (legacy — الجزء الخاص بـ Registry/Contracts الأساسية لا يزال ساريًا؛ `initiate`/webhook DTOs أصبحت legacy) |
| **7.2** | Gateway Implementations (ShamCash, Syriatel Cash, …) | 7.1 | ✅ (legacy — hosted checkout + signature verifier؛ superseded by §7.9) |
| **7.3** | Webhook Infrastructure (Signature Verification + Replay Protection) | 7.2 | ✅ (legacy — لا مزيد من Webhooks؛ superseded by §7.9) |
| **7.4** | Gateway Orchestration (`PaymentGatewayService`) | 7.3 | ✅ (legacy — `initiatePayment()`/`handleWebhook()` تُستبدَل بـ `verifyTransaction()` orchestration §7.9.4) |
| **7.5** | End-to-End Integration + `GatewayArchitectureGuardTest` | 7.1–7.4 | ✅ (legacy — يُستبدَل E2E الخاص بمسار hosted checkout بـ E2E جديد لمسار Manual Transfer، §7.9.11) |
| **7.6** | Gateway Contract Redesign (`verifyTransaction`) + Verification DTOs + `PaymentTransactionStatus` state machine الجديدة + `PaymentInstructionService`/`PaymentVerificationService` + Architecture Guards + global `transaction_number` uniqueness (§7.9.6.1) | 7.1 (Registry/Contracts فقط) | ✅ |
| **7.7** | API Wiring: `PaymentController::store()` مُعدَّل + `PaymentController::verify()` جديد + Routes + Requests + Resources/OpenAPI + Deprecation صريح لـ `complete`/`fail` | 7.6 | ✅ Done |
| **7.8** | Manual Transfer E2E Integration (`PaymentFlowE2ETest` الجديد لمسار Manual Transfer) | 7.6–7.7 | ✅ Done |
| **7.9** *(Cleanup — batch مستقل)* | إزالة فعلية للكود Legacy الخامل (webhooks, hosted-checkout initiate, signature verifiers, `complete`/`fail` endpoints) + migration `drop_webhook_logs` | 7.6–7.8 | ✅ Done |
| **7.10** *(Live Integration)* | API Syria HTTP الحقيقي (GET + X-Api-Key) + `apisyria:probe` + per-provider timeouts | 7.9 | ✅ Done |
| **7.11** *(Multi-tenant Payment Accounts)* | `payment_accounts` per venue + `PaymentAccountResolver` + gateway receives `GatewayPaymentAccount` (no global merchant config) | 7.10 | ✅ Done |

كل batch ينتهي باختبارات خضراء قبل التالي. **تفاصيل Batches 7.6–7.9 في §7.9 (subsection) أدناه.** *(ملاحظة تسمية: "Batch 7.9" و subsection "§7.9" رقمان مستقلان — الأول رقم دفعة تنفيذية، الثاني رقم قسم في هذه الوثيقة يوثّق كل الدفعات 7.6–7.9).*

---

##### §7.1 — Service Ownership (جدول مسؤوليات)

| Service / Component | مسؤولية | **لا** يفعل |
|---|---|---|
| `PaymentService` | حالة الدفع **داخل النظام** (`PaymentTransaction`, `Order.status` SSOT) | استدعاء HTTP للـ Gateway، التحقق من التوقيع |
| `RefundService` | حالة الاسترداد **داخل النظام** + `commission_adjustments` (§52) | استدعاء HTTP للـ Gateway |
| `PaymentGatewayService` | **Anti-Corruption Layer** — التواصل مع مزود الدفع (initiate, capture, refund request, verify signature, provider mapping)؛ **الوحيد** المسموح له باستخدام `PaymentGatewayRegistry` + Gateway contracts | `Model::save()`, `DB::`, ActivityLog, Outbox؛ **لا** `if ($provider === 'shamcash')` — انظر §7.4 |
| `WebhookService` | **Orchestrator فقط** (مثل Controllers في Phase 6): استقبال HTTP → normalize → تفويض لـ `PaymentGatewayService` → HTTP response | التحقق من التوقيع، Replay Protection، `WebhookLog`، `PaymentService`، `RefundService`، تفسير payload المزود، Registry، Gateway contracts |
| `WebhookLogService` | إنشاء وتحديث `webhook_logs` (append-only transitions §7.3.2) | Domain logic، استدعاء Gateway |
| `GatewaySignatureVerifier` *(interface)* | التحقق من التوقيع **فقط** (via Registry — لا استدعاء مباشر من WebhookService) | أي DB أو domain logic |
| `ReplayProtectionService` | منع إعادة معالجة webhook (idempotency §7.3.1) | Cache-only؛ يستخدم تخزينًا دائمًا |

**يمنع** تضخم `PaymentService` بمنطق Gateway/Webhook.

**قاعدة Registry Access (Anti-Corruption Layer):** `PaymentGatewayService` هو **الخدمة الوحيدة** المسموح لها باستخدام:

- `PaymentGatewayRegistry`
- `PaymentGateway` / `RefundGateway` / `GatewaySignatureVerifier`

**يمنع** على: Controllers، `WebhookService`، `PaymentService`، `RefundService`، وأي Domain service آخر استدعاء Registry أو Gateway contracts مباشرة.

```
WebhookService → PaymentGatewayService → PaymentGatewayRegistry → Provider impl
Controller     → PaymentGatewayService → …
```

**لا** `WebhookService → PaymentGatewayRegistry` ❌ — **لا** `Controller → PaymentGatewayRegistry` ❌

**قاعدة WebhookService (Orchestrator Only — قبل كتابة Batch 7.3):**  
`WebhookService` = orchestrator رفيع فقط — **لا God Service**. مسؤولياته:

```
Receive HTTP Request
        ↓
Normalize Request (provider slug, raw body, headers → DTO)
        ↓
PaymentGatewayService.handleWebhook(...)   ← كل منطق التكامل هنا
        ↓
Return HTTP Response
```

**يمنع** على `WebhookService`: التحقق من التوقيع، Replay Protection، كتابة `WebhookLog`، استدعاء `PaymentService` / `RefundService`، تفسير payload خاص بمزود.

---

##### §7.2 — Webhook Pipeline (تدفق إلزامي — يُنفَّذ داخل `PaymentGatewayService` + خدمات مساعدة)

**من منظور `WebhookService` (Orchestrator):**

```
HTTP Webhook Request
        ↓
WebhookService.receive()          ← normalize فقط
        ↓
PaymentGatewayService.handleWebhook()
        ↓
HTTP Response
```

**من منظور `PaymentGatewayService` (Anti-Corruption Layer — التفاصيل الداخلية):**

```
PaymentGatewayService.handleWebhook()
        ↓
WebhookLogService.recordReceived()
        ↓
PaymentGatewayService.verifySignature()   ← GatewaySignatureVerifier via Registry
        ↓
WebhookLogService.markVerified() | markFailedSignature()
        ↓
ReplayProtectionService.assertNotProcessed(provider, event_id)
        ↓
WebhookLogService.markReplayed()            ← on duplicate
        ↓
WebhookLogService.markProcessing()
        ↓
PaymentGatewayService.mapWebhookPayload() ← Gateway DTO → Domain DTO (§7.4)
        ↓
PaymentService / RefundService              ← الوحيدان اللذان يغيّران domain state
        ↓
ActivityLogService + OutboxService        ← داخل TransactionRunner (Phase 5)
        ↓
WebhookLogService.markProcessed() | markFailed()
```

**قاعدة Phase 7:** **Gateway لا يغيّر Database مباشرة** — لا `Model::query()`, لا `save()`, لا `DB::transaction()` في طبقة Gateway/Webhook.

**Routes:** `routes/webhooks.php` — **لا** tenant subdomain؛ ربط `venue_id` عند معالجة payment (§51).

---

##### §7.3 — Webhook Infrastructure (Batch 7.3 — تقسيم مسؤوليات)

| Service | مسؤولية |
|---|---|
| `WebhookService` | Orchestrator فقط (§7.1) |
| `PaymentGatewayService` | Signature verification + provider mapping + اختيار Gateway (via Registry) |
| `ReplayProtectionService` | منع التكرار (DB-backed — `webhook_logs` UNIQUE) |
| `WebhookLogService` | إنشاء وتحديث `webhook_logs` (transitions §7.3.2) |
| `PaymentService` | تحديث المدفوعات (domain SSOT) |
| `RefundService` | تحديث الاستردادات (domain SSOT) |

**Exit criteria (Batch 7.3):**
- Webhook route + `WebhookService` orchestrator (thin)
- `PaymentGatewayService.handleWebhook()` ينسّق pipeline دون provider-specific `if`
- `ReplayProtectionService` + `WebhookLogService`
- Architecture guard: `WebhookService` لا يستورد Registry / Gateway contracts / Domain payment services
- اختبارات: duplicate webhook → `replayed`؛ invalid signature → `failed_signature`

---

##### §7.3.1 — Idempotency Keys (مقفلة)

| Operation | Idempotency Key | Rule |
|---|---|---|
| `initiatePayment()` | `(order_id, provider)` | **عملية pending واحدة فعالة** لكل (order, provider) — لا orphaned TXs متعددة |
| Webhook | `(provider, provider_event_id)` | يُعالج **مرة واحدة فقط** — `webhook_logs` UNIQUE §51 |
| Refund (gateway) | `(provider_refund_id)` | مرة واحدة فقط لكل refund خارجي |
| Commission adjustment | `refund_id` UNIQUE | **بدون تغيير** — append-only §52 |

**Webhook replay:** لا Cache-only. استخدم `webhook_logs` (موجود: `provider`, `provider_event_id`, `status`, `created_at`).  
`created_at` = `received_at`؛ أضف `processed_at` في batch 7.3 إذا لزم تمييز صريح.

---

##### §7.3.2 — WebhookLog Status State Machine (مقفلة)

**الحالة الحالية (enum DB):** `received`, `verified`, `failed`, `processed` — **Batch 7.3** يوسّع إلى:

| Status | معنى |
|---|---|
| `received` | HTTP webhook وصل وسُجّل |
| `verified` | التوقيع صحيح |
| `processing` | domain processing جاري |
| `processed` | اكتمل بنجاح |
| `failed_signature` | فشل التحقق من التوقيع |
| `replayed` | duplicate `(provider, provider_event_id)` |
| `failed` | فشل أثناء/بعد processing |

**مسارات النجاح:**

```
received → verified → processing → processed
```

**مسارات الخطأ:**

```
received → failed_signature
verified → replayed
processing → failed
```

**قواعد:**
- `webhook_logs` append-only (لا `updated_at`) — transitions عبر `WebhookLogService` فقط
- UNIQUE `(provider, provider_event_id)` يبقى SSOT لـ replay protection §51
- `error_message` يُملأ عند `failed_signature`, `replayed`, `failed`

---

##### §7.4 — PaymentGatewayService Orchestration (Batch 7.4)

**Anti-Corruption Layer — بدون provider-specific branching:**

```
Registry
        ↓
Gateway Contract (PaymentGateway / RefundGateway / GatewaySignatureVerifier)
        ↓
Gateway DTO
        ↓
Domain DTO
        ↓
PaymentService / RefundService
```

**يمنع** داخل `PaymentGatewayService`:

```php
if ($provider === 'shamcash') { ... }   // ❌
match ($provider) { 'syriatel_cash' => ... };  // ❌
```

**يُسمح:** استدعاء Registry → contract method → map Gateway DTO إلى Domain DTO (mapper/generic strategy — **لا** hardcode per provider).

**مسؤوليات Batch 7.4:** ✅
- `initiatePayment()` / `refund()` orchestration (HTTP API → Gateway → Domain)
- Webhook payload mapping (Gateway DTO → Domain command/DTO)
- **لا** `Model::save()` — يفوّض لـ `PaymentService` / `RefundService`
- Mapper registry per event (`WebhookDomainCommandMapperRegistry`) + correlation ID عبر `WebhookLog` / `ActivityLog` / `OutboxEvent`

**Signature verification (implemented via Registry in 7.3/7.4):**
- `GatewaySignatureVerifier` per provider (ShamCash, Syriatel Cash, …) — already in Gateway layer (7.2)
- `PaymentGatewayService` يستدعي Verifier من Registry فقط — **لا** HMAC/RSA في WebhookService
- فشل التحقق → `WebhookVerificationException` (§56) — **لا** معالجة domain

---

##### §7.5 — End-to-End Integration (Batch 7.5) ✅

**ملف:** `tests/Feature/Payments/PaymentFlowE2ETest.php`

| Category | Scenario | Covered |
|---|---|---|
| Payment | Initiate → Redirect → Webhook → Paid | ✅ |
| Payment | Initiate → Gateway Failure → Retry | ✅ |
| Payment | Initiate twice `(order_id, provider)` | ✅ |
| Payment | Race / lock: duplicate initiate under order lock | ✅ |
| Webhook | Duplicate webhook → single domain effect | ✅ |
| Webhook | Webhook before redirect | ✅ |
| Webhook | Invalid signature | ✅ |
| Refund | Full refund (gateway + webhook) | ✅ |
| Refund | Duplicate refund webhook | ✅ |
| Commission | Single commission after paid (worker simulation) | ✅ |
| Commission | Single adjustment after refund (worker simulation) | ✅ |
| Correlation | Shared `{provider}:{reference}` across initiate + webhook | ✅ |

**ملاحظة:** Commission/Adjustment تُستدعى صراحةً في الاختبار (محاكاة Phase 8 Outbox Worker) — لا wiring إنتاجي جديد.

---

##### §7.5 (legacy) — Gateway Abstractions (7.1 — هيكل مقترح)

```
app/
├── Contracts/Payments/
│   ├── PaymentGatewayInterface.php      # initiate, refund, parseWebhook
│   └── GatewaySignatureVerifier.php
├── Services/Payments/
│   ├── PaymentGatewayService.php       # ACL (7.3 webhook + 7.4 initiate/refund)
│   └── Gateway/
│       ├── PaymentGatewayRegistry.php
│       ├── ShamCash/
│       │   ├── ShamCashGateway.php
│       │   └── ShamCashSignatureVerifier.php
│       └── SyriatelCash/
│           ├── SyriatelCashGateway.php
│           └── SyriatelCashSignatureVerifier.php
├── Services/Webhooks/
│   ├── WebhookService.php              # orchestrator only (7.3)
│   ├── WebhookLogService.php           # webhook_logs transitions (7.3)
│   └── ReplayProtectionService.php     # DB-backed idempotency (7.3)
└── DTOs/Webhooks/
    └── WebhookPayloadData.php
```

**Gateway Interface** — HTTP + parsing فقط؛ **لا** Eloquent.

---

##### §7.6 — Gateway Architecture Guard (Phase 7.5)

**ملف:** `tests/Feature/Architecture/GatewayArchitectureGuardTest.php`

| Rule | Guard |
|---|---|
| Gateway classes لا تستورد `App\Models\*` | ✅ |
| Gateway / Webhook layer لا تستخدم `DB::` | ✅ |
| Gateway لا يكتب `ActivityLog` / `OutboxEvent` | ✅ |
| Gateway لا يستدعي `ActivityLogService` / `OutboxService` | ✅ |
| **فقط** `PaymentGatewayService` يستخدم `PaymentGatewayRegistry` + Gateway contracts | ✅ (Phase 7.2 guard) |
| Controllers / Domain services / `WebhookService` **لا** يستدعون Registry أو Gateway contracts | ✅ (Phase 7.2 guard) |
| `WebhookService` orchestrator فقط — **لا** signature / replay / WebhookLog / PaymentService | ✅ (Phase 7.3 guard) |
| `PaymentGatewayService` **لا** يحتوي provider-specific `if ($provider === …)` | ✅ (Phase 7.4 guard) |
| تغييرات `Order.status` / `PaymentTransaction.status` تمر عبر `PaymentService` أو `RefundService` فقط | ✅ |
| `PaymentGatewayService` لا يستدعي `Model::save()` مباشرة | ✅ |

**يكمل** `ServiceArchitectureGuardTest` + `ControllerArchitectureGuardTest` (Phase 5.6 + 6.8).

---

##### §7.7 — Exit Criteria (Phase 7 كاملة) — **(legacy — يصف مسار hosted checkout، مُستبدَل بـ §7.9.11)**

| # | Criterion |
|---|---|
| 1 | ShamCash + Syriatel Cash (أو mock gateways) مع Registry |
| 2 | Webhook endpoint + signature verification + replay protection (DB-backed) |
| 3 | E2E: webhook → PaymentService → Order paid + Outbox event |
| 4 | Refund gateway flow → RefundService → commission_adjustment |
| 5 | `GatewayArchitectureGuardTest` خضراء |
| 6 | Idempotency keys §7.3 مُختبرة (duplicate webhook, duplicate refund) |

---

**محتوى Phase 7 (ملخص) — legacy، مسار hosted checkout كما نُفِّذ في Batch 7.1–7.5:**
- Payment Gateway integration, `payment_transactions` (عبر PaymentService — SSOT).
- Webhooks (signature §56, idempotency §51, replay §7.3).
- Refunds + `commission_adjustments` (§52) — عبر RefundService.
- Commissions (append-only) — بدون تغيير Phase 5 rules.

**مسار الدفع الفعلي الحالي (SSOT) موصوف في §7.9 أدناه — بند "محتوى Phase 7" النهائي الصحيح هو §7.9.12.**

---

##### §7.9 — تحديث معماري: نموذج الدفع اليدوي عبر المحفظة (Manual Wallet Transfer — API Syria)

**الحالة:** ⏳ مُوثَّق، بانتظار موافقة نهائية على الوثيقة قبل بدء أي تنفيذ (per طلب المستخدم — "Do NOT start coding immediately").

**السبب:** توثيق API Syria الرسمي لا يوفّر hosted checkout / redirect / webhooks. الدفع الوحيد المتاح هو: العميل يحوّل المال يدويًا إلى محفظة التاجر، ثم يُرسل رقم العملية (transaction number)، والنظام يتحقق منه عبر واجهة `find_tx` الخاصة بـ API Syria.

---

###### §7.9.1 — SSOT Flow (الجديد — يستبدل تدفق §7.2)

```
Order (status = pending)
        ↓
PaymentInstructionService.createInstructions(order)
        ↓
PaymentTransaction (status = awaiting_transfer) + PaymentInstructionData
        ↓
Response → Customer يرى: merchant wallet / amount / currency / expires_at / instructions
        ↓
[Customer يحوّل المال يدويًا خارج النظام — لا فعل من طرف النظام هنا]
        ↓
Customer → POST /payments/{payment}/verify { transaction_number }
        ↓
PaymentVerificationService.verify(payment, transactionNumber)
        ↓
PaymentTransaction (status = verifying)
        ↓
PaymentGatewayService.verifyTransaction(...)   ← Anti-Corruption Layer (بدون تغيير مبدأ §7.1)
        ↓
PaymentGatewayRegistry → Gateway Contract (PaymentGateway::verifyTransaction) → API Syria find_tx
        ↓
Gateway DTO (TransactionVerificationResult) → Domain DTO
        ↓
PaymentVerificationService يطبّق قواعد التحقق (§7.9.6) على النتيجة
        ↓
   نجاح ─────────────────────────────► فشل / عدم تطابق
        ↓                                        ↓
PaymentService.completePayment()        PaymentService.failPayment()
        ↓                                        ↓
PaymentTransaction = paid                PaymentTransaction = failed
Order = paid (SSOT §Phase 5)             Order يبقى pending (قابل لإعادة المحاولة)
        ↓
ActivityLog + Outbox (عبر TransactionRunner — بدون تغيير Phase 5)
```

**لا** hosted checkout، **لا** redirect URL، **لا** webhook endpoint، **لا** signature verification لهذا المسار.

---

###### §7.9.2 — العناصر المُلغاة/المُستبدَلة من Batch 7.1–7.5 (Superseded)

| عنصر (Legacy) | القرار | البديل |
|---|---|---|
| `PaymentGatewayService::initiatePayment()` | يُلغى لمسار hosted-checkout | `PaymentGatewayService::verifyTransaction()` (§7.9.4) |
| `PaymentGatewayService::handleWebhook()` + كل webhook pipeline (§7.2–§7.3) | **Dormant** — لا يُستدعى من أي مسار إنتاجي جديد؛ **لا حذف** في Batch 7.6–7.8 | لا مقابل لمسار الدفع الحالي؛ يبقى متاحًا (dormant) لمزوّدين مستقبليين قد يدعمون callbacks/webhooks |
| `WebhookController`, `routes/webhooks.php` | **قرار نهائي: Dormant, not deleted** — يبقى معزولًا وغير نشط؛ لا مسار إنتاجي حالي يعتمد عليه | حذف فعلي فقط في **Batch 7.9 (Cleanup)** المستقل، وبعد استقرار المسار الجديد فقط — إن قرَّر المستخدم لاحقًا |
| `WebhookService`, `WebhookLogService`, `ReplayProtectionService` | **Dormant, not deleted** — البنية التحتية العامة لـ webhooks تبقى جزءًا من منصة الدفع العامة لمزوّدين مستقبليين | منطق uniqueness لمسار Manual Transfer الجديد = "transaction_number لم يُستخدم سابقًا" (§7.9.6) — **لا** علاقة بـ `webhook_logs` |
| `GatewaySignatureVerifier` (contract) + `ShamCashSignatureVerifier`/`SyriatelCashSignatureVerifier` | **Dormant, not deleted** — API Syria الحالي لا يوفّر webhooks، لذا لا يُستخدَم فعليًا، لكن يبقى متاحًا لمزوّدين آخرين قد يدعمون توقيع callbacks | لا توقيع HMAC/RSA لمسار Manual Transfer — التحقق يتم عبر استعلام `find_tx` مباشرة (server-to-server، لا webhook وارد) |
| `WebhookDomainCommandMapperRegistry` + `PaymentCompletedMapper`/`PaymentFailedMapper`/`RefundProcessedMapper` | **Dormant, not deleted** لنفس السبب أعلاه | لا mapping لأحداث واردة في مسار Manual Transfer؛ الاستجابة من `verifyTransaction()` تُعالَج synchronously ضمن نفس الطلب (لا event mapping) |
| `WebhookLog` model + جدول `webhook_logs` | **قرار نهائي: Dormant, not deleted.** يبقى جزءًا من منصة الدفع العامة (مزوّدون مستقبليون قد يدعمون webhooks) — **ممنوع** على أي كود إنتاجي في مسار Manual Transfer (`PaymentInstructionService`, `PaymentVerificationService`, `PaymentGatewayService::verifyTransaction()`) الإشارة إلى `WebhookLog`/`WebhookLogService` (مُطبَّق كـ Architecture Guard §7.9.9) | — |
| `InitiatePaymentDTO`, `InitiatePaymentRequest`, `GatewayInitiatePaymentData/Result`, `WebhookPayload`, `WebhookVerificationResult` | **Dormant, not deleted** — تبقى مستخدَمة نظريًا من مسار hosted-checkout الخامل | `PaymentInstructionData`, `VerifyTransactionData`, `VerifyTransactionResult` (§7.9.5) هي DTOs مسار Manual Transfer الحالي |
| `RefundGateway` contract + refund gateway flow (§7.4) | **خارج نطاق هذا التحديث بالكامل — قرار نهائي.** API Syria يوثّق حاليًا فقط lookup + wallet transfer؛ لا refund. **لا** تعديل على `RefundGateway`/`RefundService`/refund orchestration في أي Batch من 7.6–7.9 | بدون تغيير — يُعاد تقييمه فقط إذا وثّق API Syria قدرة refund لاحقًا |
| `GatewayArchitectureGuardTest` | **لا يُحذف** — يُحدَّث ليعكس القواعد الجديدة (§7.9.9)، بما فيها حراسة عدم استخدام dormant webhook code من مسار Manual Transfer | نفس الملف، حراسات مُحدَّثة (إضافة، لا حذف الحراسات القديمة الخاصة بـ webhook التي تبقى صحيحة لمكوّنات dormant) |
| Correlation IDs / ActivityLog / Outbox / TransactionRunner / DTO boundaries / Controller thinness / Registry pattern | **بدون تغيير** (per "DO NOT CHANGE" في طلب المستخدم) | — |

**قرار نهائي (تمت الموافقة من المستخدم):** لا حذف فعلي لأي كود Legacy/Webhook في Batch 7.6/7.7/7.8. الحذف الفعلي (إن حدث) يقع فقط في **Batch 7.9 — Cleanup** المستقل، بعد اكتمال واستقرار المسار الجديد بالكامل في الإنتاج. حتى ذلك الحين: الكود القديم يبقى **معزولًا وخاملًا (isolated & dormant)** — لا مسار إنتاجي جديد يستدعيه أو يعتمد عليه.

---

###### §7.9.3 — Service Ownership الجديد (يُحدِّث جدول §7.1)

| Service | مسؤولية | **لا** يفعل |
|---|---|---|
| `PaymentInstructionService` | إصدار تعليمات الدفع: محفظة التاجر، العملة، المبلغ، `expires_at`، نص تعليمات human-readable؛ إنشاء `PaymentTransaction` بحالة `awaiting_transfer` | **لا** استدعاء Gateway؛ **لا** التحقق من transaction number؛ **لا** `Model::save()` مباشر خارج DTO التنسيق (يُفوَّض لـ `PaymentService`) |
| `PaymentVerificationService` | استقبال `transaction_number` من العميل → تحميل `PaymentTransaction` → استدعاء `PaymentGatewayService::verifyTransaction()` → تطبيق قواعد التحقق (§7.9.6) على النتيجة → عند النجاح: `PaymentService::completePayment()`؛ عند الفشل: `PaymentService::failPayment()` | **لا** استدعاء Registry/Gateway contracts مباشرة (فقط عبر `PaymentGatewayService`)؛ **لا** `Model::save()` مباشر على `Order`/`PaymentTransaction` |
| `PaymentGatewayService` | **Anti-Corruption Layer (بدون تغيير المبدأ)** — الوحيد المسموح له باستخدام `PaymentGatewayRegistry` + `PaymentGateway` contract؛ `verifyTransaction()` orchestration: استدعاء Gateway → map Gateway DTO → Domain DTO | `Model::save()`, ActivityLog, Outbox؛ **لا** `if ($provider === 'shamcash')` (نفس قاعدة §7.4) |
| `PaymentService` | حالة الدفع **داخل النظام** (`PaymentTransaction`, `Order.status` SSOT) — بدون تغيير عن Phase 5/7.1، مع إضافة `completePayment()`/`failPayment()` إذا لم تكن موجودة بهذا الاسم | استدعاء HTTP للـ Gateway مباشرة |
| `RefundService` | **بدون تغيير** — خارج نطاق هذا التحديث (§7.9.2) | — |

**قاعدة الوصول لا تتغيّر:** Controllers → `PaymentInstructionService` / `PaymentVerificationService` فقط. **لا** Controller يستدعي `PaymentGatewayService` أو Registry مباشرة (Controller thinness — بدون تغيير).

```
Controller → PaymentInstructionService  →  PaymentService (create awaiting_transfer)
Controller → PaymentVerificationService →  PaymentGatewayService → Registry → Gateway Contract
                                        →  PaymentService (complete/fail)
```

---

###### §7.9.4 — Gateway Contract Redesign

**يُستبدَل** `PaymentGateway::initiate()` (hosted checkout) بـ:

```php
interface PaymentGateway
{
    public function provider(): string;

    public function verifyTransaction(VerifyTransactionRequest $request): VerifyTransactionResponse;
}
```

- `VerifyTransactionRequest` (Gateway DTO): `transactionNumber`, `expectedAmount`, `expectedCurrency`, `merchantAccount`.
- `VerifyTransactionResponse` (Gateway DTO): نتيجة خام من `find_tx` — `found`, `amount`, `currency`, `receiverAccount`, `providerTransactionId`, `rawStatus`.
- **`GatewaySignatureVerifier` يُحذف من الاستخدام لهذا المسار** — لا webhook وارد يحتاج توقيعًا. (لا يُحذف الملف تلقائيًا؛ القرار النهائي جزء من §7.9.2).
- Registry access rule **بدون تغيير**: فقط `PaymentGatewayService` يستدعي `PaymentGatewayRegistry` + `PaymentGateway` contract.

---

###### §7.9.5 — DTOs الجديدة (Domain + Gateway boundaries — بدون تغيير مبدأ DTO boundaries)

| DTO | الطبقة | الحقول |
|---|---|---|
| `PaymentInstructionData` | Domain (خرج `PaymentInstructionService`) | `paymentId`, `provider`, `merchantAccount`, `amount`, `currency`, `expiresAt`, `instructions` |
| `VerifyTransactionData` | Domain (دخل `PaymentVerificationService`) | `paymentId`, `transactionNumber` |
| `VerifyTransactionRequest` | Gateway (دخل `PaymentGateway::verifyTransaction()`) | `transactionNumber`, `expectedAmount`, `expectedCurrency`, `merchantAccount` |
| `VerifyTransactionResponse` | Gateway (خرج `PaymentGateway::verifyTransaction()`) | `found`, `amount`, `currency`, `receiverAccount`, `providerTransactionId`, `rawStatus` |
| `TransactionVerificationResult` | Domain (خرج `PaymentGatewayService::verifyTransaction()` بعد mapping) | `matched: bool`, `reason: ?string` (enum سبب الرفض عند `matched=false`)، `providerTransactionId` |

**لا** تُدمَج هذه الحقول في نموذج واحد "God DTO" — كل DTO حدود طبقة واحدة (بدون تغيير عن مبدأ DTO boundaries المقفل).

---

###### §7.9.6 — Verification Rules (مقفلة — Business Rules)

الدفعة تُعتبر صحيحة **فقط** إذا تحققت **جميع** الشروط التالية معًا:

| # | الشرط |
|---|---|
| 1 | Transaction موجودة فعليًا عند API Syria (`find_tx` يعيد نتيجة، `found = true`) |
| 2 | `amount` في الاستجابة == `expected_amount` للـ `PaymentTransaction` (مطابقة تامة، لا هامش تسامح إلا إن وثّقه API Syria) |
| 3 | `currency` في الاستجابة == عملة `PaymentTransaction` |
| 4 | `receiverAccount` في الاستجابة == `account_identifier` لحساب الدفع (`payment_accounts`) المرتبط بـ venue الطلب — **لا** config/global env |
| 5 | `transaction_number` **فريد على مستوى المنصة بالكامل** — لم يُستخدم من قبل لأي `PaymentTransaction` آخر، بأي حالة (انظر §7.9.6.1) |
| 6 | `PaymentTransaction` الحالية بحالة `awaiting_transfer` أو `verifying` وقت التحقق (**ليست** `paid`/`failed`/`expired`؛ إن كانت `expired` → رفض فوري بدون استدعاء Gateway) |

**فشل أي شرط ⇒** `TransactionVerificationResult.matched = false` مع `reason` واضح (enum: `not_found`, `amount_mismatch`, `currency_mismatch`, `receiver_mismatch`, `duplicate_transaction_number`, `not_awaiting_transfer`, `expired`) → `PaymentVerificationService` يستدعي `PaymentService::markVerificationFailed()`/`expirePayment()` (بدون تغيير حالة إن كانت `paid`/`failed`/`expired` مسبقًا — قراءة فقط).

---

###### §7.9.6.1 — Global Transaction Number Uniqueness (متطلب معماري إضافي — مقفل)

**القاعدة:** `transaction_number` يجب أن يكون **فريدًا على مستوى المنصة بالكامل** (عبر كل الفعاليات/المنشآت/الطلبات) — **بمجرد** ربط رقم عملية بنجاح بدفعة واحدة (`paid`)، **يُمنع نهائيًا** قبوله لأي دفعة أخرى، إلى الأبد. هذا **متطلب أمان لمنع الاحتيال** (استخدام نفس التحويل المصرفي/المحفظة للمطالبة بأكثر من طلب) — **ليس** مجرد idempotency key تقني.

**التطبيق على طبقتين إلزاميتين معًا (Defense in Depth):**

| الطبقة | الآلية | التفصيل |
|---|---|---|
| **Database (SSOT النهائي)** | `UNIQUE` index على عمود `payment_transactions.transaction_number` (nullable-safe — قيم `NULL` متعددة مسموحة، القيم غير-`NULL` فريدة إلزاميًا) | يضمن عدم إمكانية وجود صفّين بنفس `transaction_number` **حتى تحت تزامن/race condition** — هذا هو الضمان الحقيقي، وليس تطبيق الطبقة التالية |
| **Application (UX + fail-fast)** | `PaymentVerificationService` يفحص عبر استعلام (`WHERE transaction_number = ? AND id != ?`) **قبل** استدعاء Gateway، ويرفض فورًا بـ `reason = duplicate_transaction_number` دون أي طلب HTTP خارجي | يمنع استدعاءات Gateway غير الضرورية، ويُعيد خطأ عمل واضح (409/422) بدل ترك `QueryException` خام يتسرب من طبقة الـ DB |

**قاعدة التعامل مع التزامن (race condition):** إن نجح الفحص التطبيقي لكنّ إدراج/تحديث `transaction_number` فشل لاحقًا بسبب `QueryException` (انتهاك UNIQUE، بسبب طلب مُتزامن آخر أسرع)، تُعامَل كـ `duplicate_transaction_number` (نفس معالجة الفحص التطبيقي) — **لا** تتسرب `QueryException` خام إلى الـ Controller. **نفس نمط** `PaymentService::initiatePayment()` الحالي (يمسك `QueryException` على `UNIQUE(provider, provider_transaction_id)`، §7.1).

**لا استثناء:** لا فرق بين `transaction_number` تابع لدفعة `paid` أو `failed` أو `expired` أو `verifying` أو `awaiting_transfer` — الفريدة **مطلقة** على مستوى العمود بالكامل، بلا شرط "فقط بعد النجاح". هذا أكثر تحفظًا (وأكثر أمانًا) من "يُمنع فقط بعد الربط الناجح" المطلوب صراحةً، ولا يتعارض معه.

**قاعدة Idempotency الجديدة (تُحدِّث §7.3.1 لمسار الدفع):**

| Operation | Idempotency Key | Rule |
|---|---|---|
| `PaymentInstructionService.createInstructions()` | `(order_id, provider)` | **بدون تغيير عن §7.3.1** — عملية `awaiting_transfer` واحدة فعالة لكل (order, provider) |
| `PaymentVerificationService.verify()` | `transaction_number` (global UNIQUE عبر كل `payment_transactions`) | رقم عملية واحد لا يمكن استخدامه لأكثر من دفعة واحدة — يستبدل `(provider, provider_event_id)` UNIQUE الخاص بـ webhooks |

---

###### §7.9.7 — Payment States (يُوسِّع `PaymentTransactionStatus` — **إضافة، لا استبدال**، per قرار §7.9.2/§7.9.10: الكود القديم يبقى dormant وعامل)

**الحالي (`app/Enums/FinancialDomain/PaymentTransactionStatus.php`):** `pending`, `completed`, `failed`, `refunded` — **تبقى بدون حذف** (يستخدمها مسار hosted-checkout الخامل + اختباراته الحالية، والتي يجب أن تبقى خضراء).

**الجديد (يُضاف إلى نفس الـ enum):**

```php
enum PaymentTransactionStatus: string
{
    // Legacy (hosted checkout — dormant، بدون حذف، §7.9.2)
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';

    // Manual Wallet Transfer (Batch 7.6+ — المسار الفعلي الحالي)
    case AwaitingTransfer = 'awaiting_transfer';
    case Verifying = 'verifying';
    case Paid = 'paid';
    case Expired = 'expired';
}
```

**سبب الإبقاء على `Failed` مشتركًا بين المسارين:** المعنى مطابق (فشل نهائي) في كل من hosted-checkout والـ Manual Transfer — لا حاجة لقيمة مكررة. `PaymentTransactionStateMachine` يُحدَّث بمسارين مستقلين لكل enum family (لا تقاطع انتقالات بين legacy states والحالات الجديدة).

**Migration:** إضافة القيم الجديدة إلى عمود MySQL `ENUM` الحالي عبر `ALTER TABLE ... MODIFY COLUMN` (نفس نمط `2026_07_08_180000_expand_webhook_log_statuses.php`) — **لا** migration جديدة تُنشئ عمودًا آخر، **لا** كسر للبيانات القديمة أو الاختبارات الحالية.

**State Machine (الجديد — مسار مستقل تمامًا عن legacy):**

```
awaiting_transfer → verifying → paid
awaiting_transfer → verifying → failed → (retry يُعيد الحالة إلى verifying أو يبقى awaiting_transfer حتى محاولة تحقق جديدة)
awaiting_transfer → expired                              [عبر scheduled job — خارج نطاق Batch 7.6، يُطرح لاحقًا إن لزم]
```

**قرار نهائي:** لا حاجة لحالة `refunded` جديدة — الاسترداد يبقى عبر `refunds.status` المستقل تمامًا (بدون تغيير — Refund flow خارج النطاق §7.9.2)؛ `PaymentTransaction.status = paid` يبقى كما هو حتى بعد استرداد مرتبط به.

**تأثير على `PaymentService`:** **لا** تعديل على الدوال الحالية (`initiatePayment`/`completePayment`/`failPayment`) التي تخدم legacy hosted-checkout — تبقى كما هي وتُستخدَم فقط من الكود الخامل/اختباراته. الدوال الجديدة لمسار Manual Transfer تُضاف بأسماء مستقلة (`PaymentInstructionService`/`PaymentVerificationService` يستدعيان دوال جديدة على `PaymentService`، لا يُعاد استخدام الدوال القديمة بنفس التوقيع لتجنّب تشابك state machines).

---

###### §7.9.8 — New API Surface (يستبدل `POST /payments` الحالي في §6.6)

| Endpoint | الوصف | Controller | Service |
|---|---|---|---|
| `POST /payments` | ينشئ `PaymentTransaction(awaiting_transfer)` ويُرجع تعليمات الدفع | `PaymentController::store()` (مُعدَّل) | `PaymentInstructionService` |
| `POST /payments/{payment}/verify` | يستقبل `transaction_number`، يُشغّل مسار التحقق الكامل | `PaymentController::verify()` (جديد) | `PaymentVerificationService` |

**Response شكل `POST /payments` (مثال — عبر ApiResource، بدون تغيير مبدأ ApiResources):**

```json
{
    "payment_id": "…",
    "provider": "…",
    "merchant_account": "…",
    "amount": "…",
    "currency": "…",
    "expires_at": "…",
    "instructions": "…"
}
```

**Request/Response لـ `POST /payments/{payment}/verify`:**

```json
// Request
{ "transaction_number": "…" }

// Response (success)
{ "payment_id": "…", "status": "paid", "order_id": "…" }

// Response (failure)
{ "payment_id": "…", "status": "failed", "reason": "amount_mismatch" }
```

**تأثير على المسارات الحالية:**

| المسار الحالي | القرار |
|---|---|
| `GET /payments`, `GET /payments/{paymentTransaction}` | **بدون تغيير** — تبقى للاستعلام |
| `POST /payments` | **يُعدَّل** — نفس الفعل، منطق داخلي مختلف (§7.9.3) |
| `POST /payments/{paymentTransaction}/complete` | **قرار نهائي: Deprecated.** يبقى موجودًا مؤقتًا للتوافق الخلفي (backward compatibility)، **يُعلَّم صريحًا `@deprecated`** في الكود/OpenAPI؛ `POST /payments/{payment}/verify` هو **المدخل العام الوحيد** الذي يجوز له إكمال الدفع (`PaymentService::completePayment()` يُستدعى فقط من `PaymentVerificationService` في المسار الطبيعي). يُحذف فعليًا في Batch 7.9 (Cleanup) بعد الترحيل |
| `POST /payments/{paymentTransaction}/fail` | **قرار نهائي: Deprecated** — نفس المعالجة أعلاه |
| `POST /webhooks/{provider}`, `routes/webhooks.php` | **Dormant, not deleted** (§7.9.2) — يبقى موجودًا لكن غير مُستدعى من مسار الدفع الحالي؛ لا حذف قبل Batch 7.9 |

---

###### §7.9.9 — Architecture Guard Updates (`GatewayArchitectureGuardTest`)

**تُضاف/تُحدَّث الحراسات التالية:**

| Rule | ملاحظة |
|---|---|
| `PaymentGateway` contract يُصرِّح `verifyTransaction()` فقط — **لا** `initiate()` | يمنع رجوع hosted-checkout بالخطأ |
| **فقط** `PaymentGatewayService` يستخدم `PaymentGatewayRegistry` + `PaymentGateway` contract | **بدون تغيير** عن Phase 7.1/7.4 |
| Controllers لا تستدعي `PaymentGatewayService` مباشرة — فقط عبر `PaymentInstructionService`/`PaymentVerificationService` | جديد — يعكس §7.9.3 |
| `PaymentInstructionService` لا يستدعي Gateway/Registry | جديد |
| `PaymentGatewayService` لا يحتوي `if ($provider === …)` | **بدون تغيير** عن §7.4 |
| Gateway classes لا تستورد `App\Models\*` / لا تستخدم `DB::` | **بدون تغيير** |
| تغييرات `PaymentTransaction.status` تمر عبر `PaymentService` فقط | **بدون تغيير** |
| `PaymentInstructionService` / `PaymentVerificationService` / `PaymentGatewayService::verifyTransaction()` **لا** يستوردون `WebhookLog`, `WebhookLogService`, `WebhookService`, `ReplayProtectionService`, `GatewaySignatureVerifier`, أو أي mapper من `WebhookDomainCommandMapperRegistry` | **جديد** — يضمن أن الكود الخامل (dormant) لا يُستدعى من مسار Manual Transfer (§7.9.2/§7.9.10) |
| حراسات §7.2–§7.4 الخاصة بـ `WebhookService` (orchestrator-only) | **تبقى كما هي** — لا تُحذف، لأن الكود الذي تحرسه لا يزال موجودًا (dormant) وقد يُستخدَم لمزوّد مستقبلي |

---

###### §7.9.10 — Migration & Data Considerations (قرارات نهائية — تمت الموافقة)

| # | القرار | الحسم النهائي |
|---|---|---|
| 1 | مصير جدول/بنية `webhook_logs` وكل Webhook infra (`WebhookController`, `WebhookService`, `WebhookLogService`, `ReplayProtectionService`, `GatewaySignatureVerifier`, mapper registry) | **Dormant, not deleted.** تبقى جزءًا من منصة الدفع العامة (مزوّدون مستقبليون قد يدعمون webhooks). **لا** كتابة/قراءة إليها من مسار Manual Transfer الجديد. حذف فعلي فقط في Batch 7.9 (Cleanup) المستقل بعد استقرار الإنتاج |
| 2 | مصير `PaymentTransaction` الحالية بحالة `pending` (القديمة) عند النشر | خارج نطاق هذه الوثيقة (لا بيئة إنتاج فعلية بعد) — يُحدَّد عمليًا وقت الترحيل الفعلي إن لزم |
| 3 | مصير `POST /payments/{paymentTransaction}/complete` و `/fail` | **Deprecated** — تبقى للتوافق الخلفي، تُعلَّم صريحًا، `verify` هو المدخل العام الوحيد لإكمال الدفع؛ حذف فعلي في Batch 7.9 |
| 4 | `RefundGateway` / Refund flow | **خارج النطاق بالكامل — نهائي.** لا تعديل في أي Batch من 7.6–7.9 |
| 5 | ملفات Legacy (`ShamCashGateway`, `SyriatelCashGateway`, Signature Verifiers, `WebhookController`, إلخ) | **لا حذف في 7.6–7.8.** تبقى معزولة وخاملة (isolated & dormant) — **لا** مسار إنتاجي جديد يعتمد عليها (مُطبَّق كـ Architecture Guard §7.9.9). الحذف الفعلي يقع في **Batch 7.9 (Cleanup)** كدفعة مستقلة، فقط بعد اكتمال واختبار 7.6–7.8 |

**نطاق Batch 7.6 (الدفعة الحالية — بدء التنفيذ):** Gateway Contract Redesign + Verification DTOs + Payment States (`PaymentTransactionStatus`) + `PaymentInstructionService`/`PaymentVerificationService` (skeleton/interfaces) + Architecture Guards. **لا** ضمن هذا الـ Batch: API wiring كامل (Controllers/Routes — Batch 7.7)، E2E (Batch 7.8)، أي حذف لكود Legacy (Batch 7.9)، أي تعديل على Refund flow.

---

###### §7.9.11 — Exit Criteria

**Batch 7.6 (الدفعة الحالية):**

| # | Criterion |
|---|---|
| 1 | `PaymentGateway::verifyTransaction()` (contract جديد) + Gateway DTOs (`VerifyTransactionRequest`/`Response`) |
| 2 | Domain DTOs: `PaymentInstructionData`, `VerifyTransactionData`, `TransactionVerificationResult` |
| 3 | `PaymentTransactionStatus` الجديد (§7.9.7) + migration لعمود الحالة، دون كسر البيانات القديمة |
| 4 | `PaymentInstructionService` (skeleton) — ينشئ `PaymentTransaction(awaiting_transfer)`، بدون أي استدعاء Gateway |
| 5 | `PaymentVerificationService` (skeleton) — يطبّق **كل** قواعد §7.9.6 عبر استدعاء `PaymentGatewayService::verifyTransaction()` |
| 6 | `GatewayArchitectureGuardTest` مُحدَّث وخضراء، بما فيها حراسة عدم استدعاء dormant webhook code (§7.9.9) |
| 7 | اختبارات Unit خضراء لكل مكوّن جديد؛ **لا** تعديل/حذف لأي اختبار Legacy قائم (يبقى أخضر كما هو) |

**Batch 7.7 (لاحقًا):** API wiring كامل (`PaymentController::store()`/`verify()`, Routes, Requests, Resources/OpenAPI) + Deprecation صريح لـ `complete`/`fail`.

**Batch 7.8 (لاحقًا):** E2E جديد يغطي: نجاح → `paid`، رفض كل شرط من §7.9.6 على حدة، إعادة محاولة بعد فشل، رفض transaction_number مُعاد استخدامه، انتهاء الصلاحية (`expired`) يرفض بدون استدعاء Gateway، Correlation ID ساري المفعول عبر `ActivityLog`/`Outbox` لمسار `verify`.

**Batch 7.9 — Cleanup (✅ Done):** حُذف فعليًا webhook infra + hosted-checkout initiate + `complete`/`fail` endpoints + اختبارات legacy؛ `RefundGateway` (ShamCash/Syriatel) و `ApiSyriaGateway::verifyTransaction()` بقيا؛ **477/477** tests green.

---

###### §7.9.12 — محتوى Phase 7 (ملخص نهائي — يستبدل الملخص في §7.7)

- Payment instructions (`PaymentInstructionService`) + Manual transfer verification (`PaymentVerificationService`) عبر `PaymentGatewayService` (ACL) → API Syria `find_tx`.
- `payment_transactions` (عبر `PaymentService` — SSOT) بحالات `awaiting_transfer/verifying/paid/failed/expired`.
- Refunds + `commission_adjustments` (§52) — عبر `RefundService`، **بدون تغيير**.
- Commissions (append-only) — بدون تغيير Phase 5 rules.
- Batch 7.1–7.5 (hosted checkout + webhooks) محفوظة كسجل تاريخي في §7.0–§7.7، غير فعّالة إنتاجيًا بعد اكتمال §7.9.

---

#### Phase 8 — Notifications

- Email / SMS / Templates.
- Outbox Worker (§57 consumers).
- Audit trail activation كامل (§59) + Observers.

---

#### Phase 9 — Production Hardening

- Performance, queue workers, monitoring, security review, load testing.
- MySQL 8 validation (CI ✅), retention jobs, full integration sweep.

---

### §1.2 — ربط المراحل القديمة (1–23) → الجديدة (4–9)

| المراحل القديمة (§2 granular) | المرحلة الجديدة |
|---|---|
| 4 Tenant Middleware | ✅ مدمجة في Phase 2 |
| 5 RBAC | Phase 4 |
| 6 Venues, 7 Categories/Events, 8 Ticket Types, 9 Seating, 10 Products, 11 Discounts, 12 Financial | Phase 4 (Models) → Phase 5 (Services) → Phase 6 (APIs) |
| 13 Orders/Checkout, 17 Check-in | Phase 5 + Phase 6 |
| 14 Payments/Webhooks, 15 Commissions, 16 Refunds | Phase 7 |
| 18 Outbox, 19 Notifications, 20 Audit | Phase 5 (hooks) + Phase 8 (Worker) |
| 21 Media/Documents, 22 API Clients | Phase 4 (Models) + Phase 6 (APIs) |
| 23 Hardening | Phase 9 |

---

### §1.3 — الترتيب القديم (23 phase — مرجع granular)

<details>
<summary>الجدول الأصلي (Phases 1–23) — للرجوع إلى §2</summary>

| # | Phase | العنوان |
|---|---|---|
| 1 | **Infrastructure** | إعداد المشروع، الحزم، config، الطبقات الأساسية (Support/Domain skeleton) |
| 2 | **Core Traits & Tenancy Foundation** | `BelongsToVenue`, `TenantContext`, `HasVersion`, base classes |
| 3 | **Authentication** | Sanctum + sessions + password reset + users |
| 4 | **Tenant Resolution Middleware** | `ResolveTenantMiddleware` (subdomain) + `ResolveApiClientTenantMiddleware` (§53) |
| 5 | **Authorization (RBAC)** | `permissions`, `role_permissions`, `user_permissions`, Policies, escalation protection (§54) |
| 6 | **Venues** | `venues` + `venue_user` + versioning |
| 7 | **Categories & Events** | `categories`, `events` (lifecycle + version) |
| 8 | **Ticket Types** | `ticket_types` (+ pessimistic lock على `quantity_sold`، version) |
| 9 | **Seating & Reservations** | `zones`, `venue_tables`, `table_seats`, `reservations` |
| 10 | **Products** | `products`, `product_variants` |
| 11 | **Discounts** | `coupons`, `promo_codes` (+ `deleted_at` §50) |
| 12 | **Financial Settings** | `tax_rates`, `platform_settings` (+ version §58) |
| 13 | **Orders & Checkout** | `orders`, `tickets`, `ticket_serial_counters` (Payment Flow core) |
| 14 | **Payments & Webhooks** | `payment_transactions`, `webhook_logs` (idempotency §51 + signature §56) |
| 15 | **Commissions** | `commissions` (تُكتب ضمن Payment Flow) |
| 16 | **Refunds & Commission Adjustments** | `refunds`, `commission_adjustments` (§52) |
| 17 | **Check-in** | Check-in Flow على `tickets` |
| 18 | **Outbox Pattern** | `outbox_events` + Worker (§57) |
| 19 | **Notifications & Templates** | `notifications`, `email_templates`, `sms_templates` (consumers للـ Outbox) |
| 20 | **Audit Trail** | `activity_logs` + `changed_fields` + Observers (§59) |
| 21 | **Media & Documents** | `media`, `documents` (polymorphic) |
| 22 | **API Clients (Third-Party)** | `api_clients` scopes + tenant resolution path |
| 23 | **Hardening & Production Readiness** | caching, indexes review, retention jobs, full test sweep |

</details>

---

## 2. تفاصيل كل Phase

> لكل Phase: **Purpose / Prerequisites / Models / Migrations / Policies / Services / Repositories / Controllers / Form Requests / API Resources / Events / Listeners / Jobs / Notifications / Observers / Middleware / Console Commands / Seeders / Factories / Tests / Routes / Validation / Indexes / Transactions / Locks / Caching / Queues.** إذا كان العنصر غير مطلوب في هذه المرحلة يُذكر «— لا شيء».

---

### Phase 1 — Infrastructure

- **Purpose:** تجهيز مشروع Laravel 12 نظيف بالطبقات المعمارية الفارغة (skeleton) قبل أي كود دومين.
- **Prerequisites:** لا شيء.
- **Models:** لا شيء.
- **Migrations:** لا شيء دومينية بعد (فقط إبقاء migrations الافتراضية لـ Laravel المتعلقة بـ jobs/cache/failed_jobs إن استُخدمت database driver).
- **Policies:** لا شيء.
- **Services:** لا شيء (إنشاء مجلد `app/Services` فقط).
- **Repositories:** لا شيء (إنشاء `app/Repositories` فقط عند الحاجة لاحقًا).
- **Controllers:** لا شيء.
- **Form Requests / API Resources:** لا شيء.
- **Events / Listeners / Jobs / Notifications / Observers:** لا شيء (إنشاء المجلدات فقط).
- **Middleware:** لا شيء دومينية.
- **Console Commands:** لا شيء.
- **Seeders / Factories:** لا شيء.
- **Tests:** إعداد `phpunit.xml`، قاعدة اختبار (SQLite/Postgres test)، `TestCase` أساسي + smoke test.
- **Routes:** تقسيم `routes/` (`web.php`, `api.php`, ملف `tenant.php` وملف `api_clients.php` كـ placeholders).
- **Validation Rules:** لا شيء.
- **Indexes / Transactions / Locks:** لا شيء.
- **Caching:** ضبط `config/cache.php` (Redis)، `config/queue.php` (Redis/database)، `config/session.php` (database إن كان جدول `sessions` هو المستهدف).
- **Queues:** إعداد اتصال الطابور + queue `default`, `outbox`, `notifications`.
- **مخرجات إضافية:** تركيب `laravel/sanctum`، ضبط `.env.example`، إعداد `DB` (PostgreSQL موصى بها في §50 — أو MySQL مع generated columns)، إعداد `config/tenancy.php` مخصص (اختياري داخلي).

---

### Phase 2 — Core Traits & Tenancy Foundation

- **Purpose:** بناء الأساس المشترك الذي تعتمد عليه كل الجداول المستأجرة: العزل عبر `venue_id`، والـ optimistic locking.
- **Prerequisites:** Phase 1.
- **Models:** `BaseModel` (اختياري abstract)، لا جداول بعد.
- **Migrations:** لا شيء.
- **Policies:** `BasePolicy` (abstract) يحقن فحص `TenantContext`.
- **Services:** `TenantContext` (Support, request-bound singleton) — يحمل `venue_id` الحالي؛ **نفس الواجهة** بغض النظر عن مصدر الطلب (subdomain أو api_client) طبقًا لـ §53.
- **Repositories:** لا شيء.
- **Controllers / Form Requests / Resources:** لا شيء.
- **Events / Listeners / Jobs / Notifications:** لا شيء.
- **Observers:** لا شيء بعد.
- **Middleware:** `TenantMiddleware`, `ApiClientMiddleware` (§53) — **مكتمل في Phase 2**.
- **Console Commands / Seeders / Factories:** لا شيء.
- **Tests:** Unit tests لـ `BelongsToVenue` Global Scope (يحقن `where venue_id` تلقائيًا + يمنع القراءة عبر المستأجرين)، و`HasVersion`.
- **Routes:** لا شيء.
- **Validation Rules:** لا شيء.
- **Indexes:** لا شيء.
- **Transactions / Locks:** تعريف Trait `HasOptimisticLock` (يطبّق `WHERE version = :current` ثم `version+1`، ويرمي `StaleModelException` عند 0 rows — §58).
- **Caching:** بنية مفتاح cache للـ tenant (نمط تخزين venue بالـ subdomain — v1.0 §2).
- **Queues:** لا شيء.
- **Traits المطلوبة:** `BelongsToVenue` (Global Scope + auto-fill `venue_id`)، `HasVersion` / `HasOptimisticLock`، `HasUuidSerial` (إن لزم للتذاكر لاحقًا).

---

### Phase 3 — Authentication

- **Purpose:** توفير أساس المصادقة القياسي (Sanctum + sessions + password reset) للأدوار الأربعة.
- **Prerequisites:** Phase 1, 2.
- **Models:** `User` (تفعيل `HasApiTokens`, `Notifiable`), `PersonalAccessToken` (Sanctum القياسي).
- **Migrations:** `users` (كما في v1.0)، `personal_access_tokens` (شكل Sanctum القياسي §55)، `sessions` (§55)، `password_reset_tokens` (§55). **بدون `venue_id`** (جداول عالمية §55, §64).
- **Policies:** `UserPolicy`, `VenuePolicy` (أساس — تُكمَّل في Phase 4).
- **Services:** `AuthService` (login, logout, issueToken, revokeToken)، `PasswordResetService`.
- **Repositories:** `UserRepository` (اختياري).
- **Controllers:** `Auth/LoginController`, `Auth/LogoutController`, `Auth/PasswordResetController`, `Auth/TokenController`.
- **Form Requests:** `LoginRequest`, `ForgotPasswordRequest`, `ResetPasswordRequest`, `IssueTokenRequest`.
- **API Resources:** `UserResource`, `TokenResource`.
- **Events:** `UserLoggedIn`, `PasswordResetRequested`.
- **Listeners:** لا شيء إلزامي (يمكن ربطها لاحقًا بالـ Audit في Phase 20).
- **Jobs:** لا شيء (البريد يمر عبر Notifications/Outbox لاحقًا؛ في هذه المرحلة إرسال reset مباشر مقبول لأن Outbox لم يُبنَ بعد — أو تأجيل التكامل حتى Phase 18/19).
- **Notifications:** `ResetPasswordNotification`.
- **Observers:** لا شيء.
- **Middleware:** `auth:sanctum` (قياسي)، لا شيء مخصص.
- **Console Commands:** لا شيء.
- **Seeders:** `SuperAdminSeeder` (إنشاء `is_super_admin = true` أولي).
- **Factories:** `UserFactory`.
- **Tests:** Feature tests (login/logout/token issue/expiry/password reset)، Unit للـ `AuthService`.
- **Routes:** `routes/api.php` (auth group): `POST /login`, `POST /logout`, `POST /forgot-password`, `POST /reset-password`, `POST /tokens`.
- **Validation Rules:** email موجود، password policy، token صالح غير منتهٍ.
- **Indexes:** `personal_access_tokens(token)` unique، `sessions(user_id)`، `sessions(last_activity)`، `password_reset_tokens(email)` PK (§55).
- **Transactions:** إصدار/إبطال التوكن ضمن transaction بسيطة.
- **Locks:** لا شيء.
- **Caching:** لا شيء (أو cache لجلسة المستخدم عند الحاجة).
- **Queues:** `notifications` (لإرسال بريد إعادة التعيين) — يُفعَّل تكامله الكامل مع Outbox في Phase 18/19.

---

### Phase 4 (جديد) — Domain Models & Authorization

> **يحل محل** المراحل القديمة 5–6–7–8–9–10–11–12–21 (Models + Policies + RBAC) في التسلسل المحدّث §1.1. **لا Controllers أعمال.**

- **Purpose:** بناء طبقة Domain كاملة (Models + علاقات + Policies + RBAC) قبل Services أو APIs.
- **Prerequisites:** Phase 1–3 ✅، Migrations ✅.
- **Models:** **جميع** models §0 (4 موجودة — 31 متبقية).
- **Migrations:** — (مكتملة).
- **Policies:** policy لكل مورد tenant-scoped + `PermissionPolicy`, `UserPermissionPolicy` (§54).
- **Services:** `PermissionService` فقط (RBAC — ليس business services).
- **Repositories:** — لا شيء.
- **Controllers:** — **ممنوع** business controllers.
- **Form Requests / API Resources:** — لا شيء.
- **Events / Listeners:** `PermissionGranted`, `PermissionRevoked` + listeners للـ audit (stub حتى Phase 8).
- **Jobs / Notifications:** — لا شيء.
- **Observers:** تسجيل Observers (هيكل) — تفعيل audit كامل Phase 8.
- **Middleware:** `CheckPermission` / Gate helpers.
- **Console Commands:** `permissions:sync` (اختياري).
- **Seeders:** `PermissionSeeder` ✅ — `RolePermissionSeeder` (إن لزم).
- **Factories:** factory لكل model.
- **Tests:** Matrix كامل Owner / Staff / Customer / Super Admin × CRUD policies؛ escalation §54؛ tenant isolation.
- **Routes:** — لا business routes.
- **Validation / Indexes / Transactions / Locks / Caching / Queues:** — لا شيء (Models layer only).

---

### Phase 4 (قديم) — Tenant Resolution Middleware

> **✅ مكتمل** — نُفِّذ في Phase 2 (`TenantMiddleware`, `ApiClientMiddleware`, `TenantContext`, lookups §53).

- **Purpose:** تحديد المستأجر عبر مسارين مستقلين تمامًا (§53): Subdomain للمستخدمين، API Key للتكاملات.
- **Prerequisites:** Phase 2 (TenantContext), Phase 3 (users/tokens)، وجود جدول `venues` مطلوب فعليًا لذا يُنفَّذ ربطه بعد Phase 6 — **الميدلوير يُكتب هنا لكن اختباره الكامل يعتمد على Phase 6**.
- **Models:** لا جديد (يقرأ `venues`, `api_clients`).
- **Migrations:** لا شيء (`api_clients` موجود من v1.2؛ migration فعلي في Phase 22).
- **Policies:** لا شيء.
- **Services:** `TenantResolver` (subdomain → venue)، `ApiClientResolver` (api_key/secret → venue) — كلاهما يملأ نفس `TenantContext`.
- **Repositories:** لا شيء.
- **Controllers / Form Requests / Resources:** لا شيء.
- **Events / Listeners / Jobs / Notifications / Observers:** لا شيء.
- **Middleware:**
  - `ResolveTenantMiddleware` — استخراج subdomain → lookup `venues` (مع cache) → `TenantContext::bind(venue_id)`.
  - `ResolveApiClientTenantMiddleware` — قراءة API Key + Secret من Header → `api_clients WHERE api_key AND active` → verify hashed secret → `bind(venue_id)` → update `last_used_at` → فحص scopes. **مستقل تمامًا** ولا يستدعي الأول (§53). أي فشل → 401 قبل أي Controller.
- **Console Commands:** لا شيء.
- **Seeders / Factories:** `VenueFactory` (subdomain) لاحقًا في Phase 6.
- **Tests:** Feature: طلب عبر subdomain صحيح/خاطئ، طلب api_client صحيح/غير نشط/secret خاطئ (401)، تطابق شكل `TenantContext` من المسارين.
- **Routes:** تسجيل مجموعتين: `tenant` (تطبّق `ResolveTenantMiddleware`) و`api-clients` (تطبّق `ResolveApiClientTenantMiddleware`). **لا مسار ثالث مختلط** (§53).
- **Validation Rules:** وجود header المفتاح/السر.
- **Indexes:** `api_clients(api_key)` مفهرس (يُنشأ في Phase 22)، `venues(subdomain)` partial unique (§50).
- **Transactions:** لا شيء.
- **Locks:** لا شيء.
- **Caching:** cache تحويل subdomain→venue و api_key→venue (نفس نمط v1.0 §2, §53).
- **Queues:** لا شيء.

---

### Phase 5 — Authorization (RBAC)

- **Purpose:** كتالوج الصلاحيات وربطها بالأدوار والمستخدمين، مع حماية تصعيد الصلاحيات (§54).
- **Prerequisites:** Phase 3 (users), Phase 4 (tenant context)، ويكتمل ربط venue في Phase 6.
- **Models:** `Permission`, `RolePermission`, `UserPermission`.
- **Migrations:** `permissions`, `role_permissions`, `user_permissions` (كما في v1.2 §42). `user_permissions` يحمل `venue_id`.
- **Policies:** `UserPermissionPolicy` — **فقط** `owner` (venue_user.role) أو Super Admin يكتبان (grant/revoke) على `user_permissions` (§54). `RolePermissionPolicy`.
- **Services:** `PermissionService` (grant, revoke, sync, checkAbility). كل grant/revoke: (1) يمرّ عبر `UserPermissionPolicy`، (2) يُسجَّل إلزاميًا في `activity_logs` مع `actor_user_id`/before/after/`ip_address` (§54) — إذا لم يُبنَ `activity_logs` بعد (Phase 20) يُسجَّل الالتزام كـ TODO مربوط، والأفضل ترتيبيًا تفعيل التسجيل عند اكتمال Phase 20.
- **Repositories:** لا شيء.
- **Controllers:** `PermissionController`, `UserPermissionController` (grant/revoke).
- **Form Requests:** `GrantPermissionRequest`, `RevokePermissionRequest`.
- **API Resources:** `PermissionResource`, `UserPermissionResource`.
- **Events:** `PermissionGranted`, `PermissionRevoked`.
- **Listeners:** `LogPermissionChange` (يكتب في `activity_logs`).
- **Jobs / Notifications:** لا شيء.
- **Observers:** لا شيء (التسجيل عبر Listener/Service صراحةً هنا نظرًا للحساسية §54).
- **Middleware:** `CheckPermission` / `authorize` gate.
- **Console Commands:** `permissions:sync` (تعبئة كتالوج `permissions`).
- **Seeders:** `PermissionSeeder` (الكتالوج الثابت), `RolePermissionSeeder`.
- **Factories:** `PermissionFactory`.
- **Tests:** Staff لا يستطيع منح نفسه صلاحية (يُرفض §54-3)، owner/super admin فقط يكتبان، كل grant/revoke يُنتج سجل تدقيق، العزل ضمن venue.
- **Routes:** `GET /permissions`, `POST /users/{user}/permissions`, `DELETE /users/{user}/permissions/{permission}`.
- **Validation:** permission موجود في الكتالوج، user ضمن نفس venue.
- **Indexes:** `user_permissions(venue_id, user_id)`, `role_permissions(role, permission_id)`.
- **Transactions:** grant/revoke + سجل التدقيق ضمن transaction واحدة.
- **Locks:** لا شيء.
- **Caching:** cache صلاحيات المستخدم (إبطال عند grant/revoke).
- **Queues:** لا شيء.

---

### Phase 6 — Venues

- **Purpose:** المستأجر الأساسي؛ كل الجداول المستأجرة تعتمد عليه.
- **Prerequisites:** Phase 2–5.
- **Models:** `Venue` (SoftDeletes, `HasVersion`), `VenueUser` (pivot: `role`).
- **Migrations:** `venues` (SoftDeletes، `version` default 1 §58، `commission_rate`, `theme_config`, `subdomain`), `venue_user` (role). فهرس partial unique على `subdomain WHERE deleted_at IS NULL` (§50 — Postgres) أو generated column `subdomain_active` (§50 — MySQL).
- **Policies:** `VenuePolicy` (owner/super admin يعدّلان).
- **Services:** `VenueService` (create, update باستخدام optimistic lock على `version`، softDelete, attachUser). التعديلات على `commission_rate`/`theme_config` عبر optimistic lock (§58).
- **Repositories:** `VenueRepository` (lookup by subdomain — للـ cache).
- **Controllers:** `VenueController`, `VenueUserController`.
- **Form Requests:** `StoreVenueRequest`, `UpdateVenueRequest` (يحمل `version` للـ optimistic check), `AttachVenueUserRequest`.
- **API Resources:** `VenueResource`, `VenueUserResource`.
- **Events:** `VenueCreated`, `VenueUpdated`.
- **Listeners:** لا شيء إلزامي (audit عبر Observer لاحقًا).
- **Jobs / Notifications:** لا شيء.
- **Observers:** `VenueObserver` (لاحقًا يسجّل تغيّر `commission_rate` في `activity_logs` — إلزامي §59؛ يُفعَّل عند Phase 20).
- **Middleware:** يستهلك `ResolveTenantMiddleware` من Phase 4.
- **Console Commands:** لا شيء.
- **Seeders:** `VenueSeeder` (بيئة تطوير).
- **Factories:** `VenueFactory`, `VenueUserFactory`.
- **Tests:** unique subdomain مع soft delete (إعادة استخدام subdomain بعد الحذف §50)، optimistic conflict على `version` (§58)، عزل المستأجر.
- **Routes:** CRUD venues (Super Admin) + إدارة أعضاء المنشأة (owner).
- **Validation:** subdomain فريد ضمن `deleted_at IS NULL` (§50)، `commission_rate` رقم صالح، `version` مطابق.
- **Indexes:** partial/generated unique `subdomain` (§50).
- **Transactions:** create venue + attach owner ضمن transaction.
- **Locks:** optimistic (`version`) على update (§58).
- **Caching:** subdomain→venue (Phase 4).
- **Queues:** لا شيء.

---

### Phase 7 — Categories & Events

- **Purpose:** الفعاليات ودورة حياتها ضمن المنشأة.
- **Prerequisites:** Phase 6.
- **Models:** `Category`, `Event` (SoftDeletes, `HasVersion`, `BelongsToVenue`).
- **Migrations:** `categories`, `events` (SoftDeletes، `version` §58، `slug`، lifecycle status، `venue_id`). فهرس partial/generated unique على `(venue_id, slug) WHERE deleted_at IS NULL` (§50).
- **Policies:** `EventPolicy`, `CategoryPolicy` (owner/staff بصلاحية).
- **Services:** `EventService` (create, update via optimistic lock, publish/lifecycle transitions — **حسب Event Lifecycle المقفل، دون تغيير**), `CategoryService`.
- **Repositories:** `EventRepository` (اختياري).
- **Controllers:** `EventController`, `CategoryController`.
- **Form Requests:** `StoreEventRequest`, `UpdateEventRequest` (+`version`), `StoreCategoryRequest`.
- **API Resources:** `EventResource`, `CategoryResource`.
- **Events:** `EventPublished`, `EventUpdated`, `EventStatusChanged`.
- **Listeners:** لا شيء إلزامي.
- **Jobs / Notifications:** لا شيء بعد.
- **Observers:** `EventObserver` (audit على تعديلات حساسة لاحقًا §59).
- **Middleware:** tenant من Phase 4.
- **Console Commands:** `events:transition-lifecycle` (إن كان جزءًا من lifecycle المقفل، مثل الانتهاء التلقائي).
- **Seeders:** `CategorySeeder`.
- **Factories:** `CategoryFactory`, `EventFactory`.
- **Tests:** unique slug ضمن venue مع soft delete (§50)، optimistic conflict (§58)، lifecycle transitions مطابقة للـ v1.0، عزل المستأجر.
- **Routes:** CRUD events + categories ضمن tenant.
- **Validation:** slug فريد ضمن venue (`deleted_at IS NULL` §50)، تواريخ صالحة، status ضمن Enum المقفل.
- **Indexes:** partial/generated unique `(venue_id, slug)` (§50)، فهرس `(venue_id, status)`.
- **Transactions:** create event + ticket types (لاحقًا) قد تُجمَّع.
- **Locks:** optimistic (§58).
- **Caching:** cache صفحة الفعالية العامة (اختياري).
- **Queues:** لا شيء.

---

### Phase 8 — Ticket Types

- **Purpose:** أنواع التذاكر مع سعر/كمية، محمية بـ pessimistic lock على `quantity_sold`.
- **Prerequisites:** Phase 7.
- **Models:** `TicketType` (`HasVersion`, `BelongsToVenue`).
- **Migrations:** `ticket_types` (`event_id`, `venue_id`, `price`, `quantity`, `quantity_sold`, `version` §58).
- **Policies:** `TicketTypePolicy`.
- **Services:** `TicketTypeService` (create, update price/quantity via optimistic lock §58). ملاحظة: تعديل `quantity_sold` أثناء الشراء يتم عبر **pessimistic `lockForUpdate`** ضمن `OrderService` (Phase 13) — لا optimistic هنا لهذا العمود تحديدًا (§58).
- **Repositories:** لا شيء.
- **Controllers:** `TicketTypeController`.
- **Form Requests:** `StoreTicketTypeRequest`, `UpdateTicketTypeRequest` (+`version`).
- **API Resources:** `TicketTypeResource`.
- **Events:** `TicketTypePriceChanged`.
- **Listeners:** لا شيء إلزامي.
- **Observers:** `TicketTypeObserver` (audit إلزامي على `price` §59 — يُفعَّل Phase 20).
- **Middleware:** tenant.
- **Console Commands / Seeders:** لا شيء.
- **Factories:** `TicketTypeFactory`.
- **Tests:** optimistic conflict على تعديل السعر/الكمية (§58)، تسجيل تغيّر السعر في التدقيق (§59)، تحقق الكمية.
- **Routes:** CRUD ضمن event.
- **Validation:** price ≥ 0، quantity ≥ quantity_sold، `version`.
- **Indexes:** `(event_id)`, `(venue_id)`.
- **Transactions:** update ضمن transaction.
- **Locks:** optimistic لتعديل الإدارة (§58)؛ pessimistic (`lockForUpdate`) على `quantity_sold` عند الشراء (v1.0 — يُطبَّق Phase 13).
- **Caching:** توافر التذاكر (اختياري، إبطال عند البيع).
- **Queues:** لا شيء.

---

### Phase 9 — Seating & Reservations

- **Purpose:** الخرائط المكانية والحجوزات (Reservation Flow المقفل).
- **Prerequisites:** Phase 7 (events)، Phase 8.
- **Models:** `Zone`, `VenueTable`, `TableSeat`, `Reservation` (كلها `BelongsToVenue`).
- **Migrations:** `zones`, `venue_tables`, `table_seats`, `reservations` (كما v1.0/v1.1، علاقات Zones↔VenueTables↔TableSeats↔Reservations دون تغيير).
- **Policies:** `ReservationPolicy`, `ZonePolicy`.
- **Services:** `ReservationService` (hold/confirm/release — **حسب Reservation Flow المقفل**), `SeatingService` (zones/tables/seats CRUD).
- **Repositories:** `SeatAvailabilityRepository` (اختياري).
- **Controllers:** `ZoneController`, `VenueTableController`, `TableSeatController`, `ReservationController`.
- **Form Requests:** `StoreZoneRequest`, `StoreReservationRequest`, ...
- **API Resources:** `ZoneResource`, `VenueTableResource`, `TableSeatResource`, `ReservationResource`.
- **Events:** `ReservationCreated`, `ReservationReleased`.
- **Listeners:** لا شيء إلزامي.
- **Jobs:** `ReleaseExpiredReservations` (إن كان الـ hold مؤقتًا في الـ flow المقفل).
- **Notifications:** لا شيء بعد.
- **Observers:** لا شيء إلزامي.
- **Middleware:** tenant.
- **Console Commands:** `reservations:release-expired` (يشغّل الـ Job).
- **Seeders:** لا شيء.
- **Factories:** factories للأربعة.
- **Tests:** منع الحجز المزدوج للمقعد نفسه (تزامن)، release عند الانتهاء، عزل المستأجر.
- **Routes:** CRUD seating + reservations.
- **Validation:** المقعد متاح، ينتمي للـ venue/event الصحيح.
- **Indexes:** `(venue_id)`, `(table_id)`, `(seat_id)`, `(reservation status)`.
- **Transactions:** إنشاء الحجز + قفل المقاعد ضمن transaction.
- **Locks:** `lockForUpdate` على المقاعد أثناء الحجز (منع double-booking).
- **Caching:** خريطة المقاعد (إبطال عند الحجز).
- **Queues:** `default` لـ release-expired.

---

### Phase 10 — Products

- **Purpose:** المنتجات ومتغيّراتها (add-ons/بضائع).
- **Prerequisites:** Phase 6.
- **Models:** `Product`, `ProductVariant` (`BelongsToVenue`).
- **Migrations:** `products`, `product_variants` (`price`, `price_override`).
- **Policies:** `ProductPolicy`.
- **Services:** `ProductService`.
- **Repositories:** لا شيء.
- **Controllers:** `ProductController`, `ProductVariantController`.
- **Form Requests:** `StoreProductRequest`, `UpdateProductRequest`, `StoreVariantRequest`.
- **API Resources:** `ProductResource`, `ProductVariantResource`.
- **Events:** `ProductPriceChanged`.
- **Observers:** `ProductObserver`, `ProductVariantObserver` (audit إلزامي على `price`/`price_override` §59).
- **Middleware:** tenant.
- **Console Commands / Seeders:** لا شيء.
- **Factories:** `ProductFactory`, `ProductVariantFactory`.
- **Tests:** audit تغيّر السعر (§59)، عزل.
- **Routes:** CRUD products/variants.
- **Validation:** price ≥ 0.
- **Indexes:** `(venue_id)`, `(product_id)`.
- **Transactions:** update ضمن transaction.
- **Locks:** لا شيء (ليست ضمن جداول version §58).
- **Caching:** كتالوج المنتجات (اختياري).
- **Queues:** لا شيء.

---

### Phase 11 — Discounts (Coupons & Promo Codes)

- **Purpose:** الكوبونات وأكواد الخصم مع soft-delete-safe uniqueness (§50).
- **Prerequisites:** Phase 6.
- **Models:** `Coupon` (SoftDeletes — **جديد في §50**), `PromoCode` (SoftDeletes — **جديد في §50**).
- **Migrations:** `coupons` + إضافة `deleted_at` (§50)، `promo_codes` + إضافة `deleted_at` (§50). فهرس partial/generated unique `(venue_id, code) WHERE deleted_at IS NULL` لكليهما (§50).
- **Policies:** `CouponPolicy`, `PromoCodePolicy`.
- **Services:** `CouponService`, `PromoCodeService` (validate/apply — يُستدعى ضمن Checkout في Phase 13). التحقق من التوفر دائمًا ضمن `deleted_at IS NULL` (§50).
- **Repositories:** لا شيء.
- **Controllers:** `CouponController`, `PromoCodeController`.
- **Form Requests:** `StoreCouponRequest`, `StorePromoCodeRequest`.
- **API Resources:** `CouponResource`, `PromoCodeResource`.
- **Events:** لا شيء إلزامي.
- **Observers:** لا شيء إلزامي.
- **Middleware:** tenant.
- **Console Commands / Seeders:** لا شيء.
- **Factories:** `CouponFactory`, `PromoCodeFactory`.
- **Tests:** إعادة استخدام `code` بعد soft delete (§50)، uniqueness ضمن venue، تطبيق الخصم.
- **Routes:** CRUD.
- **Validation:** `code` فريد ضمن venue (`deleted_at IS NULL` §50)، تواريخ الصلاحية.
- **Indexes:** partial/generated unique `(venue_id, code)` (§50).
- **Transactions:** لا شيء خاص (التطبيق داخل transaction الطلب).
- **Locks:** لا شيء.
- **Caching:** لا شيء.
- **Queues:** لا شيء.

---

### Phase 12 — Financial Settings (Tax Rates & Platform Settings)

- **Purpose:** الضرائب وإعدادات المنصة (singleton) مع optimistic lock (§58).
- **Prerequisites:** Phase 6.
- **Models:** `TaxRate` (`HasVersion`, `BelongsToVenue`), `PlatformSetting` (`HasVersion`, singleton, global).
- **Migrations:** `tax_rates` (+`version` §58)، `platform_settings` (+`version` §58، صف واحد).
- **Policies:** `TaxRatePolicy`, `PlatformSettingPolicy` (Super Admin فقط للأخير).
- **Services:** `TaxRateService`, `PlatformSettingService` (update via optimistic lock §58).
- **Repositories:** لا شيء.
- **Controllers:** `TaxRateController`, `PlatformSettingController`.
- **Form Requests:** `UpdateTaxRateRequest` (+`version`), `UpdatePlatformSettingRequest` (+`version`).
- **API Resources:** `TaxRateResource`, `PlatformSettingResource`.
- **Events:** `PlatformSettingUpdated`, `TaxRateUpdated`.
- **Observers:** `PlatformSettingObserver`, `TaxRateObserver` (audit إلزامي §59 — كل الأعمدة لـ platform_settings).
- **Middleware:** tenant / super-admin.
- **Console Commands:** لا شيء.
- **Seeders:** `PlatformSettingSeeder` (الصف الوحيد), `TaxRateSeeder`.
- **Factories:** `TaxRateFactory`.
- **Tests:** optimistic conflict على تعديل متزامن من عدة Super Admin (§58)، audit إلزامي (§59).
- **Routes:** update settings/tax rates.
- **Validation:** rate صالح، `version`.
- **Indexes:** `(venue_id)` لـ tax_rates.
- **Transactions:** update ضمن transaction.
- **Locks:** optimistic (§58).
- **Caching:** cache `platform_settings` (إبطال عند التعديل).
- **Queues:** لا شيء.

---

### Phase 13 — Orders & Checkout (Payment Flow Core)

- **Purpose:** إنشاء الطلبات وإصدار التذاكر مع serial صارم (Payment Flow المقفل). **هذا التدفق يُنفَّذ حرفيًا كما في v1.0 — دون تغيير.**
- **Prerequisites:** Phase 8 (ticket_types), 9 (reservations إن لزم), 10, 11, 12.
- **Models:** `Order`, `Ticket`, `TicketSerialCounter`.
- **Migrations:** `orders`, `tickets`, `ticket_serial_counters` (كما v1.0). **لا `version`** على orders/tickets (محميّة بـ pessimistic lock §58).
- **Policies:** `OrderPolicy`, `TicketPolicy`.
- **Services:** `OrderService` (createOrder, checkout) — يطبّق:
  - `lockForUpdate` على `ticket_types.quantity_sold` (v1.0).
  - `lockForUpdate` على `ticket_serial_counters` لإصدار serial صارم.
  - تطبيق coupon/promo/tax ضمن نفس transaction.
  - إنشاء صف `outbox_events` (`order.paid`) ضمن نفس transaction (§57 — يُفعَّل تكامله عند Phase 18).
- **Repositories:** `OrderRepository` (اختياري).
- **Controllers:** `OrderController`, `CheckoutController`.
- **Form Requests:** `CreateOrderRequest`, `CheckoutRequest`.
- **API Resources:** `OrderResource`, `TicketResource`.
- **Events:** `OrderCreated`, `OrderPaid`.
- **Listeners:** لا شيء متزامن يرسل بريد داخل transaction (ممنوع §57) — الإرسال عبر Outbox.
- **Jobs:** `ExpireUnpaidOrders` (إن وُجد في الـ flow المقفل).
- **Notifications:** لا شيء مباشر داخل الـ transaction (§57).
- **Observers:** لا شيء إلزامي.
- **Middleware:** tenant + auth.
- **Console Commands:** `orders:expire-unpaid` (إن لزم).
- **Seeders:** لا شيء.
- **Factories:** `OrderFactory`, `TicketFactory`.
- **Tests:** تزامن الشراء (لا overselling عبر lockForUpdate)، serial متسلسل صارم، تطبيق الخصم/الضريبة، إنشاء صف outbox داخل نفس الـ transaction.
- **Routes:** `POST /orders`, `POST /checkout`, `GET /orders/{order}`.
- **Validation:** توافر التذاكر، صحة الكوبون (`deleted_at IS NULL` §50)، بيانات الدفع.
- **Indexes:** `(venue_id)`, `(event_id)`, `(order status)`, `tickets(order_id)`.
- **Transactions:** transaction واحدة تشمل: خصم الكمية + serial + order + tickets + commission (Phase 15) + صف outbox (§57).
- **Locks:** **pessimistic `lockForUpdate`** على `ticket_types.quantity_sold` و`ticket_serial_counters` (v1.0, §58).
- **Caching:** إبطال توافر التذاكر.
- **Queues:** `outbox` (الإرسال لاحقًا)، `default` (expire).

---

### Phase 14 — Payments & Webhooks

- **Purpose:** تسجيل معاملات الدفع ومعالجة webhooks بأمان (idempotency §51 + signature §56).
- **Prerequisites:** Phase 13.
- **Models:** `PaymentTransaction`, `WebhookLog`.
- **Migrations:** `payment_transactions` (v1.2)، `webhook_logs` + `provider_event_id` (§51). فهرس `UNIQUE(provider, provider_event_id)` (§51)، `INDEX(status)` (§51).
- **Policies:** `PaymentTransactionPolicy`.
- **Services:** `PaymentService` (initiate/confirm), `WebhookService` — يطبّق التدفق المقفل حرفيًا (§56):
  1. Verify Signature (فشل → `webhook_logs.status = failed` ورفض فوري).
  2. Store (`status = verified`).
  3. Idempotency: `INSERT ... ON CONFLICT DO NOTHING` على `provider_event_id` (§51 — DB-level، لا SELECT-then-insert).
  4. Business Processing (فقط بعد اجتياز التحقق **و** التفرد معًا).
  5. Payment Update على `orders`/`payment_transactions`/`refunds`.
- **Repositories:** لا شيء.
- **Controllers:** `WebhookController` (endpoint عام لكل provider), `PaymentController`.
- **Form Requests:** `InitiatePaymentRequest` (الـ webhook لا يمر بـ FormRequest عادي بل بتحقق التوقيع).
- **API Resources:** `PaymentTransactionResource`.
- **Events:** `PaymentConfirmed`, `WebhookReceived`.
- **Listeners:** `ProcessVerifiedWebhook`.
- **Jobs:** `RetryFailedWebhooks` (يعتمد `INDEX(status)` §51).
- **Notifications:** لا شيء مباشر (Outbox).
- **Observers:** لا شيء.
- **Middleware:** `VerifyWebhookSignature` (إلزامي كأول خطوة §56)، **لا** يمر webhook عبر tenant subdomain (يُربط venue عند معالجة payment §51).
- **Console Commands:** `webhooks:retry`.
- **Seeders:** لا شيء.
- **Factories:** `PaymentTransactionFactory`, `WebhookLogFactory`.
- **Tests:** إعادة إرسال نفس webhook لا تُعالَج مرتين (§51)، توقيع فاسد يُرفض ويُسجَّل failed (§56)، race condition عبر UNIQUE (§51)، لا وصول لـ Payment Update دون verify+idempotency (§56).
- **Routes:** `POST /webhooks/{provider}` (خارج tenant subdomain group).
- **Validation:** توقيع صالح (§56).
- **Indexes:** `UNIQUE(provider, provider_event_id)` (§51)، `INDEX(status)` (§51).
- **Transactions:** معالجة webhook + payment update ضمن transaction (مع إدراج outbox عند اللزوم §57).
- **Locks:** يعتمد على قيد UNIQUE للتفرد بدل القفل (§51).
- **Caching:** لا شيء.
- **Queues:** `default` لإعادة المحاولة.

---

### Phase 15 — Commissions

- **Purpose:** احتساب عمولة المنصة عند الطلب (تُكتب مرة واحدة، append-only — دون تعديل §52).
- **Prerequisites:** Phase 13, 14.
- **Models:** `Commission`.
- **Migrations:** `commissions` (كما v1.2، `rate`, `amount`, `venue_id`).
- **Policies:** `CommissionPolicy` (قراءة فقط للإدارة).
- **Services:** `CommissionService` (calculate & record ضمن Payment Flow transaction). **لا يعدّل `commissions.amount` أبدًا** — الصافي يُحسب لاحقًا بالطرح (§52).
- **Repositories:** لا شيء.
- **Controllers:** `CommissionController` (تقارير قراءة).
- **Form Requests:** لا شيء (تُنشأ آليًا).
- **API Resources:** `CommissionResource`.
- **Events:** `CommissionRecorded`.
- **Observers:** لا شيء إلزامي (لا تُعدَّل).
- **Middleware:** tenant.
- **Console Commands:** لا شيء.
- **Seeders / Factories:** `CommissionFactory`.
- **Tests:** احتساب صحيح ضمن transaction الطلب، ثبات (append-only).
- **Routes:** `GET /commissions` (تقارير).
- **Validation:** لا شيء (داخلي).
- **Indexes:** `(venue_id)`, `(order_id)`.
- **Transactions:** تُكتب ضمن نفس transaction الطلب (Phase 13).
- **Locks:** لا شيء إضافي.
- **Caching:** تجميعات التقارير (اختياري).
- **Queues:** لا شيء.

---

### Phase 16 — Refunds & Commission Adjustments

- **Purpose:** الاسترجاع وتعديل العمولة المقابل (§52) — كل refund = صف adjustment واحد.
- **Prerequisites:** Phase 14, 15.
- **Models:** `Refund`, `CommissionAdjustment` (append-only، بلا `updated_at` §52).
- **Migrations:** `refunds` (v1.2)، **`commission_adjustments` (جديد §52)**: `venue_id`, `commission_id` FK, `refund_id` FK **unique**, `adjustment_amount` decimal(10,2), `rate_snapshot` decimal(5,2), `created_at` فقط.
- **Policies:** `RefundPolicy`.
- **Services:** `RefundService` — عند تحويل `refunds.status = processed` (ضمن transaction واحدة):
  - يحسب `adjustment_amount = refunds.amount × rate_snapshot` (§52).
  - `rate_snapshot` = نسخة من `commissions.rate` وقت الإنشاء (§52).
  - يفرض سقف مجموع الـ adjustments ≤ `commissions.amount` **على مستوى الخدمة** (لا عبر قيد DB §52).
  - يدرج صف `outbox_events` (`refund.processed`) ضمن نفس transaction (§57).
- **Repositories:** لا شيء.
- **Controllers:** `RefundController`.
- **Form Requests:** `CreateRefundRequest`.
- **API Resources:** `RefundResource`, `CommissionAdjustmentResource`.
- **Events:** `RefundProcessed`, `CommissionAdjusted`.
- **Observers:** لا شيء (append-only).
- **Middleware:** tenant.
- **Console Commands:** لا شيء.
- **Seeders / Factories:** `RefundFactory`, `CommissionAdjustmentFactory`.
- **Tests:** full refund = adjustment واحد بكامل القيمة (§52)، partial refunds متعددة تتراكم حتى السقف (§52)، `refund_id` unique (§52)، الصافي = `commissions.amount − SUM(adjustments)` (§52).
- **Routes:** `POST /refunds`, `GET /refunds/{refund}`.
- **Validation:** المبلغ ≤ المتبقي القابل للاسترجاع، السقف على مستوى الخدمة (§52).
- **Indexes:** `(commission_id)`, `(refund_id)` unique, `(venue_id)` (§52).
- **Transactions:** refund status → processed + commission_adjustment + outbox ضمن transaction واحدة (§52, §57).
- **Locks:** لا شيء إضافي (append-only + unique).
- **Caching:** إبطال تقارير العمولة.
- **Queues:** `outbox`.

---

### Phase 17 — Check-in

- **Purpose:** تفعيل حضور التذاكر (Check-in Flow المقفل — دون تغيير).
- **Prerequisites:** Phase 13.
- **Models:** يستخدم `Ticket` (لا جديد).
- **Migrations:** لا شيء جديد (حالة check-in موجودة في `tickets`).
- **Policies:** `CheckInPolicy` (staff/owner).
- **Services:** `CheckInService` (validate ticket, mark checked-in) — يدرج `outbox_events` (`ticket.checked_in`) ضمن transaction (§57).
- **Repositories:** لا شيء.
- **Controllers:** `CheckInController`.
- **Form Requests:** `CheckInRequest` (serial/QR).
- **API Resources:** `CheckInResultResource`.
- **Events:** `TicketCheckedIn`.
- **Listeners:** لا شيء مباشر (Outbox).
- **Jobs / Notifications:** لا شيء مباشر.
- **Observers:** لا شيء.
- **Middleware:** tenant + auth.
- **Console Commands:** لا شيء.
- **Seeders / Factories:** لا شيء جديد.
- **Tests:** لا يمكن check-in مرتين لنفس التذكرة، صحة الـ serial/QR، العزل، إدراج outbox.
- **Routes:** `POST /check-in`.
- **Validation:** التذكرة صالحة، غير مُستخدمة، تنتمي للـ venue/event.
- **Indexes:** `tickets(serial)`, `(event_id, status)`.
- **Transactions:** mark checked-in + outbox ضمن transaction.
- **Locks:** `lockForUpdate` على صف التذكرة أثناء الـ check-in (منع تزامن).
- **Caching:** لا شيء.
- **Queues:** `outbox`.

---

### Phase 18 — Outbox Pattern

- **Purpose:** فصل «تسجيل نية الإرسال» عن «الإرسال الفعلي» (§57). يربط كل الـ producers السابقة (13, 16, 17) بالـ consumers (19).
- **Prerequisites:** Phase 13–17.
- **Models:** `OutboxEvent`.
- **Migrations:** **`outbox_events` (جديد §57)**: `venue_id` nullable, `event_type`, `aggregate_type`, `aggregate_id`, `payload` json, `status` enum(pending/processing/sent/failed), `attempts` int default 0, `processed_at` nullable, timestamps. **لا FK للـ aggregate** (polymorphic-style §57).
- **Policies:** لا شيء (داخلي).
- **Services:** `OutboxService` (record داخل transaction — الوظيفة الوحيدة المسموح بها داخل الـ transaction §57), `OutboxDispatcher` (يسحب pending، ينفّذ عبر consumers، يحدّث status/attempts).
- **Repositories:** `OutboxRepository` (سحب pending حسب `(status, created_at)`).
- **Controllers:** لا شيء.
- **Form Requests / Resources:** لا شيء.
- **Events:** لا شيء (هو نفسه آلية الأحداث).
- **Listeners:** لا شيء.
- **Jobs:** `ProcessOutboxEvents` (Worker غير متزامن §57، backoff عبر `attempts`).
- **Notifications:** لا شيء مباشر (تُطلق من Phase 19).
- **Observers:** لا شيء.
- **Middleware:** لا شيء.
- **Console Commands:** `outbox:process` (يشغّل الـ Worker)، `outbox:prune` (سياسة الاستبقاء §62).
- **Seeders / Factories:** `OutboxEventFactory`.
- **Tests:** الصف يُنشأ ضمن نفس transaction الحدث الأصلي فقط (§57)، rollback يلغي الصف، الـ Worker يحوّل pending→sent/failed، backoff، **منع أي إرسال شبكي داخل transaction** (§57).
- **Routes:** لا شيء.
- **Validation:** `event_type`/`aggregate_type` ضمن القيم المعروفة.
- **Indexes:** `(status, created_at)` (§57)، `(aggregate_type, aggregate_id)` (§57).
- **Transactions:** الإدراج فقط داخل transaction الحدث؛ المعالجة خارجها (§57).
- **Locks:** `lockForUpdate`/`SKIP LOCKED` عند سحب الصفوف لمنع معالجة مزدوجة بين workers.
- **Caching:** لا شيء.
- **Queues:** `outbox` (Worker مخصص).

---

### Phase 19 — Notifications & Templates

- **Purpose:** الإرسال الفعلي (بريد/SMS/إشعار) كـ **consumers للـ Outbox** (§57).
- **Prerequisites:** Phase 18.
- **Models:** `Notification`, `EmailTemplate`, `SmsTemplate`.
- **Migrations:** `notifications` (v1.1), `email_templates`, `sms_templates`.
- **Policies:** `NotificationPolicy`, `TemplatePolicy`.
- **Services:** `NotificationService`, `EmailService`, `SmsService` — تُستدعى من `OutboxDispatcher` فقط، لا من داخل أي transaction (§57).
- **Repositories:** لا شيء.
- **Controllers:** `NotificationController`, `EmailTemplateController`, `SmsTemplateController`.
- **Form Requests:** `StoreEmailTemplateRequest`, `StoreSmsTemplateRequest`.
- **API Resources:** `NotificationResource`, `EmailTemplateResource`, `SmsTemplateResource`.
- **Events:** لا شيء (تُستهلك من Outbox).
- **Listeners:** `SendOrderPaidNotification`, `SendRefundProcessedNotification`, `SendCheckInNotification` (مرتبطة بـ `event_type` من outbox).
- **Jobs:** `SendEmailJob`, `SendSmsJob`, `SendDatabaseNotificationJob`.
- **Notifications:** `OrderPaidNotification`, `RefundProcessedNotification`, `TicketCheckedInNotification`, ...
- **Observers:** لا شيء.
- **Middleware:** tenant للإدارة.
- **Console Commands:** لا شيء.
- **Seeders:** `EmailTemplateSeeder`, `SmsTemplateSeeder`.
- **Factories:** factories للثلاثة.
- **Tests:** ربط `event_type` بالقالب الصحيح، فشل الإرسال يُحدّث outbox → failed، القوالب ضمن venue.
- **Routes:** CRUD templates + قراءة notifications.
- **Validation:** متغيّرات القالب صالحة.
- **Indexes:** `(venue_id)`, `notifications(user_id, read_at)`.
- **Transactions:** لا شيء (خارج أي transaction §57).
- **Locks:** لا شيء.
- **Caching:** cache القوالب (إبطال عند التعديل).
- **Queues:** `notifications`.

---

### Phase 20 — Audit Trail

- **Purpose:** تسجيل التغييرات الحساسة إلزاميًا عبر Observers (§59) + `changed_fields`.
- **Prerequisites:** كل الجداول التي تُدقَّق (6, 8, 10, 12, 5).
- **Models:** `ActivityLog`.
- **Migrations:** `activity_logs` (v1.0، `old_values`/`new_values` موجودة) + **`changed_fields` json nullable (§59)**.
- **Policies:** `ActivityLogPolicy` (قراءة للإدارة).
- **Services:** `ActivityLogService` (تُستدعى من Observers).
- **Repositories:** `ActivityLogRepository` (استعلامات `changed_fields`).
- **Controllers:** `ActivityLogController` (قراءة/بحث).
- **Form Requests:** لا شيء.
- **API Resources:** `ActivityLogResource`.
- **Events:** لا شيء.
- **Listeners:** `LogPermissionChange` (من Phase 5، إلزامي §54).
- **Jobs:** `PruneActivityLogs` (سياسة الاستبقاء §62).
- **Observers (إلزامية §59):** `TicketTypeObserver` (`price`), `ProductObserver`/`ProductVariantObserver` (`price`/`price_override`), `VenueObserver` (`commission_rate`), `PlatformSettingObserver` (كل الأعمدة), `TaxRateObserver`, `UserPermissionObserver`/`RolePermissionObserver` (Grant/Revoke), `ApiClientObserver` (`scopes`). كل Observer يملأ `old_values`/`new_values`/`changed_fields` (§59).
- **Middleware:** لا شيء.
- **Console Commands:** `activity-logs:prune`.
- **Seeders / Factories:** `ActivityLogFactory`.
- **Tests:** كل عملية من قائمة §59 تُنتج سجلًا يحوي القيم الفعلية قبل/بعد (لا «تم التعديل» فقط §59)، `changed_fields` صحيح، تسجيل grant/revoke إلزامي (§54).
- **Routes:** `GET /activity-logs`.
- **Validation:** لا شيء.
- **Indexes:** `(venue_id)`, `(entity_type, entity_id)`, فهرس على `changed_fields` (GIN في Postgres، اختياري).
- **Transactions:** السجل يُكتب ضمن نفس transaction التغيير (عبر Observer).
- **Locks:** لا شيء.
- **Caching:** لا شيء.
- **Queues:** `default` للـ prune.

---

### Phase 21 — Media & Documents

- **Purpose:** المرفقات polymorphic (نفس نمط `personal_access_tokens` §55).
- **Prerequisites:** الكيانات التي تملك مرفقات (venues, events, products...).
- **Models:** `Media`, `Document` (polymorphic).
- **Migrations:** `media`, `documents` (`*_type`/`*_id` polymorphic — كما v1.0).
- **Policies:** `MediaPolicy`, `DocumentPolicy`.
- **Services:** `MediaService`, `DocumentService` (upload/store/delete).
- **Repositories:** لا شيء.
- **Controllers:** `MediaController`, `DocumentController`.
- **Form Requests:** `UploadMediaRequest`, `UploadDocumentRequest`.
- **API Resources:** `MediaResource`, `DocumentResource`.
- **Events:** `MediaUploaded`.
- **Jobs:** `ProcessMediaJob` (resize/optimize إن كان ضمن الـ scope المقفل).
- **Observers:** لا شيء إلزامي.
- **Middleware:** tenant + auth.
- **Console Commands:** لا شيء.
- **Seeders / Factories:** `MediaFactory`, `DocumentFactory`.
- **Tests:** رفع/ربط polymorphic، صلاحيات، عزل.
- **Routes:** `POST /media`, `POST /documents`, delete.
- **Validation:** نوع/حجم الملف.
- **Indexes:** `(mediable_type, mediable_id)`.
- **Transactions:** لا شيء خاص.
- **Locks:** لا شيء.
- **Caching:** لا شيء.
- **Queues:** `default` لمعالجة الوسائط.

---

### Phase 22 — API Clients (Third-Party)

- **Purpose:** إدارة عملاء الطرف الثالث ومساراتهم (§53) — scopes + tenant resolution (الميدلوير من Phase 4).
- **Prerequisites:** Phase 4 (middleware), Phase 6 (venues).
- **Models:** `ApiClient`.
- **Migrations:** `api_clients` (v1.2: `venue_id`, `api_key`, `secret` hashed, `scopes`, `active`, `expires_at`, `last_used_at`).
- **Policies:** `ApiClientPolicy` (owner/super admin).
- **Services:** `ApiClientService` (create/rotate key & secret, activate/deactivate).
- **Repositories:** `ApiClientRepository` (lookup by api_key + cache §53).
- **Controllers:** `ApiClientController`.
- **Form Requests:** `StoreApiClientRequest`, `UpdateApiClientScopesRequest`.
- **API Resources:** `ApiClientResource` (لا يكشف الـ secret إلا مرة الإنشاء).
- **Events:** `ApiClientCreated`, `ApiClientScopesUpdated`.
- **Observers:** `ApiClientObserver` (audit على `scopes` §59).
- **Middleware:** يُفعَّل `ResolveApiClientTenantMiddleware` على مجموعة مسارات الطرف الثالث (§53).
- **Console Commands:** لا شيء.
- **Seeders / Factories:** `ApiClientFactory`.
- **Tests:** scopes تُفرض ضد Policies (§53)، secret مُخزَّن hashed، `last_used_at` يُحدَّث، عزل، مسار مستقل تمامًا عن subdomain (§53).
- **Routes:** إدارة (tenant): CRUD؛ ومجموعة الطرف الثالث المحمية بـ `ResolveApiClientTenantMiddleware`.
- **Validation:** scopes ضمن الكتالوج.
- **Indexes:** `api_clients(api_key)` (§53), `(venue_id)`.
- **Transactions:** create + إصدار المفتاح ضمن transaction.
- **Locks:** لا شيء.
- **Caching:** api_key→venue (§53).
- **Queues:** لا شيء.

---

### Phase 23 — Hardening & Production Readiness

- **Purpose:** مراجعة شاملة نهائية دون تغيير معماري.
- **Prerequisites:** كل ما سبق.
- **المهام:** التأكد من كل الفهارس (§50, §51, §57)، اختبارات تكامل شاملة للتدفقات المقفلة (Payment/Reservation/Check-in/Refund/Webhook/Outbox)، سياسات الاستبقاء (`outbox:prune`, `activity-logs:prune`, notifications §62)، ضبط الطوابير في الإنتاج، مراجعة caching، اختبار الحمل على Checkout، مراجعة الأمان للبنود السبعة (§63).
- **Jobs/Commands:** `outbox:prune`, `activity-logs:prune`, retention jobs، health checks.
- **Tests:** E2E لكل تدفق، اختبارات تزامن، اختبارات أمان (webhook spoofing §56، permission escalation §54، tenant isolation §64).
- **Deliverable:** تقرير جاهزية مطابق لدرجة §66.

---

## 3. مخطط الاعتماديات (Dependency Graph)

| Phase | يجب أن يوجد مسبقًا | يعتمد عليه | يمكن تأجيله |
|---|---|---|---|
| 1 Infrastructure | — | الكل | — |
| 2 Traits/Tenancy | 1 | كل الجداول المستأجرة | — |
| 3 Auth | 1,2 | 4,5, كل ما يتطلب مستخدم | التكامل مع Outbox للبريد → 18/19 |
| 4 Tenant Middleware | 2,3 (+6 للاختبار) | كل مسارات tenant، 22 | اختبار api_client الكامل → 22 |
| 5 RBAC | 3,4 | كل Policies | تسجيل audit → 20 |
| 6 Venues | 2,3,4,5 | 7–22 (كل المستأجر) | — |
| 7 Categories/Events | 6 | 8,9,13 | — |
| 8 TicketTypes | 7 | 13 | — |
| 9 Reservations | 7,8 | 13 (إن ربطت) | يمكن مع/بعد 13 |
| 10 Products | 6 | 13 (add-ons) | مستقل نسبيًا |
| 11 Discounts | 6 | 13 | — |
| 12 Financial Settings | 6 | 13,15,16 | — |
| 13 Orders/Checkout | 8,(9),10,11,12 | 14,15,16,17,18 | — |
| 14 Payments/Webhooks | 13 | 16 | — |
| 15 Commissions | 13,14 | 16 | — |
| 16 Refunds/Adjustments | 14,15 | — | — |
| 17 Check-in | 13 | 18 (producer) | مستقل عن 14–16 |
| 18 Outbox | 13,16,17 | 19 | producers تُدرج outbox من 13 لكن المعالجة هنا |
| 19 Notifications | 18 | — | — |
| 20 Audit | 5,6,8,10,12 | — | يُفعّل Observers المشار إليها في مراحل سابقة |
| 21 Media/Documents | 6,7,10 | — | مستقل، يمكن بأي وقت بعد 6 |
| 22 API Clients | 4,6 | تكاملات الطرف الثالث | — |
| 23 Hardening | الكل | الإنتاج | — |

**قواعد الترتيب الحرجة:**
- `outbox_events` تُدرَج (كتابةً) بدءًا من Phase 13، لكن **المعالجة/الإرسال** لا يعملان قبل Phase 18/19 — هذا مقبول لأن الصفوف تتراكم كـ `pending` بأمان.
- تسجيل التدقيق الإلزامي (§54, §59) يُكتب فعليًا عند اكتمال Phase 20؛ ما قبله تُترك خطافات Observer/Listener موصولة وتُفعَّل معه.
- مسار API Client (§53) مستقل تمامًا عن subdomain — لا يُدمجان أبدًا.

---

## 4. هيكل مجلدات Laravel 12 المثالي

```
app/
├── Actions/
│   ├── Orders/
│   │   ├── CreateOrderAction.php
│   │   └── CheckoutAction.php
│   ├── Refunds/ProcessRefundAction.php
│   ├── CheckIn/CheckInTicketAction.php
│   └── Webhooks/ProcessWebhookAction.php
├── Console/
│   └── Commands/
│       ├── OutboxProcessCommand.php
│       ├── OutboxPruneCommand.php
│       ├── ActivityLogsPruneCommand.php
│       ├── WebhooksRetryCommand.php
│       ├── ReservationsReleaseExpiredCommand.php
│       ├── OrdersExpireUnpaidCommand.php
│       └── PermissionsSyncCommand.php
├── Domain/
│   ├── Tenancy/
│   │   ├── TenantContext.php
│   │   ├── TenantResolver.php
│   │   └── ApiClientResolver.php
│   ├── Payments/
│   │   └── Contracts/PaymentGateway.php
│   └── Outbox/
│       ├── OutboxDispatcher.php
│       └── Contracts/OutboxConsumer.php
├── DTOs/
│   ├── Orders/OrderData.php
│   ├── Orders/CheckoutResultData.php
│   ├── Refunds/RefundData.php
│   ├── CheckIn/CheckInResultData.php
│   ├── Payments/PaymentResultData.php
│   ├── Webhooks/WebhookPayloadData.php
│   └── Tenancy/TenantData.php
├── Enums/
│   ├── OutboxStatus.php
│   ├── WebhookStatus.php
│   ├── OrderStatus.php
│   ├── RefundStatus.php
│   ├── TicketStatus.php
│   ├── EventLifecycleStatus.php
│   └── VenueUserRole.php
├── Events/
│   ├── Orders/OrderPaid.php
│   ├── Refunds/RefundProcessed.php
│   ├── CheckIn/TicketCheckedIn.php
│   ├── Payments/PaymentConfirmed.php
│   └── Permissions/PermissionGranted.php
├── Exceptions/
│   ├── StaleModelException.php          (optimistic conflict §58)
│   ├── TenantResolutionException.php
│   ├── WebhookVerificationException.php  (§56)
│   ├── DuplicateWebhookException.php     (§51)
│   ├── InsufficientTicketsException.php
│   ├── RefundExceedsCommissionException.php (§52)
│   └── PermissionEscalationException.php (§54)
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   ├── Tenant/           (venues, events, orders, ...)
│   │   ├── Webhooks/WebhookController.php
│   │   └── ApiClients/       (third-party protected group)
│   ├── Middleware/
│   │   ├── ResolveTenantMiddleware.php
│   │   ├── ResolveApiClientTenantMiddleware.php
│   │   ├── VerifyWebhookSignature.php
│   │   └── CheckPermission.php
│   ├── Requests/
│   │   ├── Auth/
│   │   ├── Orders/
│   │   ├── Refunds/
│   │   └── ...
│   └── Resources/
│       ├── VenueResource.php
│       ├── EventResource.php
│       ├── OrderResource.php
│       └── ...
├── Jobs/
│   ├── ProcessOutboxEvents.php
│   ├── SendEmailJob.php
│   ├── SendSmsJob.php
│   ├── RetryFailedWebhooks.php
│   ├── ReleaseExpiredReservations.php
│   ├── PruneActivityLogs.php
│   └── ProcessMediaJob.php
├── Listeners/
│   ├── ProcessVerifiedWebhook.php
│   ├── LogPermissionChange.php
│   └── Notifications/ (mapped to outbox event_type)
├── Models/
│   ├── User.php
│   ├── Venue.php
│   ├── VenueUser.php
│   ├── Category.php
│   ├── Event.php
│   ├── TicketType.php
│   ├── Zone.php
│   ├── VenueTable.php
│   ├── TableSeat.php
│   ├── Reservation.php
│   ├── Product.php
│   ├── ProductVariant.php
│   ├── Coupon.php
│   ├── PromoCode.php
│   ├── TaxRate.php
│   ├── PlatformSetting.php
│   ├── Order.php
│   ├── Ticket.php
│   ├── TicketSerialCounter.php
│   ├── PaymentTransaction.php
│   ├── WebhookLog.php
│   ├── Commission.php
│   ├── CommissionAdjustment.php
│   ├── Refund.php
│   ├── OutboxEvent.php
│   ├── Notification.php
│   ├── EmailTemplate.php
│   ├── SmsTemplate.php
│   ├── ActivityLog.php
│   ├── Permission.php
│   ├── RolePermission.php
│   ├── UserPermission.php
│   ├── ApiClient.php
│   ├── Media.php
│   └── Document.php
├── Notifications/
│   ├── ResetPasswordNotification.php
│   ├── OrderPaidNotification.php
│   ├── RefundProcessedNotification.php
│   └── TicketCheckedInNotification.php
├── Observers/
│   ├── VenueObserver.php
│   ├── TicketTypeObserver.php
│   ├── ProductObserver.php
│   ├── ProductVariantObserver.php
│   ├── PlatformSettingObserver.php
│   ├── TaxRateObserver.php
│   ├── UserPermissionObserver.php
│   ├── RolePermissionObserver.php
│   └── ApiClientObserver.php
├── Policies/
│   ├── VenuePolicy.php
│   ├── EventPolicy.php
│   ├── OrderPolicy.php
│   ├── RefundPolicy.php
│   ├── UserPermissionPolicy.php   (§54)
│   ├── PlatformSettingPolicy.php
│   └── ... (policy لكل مورد)
├── Repositories/
│   ├── VenueRepository.php
│   ├── OrderRepository.php
│   ├── OutboxRepository.php
│   ├── ApiClientRepository.php
│   └── ActivityLogRepository.php
├── Services/
│   ├── AuthService.php
│   ├── PasswordResetService.php
│   ├── PermissionService.php
│   ├── VenueService.php
│   ├── EventService.php
│   ├── CategoryService.php
│   ├── TicketTypeService.php
│   ├── ReservationService.php
│   ├── SeatingService.php
│   ├── ProductService.php
│   ├── CouponService.php
│   ├── PromoCodeService.php
│   ├── TaxRateService.php
│   ├── PlatformSettingService.php
│   ├── OrderService.php
│   ├── PaymentService.php
│   ├── WebhookService.php
│   ├── CommissionService.php
│   ├── RefundService.php
│   ├── CheckInService.php
│   ├── OutboxService.php
│   ├── NotificationService.php
│   ├── EmailService.php
│   ├── SmsService.php
│   ├── ActivityLogService.php
│   ├── MediaService.php
│   ├── DocumentService.php
│   └── ApiClientService.php
├── Support/
│   ├── Concerns/
│   │   ├── BelongsToVenue.php        (Global Scope §v1.0)
│   │   ├── HasOptimisticLock.php     (§58)
│   │   └── HasSerial.php
│   ├── Money.php
│   └── Signature/WebhookSignatureVerifier.php (§56)
└── Providers/
    ├── AppServiceProvider.php
    ├── AuthServiceProvider.php       (policies + gates)
    ├── EventServiceProvider.php      (observers + listeners)
    └── TenancyServiceProvider.php

database/
├── migrations/
├── factories/
└── seeders/

routes/
├── api.php
├── web.php
├── tenant.php          (ResolveTenantMiddleware)
├── api_clients.php     (ResolveApiClientTenantMiddleware §53)
└── webhooks.php        (VerifyWebhookSignature §56)

tests/
├── Feature/
├── Unit/
└── Integration/        (locked flows end-to-end)
```

---

## 5. خريطة طبقة الخدمات (Service Layer Map)

> لكل خدمة: المسؤوليات / الدوال العامة / الاعتماديات / حدود الـ Transaction / استخدام الطوابير / استخدام الأقفال / DTO المُعاد / الاستثناءات.

### AuthService
- **المسؤوليات:** login/logout، إصدار/إبطال Sanctum tokens.
- **الدوال:** `login(LoginData): TokenData`, `logout(User): void`, `issueToken(User, abilities): TokenData`, `revokeToken(token): void`.
- **الاعتماديات:** `UserRepository`, Sanctum.
- **Transaction:** إصدار/إبطال التوكن.
- **Queues:** `notifications` (لاحقًا). **Locks:** لا. **DTO:** `TokenData`, `UserData`.
- **Exceptions:** `AuthenticationException`.

### PermissionService
- **المسؤوليات:** grant/revoke/sync/checkAbility مع حماية التصعيد (§54).
- **الدوال:** `grant(User target, Permission, actor): void`, `revoke(...)`, `sync(...)`, `can(User, ability): bool`.
- **الاعتماديات:** `UserPermissionPolicy` (§54), `ActivityLogService` (§54).
- **Transaction:** grant/revoke + سجل تدقيق معًا.
- **Queues:** لا. **Locks:** لا. **DTO:** —.
- **Exceptions:** `PermissionEscalationException` (§54), `AuthorizationException`.

### VenueService
- **المسؤوليات:** CRUD المنشأة، ربط الأعضاء، تعديل `commission_rate`/`theme_config` بـ optimistic lock.
- **الدوال:** `create(VenueData): VenueData`, `update(Venue, VenueData, version): VenueData`, `softDelete(Venue)`, `attachUser(Venue, User, role)`.
- **الاعتماديات:** `VenueRepository`.
- **Transaction:** create + attach owner.
- **Locks:** optimistic (`version` §58). **Queues:** لا. **DTO:** `VenueData`.
- **Exceptions:** `StaleModelException` (§58).

### EventService
- **المسؤوليات:** CRUD الفعاليات + انتقالات Event Lifecycle المقفلة، optimistic lock.
- **الدوال:** `create(EventData): EventData`, `update(Event, EventData, version)`, `transition(Event, status)`.
- **الاعتماديات:** `EventRepository`.
- **Transaction:** التعديلات.
- **Locks:** optimistic (§58). **DTO:** `EventData`.
- **Exceptions:** `StaleModelException`, `InvalidLifecycleTransitionException`.

### TicketTypeService
- **المسؤوليات:** CRUD أنواع التذاكر، تعديل السعر/الكمية بـ optimistic lock (§58). لا يعدّل `quantity_sold` (يخص OrderService بـ pessimistic).
- **الدوال:** `create`, `update(TicketType, data, version)`.
- **Locks:** optimistic (§58). **DTO:** `TicketTypeData`.
- **Exceptions:** `StaleModelException`.

### ReservationService
- **المسؤوليات:** hold/confirm/release للمقاعد (Reservation Flow المقفل).
- **الدوال:** `hold(seats): ReservationData`, `confirm(Reservation)`, `release(Reservation)`.
- **Transaction:** hold + قفل المقاعد.
- **Locks:** `lockForUpdate` على المقاعد. **Queues:** `default` (release expired). **DTO:** `ReservationData`.
- **Exceptions:** `SeatUnavailableException`.

### OrderService
- **المسؤوليات:** إنشاء الطلب والـ checkout (Payment Flow core) — **التدفق المقفل حرفيًا**.
- **الدوال:** `createOrder(OrderData): OrderData`, `checkout(CheckoutData): CheckoutResultData`.
- **الاعتماديات:** `TicketTypeService`, `CouponService`, `PromoCodeService`, `TaxRateService`, `CommissionService`, `OutboxService`.
- **Transaction (واحدة شاملة):** خصم `quantity_sold` + serial + order + tickets + commission + صف outbox.
- **Locks:** **pessimistic `lockForUpdate`** على `ticket_types.quantity_sold` و`ticket_serial_counters` (v1.0, §58).
- **Queues:** `outbox`. **DTO:** `OrderData`, `CheckoutResultData`.
- **Exceptions:** `InsufficientTicketsException`, `InvalidCouponException`.

### PaymentService
- **المسؤوليات:** بدء/تأكيد الدفع، تحديث `payment_transactions`.
- **الدوال:** `initiate(Order): PaymentResultData`, `confirm(PaymentTransaction)`.
- **الاعتماديات:** `PaymentGateway` (contract), `OutboxService`.
- **Transaction:** تحديث الحالة + outbox.
- **DTO:** `PaymentResultData`.
- **Exceptions:** `PaymentFailedException`.

### WebhookService
- **المسؤوليات:** التدفق المقفل: Verify → Store → Idempotency → Process → Payment Update (§51, §56).
- **الدوال:** `handle(provider, payload, signature): void`.
- **الاعتماديات:** `WebhookSignatureVerifier` (§56), `PaymentService`, `RefundService`.
- **Transaction:** المعالجة + payment update + outbox.
- **Locks:** يعتمد على `UNIQUE(provider, provider_event_id)` بدل القفل (§51).
- **Queues:** `default` (retry). **DTO:** —.
- **Exceptions:** `WebhookVerificationException` (§56), `DuplicateWebhookException` (§51).

### CommissionService
- **المسؤوليات:** احتساب وتسجيل العمولة (append-only §52). حساب الصافي = amount − SUM(adjustments).
- **الدوال:** `record(Order): void`, `netFor(Commission): Money`.
- **Transaction:** ضمن transaction الطلب (OrderService).
- **DTO:** —. **Exceptions:** —.

### RefundService
- **المسؤوليات:** الاسترجاع + `commission_adjustments` (§52)، فرض السقف على مستوى الخدمة.
- **الدوال:** `process(RefundData): RefundData`.
- **الاعتماديات:** `CommissionService`, `OutboxService`.
- **Transaction:** refund→processed + adjustment + outbox معًا (§52, §57).
- **DTO:** `RefundData`.
- **Exceptions:** `RefundExceedsCommissionException` (§52).

### CheckInService
- **المسؤوليات:** تحقق وتفعيل حضور التذكرة (Check-in Flow المقفل).
- **الدوال:** `checkIn(serial): CheckInResultData`.
- **الاعتماديات:** `OutboxService`.
- **Transaction:** mark + outbox.
- **Locks:** `lockForUpdate` على التذكرة. **DTO:** `CheckInResultData`.
- **Exceptions:** `TicketAlreadyCheckedInException`, `InvalidTicketException`.

### OutboxService / OutboxDispatcher
- **المسؤوليات:** `record()` (داخل transaction فقط §57)؛ `dispatch()` (Worker خارج transaction §57).
- **الدوال:** `record(eventType, aggregate, payload): void`, `dispatchPending(): void`.
- **الاعتماديات:** consumers (`NotificationService`, `EmailService`, `SmsService`).
- **Transaction:** الإدراج فقط داخل transaction الحدث؛ المعالجة خارجها.
- **Locks:** `lockForUpdate`/`SKIP LOCKED` عند السحب. **Queues:** `outbox`.
- **Exceptions:** consumer exceptions → `attempts++` + `failed`.

### NotificationService / EmailService / SmsService
- **المسؤوليات:** الإرسال الفعلي كـ consumers للـ Outbox (§57) — **لا تُستدعى داخل أي transaction**.
- **Transaction:** لا. **Queues:** `notifications`. **DTO:** —.
- **Exceptions:** `NotificationDeliveryException` (تُعيد `failed` للـ outbox).

### ActivityLogService
- **المسؤوليات:** كتابة سجلات التدقيق الإلزامية (§59) — تُستدعى من Observers.
- **الدوال:** `record(actor, entity, oldValues, newValues, changedFields, ip): void`.
- **Transaction:** ضمن transaction التغيير. **DTO:** —.

### PlatformSettingService / TaxRateService
- **المسؤوليات:** تعديل الإعدادات المالية بـ optimistic lock (§58) + audit إلزامي (§59).
- **Locks:** optimistic. **Exceptions:** `StaleModelException`.

### ApiClientService
- **المسؤوليات:** إدارة عملاء الطرف الثالث + مفاتيح/scopes (§53).
- **الدوال:** `create(VenueData): ApiClientData` (يعيد secret مرة واحدة), `rotate(...)`, `updateScopes(...)`.
- **DTO:** `ApiClientData`. **Exceptions:** —.

---

## 6. تقدير التعقيد (Implementation Complexity)

| Module (Phase) | التعقيد | عدد الملفات ~ | Migrations ~ | جهد الاختبار | المخاطر الرئيسية |
|---|---|---|---|---|---|
| 1 Infrastructure | منخفض | 8–12 | 0–3 | منخفض | إعداد خاطئ للطوابير/الكاش |
| 2 Traits/Tenancy | متوسط | 6–8 | 0 | متوسط | ثغرة في Global Scope → تسريب عبر المستأجرين |
| 3 Auth | متوسط | 14–18 | 4 | متوسط | polymorphic tokens، انتهاء الصلاحية |
| 4 Tenant Middleware | مرتفع | 6–8 | 0 | مرتفع | خلط المسارين (§53)، أخطاء 401 |
| 5 RBAC | مرتفع | 16–20 | 3 | مرتفع | **تصعيد الصلاحيات §54** |
| 6 Venues | متوسط | 14–18 | 2 | متوسط | unique مع soft delete §50، optimistic §58 |
| 7 Categories/Events | متوسط | 14–18 | 2 | متوسط | slug uniqueness §50، lifecycle |
| 8 TicketTypes | متوسط | 8–10 | 1 | مرتفع | التمييز optimistic/pessimistic §58 |
| 9 Reservations | مرتفع | 18–22 | 4 | مرتفع | double-booking، قفل المقاعد |
| 10 Products | منخفض | 10–12 | 2 | منخفض | audit السعر §59 |
| 11 Discounts | متوسط | 10–12 | 2 | متوسط | code reuse بعد soft delete §50 |
| 12 Financial Settings | متوسط | 10–14 | 2 | متوسط | optimistic §58، audit §59 |
| 13 Orders/Checkout | **مرتفع جدًا** | 20–26 | 3 | **مرتفع جدًا** | overselling، serial، atomicity §57 |
| 14 Payments/Webhooks | **مرتفع جدًا** | 16–20 | 2 | **مرتفع جدًا** | idempotency §51، signature §56، race |
| 15 Commissions | متوسط | 6–8 | 1 | متوسط | صحة الاحتساب، ثبات append-only §52 |
| 16 Refunds/Adjustments | مرتفع | 12–14 | 2 | مرتفع | سقف الاسترجاع §52، rate_snapshot |
| 17 Check-in | متوسط | 8–10 | 0 | متوسط | double check-in، القفل |
| 18 Outbox | مرتفع | 10–12 | 1 | مرتفع | **إرسال داخل transaction §57**، معالجة مزدوجة |
| 19 Notifications | متوسط | 16–20 | 3 | متوسط | ربط event_type بالقالب |
| 20 Audit | متوسط | 14–18 | 1 | مرتفع | إفلات عملية من التسجيل §54/§59 |
| 21 Media/Documents | منخفض | 10–12 | 2 | منخفض | حجم/نوع الملفات |
| 22 API Clients | متوسط | 10–12 | 1 | مرتفع | scopes، عزل المسار §53 |
| 23 Hardening | متوسط | (مراجعة) | 0 | **مرتفع جدًا** | ثغرات تكامل بين التدفقات |

**إجمالي تقريبي:** ~35 model، ~30–34 migration، ~28 service، ~9 observers، ~4 middleware مخصص، مئات ملفات (controllers/requests/resources/tests). التركيز الأعلى للاختبار على Phases 13, 14, 18, 23.

---

## 7. تسلسل التطوير الدقيق (Development Sequence)

نفّذ بالترتيب التالي (§1.1). لا تبدأ خطوة قبل مرور اختبارات السابقة:

1. ✅ Infrastructure (Phase 1)
2. ✅ Core Traits & Tenancy Foundation (Phase 2) — includes tenant middleware §53
3. ✅ Authentication (Phase 3)
4. ✅ Phase 4.1 — RBAC Foundation (Permission models + PermissionService + Gates + tests)
5. ✅ Phase 4.2 — Event Domain models + policies
6. ✅ Phase 4.3 — Commerce Domain
7. Phase 4.4a — Orders & Tickets (Order, Ticket, TicketSerialCounter)
8. Phase 4.4b — Payments & Commissions (PaymentTransaction, Refund, Commission, CommissionAdjustment)
9. ✅ Phase 4.5 — Infrastructure models + Architecture Review
10. Domain Services (Phase 5)
11. APIs (Phase 6) — Controllers/Requests/Resources للأعمال (Events, Orders, Reservations, …)
12. Payments (Phase 7) — Gateway, Webhooks, Refunds, Commissions
13. Notifications (Phase 8) — Email/SMS/Templates, Outbox Worker, Audit activation
14. Production Hardening (Phase 9)

**ملاحظات تنفيذية إلزامية:**
- **لا تنتقل إلى Phase 5 (Services) أو Phase 6 (APIs) قبل اكتمال Phase 4** — Policies تعتمد على العلاقات. ✅ Phase 4 مكتمل.
- **Phase 5:** التزم بـ §5.1–§5.9 (`TransactionRunner`, Aggregate Boundaries, Service Ownership + Cannot Modify, Outbox triple-write, ActivityLog/Outbox ownership, PlatformSetting, Service Architecture Guard). ✅
- **Phase 6:** التزم بـ §6.1–§6.12 (Thin Controllers, OpenAPI projections, Architecture Guards). ✅
- **Phase 7:** التزم بـ §7.0–§7.9 (Gateway layer **لا DB**؛ domain state عبر PaymentService/RefundService فقط؛ GatewayArchitectureGuardTest). **تحديث:** نموذج الدفع الفعلي (initiation) أصبح Manual Wallet Transfer + Verification (§7.9) — Webhooks/hosted-checkout (§7.2–§7.5) أصبحت مرجعًا تاريخيًا لهذا المسار المحدد.
- من Phase 5 فصاعدًا: كل domain event حرج يُسجَّل في `outbox_events` داخل نفس الـ transaction (§57).
- لا تدمج مسار subdomain مع api_client (§53).
- لا `version` على `orders`/`tickets`/`payment_transactions` (§58).

<details>
<summary>التسلسل القديم (23 phase) — مرجع</summary>

4. Tenant Resolution Middleware → ✅ Phase 2
5. Authorization / RBAC → Phase 4
6–12, 21. Domain entities → Phase 4 (Models) / 5 (Services) / 6 (APIs)
13–17. Orders/Checkout/Check-in → Phase 5–6
14–16. Payments → Phase 7
18–20. Outbox/Notifications/Audit → Phase 5 hooks + Phase 8
23. Hardening → Phase 9

</details>

---

## 8. Master Checklist (Deliverables)

```
البنية والأساس
☑ Phase 1  — Infrastructure مكتمل
☑ Phase 2  — Core Traits & Tenancy Foundation مكتمل (BelongsToVenue + TenantContext + TenantMiddleware §53)
☑ Phase 3  — Authentication (Sanctum + sessions + password reset) مكتمل

Domain & Authorization (§1.1)
☑ Phase 4.1 — RBAC Foundation (Permission, RolePermission, UserPermission + PermissionService + Gates)
☑ Phase 4.2 — Event Domain models + policies
☑ Phase 4.3 — Commerce Domain
☑ Phase 4.4a — Orders & Tickets (Order, Ticket, TicketSerialCounter)
☑ Phase 4.4b — Payments & Commissions (PaymentTransaction, Refund, Commission, CommissionAdjustment)
☑ Phase 4.5 — Infrastructure models + Architecture Review (قبل Phase 5)
☑ Phase 5  — Domain Services (§5.1–§5.9 — Batches 5.1→5.6)
☑ Phase 6  — APIs (Business Controllers) — §6.1–§6.12 ✅
  ☑ Phase 6.1 — API Infrastructure
  ☑ Phase 6.2 — Authentication APIs
  ☑ Phase 6.3 — Event APIs (§6.11)
  ☑ Phase 6.4 — Commerce APIs
  ☑ Phase 6.5 — Order APIs
  ☑ Phase 6.6 — Payment APIs
  ☑ Phase 6.7 — Platform APIs
  ☑ Phase 6.8 — OpenAPI/Swagger + Architecture Guards
☑ Phase 7  — Payments (§7.0–§7.9)
  ☑ Phase 7.1 — Gateway Abstractions (Interfaces + DTOs + Registry)          [legacy — superseded by §7.9]
  ☑ Phase 7.2 — Gateway Implementations (ShamCash, Syriatel Cash)           [legacy — superseded by §7.9]
  ☑ Phase 7.3 — Webhook Infrastructure (Signature + Replay Protection)      [legacy — removed in 7.9]
  ☑ Phase 7.4 — PaymentGatewayService Orchestration                        [legacy — superseded by §7.9]
  ☑ Phase 7.5 — E2E Integration + GatewayArchitectureGuardTest              [legacy — superseded by §7.9]
  ☑ Phase 7.6 — Gateway Contract Redesign (verifyTransaction) + DTOs + PaymentTransactionStatus + PaymentInstructionService/PaymentVerificationService + Architecture Guards + global transaction_number uniqueness (§7.9) ✅
  ☑ Phase 7.7 — API Wiring: PaymentController::store()/verify() + Routes + Requests/Resources/OpenAPI + Deprecate complete/fail (§7.9)
  ☑ Phase 7.8 — Manual Transfer E2E Integration (§7.9)
  ☑ Phase 7.9 — Cleanup: webhook infra + hosted-checkout legacy + complete/fail endpoints removed (§7.9)
  ☑ Phase 7.10 — API Syria live HTTP integration + apisyria:probe command
  ☑ Phase 7.11 — Multi-tenant payment_accounts + PaymentAccountResolver
☑ Phase 8  — Ticket Lifecycle & Financial Layer (8.1–8.5.4)
  ☑ Phase 8.3 — QR / PDF / Email artifacts
  ☑ Phase 8.4 — Check-in
  ☑ Phase 8.5.1 — Commission receivable ledger
  ☑ Phase 8.5.3 — Manual commission payments
  ☑ Phase 8.5.4 — Settlement read APIs
  ✅ Phase 8.5.5 — Reports (closed)
  ☐ Phase 8.6 — Organizer Dashboard API
  ☐ Phase 8.7 — Admin Dashboard API
  ☐ Backend Freeze Checklist + ADR-0001 + tag v1.0-backend-freeze
☐ Phase 9  — Frontend v1 (Organizer / Admin / Public / Scanner PWA)
☐ Phase 10 — Production Hardening + Pilot Event

<!-- مرجع granular (§1.3 / §2) — للتفاصيل per-entity -->
<!--
☐ Phase 5 (قديم) RBAC → Phase 4
☐ Phase 6–12, 21 (قديم) → Phase 4–6
☐ Phase 13–17 (قديم) → Phase 5–6
☐ Phase 14–16 (قديم) → Phase 7
☐ Phase 18–20 (قديم) → Phase 5 + 8
☐ Phase 23 (قديم) → Phase 9
-->

التحقق قبل الإصدار (Pre-Release)
☐ MySQL 8 Validation — `migrate:fresh --seed` على MySQL 8
☐ MySQL 8 Validation — `php artisan test` على MySQL 8
☐ MySQL 8 Validation — `schema:dump --database=mysql`
☐ (محليًا بدون Docker) `.\scripts\validate-mysql-migrations.ps1` عند توفر MySQL

التحقق النهائي من البنود الحرجة السبعة (§63)
☐ Soft Delete Unique Constraints (§50) مُتحقَّق
☐ Webhook Idempotency (§51) مُتحقَّق
☐ Commission Reversal / Adjustments (§52) مُتحقَّق
☐ API Client Tenant Resolution (§53) مُتحقَّق
☐ Permission Escalation Protection (§54) مُتحقَّق
☐ Authentication Infrastructure (§55) مُتحقَّق
☐ Webhook Signature Verification (§56) مُتحقَّق
☐ Outbox Atomicity (§57) مُتحقَّق
☐ Optimistic Locking على الجداول المحددة (§58) مُتحقَّق
☐ Audit Trail الإلزامي (§59) مُتحقَّق

☐ كل اختبارات التكامل للتدفقات المقفلة (Payment/Reservation/Check-in/Refund/Webhook/Outbox) خضراء
☐ سياسات الاستبقاء (outbox / activity_logs / notifications) مفعّلة
☐ عزل المستأجر مُختبَر عبر كل الجداول
☐ Production Ready
```

---

**ملاحظة ختامية:** كل بند أعلاه مشتق حرفيًا من المعمارية المقفلة v1.0–v1.3. لم يُضَف أي جدول أو علاقة أو Enum أو ميزة، ولم يُعَد تصميم أي تدفق. هذا المستند خطة تنفيذ فقط.
