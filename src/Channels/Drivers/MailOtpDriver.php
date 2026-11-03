<?php

namespace Omaacha\TwoFactor\Channels\Drivers;

use Illuminate\Support\Facades\Mail;
use Omaacha\TwoFactor\Channels\Contracts\OtpChannelDriver;
use Omaacha\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Omaacha\TwoFactor\Mail\TwoFactorOtpMail;

class MailOtpDriver implements OtpChannelDriver
{
    public function send(TwoFactorAuthenticatable $user, string $code, int $ttlMinutes): bool
    {
        try {
            Mail::to($user->getEmailForTwoFactor())->send(new TwoFactorOtpMail($user, $code, $ttlMinutes));
            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }
}

