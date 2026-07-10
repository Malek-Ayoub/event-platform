<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_consumer_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outbox_event_id')->constrained('outbox_events')->cascadeOnDelete();
            $table->string('consumer_key');
            $table->timestamp('processed_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['outbox_event_id', 'consumer_key']);
            $table->index('consumer_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_consumer_receipts');
    }
};
