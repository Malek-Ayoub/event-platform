<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $statuses = [
        'received',
        'verified',
        'processing',
        'processed',
        'failed',
        'failed_signature',
        'replayed',
    ];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $rows = DB::table('webhook_logs')->get();

            Schema::drop('webhook_logs');

            Schema::create('webhook_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('provider');
                $table->string('provider_event_id');
                $table->longText('payload');
                $table->string('signature')->nullable();
                $table->string('status')->default('received');
                $table->text('error_message')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('processed_at')->nullable();

                $table->unique(['provider', 'provider_event_id']);
                $table->index('status');
            });

            foreach ($rows as $row) {
                DB::table('webhook_logs')->insert([
                    'id' => $row->id,
                    'provider' => $row->provider,
                    'provider_event_id' => $row->provider_event_id,
                    'payload' => $row->payload,
                    'signature' => $row->signature,
                    'status' => $row->status,
                    'error_message' => $row->error_message,
                    'created_at' => $row->created_at,
                    'processed_at' => null,
                ]);
            }

            return;
        }

        Schema::table('webhook_logs', function (Blueprint $table): void {
            $table->timestamp('processed_at')->nullable()->after('created_at');
        });

        $enum = "'".implode("','", $this->statuses)."'";

        DB::statement("ALTER TABLE webhook_logs MODIFY COLUMN status ENUM({$enum}) NOT NULL DEFAULT 'received'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $rows = DB::table('webhook_logs')
                ->whereNotIn('status', ['processing', 'failed_signature', 'replayed'])
                ->get();

            Schema::drop('webhook_logs');

            Schema::create('webhook_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('provider');
                $table->string('provider_event_id');
                $table->longText('payload');
                $table->string('signature')->nullable();
                $table->string('status')->default('received');
                $table->text('error_message')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['provider', 'provider_event_id']);
                $table->index('status');
            });

            foreach ($rows as $row) {
                DB::table('webhook_logs')->insert([
                    'id' => $row->id,
                    'provider' => $row->provider,
                    'provider_event_id' => $row->provider_event_id,
                    'payload' => $row->payload,
                    'signature' => $row->signature,
                    'status' => $row->status,
                    'error_message' => $row->error_message,
                    'created_at' => $row->created_at,
                ]);
            }

            return;
        }

        Schema::table('webhook_logs', function (Blueprint $table): void {
            $table->dropColumn('processed_at');
        });

        DB::statement("ALTER TABLE webhook_logs MODIFY COLUMN status ENUM('received', 'verified', 'failed', 'processed') NOT NULL DEFAULT 'received'");
    }
};
