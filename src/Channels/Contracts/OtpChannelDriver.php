<?php

namespace Omaacha\TwoFactor\Channels\Contracts;

use Omaacha\TwoFactor\Contracts\TwoFactorAuthenticatable;

interface OtpChannelDriver
{
    /**
     * Send a one-time verification code to the authenticatable user.
     *
     * @param TwoFactorAuthenticatable $user
     * @param string $code (6-digit OTP)
     * @param int $ttlMinutes
     * @return bool
     */
    public function send(TwoFactorAuthenticatable $user, string $code, int $ttlMinutes): bool;
}

