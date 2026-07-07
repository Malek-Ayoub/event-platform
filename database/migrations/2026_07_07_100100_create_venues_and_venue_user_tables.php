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
        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('subdomain');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('theme_config')->nullable();
            $table->string('shamcash_account_id')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(1.00);
            $table->enum('status', ['active', 'suspended', 'pending'])->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        $this->softDeleteSafeUnique('venues', ['subdomain'], 'venues_subdomain_unique');
        $this->softDeleteSafeUnique('venues', ['slug'], 'venues_slug_unique');

        Schema::create('venue_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['owner', 'staff']);
            $table->json('permissions')->nullable();
            $table->timestamps();

            $table->unique(['venue_id', 'user_id']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_user');
        $this->dropSoftDeleteSafeUnique('venues', 'venues_slug_unique');
        $this->dropSoftDeleteSafeUnique('venues', 'venues_subdomain_unique');
        Schema::dropIfExists('venues');
    }
};
