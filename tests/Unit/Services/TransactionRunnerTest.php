<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\TransactionRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class TransactionRunnerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_commits_work_when_callback_succeeds(): void
    {
        $runner = app(TransactionRunner::class);

        $userId = $runner->run(function (): int {
            $user = User::factory()->create(['email' => 'runner-success@test.test']);

            return $user->id;
        });

        $this->assertDatabaseHas('users', ['id' => $userId, 'email' => 'runner-success@test.test']);
    }

    #[Test]
    public function it_rolls_back_work_when_callback_throws(): void
    {
        $runner = app(TransactionRunner::class);

        try {
            $runner->run(function (): void {
                User::factory()->create(['email' => 'runner-rollback@test.test']);

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertDatabaseMissing('users', ['email' => 'runner-rollback@test.test']);
    }

    #[Test]
    public function nested_runner_calls_share_the_same_transaction(): void
    {
        $runner = app(TransactionRunner::class);

        $runner->run(function () use ($runner): void {
            User::factory()->create(['email' => 'nested-outer@test.test']);

            $runner->run(function (): void {
                User::factory()->create(['email' => 'nested-inner@test.test']);
            });
        });

        $this->assertDatabaseHas('users', ['email' => 'nested-outer@test.test']);
        $this->assertDatabaseHas('users', ['email' => 'nested-inner@test.test']);
    }
}
