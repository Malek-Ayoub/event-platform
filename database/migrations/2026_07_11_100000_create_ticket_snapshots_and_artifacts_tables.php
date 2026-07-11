<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained('tickets')->cascadeOnDelete();
            $table->json('payload');
            $table->timestamps();
        });

        Schema::create('ticket_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('type', 32);
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['pending', 'generating', 'ready', 'failed', 'deleted'])->default('pending');
            $table->string('disk', 64);
            $table->string('path');
            $table->string('mime_type', 128);
            $table->string('checksum', 64)->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['ticket_id', 'type', 'version']);
            $table->index(['ticket_id', 'type']);
            $table->index(['ticket_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_artifacts');
        Schema::dropIfExists('ticket_snapshots');
    }
};
