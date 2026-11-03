<?php

namespace Omaacha\TwoFactor\Managers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Omaacha\TwoFactor\Channels\OtpChannelManager;
use Omaacha\TwoFactor\Models\AuthChallenge;
use Omaacha\TwoFactor\Models\EmailOtp;
use RuntimeException;

class EmailOtpManager
{
    public const MAX_REQUESTS_PER_CHALLENGE = 3;

    public function __construct(
        private OtpChannelManager $channelManager
    ) {}

    public function getOtpTtlMinutes(): int
    {
        return (int) config('two-factor.email_otp_ttl', 5);
    }

    public function getCooldownSeconds(): int
    {
        return (int) config('two-factor.email_otp_cooldown', 60);
    }

    /**
     * Request an OTP for the given challenge and supplied identifier.
     * Implements anti-enumeration: if email does not match challenge user, returns success message without sending.
     */
    public function requestOtp(AuthChallenge $challenge, ?string $suppliedEmail = null): array
    {
        $cooldownSeconds = $this->getCooldownSeconds();
        $ttlMinutes = $this->getOtpTtlMinutes();

        $cooldownKey = sprintf('2fa_email_otp_cd_%s', $challenge->id);
        if (Cache::has($cooldownKey)) {
            $remaining = Cache::get($cooldownKey) - time();
            throw new RuntimeException(sprintf('Please wait %d seconds before requesting another code.', max(1, $remaining)));
        }

        $requestCount = EmailOtp::where('challenge_id', $challenge->id)->count();
        if ($requestCount >= self::MAX_REQUESTS_PER_CHALLENGE) {
            throw new RuntimeException('Maximum email verification attempts reached for this session.');
        }

        $user = $challenge->user;
        $normalizedSupplied = strtolower(trim((string) $suppliedEmail));
        $normalizedUserEmail = strtolower(trim($user->getEmailForTwoFactor()));

        // Anti-enumeration check: valid if empty (resend), contains masking (*), or matches exact user email
        $emailMatches = empty($normalizedSupplied)
            || str_contains($normalizedSupplied, '*')
            || hash_equals($normalizedUserEmail, $normalizedSupplied);

        if ($emailMatches) {
            $otpCode = (string) random_int(100000, 999999);
            $otpHash = hash('sha256', $otpCode);

            EmailOtp::create([
                'user_id'         => $user->getAuthIdentifier(),
                'challenge_id'    => $challenge->id,
                'otp_hash'        => $otpHash,
                'failed_attempts' => 0,
                'expires_at'      => Carbon::now()->addMinutes($ttlMinutes),
            ]);

            // Record cooldown
            Cache::put($cooldownKey, time() + $cooldownSeconds, $cooldownSeconds);

            // Dispatch OTP via the configured channel (mail, notification, sms, etc.)
            $this->channelManager->driver()->send($user, $otpCode, $ttlMinutes);
        }

        return [
            'sent'           => true,
            'cooldown'       => $cooldownSeconds,
            'expires_in'     => $ttlMinutes * 60,
            'message'        => 'If the email matches your account, a verification code has been sent.',
        ];
    }

    /**
     * Verify the 6-digit OTP against the latest active record.
     */
    public function verifyOtp(AuthChallenge $challenge, string $code): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        $otpRecord = EmailOtp::where('challenge_id', $challenge->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', Carbon::now())
            ->orderByDesc('id')
            ->first();

        if (!$otpRecord) {
            return false;
        }

        $computedHash = hash('sha256', $code);
        if (!hash_equals($otpRecord->otp_hash, $computedHash)) {
            $otpRecord->increment('failed_attempts');
            return false;
        }

        // Consume atomically
        $otpRecord->update([
            'consumed_at' => Carbon::now(),
        ]);

        return true;
    }
}

