<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sends_verification_notification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/email/verification-notification');

        $response->assertOk();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[Test]
    public function it_verifies_email_with_valid_signed_url(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $response = $this->actingAs($user, 'sanctum')->getJson($url);

        $response->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function it_rejects_invalid_verification_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1('wrong@example.com'),
            ],
        );

        $response = $this->actingAs($user, 'sanctum')->getJson($url);

        $response->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function verified_user_gets_already_verified_message(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/email/verification-notification');

        $response->assertOk()->assertJsonPath('message', 'Email already verified.');
    }
}
