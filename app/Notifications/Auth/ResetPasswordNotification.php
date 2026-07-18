<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Password-reset email in the HRX branded template (emails.reset_password),
 * replacing Laravel's default plain markdown mail.
 */
class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = $this->resetUrl($notifiable);
        $expireMinutes = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

        return (new MailMessage)
            ->subject('Reset Your Password — Hi Tech HRX')
            ->view('emails.reset_password', [
                'user'          => $notifiable,
                'resetUrl'      => $resetUrl,
                'expireMinutes' => $expireMinutes,
            ]);
    }

    /**
     * Build the reset URL (mirrors Laravel's default password.reset route).
     */
    protected function resetUrl($notifiable): string
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable, $this->token);
        }

        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
