<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['venue_id', 'event_id']);
        });

        Schema::create('venue_tables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->string('table_number');
            $table->unsignedInteger('capacity');
            $table->decimal('min_spend', 10, 2)->nullable();
            $table->enum('status', ['available', 'reserved', 'unavailable'])->default('available');
            $table->timestamps();

            $table->unique(['zone_id', 'table_number']);
            $table->index(['venue_id', 'event_id', 'status']);
        });

        Schema::create('table_seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('venue_table_id')->constrained('venue_tables')->cascadeOnDelete();
            $table->string('seat_number');
            $table->enum('status', ['available', 'reserved', 'unavailable'])->default('available');
            $table->timestamps();

            $table->unique(['venue_table_id', 'seat_number']);
            $table->index(['venue_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_seats');
        Schema::dropIfExists('venue_tables');
        Schema::dropIfExists('zones');
    }
};
