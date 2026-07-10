<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->string('ticket_number', 64)->nullable()->unique()->after('serial');
            $table->uuid('qr_token')->nullable()->unique()->after('ticket_number');
            $table->timestamp('issued_at')->nullable()->after('qr_token');
        });

        DB::table('tickets')->where('status', 'valid')->update(['status' => 'issued']);
        DB::table('tickets')->where('status', 'used')->update(['status' => 'checked_in']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE tickets MODIFY COLUMN status ENUM('issued', 'checked_in', 'cancelled', 'refunded', 'invalidated') NOT NULL DEFAULT 'issued'",
            );
        }
    }

    public function down(): void
    {
        DB::table('tickets')->where('status', 'issued')->update(['status' => 'valid']);
        DB::table('tickets')->where('status', 'checked_in')->update(['status' => 'used']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE tickets MODIFY COLUMN status ENUM('valid', 'used', 'cancelled', 'refunded') NOT NULL DEFAULT 'valid'",
            );
        }

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropUnique(['ticket_number']);
            $table->dropUnique(['qr_token']);
            $table->dropColumn(['ticket_number', 'qr_token', 'issued_at']);
        });
    }
};
