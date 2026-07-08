<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table): void {
            $table->string('correlation_id')->nullable()->after('provider_event_id');
            $table->index('correlation_id');
        });

        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->string('correlation_id')->nullable()->after('actor_user_id');
            $table->index('correlation_id');
        });

        Schema::table('outbox_events', function (Blueprint $table): void {
            $table->string('correlation_id')->nullable()->after('venue_id');
            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table): void {
            $table->dropIndex(['correlation_id']);
            $table->dropColumn('correlation_id');
        });

        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropIndex(['correlation_id']);
            $table->dropColumn('correlation_id');
        });

        Schema::table('outbox_events', function (Blueprint $table): void {
            $table->dropIndex(['correlation_id']);
            $table->dropColumn('correlation_id');
        });
    }
};
