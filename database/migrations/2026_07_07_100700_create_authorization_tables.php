<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('role');
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role', 'permission_id']);
            $table->index('role');
        });

        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['venue_id', 'user_id', 'permission_id']);
            $table->index(['venue_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
    }
};
