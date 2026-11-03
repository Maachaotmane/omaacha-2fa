<?php

namespace Omaacha\TwoFactor\Managers;

use Omaacha\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Omaacha\TwoFactor\Managers\QrCode\QrCodeSvg;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Production-ready RFC 6238 TOTP Manager (30s period, SHA-1, 6 digits).
 * Fully compatible with Microsoft Authenticator, Google Authenticator, and standard RFC 6238 apps.
 */
class TotpManager
{
    public const TIME_STEP = 30;
    public const DIGITS = 6;

    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a cryptographically secure random Base32 secret key.
     */
    public function generateSecretKey(int $length = 32): string
    {
        $secret = '';
        $max = strlen(self::BASE32_CHARS) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, $max)];
        }

        return $secret;
    }

    /**
     * Generate the standard otpauth:// provisioning URI.
     */
    public function getProvisioningUri(TwoFactorAuthenticatable $user, string $secret): string
    {
        $issuer = config('two-factor.issuer', config('app.name', 'Laravel'));
        $accountName = rawurlencode($user->getEmailForTwoFactor());
        $encodedIssuer = rawurlencode($issuer);

        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            $encodedIssuer,
            $accountName,
            $secret,
            $encodedIssuer,
            self::DIGITS,
            self::TIME_STEP
        );
    }

    /**
     * Generate an inline data URI SVG for the provisioning URI.
     */
    public function getQrCodeSvg(string $provisioningUri, int $size = 200): string
    {
        return QrCodeSvg::generateSvgDataUri($provisioningUri, $size);
    }

    /**
     * Verify a 6-digit TOTP code against the secret key.
     * Checks current window +- 1 step (90 seconds total) for clock drift.
     * Prevents code reuse within the same window via cache.
     */
    public function verify(string $secret, string $code, ?string $userIdentifier = null, int $window = 1): bool
    {
        $cleanCode = trim($code);
        if (strlen($cleanCode) !== self::DIGITS || !ctype_digit($cleanCode)) {
            return false;
        }

        $currentTime = time();
        $currentCounter = (int) floor($currentTime / self::TIME_STEP);

        for ($drift = -$window; $drift <= $window; $drift++) {
            $testCounter = $currentCounter + $drift;
            $calculatedCode = $this->calculateTotp($secret, $testCounter);

            if (hash_equals($calculatedCode, $cleanCode)) {
                if ($userIdentifier !== null) {
                    $cacheKey = "2fa_used_{$userIdentifier}_{$testCounter}";
                    if (Cache::has($cacheKey)) {
                        return false;
                    }
                    Cache::put($cacheKey, true, (self::TIME_STEP * ($window * 2 + 1)));
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Calculate TOTP code for a specific counter.
     */
    public function calculateTotp(string $secret, int $counter): string
    {
        $secretBinary = $this->base32Decode($secret);
        $counterBinary = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $counterBinary, $secretBinary, true);

        $offset = ord(substr($hash, -1)) & 0x0F;
        $unpacked = unpack('N', substr($hash, $offset, 4));
        $value = ($unpacked[1] & 0x7FFFFFFF) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Decode a Base32 string into binary.
     */
    public function base32Decode(string $b32): string
    {
        $b32 = strtoupper(rtrim($b32, '='));
        $binary = '';
        $buffer = 0;
        $bitsLeft = 0;

        for ($i = 0, $len = strlen($b32); $i < $len; $i++) {
            $char = $b32[$i];
            $val = strpos(self::BASE32_CHARS, $char);
            if ($val === false) {
                throw new InvalidArgumentException("Invalid Base32 character: '{$char}'");
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $binary;
    }
}

