<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('type', 32);
            $table->string('direction', 16);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('reference_type', 64);
            $table->unsignedBigInteger('reference_id');
            $table->decimal('balance_after', 14, 2);
            $table->string('correlation_id', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['reference_type', 'reference_id']);
            $table->index(['venue_id', 'occurred_at']);
            $table->index('correlation_id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_entries');
    }
};
