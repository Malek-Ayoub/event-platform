<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->morphs('mediable');
            $table->enum('type', ['image', 'video'])->default('image');
            $table->string('url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['venue_id', 'type']);
        });

        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->morphs('documentable');
            $table->string('name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->index(['venue_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('media');
    }
};
