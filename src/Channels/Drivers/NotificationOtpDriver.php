<?php

namespace Omaacha\TwoFactor\Channels\Drivers;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Omaacha\TwoFactor\Channels\Contracts\OtpChannelDriver;
use Omaacha\TwoFactor\Contracts\TwoFactorAuthenticatable;

class NotificationOtpDriver implements OtpChannelDriver
{
    public function send(TwoFactorAuthenticatable $user, string $code, int $ttlMinutes): bool
    {
        $notificationClass = config('two-factor.notification_class');
        if (!class_exists($notificationClass)) {
            return false;
        }

        try {
            if (method_exists($user, 'notify')) {
                $user->notify(new $notificationClass($code, $ttlMinutes));
            } else {
                (new AnonymousNotifiable())
                    ->route('mail', $user->getEmailForTwoFactor())
                    ->notify(new $notificationClass($code, $ttlMinutes));
            }
            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }
}

