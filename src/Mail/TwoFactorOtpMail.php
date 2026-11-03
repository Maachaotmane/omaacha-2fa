<?php

namespace Omaacha\TwoFactor\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Omaacha\TwoFactor\Contracts\TwoFactorAuthenticatable;

class TwoFactorOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TwoFactorAuthenticatable $user,
        public string $otpCode,
        public int $ttlMinutes = 5
    ) {}

    public function build()
    {
        return $this->subject(__('two-factor::messages.email_subject', ['app' => config('two-factor.issuer', config('app.name'))]))
            ->view('two-factor::emails.otp', [
                'user'       => $this->user,
                'otpCode'    => $this->otpCode,
                'ttlMinutes' => $this->ttlMinutes,
                'appName'    => config('two-factor.issuer', config('app.name')),
            ]);
    }
}

