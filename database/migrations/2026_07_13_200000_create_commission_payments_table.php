<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('payment_method', 32);
            $table->string('reference_number', 128)->nullable();
            $table->timestamp('received_at');
            $table->foreignId('received_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['venue_id', 'received_at']);
            $table->index('payment_account_id');
            $table->index('received_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_payments');
    }
};
