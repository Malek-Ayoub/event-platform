<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('provider');
            $table->string('provider_transaction_id');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_transaction_id']);
            $table->index(['order_id', 'status']);
            $table->index(['venue_id', 'status']);
        });

        Schema::create('commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('rate', 5, 2);
            $table->enum('status', ['pending', 'invoiced', 'paid'])->default('pending');
            $table->string('payout_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['venue_id', 'status']);
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->text('reason')->nullable();
            $table->string('provider_refund_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['venue_id', 'status']);
        });

        Schema::create('commission_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->foreignId('commission_id')->constrained('commissions')->restrictOnDelete();
            $table->foreignId('refund_id')->unique()->constrained('refunds')->restrictOnDelete();
            $table->decimal('adjustment_amount', 10, 2);
            $table->decimal('rate_snapshot', 5, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->index('commission_id');
            $table->index('venue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_adjustments');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('payment_transactions');
    }
};
