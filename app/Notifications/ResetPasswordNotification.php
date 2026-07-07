<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $baseUrl = (string) config('platform.auth.frontend_reset_password_url');
        $query = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
        $url = str_contains($baseUrl, '?') ? $baseUrl.'&'.$query : $baseUrl.'?'.$query;

        return (new MailMessage)
            ->subject(__('Reset Password Notification'))
            ->line(__('You are receiving this email because we received a password reset request for your account.'))
            ->action(__('Reset Password'), $url)
            ->line(__('This password reset link will expire in :count minutes.', [
                'count' => config('auth.passwords.users.expire'),
            ]))
            ->line(__('If you did not request a password reset, no further action is required.'));
    }
}
