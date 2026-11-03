<?php

namespace Omaacha\TwoFactor\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Omaacha\TwoFactor\Channels\OtpChannelManager;
use Omaacha\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Omaacha\TwoFactor\Contracts\TwoFactorTokenIssuer;
use Omaacha\TwoFactor\Managers\AuthChallengeManager;
use Omaacha\TwoFactor\Managers\EmailOtpManager;
use Omaacha\TwoFactor\Managers\RecoveryCodeManager;
use Omaacha\TwoFactor\Managers\TotpManager;
use Omaacha\TwoFactor\Managers\TrustedDeviceManager;
use Omaacha\TwoFactor\Models\AuthChallenge;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;

class TwoFactorService
{
    public function __construct(
        private TotpManager $totpManager,
        private TrustedDeviceManager $trustedDeviceManager,
        private EmailOtpManager $emailOtpManager,
        private RecoveryCodeManager $recoveryCodeManager,
        private AuthChallengeManager $challengeManager,
        private OtpChannelManager $channelManager,
        private ?TwoFactorTokenIssuer $tokenIssuer = null
    ) {}

    public function getStatus(TwoFactorAuthenticatable $user): array
    {
        $enabled = $user->hasTwoFactorEnabled();
        $trustedDevices = $this->trustedDeviceManager->getActiveDevices($user);

        return [
            'enabled'                => $enabled,
            'confirmed_at'           => $user->getTwoFactorConfirmedAt()?->toIso8601String(),
            'recovery_codes_left'    => $enabled ? count(array_filter($user->getTwoFactorRecoveryCodes() ?? [], fn($c) => empty($c['used']))) : 0,
            'trusted_devices_count'  => $trustedDevices->count(),
            'trusted_devices'        => $trustedDevices,
        ];
    }

    public function enable(TwoFactorAuthenticatable $user, bool $enforceOwnership = false): array
    {
        if ($user->hasTwoFactorEnabled()) {
            throw new RuntimeException(__('two-factor::messages.two_factor_already_enabled') ?? 'Two-factor authentication is already enabled.');
        }

        if ($enforceOwnership && !$this->hasOwnershipVerification($user)) {
            throw new RuntimeException(__('two-factor::messages.ownership_verification_required') ?? 'Please verify your identity with the email code first.');
        }

        $secret = $this->totpManager->generateSecretKey();
        $user->setTwoFactorSecret($secret);
        $user->save();

        $provisioningUri = $this->totpManager->getProvisioningUri($user, $secret);
        $qrCodeSvg = $this->totpManager->getQrCodeSvg($provisioningUri);

        return [
            'secret'           => $secret,
            'qr_code_svg'      => $qrCodeSvg,
            'provisioning_uri' => $provisioningUri,
        ];
    }

    public function confirm(TwoFactorAuthenticatable $user, string $code): array
    {
        $secret = $user->getTwoFactorSecret();
        if (empty($secret)) {
            throw new RuntimeException(__('two-factor::messages.enable_2fa_first') ?? 'Two-factor setup has not been initiated.');
        }

        if (!$this->totpManager->verify($secret, $code, (string) $user->getAuthIdentifier())) {
            throw new RuntimeException(__('two-factor::messages.invalid_totp_code') ?? 'Invalid authenticator code. Please check your authenticator app and try again.');
        }

        $plaintextCodes = $this->recoveryCodeManager->generatePlaintextCodes();
        $hashedCodes = $this->recoveryCodeManager->prepareHashedCodes($plaintextCodes);

        DB::transaction(function () use ($user, $hashedCodes) {
            $user->setTwoFactorConfirmedAt(Carbon::now());
            $user->setTwoFactorRecoveryCodes($hashedCodes);
            $user->save();
        });

        Cache::forget("2fa_ownership_verified_{$user->getAuthIdentifier()}");

        return [
            'confirmed'      => true,
            'recovery_codes' => $plaintextCodes,
            'message'        => __('two-factor::messages.two_factor_confirmed_successfully') ?? 'Two-factor authentication has been confirmed successfully.',
        ];
    }

    public function disable(TwoFactorAuthenticatable $user, string $password, string $code): bool
    {
        if (!Hash::check($password, $user->password)) {
            throw new RuntimeException(__('two-factor::messages.invalid_password') ?? 'Invalid current password.');
        }

        $secret = $user->getTwoFactorSecret();
        $validTotp = !empty($secret) && $this->totpManager->verify($secret, $code, (string) $user->getAuthIdentifier());
        $validRecovery = !$validTotp && $this->recoveryCodeManager->verifyAndConsume($user, $code);

        if (!$validTotp && !$validRecovery) {
            throw new RuntimeException(__('two-factor::messages.invalid_two_factor_code') ?? 'Invalid 2FA code or recovery code.');
        }

        DB::transaction(function () use ($user) {
            $user->setTwoFactorSecret(null);
            $user->setTwoFactorConfirmedAt(null);
            $user->setTwoFactorRecoveryCodes(null);
            $user->save();

            $this->trustedDeviceManager->revokeAllDevices($user);
        });

        return true;
    }

    public function adminDisable(TwoFactorAuthenticatable $targetUser): bool
    {
        DB::transaction(function () use ($targetUser) {
            $targetUser->setTwoFactorSecret(null);
            $targetUser->setTwoFactorConfirmedAt(null);
            $targetUser->setTwoFactorRecoveryCodes(null);
            $targetUser->save();

            $this->trustedDeviceManager->revokeAllDevices($targetUser);
        });

        return true;
    }

    public function regenerateRecoveryCodes(TwoFactorAuthenticatable $user, string $password): array
    {
        if (!Hash::check($password, $user->password)) {
            throw new RuntimeException(__('two-factor::messages.invalid_password') ?? 'Invalid current password.');
        }

        if (!$user->hasTwoFactorEnabled()) {
            throw new RuntimeException(__('two-factor::messages.two_factor_not_enabled') ?? 'Two-factor authentication is not enabled.');
        }

        $plaintextCodes = $this->recoveryCodeManager->generatePlaintextCodes();
        $hashedCodes = $this->recoveryCodeManager->prepareHashedCodes($plaintextCodes);

        $user->setTwoFactorRecoveryCodes($hashedCodes);
        $user->save();

        return [
            'recovery_codes' => $plaintextCodes,
            'message'        => __('two-factor::messages.recovery_codes_regenerated') ?? 'Recovery codes regenerated successfully.',
        ];
    }

    public function verifyLoginTotp(
        string $challengeToken,
        string $code,
        bool $trustDevice,
        ?string $deviceName,
        Request $request
    ): array {
        $challenge = $this->challengeManager->resolve($challengeToken);
        if (!$challenge) {
            throw new RuntimeException(__('two-factor::messages.invalid_or_expired_challenge') ?? 'Two-factor session has expired. Please sign in again.');
        }

        $user = $challenge->user;
        $secret = $user->getTwoFactorSecret();

        if (empty($secret) || !$this->totpManager->verify($secret, $code, (string) $user->getAuthIdentifier())) {
            throw new RuntimeException(__('two-factor::messages.invalid_totp_code') ?? 'Invalid authenticator code. Please check your authenticator app and try again.');
        }

        $this->challengeManager->consume($challenge);

        $deviceCookie = null;
        if ($trustDevice) {
            $deviceResult = $this->trustedDeviceManager->issueDeviceToken($user, $deviceName, $request);
            $deviceCookie = $deviceResult['cookie'];
        }

        $authResult = $this->issueTokenForUser($user, $request);
        if ($deviceCookie) {
            $authResult['cookie'] = $deviceCookie;
        }

        return $authResult;
    }

    public function requestEmailOtp(string $challengeToken, ?string $email = null): array
    {
        $challenge = $this->challengeManager->resolve($challengeToken);
        if (!$challenge) {
            throw new RuntimeException(__('two-factor::messages.invalid_or_expired_challenge') ?? 'Two-factor session has expired. Please sign in again.');
        }

        return $this->emailOtpManager->requestOtp($challenge, $email);
    }

    public function verifyLoginEmailOtp(
        string $challengeToken,
        string $code,
        bool $trustDevice,
        ?string $deviceName,
        Request $request
    ): array {
        $challenge = $this->challengeManager->resolve($challengeToken);
        if (!$challenge) {
            throw new RuntimeException(__('two-factor::messages.invalid_or_expired_challenge') ?? 'Two-factor session has expired. Please sign in again.');
        }

        $user = $challenge->user;
        if (!$this->emailOtpManager->verifyOtp($challenge, $code)) {
            throw new RuntimeException(__('two-factor::messages.invalid_email_otp') ?? 'Invalid verification code. Please check your email and try again.');
        }

        $user->setEmailOtpVerifiedAt(Carbon::now());
        $user->save();

        if ($challenge->requires_email_verification && !$user->hasTwoFactorEnabled()) {
            $secret = $this->totpManager->generateSecretKey();
            $provisioningUri = $this->totpManager->getProvisioningUri($user, $secret);
            $qrCodeSvg = $this->totpManager->getQrCodeSvg($provisioningUri);

            $newChallenge = $this->challengeManager->createChallenge(
                $user,
                false,
                true,
                $secret,
                $qrCodeSvg
            );

            $this->challengeManager->consume($challenge);

            return [
                'type'            => 'two_factor_invitation',
                'challenge_token' => $newChallenge['challenge_token'],
                'secret_key'      => $secret,
                'qr_code_svg'     => $qrCodeSvg,
                'email'           => $user->getEmailForTwoFactor(),
                'expires_at'      => $newChallenge['challenge']->expires_at->toIso8601String(),
            ];
        }

        $this->challengeManager->consume($challenge);

        $deviceCookie = null;
        if ($trustDevice) {
            $deviceResult = $this->trustedDeviceManager->issueDeviceToken($user, $deviceName, $request);
            $deviceCookie = $deviceResult['cookie'];
        }

        $authResult = $this->issueTokenForUser($user, $request);
        if ($deviceCookie) {
            $authResult['cookie'] = $deviceCookie;
        }

        return $authResult;
    }

    public function skipInvitation(string $challengeToken, Request $request): array
    {
        $challenge = $this->challengeManager->resolve($challengeToken);
        if (!$challenge || !$challenge->is_setup_invitation) {
            throw new RuntimeException(__('two-factor::messages.invalid_or_expired_challenge') ?? 'Invitation session has expired. Please sign in again.');
        }

        $user = $challenge->user;
        $user->setEmailOtpVerifiedAt(Carbon::now());
        $user->save();

        $this->challengeManager->consume($challenge);

        return $this->issueTokenForUser($user, $request);
    }

    public function confirmInvitation(
        string $challengeToken,
        string $code,
        bool $trustDevice,
        ?string $deviceName,
        Request $request
    ): array {
        $challenge = $this->challengeManager->resolve($challengeToken);
        if (!$challenge || !$challenge->is_setup_invitation || empty($challenge->invitation_secret)) {
            throw new RuntimeException(__('two-factor::messages.invalid_or_expired_challenge') ?? 'Invitation session has expired. Please sign in again.');
        }

        $user = $challenge->user;
        $secret = $challenge->invitation_secret;

        if (!$this->totpManager->verify($secret, $code, (string) $user->getAuthIdentifier())) {
            throw new RuntimeException(__('two-factor::messages.invalid_totp_code') ?? 'Invalid authenticator code. Please check your authenticator app and try again.');
        }

        $plaintextCodes = $this->recoveryCodeManager->generatePlaintextCodes();
        $hashedCodes = $this->recoveryCodeManager->prepareHashedCodes($plaintextCodes);

        DB::transaction(function () use ($user, $secret, $hashedCodes) {
            $user->setTwoFactorSecret($secret);
            $user->setTwoFactorConfirmedAt(Carbon::now());
            $user->setTwoFactorRecoveryCodes($hashedCodes);
            $user->setEmailOtpVerifiedAt(Carbon::now());
            $user->save();
        });

        $this->challengeManager->consume($challenge);

        $deviceCookie = null;
        if ($trustDevice) {
            $deviceResult = $this->trustedDeviceManager->issueDeviceToken($user, $deviceName, $request);
            $deviceCookie = $deviceResult['cookie'];
        }

        $authResult = $this->issueTokenForUser($user, $request);
        $authResult['payload']['recovery_codes'] = $plaintextCodes;
        $authResult['payload']['two_factor_enabled'] = true;

        if ($deviceCookie) {
            $authResult['cookie'] = $deviceCookie;
        }

        return $authResult;
    }

    public function requestOwnershipOtp(TwoFactorAuthenticatable $user): array
    {
        $cooldownKey = "2fa_ownership_cd_{$user->getAuthIdentifier()}";
        if (Cache::has($cooldownKey)) {
            $remaining = Cache::get($cooldownKey) - time();
            throw new RuntimeException(sprintf('Please wait %d seconds before requesting another code.', max(1, $remaining)));
        }

        $otpCode = (string) random_int(100000, 999999);
        $otpHash = hash('sha256', $otpCode);
        $ttl = (int) config('two-factor.email_otp_ttl', 5);
        $cooldown = (int) config('two-factor.email_otp_cooldown', 60);

        Cache::put("2fa_ownership_otp_{$user->getAuthIdentifier()}", $otpHash, Carbon::now()->addMinutes($ttl));
        Cache::put($cooldownKey, time() + $cooldown, $cooldown);

        $this->channelManager->driver()->send($user, $otpCode, $ttl);

        return [
            'sent'       => true,
            'expires_in' => $ttl * 60,
            'message'    => 'Verification code sent to your registered address.',
        ];
    }

    public function verifyOwnershipOtp(TwoFactorAuthenticatable $user, string $code): bool
    {
        $cacheKey = "2fa_ownership_otp_{$user->getAuthIdentifier()}";
        $storedHash = Cache::get($cacheKey);

        if (!$storedHash || !hash_equals($storedHash, hash('sha256', trim($code)))) {
            throw new RuntimeException(__('two-factor::messages.invalid_email_otp') ?? 'Invalid verification code.');
        }

        Cache::forget($cacheKey);
        Cache::put("2fa_ownership_verified_{$user->getAuthIdentifier()}", true, Carbon::now()->addMinutes(15));

        return true;
    }

    public function hasOwnershipVerification(TwoFactorAuthenticatable $user): bool
    {
        return Cache::has("2fa_ownership_verified_{$user->getAuthIdentifier()}");
    }

    public function verifyLoginRecoveryCode(
        string $challengeToken,
        string $recoveryCode,
        bool $trustDevice,
        ?string $deviceName,
        Request $request
    ): array {
        $challenge = $this->challengeManager->resolve($challengeToken);
        if (!$challenge) {
            throw new RuntimeException(__('two-factor::messages.invalid_or_expired_challenge') ?? 'Two-factor session has expired. Please sign in again.');
        }

        $user = $challenge->user;
        if (!$this->recoveryCodeManager->verifyAndConsume($user, $recoveryCode)) {
            throw new RuntimeException(__('two-factor::messages.invalid_recovery_code') ?? 'Invalid or already used recovery code.');
        }

        $this->challengeManager->consume($challenge);

        $deviceCookie = null;
        if ($trustDevice) {
            $deviceResult = $this->trustedDeviceManager->issueDeviceToken($user, $deviceName, $request);
            $deviceCookie = $deviceResult['cookie'];
        }

        $authResult = $this->issueTokenForUser($user, $request);
        if ($deviceCookie) {
            $authResult['cookie'] = $deviceCookie;
        }

        return $authResult;
    }

    public function confirmSudo(TwoFactorAuthenticatable $user, string $code): bool
    {
        $secret = $user->getTwoFactorSecret();
        $validTotp = !empty($secret) && $this->totpManager->verify($secret, $code, (string) $user->getAuthIdentifier());
        $validRecovery = !$validTotp && $this->recoveryCodeManager->verifyAndConsume($user, $code);

        if (!$validTotp && !$validRecovery) {
            throw new RuntimeException(__('two-factor::messages.invalid_two_factor_code') ?? 'Invalid 2FA code or recovery code.');
        }

        $sudoTtl = (int) config('two-factor.sudo_mode_ttl', 15);
        Cache::put("2fa_sudo_confirmed_{$user->getAuthIdentifier()}", true, Carbon::now()->addMinutes($sudoTtl));

        return true;
    }

    private function issueTokenForUser(TwoFactorAuthenticatable $user, Request $request): array
    {
        if ($this->tokenIssuer) {
            return $this->tokenIssuer->issueToken($user, $request);
        }

        return [
            'payload' => [
                'user'               => $user,
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            ],
        ];
    }
}

