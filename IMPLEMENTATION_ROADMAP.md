# خارطة طريق التنفيذ — منصة تذاكر الفعاليات متعددة المستأجرين (Laravel 12)

> **الحالة:** المعمارية v1.3 مقفلة ونهائية. هذا المستند **لا يعيد التصميم ولا يقترح تحسينات ولا يضيف ميزات**. مهمته الوحيدة: تحويل المعمارية المقفلة (v1.0 → v1.3) إلى خطة تنفيذ Phase-by-Phase قابلة للتنفيذ آليًا دون أي قرار معماري جديد.
>
> **المرجع المعماري الوحيد:** `blueprint_v1_3.md` (وما يشير إليه من v1.0–v1.2). كل اسم جدول/عمود/Enum/علاقة/تدفق مذكور هنا مأخوذ حرفيًا من المعمارية المقفلة.

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
| 7 | **Payments** | Gateway, Webhooks, Refunds, Commission Adjustments | ⏳ 7.1–7.5 |
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

##### §7.0 — ترتيب تنفيذ Phase 7 (_batches)

| Batch | المحتوى | يعتمد على | الحالة |
|---|---|---|---|
| **7.1** | Payment Gateway Abstractions (Interfaces + DTOs + Registry) | — | ✅ |
| **7.2** | Gateway Implementations (ShamCash, Syriatel Cash, …) | 7.1 | ✅ |
| **7.3** | Webhook Infrastructure (Signature Verification + Replay Protection) | 7.2 | — |
| **7.4** | Gateway Orchestration (`PaymentGatewayService`) | 7.3 | — |
| **7.5** | End-to-End Integration + `GatewayArchitectureGuardTest` | 7.1–7.4 | — |

كل batch ينتهي باختبارات خضراء قبل التالي.

---

##### §7.1 — Service Ownership (جدول مسؤوليات)

| Service / Component | مسؤولية | **لا** يفعل |
|---|---|---|
| `PaymentService` | حالة الدفع **داخل النظام** (`PaymentTransaction`, `Order.status` SSOT) | استدعاء HTTP للـ Gateway، التحقق من التوقيع |
| `RefundService` | حالة الاسترداد **داخل النظام** + `commission_adjustments` (§52) | استدعاء HTTP للـ Gateway |
| `PaymentGatewayService` | التواصل مع مزود الدفع (initiate, capture, refund request) | `Model::save()`, `DB::`, ActivityLog, Outbox |
| `WebhookService` | استقبال webhook، تنسيق pipeline، تفويض للـ GatewayService | معرفة HMAC/RSA (يُفوَّض لـ Verifier) |
| `GatewaySignatureVerifier` *(interface)* | التحقق من التوقيع **فقط** | أي DB أو domain logic |
| `ReplayProtectionService` | منع إعادة معالجة webhook (idempotency §51) | Cache-only؛ يستخدم تخزينًا دائمًا |

**يمنع** تضخم `PaymentService` بمنطق Gateway/Webhook.

---

##### §7.2 — Webhook Pipeline (تدفق إلزامي)

```
HTTP Webhook Request
        ↓
GatewaySignatureVerifier.verify()     ← per-provider impl (HMAC/RSA/…)
        ↓
ReplayProtectionService.assertNotProcessed(provider, event_id)
        ↓
WebhookLog (received → verified)      ← webhook_logs UNIQUE(provider, provider_event_id) §51
        ↓
WebhookPayloadData (DTO)
        ↓
PaymentGatewayService.handleWebhook()
        ↓
PaymentService / RefundService          ← الوحيدان اللذان يغيّران domain state
        ↓
ActivityLogService + OutboxService    ← داخل TransactionRunner (Phase 5)
        ↓
WebhookLog (processed)
```

**قاعدة Phase 7:** **Gateway لا يغيّر Database مباشرة** — لا `Model::query()`, لا `save()`, لا `DB::transaction()` في طبقة Gateway/Webhook.

**Routes:** `routes/webhooks.php` — **لا** tenant subdomain؛ ربط `venue_id` عند معالجة payment (§51).

---

##### §7.3 — Idempotency Keys (مقفلة)

| Operation | Idempotency Key | Rule |
|---|---|---|
| `initiatePayment()` | `(order_id, provider)` | **عملية pending واحدة فعالة** لكل (order, provider) — لا orphaned TXs متعددة |
| Webhook | `(provider, provider_event_id)` | يُعالج **مرة واحدة فقط** — `webhook_logs` UNIQUE §51 |
| Refund (gateway) | `(provider_refund_id)` | مرة واحدة فقط لكل refund خارجي |
| Commission adjustment | `refund_id` UNIQUE | **بدون تغيير** — append-only §52 |

**Webhook replay:** لا Cache-only. استخدم `webhook_logs` (موجود: `provider`, `provider_event_id`, `status`, `created_at`).  
`created_at` = `received_at`؛ أضف `processed_at` في batch 7.3 إذا لزم تمييز صريح.

---

##### §7.4 — Signature Verification

```php
interface GatewaySignatureVerifier
{
    public function verify(string $rawPayload, ?string $signatureHeader, array $config): void;
}
```

- تنفيذ **لكل Gateway** (ShamCash, Syriatel Cash, …).
- `WebhookService` **لا** يعرف خوارزمية HMAC/RSA — يستدعي Verifier من Registry فقط.
- فشل التحقق → `WebhookVerificationException` (§56) — **لا** معالجة domain.

---

##### §7.5 — Gateway Abstractions (7.1 — هيكل مقترح)

```
app/
├── Contracts/Payments/
│   ├── PaymentGatewayInterface.php      # initiate, refund, parseWebhook
│   └── GatewaySignatureVerifier.php
├── Services/Payments/Gateway/
│   ├── PaymentGatewayRegistry.php       # provider → gateway + verifier
│   ├── PaymentGatewayService.php        # orchestration (7.4)
│   ├── ShamCash/
│   │   ├── ShamCashGateway.php
│   │   └── ShamCashSignatureVerifier.php
│   └── SyriatelCash/
│       ├── SyriatelCashGateway.php
│       └── SyriatelCashSignatureVerifier.php
├── Services/Webhooks/
│   ├── WebhookService.php
│   └── ReplayProtectionService.php
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
| تغييرات `Order.status` / `PaymentTransaction.status` تمر عبر `PaymentService` أو `RefundService` فقط | ✅ |
| `PaymentGatewayService` لا يستدعي `Model::save()` مباشرة | ✅ |

**يكمل** `ServiceArchitectureGuardTest` + `ControllerArchitectureGuardTest` (Phase 5.6 + 6.8).

---

##### §7.7 — Exit Criteria (Phase 7 كاملة)

| # | Criterion |
|---|---|
| 1 | ShamCash + Syriatel Cash (أو mock gateways) مع Registry |
| 2 | Webhook endpoint + signature verification + replay protection (DB-backed) |
| 3 | E2E: webhook → PaymentService → Order paid + Outbox event |
| 4 | Refund gateway flow → RefundService → commission_adjustment |
| 5 | `GatewayArchitectureGuardTest` خضراء |
| 6 | Idempotency keys §7.3 مُختبرة (duplicate webhook, duplicate refund) |

---

**محتوى Phase 7 (ملخص):**
- Payment Gateway integration, `payment_transactions` (عبر PaymentService — SSOT).
- Webhooks (signature §56, idempotency §51, replay §7.3).
- Refunds + `commission_adjustments` (§52) — عبر RefundService.
- Commissions (append-only) — بدون تغيير Phase 5 rules.

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
- **Phase 7:** التزم بـ §7.0–§7.7 (Gateway/Webhook layer **لا DB**؛ domain state عبر PaymentService/RefundService فقط؛ Idempotency §7.3؛ GatewayArchitectureGuardTest).
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
☐ Phase 7  — Payment Gateway & Webhooks (§7.0–§7.7)
  ☑ Phase 7.1 — Gateway Abstractions (Interfaces + DTOs + Registry)
  ☑ Phase 7.2 — Gateway Implementations (ShamCash, Syriatel Cash)
  ☐ Phase 7.3 — Webhook Infrastructure (Signature + Replay Protection)
  ☐ Phase 7.4 — PaymentGatewayService Orchestration
  ☐ Phase 7.5 — E2E Integration + GatewayArchitectureGuardTest
☐ Phase 8  — Notifications (Email/SMS/Templates, Outbox Worker, Audit)
☐ Phase 9  — Production Hardening

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
