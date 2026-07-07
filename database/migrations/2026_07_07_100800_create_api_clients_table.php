<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('api_key')->unique();
            $table->string('secret');
            $table->json('scopes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['venue_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
