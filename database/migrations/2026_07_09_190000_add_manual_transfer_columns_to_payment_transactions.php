<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 7.6 (IMPLEMENTATION_ROADMAP.md §7.9.7/§7.9.10) — Manual Wallet Transfer.
 *
 * Additive only: extends the `status` enum with the new states and adds the
 * columns needed by the verification flow. Legacy hosted-checkout values
 * (`pending`, `completed`, `refunded`) are kept — dormant, not deleted (§7.9.2).
 *
 * `provider_transaction_id` becomes nullable: Manual Wallet Transfer creates the
 * `PaymentTransaction` (awaiting_transfer) before any provider transaction id is
 * known — it is only populated after a successful `verifyTransaction()` call.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $statuses = [
        'pending',
        'completed',
        'refunded',
        'failed',
        'awaiting_transfer',
        'verifying',
        'paid',
        'expired',
    ];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable(nullableProviderTransactionId: true);

            return;
        }

        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->string('transaction_number')->nullable()->after('provider_transaction_id');
            $table->timestamp('expires_at')->nullable()->after('status');
            $table->unique('transaction_number');
        });

        $enum = "'".implode("','", $this->statuses)."'";

        DB::statement("ALTER TABLE payment_transactions MODIFY COLUMN status ENUM({$enum}) NOT NULL DEFAULT 'pending'");
        DB::statement('ALTER TABLE payment_transactions MODIFY COLUMN provider_transaction_id VARCHAR(255) NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable(nullableProviderTransactionId: false);

            return;
        }

        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->dropUnique(['transaction_number']);
            $table->dropColumn(['transaction_number', 'expires_at']);
        });

        DB::statement("ALTER TABLE payment_transactions MODIFY COLUMN status ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending'");
        DB::statement('ALTER TABLE payment_transactions MODIFY COLUMN provider_transaction_id VARCHAR(255) NOT NULL');
    }

    /**
     * SQLite has no `MODIFY COLUMN` — rebuild the table preserving data, matching
     * the pattern established in `2026_07_08_180000_expand_webhook_log_statuses.php`.
     */
    private function rebuildSqliteTable(bool $nullableProviderTransactionId): void
    {
        $rows = DB::table('payment_transactions')->get();

        Schema::drop('payment_transactions');

        Schema::create('payment_transactions', function (Blueprint $table) use ($nullableProviderTransactionId): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('provider');

            if ($nullableProviderTransactionId) {
                $table->string('provider_transaction_id')->nullable();
                $table->string('transaction_number')->nullable();
            } else {
                $table->string('provider_transaction_id');
            }

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending');

            if ($nullableProviderTransactionId) {
                $table->timestamp('expires_at')->nullable();
            }

            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_transaction_id']);

            if ($nullableProviderTransactionId) {
                $table->unique('transaction_number');
            }

            $table->index(['order_id', 'status']);
            $table->index(['venue_id', 'status']);
        });

        foreach ($rows as $row) {
            $attributes = (array) $row;

            if (! $nullableProviderTransactionId) {
                unset($attributes['transaction_number'], $attributes['expires_at']);
            }

            DB::table('payment_transactions')->insert($attributes);
        }
    }
};
