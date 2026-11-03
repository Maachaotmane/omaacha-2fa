<?php

namespace Omaacha\TwoFactor\Channels;

use Illuminate\Support\Manager;
use Omaacha\TwoFactor\Channels\Contracts\OtpChannelDriver;
use Omaacha\TwoFactor\Channels\Drivers\MailOtpDriver;
use Omaacha\TwoFactor\Channels\Drivers\NotificationOtpDriver;

class OtpChannelManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('two-factor.otp_channel', 'mail');
    }

    protected function createMailDriver(): OtpChannelDriver
    {
        return new MailOtpDriver();
    }

    protected function createNotificationDriver(): OtpChannelDriver
    {
        return new NotificationOtpDriver();
    }
}

