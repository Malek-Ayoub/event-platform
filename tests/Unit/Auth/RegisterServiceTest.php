<?php

namespace Tests\Unit\Auth;

use App\DTOs\Auth\RegisterDTO;
use App\Models\User;
use App\Services\Auth\RegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegisterServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_customer_user(): void
    {
        $service = new RegisterService;

        $user = $service->register(new RegisterDTO(
            name: 'New User',
            email: 'new@example.com',
            password: 'Password123!',
            phone: '+963900000001',
        ));

        $this->assertInstanceOf(User::class, $user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.com',
            'is_super_admin' => false,
        ]);
    }

    #[Test]
    public function it_does_not_assign_venue_membership(): void
    {
        $service = new RegisterService;

        $user = $service->register(new RegisterDTO(
            name: 'New User',
            email: 'new@example.com',
            password: 'Password123!',
        ));

        $this->assertSame(0, $user->venues()->count());
    }
}
