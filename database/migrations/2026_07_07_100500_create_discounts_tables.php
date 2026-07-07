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
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('code');
            $table->enum('discount_type', ['percent', 'fixed'])->default('percent');
            $table->decimal('discount_value', 10, 2);
            $table->decimal('min_order_amount', 10, 2)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['venue_id', 'is_active']);
        });

        $this->softDeleteSafeUnique('coupons', ['venue_id', 'code'], 'coupons_venue_code_unique');

        Schema::create('promo_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('code');
            $table->enum('discount_type', ['percent', 'fixed'])->default('percent');
            $table->decimal('discount_value', 10, 2);
            $table->decimal('min_order_amount', 10, 2)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['venue_id', 'is_active']);
        });

        $this->softDeleteSafeUnique('promo_codes', ['venue_id', 'code'], 'promo_codes_venue_code_unique');
    }

    public function down(): void
    {
        $this->dropSoftDeleteSafeUnique('promo_codes', 'promo_codes_venue_code_unique');
        Schema::dropIfExists('promo_codes');
        $this->dropSoftDeleteSafeUnique('coupons', 'coupons_venue_code_unique');
        Schema::dropIfExists('coupons');
    }
};
