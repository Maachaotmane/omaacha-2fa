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

        $challenge = AuthChallenge::create([
            'challenge_token'             => $hash,
            'user_id'                     => $user->getAuthIdentifier(),
            'requires_email_verification' => $requiresEmailVerification,
            'is_setup_invitation'         => $isSetupInvitation,
            'invitation_secret'           => $invitationSecret,
            'invitation_qr_code'          => $invitationQrCode,
            'payload'                     => $payload,
            'expires_at'                  => $expiresAt,
            'ip_address'                  => request()->ip(),
            'user_agent'                  => substr((string) request()->userAgent(), 0, 500),
        ]);

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

        return AuthChallenge::where('challenge_token', $hash)
            ->where('expires_at', '>', Carbon::now())
            ->first();
    }

    /**
     * Consume the challenge atomically.
     */
    public function consume(AuthChallenge $challenge): void
    {
        $challenge->delete();
    }
}

