<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_check_ins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->timestamp('checked_in_at');
            $table->foreignId('checked_in_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('gate_id')->nullable();
            $table->string('device_id', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ticket_id', 'checked_in_at']);
            $table->index('checked_in_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_check_ins');
    }
};
