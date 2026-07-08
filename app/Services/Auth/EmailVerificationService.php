<?php

namespace App\Services\Auth;

use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class EmailVerificationService
{
    public function sendNotification(MustVerifyEmail $user): string
    {
        if ($user->hasVerifiedEmail()) {
            return 'Email already verified.';
        }

        $user->sendEmailVerificationNotification();

        return 'Verification link sent.';
    }

    public function verify(MustVerifyEmail $user): string
    {
        if ($user->hasVerifiedEmail()) {
            return 'Email already verified.';
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return 'Email verified successfully.';
    }
}
