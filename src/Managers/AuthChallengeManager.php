<?php

namespace Omaacha\TwoFactor\Managers;

use Carbon\Carbon;
use Omaacha\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Omaacha\TwoFactor\Models\AuthChallenge;

class AuthChallengeManager
{
    public function getChallengeTtlMinutes(): int
    {
        return (int) config('two-factor.challenge_ttl', 15);
    }

    /**
     * Create an opaque challenge token and store its SHA-256 hash.
     *
     * @return array{challenge_token: string, challenge: AuthChallenge}
     */
    public function createChallenge(
        TwoFactorAuthenticatable $user,
        bool $requiresEmailVerification = false,
        bool $isSetupInvitation = false,
        ?string $invitationSecret = null,
        ?string $invitationQrCode = null,
        ?array $payload = null
    ): array {
        $rawToken = 'ch_' . bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);
        $expiresAt = Carbon::now()->addMinutes($this->getChallengeTtlMinutes());

        $purpose = 'login_2fa';
        if ($isSetupInvitation) {
            $purpose = 'invitation';
        } elseif ($requiresEmailVerification) {
            $purpose = 'email_otp';
        }

        $challenge = AuthChallenge::create([
            'challenge_hash'  => $hash,
            'user_id'         => $user->getAuthIdentifier(),
            'purpose'         => $purpose,
            'failed_attempts' => 0,
            'is_consumed'     => false,
            'expires_at'      => $expiresAt,
        ]);

        if ($invitationSecret) {
            \Illuminate\Support\Facades\Cache::put("2fa_inv_secret_{$challenge->id}", $invitationSecret, $expiresAt);
        }
        if ($invitationQrCode) {
            \Illuminate\Support\Facades\Cache::put("2fa_inv_qr_{$challenge->id}", $invitationQrCode, $expiresAt);
        }

        return [
            'challenge_token' => $rawToken,
            'challenge'       => $challenge,
        ];
    }

    /**
     * Resolve a valid, unconsumed, unexpired challenge from raw client token.
     */
    public function resolve(string $rawToken): ?AuthChallenge
    {
        if (empty($rawToken) || !str_starts_with($rawToken, 'ch_')) {
            return null;
        }

        $hash = hash('sha256', $rawToken);

        return AuthChallenge::with('user')
            ->where('challenge_hash', $hash)
            ->where('is_consumed', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();
    }

    /**
     * Record a failed attempt and invalidate if max attempts exceeded.
     */
    public function recordFailure(AuthChallenge $challenge): void
    {
        $challenge->increment('failed_attempts');
    }

    /**
     * Consume the challenge atomically.
     */
    public function consume(AuthChallenge $challenge): void
    {
        $challenge->update([
            'is_consumed' => true,
        ]);
    }
}

