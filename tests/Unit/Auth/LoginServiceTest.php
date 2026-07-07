<?php

namespace Tests\Unit\Auth;

use App\DTOs\Auth\LoginDTO;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\UserNotInVenueException;
use App\Models\User;
use App\Services\Auth\LoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginServiceTest extends TestCase
{
    use RefreshDatabase;

    private LoginService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LoginService;
    }

    #[Test]
    public function it_returns_user_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'Password123!',
        ]);

        $result = $this->service->attempt(new LoginDTO(
            email: 'user@example.com',
            password: 'Password123!',
        ));

        $this->assertTrue($result->is($user));
    }

    #[Test]
    public function it_throws_for_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'Password123!',
        ]);

        $this->expectException(InvalidCredentialsException::class);

        $this->service->attempt(new LoginDTO(
            email: 'user@example.com',
            password: 'wrong',
        ));
    }

    #[Test]
    public function it_allows_venue_member_for_tenant_login(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $owner->forceFill(['password' => 'Password123!'])->save();

        $result = $this->service->attemptForVenue(
            new LoginDTO(email: $owner->email, password: 'Password123!'),
            $venue->id,
        );

        $this->assertTrue($result->is($owner));
    }

    #[Test]
    public function it_rejects_user_not_in_venue(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $outsider = User::factory()->create(['password' => 'Password123!']);

        $this->expectException(UserNotInVenueException::class);

        $this->service->attemptForVenue(
            new LoginDTO(email: $outsider->email, password: 'Password123!'),
            $venue->id,
        );
    }
}
