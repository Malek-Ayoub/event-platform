CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "phone" varchar,
  "is_super_admin" tinyint(1) not null default '0'
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  foreign key("user_id") references "users"("id") on delete set null,
  primary key("id")
);
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_expiration_index" on "cache"("expiration");
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_locks_expiration_index" on "cache_locks"("expiration");
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" varchar not null,
  "queue" varchar not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE INDEX "failed_jobs_connection_queue_failed_at_index" on "failed_jobs"(
  "connection",
  "queue",
  "failed_at"
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "personal_access_tokens"(
  "id" integer primary key autoincrement not null,
  "tokenable_type" varchar not null,
  "tokenable_id" integer not null,
  "name" text not null,
  "token" varchar not null,
  "abilities" text,
  "last_used_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "personal_access_tokens_tokenable_type_tokenable_id_index" on "personal_access_tokens"(
  "tokenable_type",
  "tokenable_id"
);
CREATE UNIQUE INDEX "personal_access_tokens_token_unique" on "personal_access_tokens"(
  "token"
);
CREATE INDEX "personal_access_tokens_expires_at_index" on "personal_access_tokens"(
  "expires_at"
);
CREATE TABLE IF NOT EXISTS "venues"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "subdomain" varchar not null,
  "owner_user_id" integer,
  "theme_config" text,
  "shamcash_account_id" varchar,
  "commission_rate" numeric not null default '1',
  "status" varchar check("status" in('active', 'suspended', 'pending')) not null default 'pending',
  "version" integer not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("owner_user_id") references "users"("id") on delete set null
);
CREATE INDEX "venues_status_index" on "venues"("status");
CREATE UNIQUE INDEX venues_subdomain_unique ON venues(
  subdomain
) WHERE deleted_at IS NULL;
CREATE UNIQUE INDEX venues_slug_unique ON venues(
  slug
) WHERE deleted_at IS NULL;
CREATE TABLE IF NOT EXISTS "venue_user"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "user_id" integer not null,
  "role" varchar check("role" in('owner', 'staff')) not null,
  "permissions" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "venue_user_venue_id_user_id_unique" on "venue_user"(
  "venue_id",
  "user_id"
);
CREATE INDEX "venue_user_role_index" on "venue_user"("role");
CREATE TABLE IF NOT EXISTS "categories"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade
);
CREATE UNIQUE INDEX "categories_venue_id_slug_unique" on "categories"(
  "venue_id",
  "slug"
);
CREATE INDEX "categories_venue_id_is_active_index" on "categories"(
  "venue_id",
  "is_active"
);
CREATE TABLE IF NOT EXISTS "events"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "category_id" integer,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "banner_url" varchar,
  "gallery" text,
  "video_url" varchar,
  "dj_info" text,
  "start_datetime" datetime not null,
  "end_datetime" datetime not null,
  "status" varchar check("status" in('draft', 'published', 'cancelled', 'completed')) not null default 'draft',
  "version" integer not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade,
  foreign key("category_id") references "categories"("id") on delete set null
);
CREATE INDEX "events_venue_id_status_index" on "events"("venue_id", "status");
CREATE INDEX "events_venue_id_start_datetime_index" on "events"(
  "venue_id",
  "start_datetime"
);
CREATE UNIQUE INDEX events_venue_slug_unique ON events(
  venue_id,
  slug
) WHERE deleted_at IS NULL;
CREATE TABLE IF NOT EXISTS "ticket_types"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "event_id" integer not null,
  "name" varchar not null,
  "price" numeric not null,
  "quantity" integer not null,
  "quantity_sold" integer not null default '0',
  "sale_start" datetime,
  "sale_end" datetime,
  "benefits" text,
  "color" varchar,
  "version" integer not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade,
  foreign key("event_id") references "events"("id") on delete cascade
);
CREATE INDEX "ticket_types_event_id_sale_start_sale_end_index" on "ticket_types"(
  "event_id",
  "sale_start",
  "sale_end"
);
CREATE INDEX "ticket_types_venue_id_index" on "ticket_types"("venue_id");
CREATE TABLE IF NOT EXISTS "zones"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "event_id" integer not null,
  "name" varchar not null,
  "description" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade,
  foreign key("event_id") references "events"("id") on delete cascade
);
CREATE INDEX "zones_venue_id_event_id_index" on "zones"(
  "venue_id",
  "event_id"
);
CREATE TABLE IF NOT EXISTS "venue_tables"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "event_id" integer not null,
  "zone_id" integer not null,
  "table_number" varchar not null,
  "capacity" integer not null,
  "min_spend" numeric,
  "status" varchar check("status" in('available', 'reserved', 'unavailable')) not null default 'available',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade,
  foreign key("event_id") references "events"("id") on delete cascade,
  foreign key("zone_id") references "zones"("id") on delete cascade
);
CREATE UNIQUE INDEX "venue_tables_zone_id_table_number_unique" on "venue_tables"(
  "zone_id",
  "table_number"
);
CREATE INDEX "venue_tables_venue_id_event_id_status_index" on "venue_tables"(
  "venue_id",
  "event_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "table_seats"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "venue_table_id" integer not null,
  "seat_number" varchar not null,
  "status" varchar check("status" in('available', 'reserved', 'unavailable')) not null default 'available',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade,
  foreign key("venue_table_id") references "venue_tables"("id") on delete cascade
);
CREATE UNIQUE INDEX "table_seats_venue_table_id_seat_number_unique" on "table_seats"(
  "venue_table_id",
  "seat_number"
);
CREATE INDEX "table_seats_venue_id_status_index" on "table_seats"(
  "venue_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "products"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "event_id" integer,
  "name" varchar not null,
  "description" text,
  "price" numeric not null,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade,
  foreign key("event_id") references "events"("id") on delete set null
);
CREATE INDEX "products_venue_id_is_active_index" on "products"(
  "venue_id",
  "is_active"
);
CREATE TABLE IF NOT EXISTS "product_variants"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "product_id" integer not null,
  "name" varchar not null,
  "sku" varchar,
  "price_override" numeric,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade,
  foreign key("product_id") references "products"("id") on delete cascade
);
CREATE INDEX "product_variants_product_id_is_active_index" on "product_variants"(
  "product_id",
  "is_active"
);
CREATE INDEX "product_variants_venue_id_index" on "product_variants"(
  "venue_id"
);
CREATE TABLE IF NOT EXISTS "coupons"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "code" varchar not null,
  "discount_type" varchar check("discount_type" in('percent', 'fixed')) not null default 'percent',
  "discount_value" numeric not null,
  "min_order_amount" numeric,
  "max_uses" integer,
  "used_count" integer not null default '0',
  "starts_at" datetime,
  "expires_at" datetime,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade
);
CREATE INDEX "coupons_venue_id_is_active_index" on "coupons"(
  "venue_id",
  "is_active"
);
CREATE UNIQUE INDEX coupons_venue_code_unique ON coupons(
  venue_id,
  code
) WHERE deleted_at IS NULL;
CREATE TABLE IF NOT EXISTS "promo_codes"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "code" varchar not null,
  "discount_type" varchar check("discount_type" in('percent', 'fixed')) not null default 'percent',
  "discount_value" numeric not null,
  "min_order_amount" numeric,
  "max_uses" integer,
  "used_count" integer not null default '0',
  "starts_at" datetime,
  "expires_at" datetime,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade
);
CREATE INDEX "promo_codes_venue_id_is_active_index" on "promo_codes"(
  "venue_id",
  "is_active"
);
CREATE UNIQUE INDEX promo_codes_venue_code_unique ON promo_codes(
  venue_id,
  code
) WHERE deleted_at IS NULL;
CREATE TABLE IF NOT EXISTS "tax_rates"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "name" varchar not null,
  "rate" numeric not null,
  "is_active" tinyint(1) not null default '1',
  "version" integer not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade
);
CREATE INDEX "tax_rates_venue_id_is_active_index" on "tax_rates"(
  "venue_id",
  "is_active"
);
CREATE TABLE IF NOT EXISTS "platform_settings"(
  "id" integer primary key autoincrement not null,
  "commission_rate" numeric not null default '1',
  "settings" text,
  "version" integer not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE IF NOT EXISTS "permissions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "permissions_slug_unique" on "permissions"("slug");
CREATE TABLE IF NOT EXISTS "role_permissions"(
  "id" integer primary key autoincrement not null,
  "role" varchar not null,
  "permission_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("permission_id") references "permissions"("id") on delete cascade
);
CREATE UNIQUE INDEX "role_permissions_role_permission_id_unique" on "role_permissions"(
  "role",
  "permission_id"
);
CREATE INDEX "role_permissions_role_index" on "role_permissions"("role");
CREATE TABLE IF NOT EXISTS "user_permissions"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "user_id" integer not null,
  "permission_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("permission_id") references "permissions"("id") on delete cascade
);
CREATE UNIQUE INDEX "user_permissions_venue_id_user_id_permission_id_unique" on "user_permissions"(
  "venue_id",
  "user_id",
  "permission_id"
);
CREATE INDEX "user_permissions_venue_id_user_id_index" on "user_permissions"(
  "venue_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "api_clients"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "name" varchar,
  "api_key" varchar not null,
  "secret" varchar not null,
  "scopes" text,
  "active" tinyint(1) not null default '1',
  "expires_at" datetime,
  "last_used_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade
);
CREATE INDEX "api_clients_venue_id_active_index" on "api_clients"(
  "venue_id",
  "active"
);
CREATE UNIQUE INDEX "api_clients_api_key_unique" on "api_clients"("api_key");
CREATE TABLE IF NOT EXISTS "orders"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "event_id" integer not null,
  "customer_user_id" integer,
  "order_number" varchar not null,
  "subtotal" numeric not null,
  "tax_amount" numeric not null default '0',
  "discount_amount" numeric not null default '0',
  "total" numeric not null,
  "commission_amount" numeric not null default '0',
  "coupon_id" integer,
  "promo_code_id" integer,
  "payment_method" varchar,
  "payment_reference" varchar,
  "status" varchar check("status" in('pending', 'paid', 'failed', 'refunded', 'cancelled')) not null default 'pending',
  "customer_name" varchar not null,
  "customer_email" varchar not null,
  "customer_phone" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete restrict,
  foreign key("event_id") references "events"("id") on delete restrict,
  foreign key("customer_user_id") references "users"("id") on delete set null,
  foreign key("coupon_id") references "coupons"("id") on delete set null,
  foreign key("promo_code_id") references "promo_codes"("id") on delete set null
);
CREATE INDEX "orders_venue_id_status_index" on "orders"("venue_id", "status");
CREATE INDEX "orders_event_id_status_index" on "orders"("event_id", "status");
CREATE INDEX "orders_customer_user_id_index" on "orders"("customer_user_id");
CREATE UNIQUE INDEX "orders_order_number_unique" on "orders"("order_number");
CREATE TABLE IF NOT EXISTS "ticket_serial_counters"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "event_id" integer not null,
  "last_serial" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade,
  foreign key("event_id") references "events"("id") on delete cascade
);
CREATE UNIQUE INDEX "ticket_serial_counters_venue_id_event_id_unique" on "ticket_serial_counters"(
  "venue_id",
  "event_id"
);
CREATE TABLE IF NOT EXISTS "tickets"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "event_id" integer not null,
  "order_id" integer not null,
  "ticket_type_id" integer not null,
  "serial" varchar not null,
  "qr_code_path" varchar,
  "status" varchar check("status" in('valid', 'used', 'cancelled', 'refunded')) not null default 'valid',
  "checked_in_at" datetime,
  "checked_in_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete restrict,
  foreign key("event_id") references "events"("id") on delete restrict,
  foreign key("order_id") references "orders"("id") on delete restrict,
  foreign key("ticket_type_id") references "ticket_types"("id") on delete restrict,
  foreign key("checked_in_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "tickets_event_id_serial_unique" on "tickets"(
  "event_id",
  "serial"
);
CREATE INDEX "tickets_venue_id_status_index" on "tickets"(
  "venue_id",
  "status"
);
CREATE INDEX "tickets_order_id_status_index" on "tickets"(
  "order_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "reservations"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "table_seat_id" integer not null,
  "order_id" integer,
  "customer_name" varchar not null,
  "customer_phone" varchar not null,
  "status" varchar check("status" in('hold', 'confirmed', 'cancelled')) not null default 'hold',
  "held_until" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade,
  foreign key("table_seat_id") references "table_seats"("id") on delete restrict,
  foreign key("order_id") references "orders"("id") on delete set null
);
CREATE INDEX "reservations_venue_id_status_index" on "reservations"(
  "venue_id",
  "status"
);
CREATE INDEX "reservations_table_seat_id_status_index" on "reservations"(
  "table_seat_id",
  "status"
);
CREATE INDEX "reservations_held_until_index" on "reservations"("held_until");
CREATE TABLE IF NOT EXISTS "payment_transactions"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "order_id" integer not null,
  "provider" varchar not null,
  "provider_transaction_id" varchar not null,
  "amount" numeric not null,
  "currency" varchar not null default 'USD',
  "status" varchar check("status" in('pending', 'completed', 'failed', 'refunded')) not null default 'pending',
  "payload" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete restrict,
  foreign key("order_id") references "orders"("id") on delete restrict
);
CREATE UNIQUE INDEX "payment_transactions_provider_provider_transaction_id_unique" on "payment_transactions"(
  "provider",
  "provider_transaction_id"
);
CREATE INDEX "payment_transactions_order_id_status_index" on "payment_transactions"(
  "order_id",
  "status"
);
CREATE INDEX "payment_transactions_venue_id_status_index" on "payment_transactions"(
  "venue_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "commissions"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "order_id" integer not null,
  "amount" numeric not null,
  "rate" numeric not null,
  "status" varchar check("status" in('pending', 'invoiced', 'paid')) not null default 'pending',
  "payout_reference" varchar,
  "paid_at" datetime,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("venue_id") references "venues"("id") on delete restrict,
  foreign key("order_id") references "orders"("id") on delete restrict
);
CREATE INDEX "commissions_venue_id_status_index" on "commissions"(
  "venue_id",
  "status"
);
CREATE UNIQUE INDEX "commissions_order_id_unique" on "commissions"("order_id");
CREATE TABLE IF NOT EXISTS "refunds"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "order_id" integer not null,
  "payment_transaction_id" integer,
  "amount" numeric not null,
  "status" varchar check("status" in('pending', 'processed', 'failed')) not null default 'pending',
  "reason" text,
  "provider_refund_id" varchar,
  "processed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete restrict,
  foreign key("order_id") references "orders"("id") on delete restrict,
  foreign key("payment_transaction_id") references "payment_transactions"("id") on delete set null
);
CREATE INDEX "refunds_order_id_status_index" on "refunds"(
  "order_id",
  "status"
);
CREATE INDEX "refunds_venue_id_status_index" on "refunds"(
  "venue_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "commission_adjustments"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "commission_id" integer not null,
  "refund_id" integer not null,
  "adjustment_amount" numeric not null,
  "rate_snapshot" numeric not null,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("venue_id") references "venues"("id") on delete restrict,
  foreign key("commission_id") references "commissions"("id") on delete restrict,
  foreign key("refund_id") references "refunds"("id") on delete restrict
);
CREATE INDEX "commission_adjustments_commission_id_index" on "commission_adjustments"(
  "commission_id"
);
CREATE INDEX "commission_adjustments_venue_id_index" on "commission_adjustments"(
  "venue_id"
);
CREATE UNIQUE INDEX "commission_adjustments_refund_id_unique" on "commission_adjustments"(
  "refund_id"
);
CREATE TABLE IF NOT EXISTS "notifications"(
  "id" varchar not null,
  "venue_id" integer,
  "user_id" integer not null,
  "type" varchar not null,
  "data" text not null,
  "read_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete set null,
  foreign key("user_id") references "users"("id") on delete cascade,
  primary key("id")
);
CREATE INDEX "notifications_user_id_read_at_index" on "notifications"(
  "user_id",
  "read_at"
);
CREATE INDEX "notifications_venue_id_created_at_index" on "notifications"(
  "venue_id",
  "created_at"
);
CREATE TABLE IF NOT EXISTS "email_templates"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer,
  "slug" varchar not null,
  "subject" varchar not null,
  "body" text not null,
  "variables" text,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade
);
CREATE UNIQUE INDEX "email_templates_venue_id_slug_unique" on "email_templates"(
  "venue_id",
  "slug"
);
CREATE TABLE IF NOT EXISTS "sms_templates"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer,
  "slug" varchar not null,
  "body" text not null,
  "variables" text,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade
);
CREATE UNIQUE INDEX "sms_templates_venue_id_slug_unique" on "sms_templates"(
  "venue_id",
  "slug"
);
CREATE TABLE IF NOT EXISTS "webhook_logs"(
  "id" integer primary key autoincrement not null,
  "provider" varchar not null,
  "provider_event_id" varchar not null,
  "payload" text not null,
  "signature" varchar,
  "status" varchar check("status" in('received', 'verified', 'failed', 'processed')) not null default 'received',
  "error_message" text,
  "created_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "webhook_logs_provider_provider_event_id_unique" on "webhook_logs"(
  "provider",
  "provider_event_id"
);
CREATE INDEX "webhook_logs_status_index" on "webhook_logs"("status");
CREATE TABLE IF NOT EXISTS "activity_logs"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer,
  "actor_user_id" integer,
  "entity_type" varchar not null,
  "entity_id" integer not null,
  "action" varchar not null,
  "old_values" text,
  "new_values" text,
  "changed_fields" text,
  "ip_address" varchar,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("venue_id") references "venues"("id") on delete set null,
  foreign key("actor_user_id") references "users"("id") on delete set null
);
CREATE INDEX "activity_logs_entity_type_entity_id_index" on "activity_logs"(
  "entity_type",
  "entity_id"
);
CREATE INDEX "activity_logs_venue_id_created_at_index" on "activity_logs"(
  "venue_id",
  "created_at"
);
CREATE INDEX "activity_logs_actor_user_id_index" on "activity_logs"(
  "actor_user_id"
);
CREATE TABLE IF NOT EXISTS "outbox_events"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer,
  "event_type" varchar not null,
  "aggregate_type" varchar not null,
  "aggregate_id" integer not null,
  "payload" text not null,
  "status" varchar check("status" in('pending', 'processing', 'sent', 'failed')) not null default 'pending',
  "attempts" integer not null default '0',
  "processed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete set null
);
CREATE INDEX "outbox_events_status_created_at_index" on "outbox_events"(
  "status",
  "created_at"
);
CREATE INDEX "outbox_events_aggregate_type_aggregate_id_index" on "outbox_events"(
  "aggregate_type",
  "aggregate_id"
);
CREATE TABLE IF NOT EXISTS "media"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer not null,
  "mediable_type" varchar not null,
  "mediable_id" integer not null,
  "type" varchar check("type" in('image', 'video')) not null default 'image',
  "url" varchar not null,
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete cascade
);
CREATE INDEX "media_mediable_type_mediable_id_index" on "media"(
  "mediable_type",
  "mediable_id"
);
CREATE INDEX "media_venue_id_type_index" on "media"("venue_id", "type");
CREATE TABLE IF NOT EXISTS "documents"(
  "id" integer primary key autoincrement not null,
  "venue_id" integer,
  "documentable_type" varchar not null,
  "documentable_id" integer not null,
  "name" varchar not null,
  "path" varchar not null,
  "mime_type" varchar,
  "size" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("venue_id") references "venues"("id") on delete set null
);
CREATE INDEX "documents_documentable_type_documentable_id_index" on "documents"(
  "documentable_type",
  "documentable_id"
);
CREATE INDEX "documents_venue_id_created_at_index" on "documents"(
  "venue_id",
  "created_at"
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2026_07_07_091415_create_personal_access_tokens_table',1);
INSERT INTO migrations VALUES(5,'2026_07_07_100000_extend_users_table',1);
INSERT INTO migrations VALUES(6,'2026_07_07_100100_create_venues_and_venue_user_tables',1);
INSERT INTO migrations VALUES(7,'2026_07_07_100200_create_events_domain_tables',1);
INSERT INTO migrations VALUES(8,'2026_07_07_100300_create_seating_tables',1);
INSERT INTO migrations VALUES(9,'2026_07_07_100400_create_products_tables',1);
INSERT INTO migrations VALUES(10,'2026_07_07_100500_create_discounts_tables',1);
INSERT INTO migrations VALUES(11,'2026_07_07_100600_create_financial_settings_tables',1);
INSERT INTO migrations VALUES(12,'2026_07_07_100700_create_authorization_tables',1);
INSERT INTO migrations VALUES(13,'2026_07_07_100800_create_api_clients_table',1);
INSERT INTO migrations VALUES(14,'2026_07_07_100900_create_orders_and_tickets_tables',1);
INSERT INTO migrations VALUES(15,'2026_07_07_101000_create_payments_and_commissions_tables',1);
INSERT INTO migrations VALUES(16,'2026_07_07_101100_create_communications_tables',1);
INSERT INTO migrations VALUES(17,'2026_07_07_101200_create_audit_and_outbox_tables',1);
INSERT INTO migrations VALUES(18,'2026_07_07_101300_create_media_and_documents_tables',1);
