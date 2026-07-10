<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_number_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['venue_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_number_counters');
    }
};
