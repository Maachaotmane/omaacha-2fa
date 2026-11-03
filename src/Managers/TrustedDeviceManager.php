<?php

namespace Omaacha\TwoFactor\Managers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Omaacha\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Omaacha\TwoFactor\Models\TrustedDevice;
use Symfony\Component\HttpFoundation\Cookie;

class TrustedDeviceManager
{
    public function getCookieName(): string
    {
        return config('two-factor.trusted_devices.cookie_name', 'omaacha_device_trust');
    }

    public function getValidityDays(): int
    {
        return (int) config('two-factor.trusted_devices.ttl_days', 14);
    }

    public function getMaxActiveDevices(): int
    {
        return (int) config('two-factor.trusted_devices.max_per_user', 3);
    }

    /**
     * Compute a deterministic device fingerprint from client signals + server request headers.
     */
    public function computeFingerprint(?Request $request): string
    {
        if (!$request) {
            return 'default_fingerprint';
        }

        $clientFp = (string) (
            $request->header('X-Device-Fingerprint')
            ?? $request->input('device_fingerprint')
            ?? ''
        );

        $serverPlatformBrowser = $this->resolvePlatformAndBrowser($request->userAgent());

        return hash('sha256', $clientFp . '|' . $serverPlatformBrowser);
    }

    /**
     * Compute the database token hash bound to the device fingerprint.
     */
    public function hashToken(string $rawToken, ?Request $request = null): string
    {
        $fingerprint = $this->computeFingerprint($request);
        return hash('sha256', $rawToken . '|' . $fingerprint);
    }

    /**
     * Issue a new trusted device token, persist its hash, and return both the raw token and cookie.
     */
    public function issueDeviceToken(TwoFactorAuthenticatable $user, ?string $deviceName, Request $request): array
    {
        $this->enforceMaxActiveLimit($user);

        $rawToken = bin2hex(random_bytes(32)); // 64 hex characters
        $tokenHash = $this->hashToken($rawToken, $request);
        $expiresAt = Carbon::now()->addDays($this->getValidityDays());

        $parsedDeviceName = $deviceName ?: $this->resolveDeviceName($request->userAgent());

        TrustedDevice::create([
            'user_id'           => $user->getAuthIdentifier(),
            'device_token_hash' => $tokenHash,
            'device_name'       => $parsedDeviceName,
            'user_agent'        => substr((string) $request->userAgent(), 0, 500),
            'ip_address'        => $request->ip(),
            'last_used_at'      => Carbon::now(),
            'expires_at'        => $expiresAt,
        ]);

        return [
            'raw_token' => $rawToken,
            'cookie'    => $this->makeCookie($rawToken, $expiresAt),
        ];
    }

    /**
     * Validate an incoming raw token against the user's active trusted devices.
     */
    public function isValid(TwoFactorAuthenticatable $user, ?string $rawToken, ?Request $request = null): bool
    {
        if (empty($rawToken) || strlen($rawToken) !== 64) {
            return false;
        }

        $tokenHash = $this->hashToken($rawToken, $request);

        $device = TrustedDevice::where('user_id', $user->getAuthIdentifier())
            ->where('device_token_hash', $tokenHash)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        return $device !== null;
    }

    /**
     * Touch the last used timestamp for an active device.
     */
    public function touch(TwoFactorAuthenticatable $user, string $rawToken, ?Request $request = null): void
    {
        $tokenHash = $this->hashToken($rawToken, $request);

        TrustedDevice::where('user_id', $user->getAuthIdentifier())
            ->where('device_token_hash', $tokenHash)
            ->where('expires_at', '>', Carbon::now())
            ->update([
                'last_used_at' => Carbon::now(),
            ]);
    }

    /**
     * Revoke a single trusted device belonging to the user.
     */
    public function revokeDevice(TwoFactorAuthenticatable $user, int $deviceId): bool
    {
        return TrustedDevice::where('user_id', $user->getAuthIdentifier())
            ->where('id', $deviceId)
            ->delete() > 0;
    }

    /**
     * Revoke all trusted devices for a user.
     */
    public function revokeAllDevices(TwoFactorAuthenticatable $user): int
    {
        return TrustedDevice::where('user_id', $user->getAuthIdentifier())->delete();
    }

    /**
     * List all active trusted devices for a user.
     */
    public function getActiveDevices(TwoFactorAuthenticatable $user)
    {
        return TrustedDevice::where('user_id', $user->getAuthIdentifier())
            ->where('expires_at', '>', Carbon::now())
            ->orderByDesc('last_used_at')
            ->limit($this->getMaxActiveDevices())
            ->get();
    }

    /**
     * Enforce maximum active trusted devices per user.
     */
    protected function enforceMaxActiveLimit(TwoFactorAuthenticatable $user): void
    {
        $max = $this->getMaxActiveDevices();
        $activeDevices = TrustedDevice::where('user_id', $user->getAuthIdentifier())
            ->where('expires_at', '>', Carbon::now())
            ->orderByDesc('last_used_at')
            ->get();

        if ($activeDevices->count() >= $max) {
            $excessCount = ($activeDevices->count() - $max) + 1;
            $devicesToDelete = $activeDevices->slice($max - $excessCount);

            foreach ($devicesToDelete as $device) {
                $device->delete();
            }
        }
    }

    /**
     * Build the secure HttpOnly cookie for the device token.
     */
    public function makeCookie(string $rawToken, Carbon $expiresAt): Cookie
    {
        $isSecure = config('app.env') !== 'local' && request()->isSecure();

        return Cookie::create(
            $this->getCookieName(),
            $rawToken,
            $expiresAt->getTimestamp(),
            '/',
            config('session.domain') ?? null,
            $isSecure,
            true, // httpOnly
            false,
            Cookie::SAMESITE_LAX
        );
    }

    public function makeForgetCookie(): Cookie
    {
        return Cookie::create(
            $this->getCookieName(),
            '',
            time() - 3600,
            '/',
            config('session.domain') ?? null
        );
    }

    public function resolvePlatformAndBrowser(?string $ua): string
    {
        $ua = (string) $ua;
        $platform = 'Unknown Platform';
        if (stripos($ua, 'Windows') !== false) {
            $platform = 'Windows';
        } elseif (stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS') !== false) {
            $platform = 'macOS';
        } elseif (stripos($ua, 'Linux') !== false) {
            $platform = 'Linux';
        } elseif (stripos($ua, 'Android') !== false) {
            $platform = 'Android';
        } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
            $platform = 'iOS';
        }

        $browser = 'Unknown Browser';
        if (stripos($ua, 'Edg') !== false) {
            $browser = 'Edge';
        } elseif (stripos($ua, 'Chrome') !== false && stripos($ua, 'Chromium') === false) {
            $browser = 'Chrome';
        } elseif (stripos($ua, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) {
            $browser = 'Safari';
        }

        return "{$browser} on {$platform}";
    }

    public function resolveDeviceName(?string $ua): string
    {
        return $this->resolvePlatformAndBrowser($ua);
    }
}

