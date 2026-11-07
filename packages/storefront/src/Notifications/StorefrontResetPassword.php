<?php

declare(strict_types=1);

namespace Shopper\Storefront\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The reset link points to the storefront, not to the admin panel's reset page.
 */
final class StorefrontResetPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = route('shopper.storefront.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject(__('shopper-storefront::auth.reset_mail.subject', ['store' => shopper_setting('name') ?? config('app.name')]))
            ->line(__('shopper-storefront::auth.reset_mail.intro'))
            ->action(__('shopper-storefront::auth.reset_mail.action'), $url)
            ->line(__('shopper-storefront::auth.reset_mail.expire', ['count' => $minutes]))
            ->line(__('shopper-storefront::auth.reset_mail.outro'));
    }
}
