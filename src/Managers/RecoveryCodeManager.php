<?php

namespace Omaacha\TwoFactor\Managers;

use Illuminate\Support\Str;
use Omaacha\TwoFactor\Contracts\TwoFactorAuthenticatable;

class RecoveryCodeManager
{
    public const CODE_COUNT = 10;

    /**
     * Generate 10 cryptographically random recovery codes (formatted as XXXX-XXXX).
     *
     * @return array<int, string> Plaintext recovery codes
     */
    public function generatePlaintextCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::CODE_COUNT; $i++) {
            $part1 = strtoupper(Str::random(4));
            $part2 = strtoupper(Str::random(4));
            $codes[] = sprintf('%s-%s', $part1, $part2);
        }

        return $codes;
    }

    /**
     * Hash plaintext codes for secure storage.
     *
     * @param array<int, string> $plaintextCodes
     * @return array<int, array{code_hash: string, used: bool}>
     */
    public function prepareHashedCodes(array $plaintextCodes): array
    {
        $hashed = [];
        foreach ($plaintextCodes as $code) {
            $normalized = $this->normalizeCode($code);
            $hashed[] = [
                'code_hash' => hash('sha256', $normalized),
                'used'      => false,
            ];
        }

        return $hashed;
    }

    /**
     * Verify and consume a single-use recovery code.
     */
    public function verifyAndConsume(TwoFactorAuthenticatable $user, string $inputCode): bool
    {
        $storedCodes = $user->getTwoFactorRecoveryCodes();
        if (empty($storedCodes) || !is_array($storedCodes)) {
            return false;
        }

        $normalizedInput = $this->normalizeCode($inputCode);
        $inputHash = hash('sha256', $normalizedInput);

        $matchedIndex = null;
        foreach ($storedCodes as $index => $item) {
            if (!empty($item['used'])) {
                continue;
            }

            if (hash_equals($item['code_hash'], $inputHash)) {
                $matchedIndex = $index;
                break;
            }
        }

        if ($matchedIndex === null) {
            return false;
        }

        // Mark as used atomically
        $storedCodes[$matchedIndex]['used'] = true;
        $user->setTwoFactorRecoveryCodes($storedCodes);
        $user->save();

        return true;
    }

    /**
     * Normalize recovery code: remove dashes/spaces and uppercase.
     */
    public function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }
}

