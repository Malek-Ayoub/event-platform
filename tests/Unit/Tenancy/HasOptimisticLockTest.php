<?php

namespace Tests\Unit\Tenancy;

use App\Exceptions\StaleModelException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Stubs\VersionedRecord;
use Tests\TestCase;

class HasOptimisticLockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('versioned_records', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('versioned_records');

        parent::tearDown();
    }

    #[Test]
    public function it_updates_record_when_version_matches(): void
    {
        $record = VersionedRecord::query()->create([
            'name' => 'original',
            'version' => 1,
        ]);

        $record->updateWithVersion(['name' => 'updated'], 1);

        $this->assertSame('updated', $record->fresh()->name);
        $this->assertSame(2, $record->fresh()->version);
    }

    #[Test]
    public function it_throws_stale_model_exception_on_version_conflict(): void
    {
        $record = VersionedRecord::query()->create([
            'name' => 'original',
            'version' => 1,
        ]);

        VersionedRecord::query()
            ->whereKey($record->getKey())
            ->update(['version' => 2, 'name' => 'changed elsewhere']);

        $this->expectException(StaleModelException::class);

        $record->updateWithVersion(['name' => 'conflict'], 1);
    }
}
