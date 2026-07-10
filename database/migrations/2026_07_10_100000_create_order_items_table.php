<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();

            $table->index(['order_id', 'ticket_type_id']);
            $table->index('venue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
