<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('webhook_logs');
    }

    public function down(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('provider_event_id');
            $table->string('correlation_id')->nullable()->index();
            $table->text('payload');
            $table->string('signature')->nullable();
            $table->string('status');
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_event_id']);
        });
    }
};
