<?php

namespace Omaacha\TwoFactor\Contracts;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;

interface TwoFactorAuthenticatable
{
    /**
     * Get the identifier for the authenticatable entity (usually ID).
     */
    public function getAuthIdentifier();

    /**
     * Get the email or destination address for Two-Factor notifications.
     */
    public function getEmailForTwoFactor(): string;

    /**
     * Determine if Two-Factor Authentication is currently confirmed and enabled.
     */
    public function hasTwoFactorEnabled(): bool;

    /**
     * Get the encrypted/base32 TOTP secret.
     */
    public function getTwoFactorSecret(): ?string;

    /**
     * Set the TOTP secret.
     */
    public function setTwoFactorSecret(?string $secret): void;

    /**
     * Get the array of recovery codes.
     */
    public function getTwoFactorRecoveryCodes(): ?array;

    /**
     * Set the array of recovery codes.
     */
    public function setTwoFactorRecoveryCodes(?array $codes): void;

    /**
     * Get the timestamp when 2FA was confirmed.
     */
    public function getTwoFactorConfirmedAt(): ?Carbon;

    /**
     * Set the timestamp when 2FA was confirmed.
     */
    public function setTwoFactorConfirmedAt(?Carbon $time): void;

    /**
     * Get the timestamp when email OTP was last verified.
     */
    public function getEmailOtpVerifiedAt(): ?Carbon;

    /**
     * Set the timestamp when email OTP was verified.
     */
    public function setEmailOtpVerifiedAt(?Carbon $time): void;

    /**
     * Determine if the non-2FA email verification is still within the grace period (e.g. 24 hours).
     */
    public function isEmailOtpVerificationValid(): bool;

    /**
     * Relationship to trusted devices.
     */
    public function trustedDevices(): HasMany;

    /**
     * Save the model changes.
     */
    public function save(array $options = []);
}

