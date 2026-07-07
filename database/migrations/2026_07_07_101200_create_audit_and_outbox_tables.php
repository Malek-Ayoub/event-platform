<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('provider_event_id');
            $table->longText('payload');
            $table->string('signature')->nullable();
            $table->enum('status', ['received', 'verified', 'failed', 'processed'])->default('received');
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider', 'provider_event_id']);
            $table->index('status');
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['venue_id', 'created_at']);
            $table->index('actor_user_id');
        });

        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->string('event_type');
            $table->string('aggregate_type');
            $table->unsignedBigInteger('aggregate_id');
            $table->json('payload');
            $table->enum('status', ['pending', 'processing', 'sent', 'failed'])->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('webhook_logs');
    }
};
