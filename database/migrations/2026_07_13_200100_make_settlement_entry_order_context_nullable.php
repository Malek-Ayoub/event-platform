<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlement_entries', function (Blueprint $table): void {
            $table->dropForeign(['event_id']);
            $table->dropForeign(['order_id']);
        });

        Schema::table('settlement_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('event_id')->nullable()->change();
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->foreign('event_id')->references('id')->on('events')->restrictOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('settlement_entries', function (Blueprint $table): void {
            $table->dropForeign(['event_id']);
            $table->dropForeign(['order_id']);
        });

        Schema::table('settlement_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('event_id')->nullable(false)->change();
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
            $table->foreign('event_id')->references('id')->on('events')->restrictOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
        });
    }
};
