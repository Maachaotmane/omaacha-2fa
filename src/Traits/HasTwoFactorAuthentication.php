<?php

namespace Omaacha\TwoFactor\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Omaacha\TwoFactor\Models\TrustedDevice;

trait HasTwoFactorAuthentication
{
    public function getEmailForTwoFactor(): string
    {
        return (string) ($this->email ?? $this->mail ?? '');
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && !empty($this->two_factor_secret);
    }

    public function getTwoFactorSecret(): ?string
    {
        return $this->two_factor_secret;
    }

    public function setTwoFactorSecret(?string $secret): void
    {
        $this->two_factor_secret = $secret;
    }

    public function getTwoFactorRecoveryCodes(): ?array
    {
        if ($this->two_factor_recovery_codes === null) {
            return null;
        }

        return is_array($this->two_factor_recovery_codes)
            ? $this->two_factor_recovery_codes
            : (json_decode((string) $this->two_factor_recovery_codes, true) ?? []);
    }

    public function setTwoFactorRecoveryCodes(?array $codes): void
    {
        $this->two_factor_recovery_codes = $codes;
    }

    public function getTwoFactorConfirmedAt(): ?Carbon
    {
        return $this->two_factor_confirmed_at ? Carbon::parse($this->two_factor_confirmed_at) : null;
    }

    public function setTwoFactorConfirmedAt(?Carbon $time): void
    {
        $this->two_factor_confirmed_at = $time;
    }

    public function getEmailOtpVerifiedAt(): ?Carbon
    {
        return $this->email_otp_verified_at ? Carbon::parse($this->email_otp_verified_at) : null;
    }

    public function setEmailOtpVerifiedAt(?Carbon $time): void
    {
        $this->email_otp_verified_at = $time;
    }

    public function isEmailOtpVerificationValid(): bool
    {
        $verifiedAt = $this->getEmailOtpVerifiedAt();
        if ($verifiedAt === null) {
            return false;
        }

        $graceHours = (int) config('two-factor.grace_period_hours', 24);

        return $verifiedAt->gt(Carbon::now()->subHours($graceHours));
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class, 'user_id', $this->getKeyName());
    }
}

