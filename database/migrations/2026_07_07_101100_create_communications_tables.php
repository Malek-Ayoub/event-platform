<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['venue_id', 'created_at']);
        });

        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->cascadeOnDelete();
            $table->string('slug');
            $table->string('subject');
            $table->text('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['venue_id', 'slug']);
        });

        Schema::create('sms_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->cascadeOnDelete();
            $table->string('slug');
            $table->text('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['venue_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('notifications');
    }
};
