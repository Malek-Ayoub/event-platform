<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('rate', 8, 4);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['venue_id', 'is_active']);
        });

        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('commission_rate', 5, 2)->default(1.00);
            $table->json('settings')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('tax_rates');
    }
};
