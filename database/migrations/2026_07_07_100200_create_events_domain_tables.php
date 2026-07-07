<?php

use App\Support\Database\AppliesArchitectureConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use AppliesArchitectureConstraints;

    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['venue_id', 'slug']);
            $table->index(['venue_id', 'is_active']);
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('banner_url')->nullable();
            $table->json('gallery')->nullable();
            $table->string('video_url')->nullable();
            $table->json('dj_info')->nullable();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->enum('status', ['draft', 'published', 'cancelled', 'completed'])->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['venue_id', 'status']);
            $table->index(['venue_id', 'start_datetime']);
        });

        $this->softDeleteSafeUnique('events', ['venue_id', 'slug'], 'events_venue_slug_unique');

        Schema::create('ticket_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->dateTime('sale_start')->nullable();
            $table->dateTime('sale_end')->nullable();
            $table->json('benefits')->nullable();
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['event_id', 'sale_start', 'sale_end']);
            $table->index('venue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
        $this->dropSoftDeleteSafeUnique('events', 'events_venue_slug_unique');
        Schema::dropIfExists('events');
        Schema::dropIfExists('categories');
    }
};
