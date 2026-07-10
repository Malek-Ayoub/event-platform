<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment accounts architecture (Batch 7.12):
 *
 * - payment_accounts: reusable wallet credentials (venue-scoped)
 * - event_payment_accounts: links events to wallets (is_default, is_active)
 * - orders.payment_account_id: immutable snapshot at order creation
 * - payment_transactions.payment_account_id: copied from order at payment time
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_accounts')) {
            if (Schema::hasColumn('payment_transactions', 'payment_account_id')) {
                Schema::table('payment_transactions', function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('payment_account_id');
                });
            }

            Schema::dropIfExists('event_payment_accounts');
            Schema::dropIfExists('payment_accounts');
        }

        Schema::create('payment_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->enum('provider', ['shamcash', 'syriatel']);
            $table->string('account_identifier');
            $table->string('cash_code')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('display_name');
            $table->timestamps();

            $table->unique(
                ['venue_id', 'provider', 'account_identifier'],
                'payment_accounts_venue_provider_identifier_unique',
            );
        });

        Schema::create('event_payment_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('payment_account_id')->constrained('payment_accounts')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event_id', 'payment_account_id'], 'event_payment_accounts_event_account_unique');
            $table->index(['event_id', 'is_active', 'is_default']);
        });

        if (! Schema::hasColumn('orders', 'payment_account_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreignId('payment_account_id')
                    ->nullable()
                    ->after('event_id')
                    ->constrained('payment_accounts')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('payment_transactions', 'payment_account_id')) {
            Schema::table('payment_transactions', function (Blueprint $table): void {
                $table->foreignId('payment_account_id')
                    ->nullable()
                    ->after('order_id')
                    ->constrained('payment_accounts')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payment_transactions', 'payment_account_id')) {
            Schema::table('payment_transactions', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('payment_account_id');
            });
        }

        if (Schema::hasColumn('orders', 'payment_account_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('payment_account_id');
            });
        }

        Schema::dropIfExists('event_payment_accounts');
        Schema::dropIfExists('payment_accounts');
    }
};
