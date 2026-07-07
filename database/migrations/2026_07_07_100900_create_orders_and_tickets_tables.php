<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->decimal('commission_amount', 10, 2)->default(0);
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->nullOnDelete();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded', 'cancelled'])->default('pending');
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->timestamps();

            $table->index(['venue_id', 'status']);
            $table->index(['event_id', 'status']);
            $table->index('customer_user_id');
        });

        Schema::create('ticket_serial_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedBigInteger('last_serial')->default(0);
            $table->timestamps();

            $table->unique(['venue_id', 'event_id']);
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->restrictOnDelete();
            $table->string('serial');
            $table->string('qr_code_path')->nullable();
            $table->enum('status', ['valid', 'used', 'cancelled', 'refunded'])->default('valid');
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'serial']);
            $table->index(['venue_id', 'status']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('table_seat_id')->constrained('table_seats')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->enum('status', ['hold', 'confirmed', 'cancelled'])->default('hold');
            $table->dateTime('held_until')->nullable();
            $table->timestamps();

            $table->index(['venue_id', 'status']);
            $table->index(['table_seat_id', 'status']);
            $table->index('held_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_serial_counters');
        Schema::dropIfExists('orders');
    }
};
