<?php

namespace Omaacha\TwoFactor\Managers\QrCode;

use InvalidArgumentException;
use RangeException;

/**
 * Pure PHP QR Code generator (ISO/IEC 18004 compliant).
 * Based on Project Nayuki's public domain QR Code reference implementation.
 */
class QrCodeSvg
{
    public const ECC_LOW = 0;
    public const ECC_MEDIUM = 1;
    public const ECC_QUARTILE = 2;
    public const ECC_HIGH = 3;

    private int $version;
    private int $size;
    private int $errorCorrectionLevel;
    private int $mask;
    private array $modules;
    private array $isFunction;

    public function __construct(int $version, int $ecc, array $dataCodewords, int $mask = -1)
    {
        if ($version < 1 || $version > 40) {
            throw new RangeException('Version out of range');
        }
        $this->version = $version;
        $this->size = $version * 4 + 17;
        $this->errorCorrectionLevel = $ecc;

        $this->modules = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->isFunction = array_fill(0, $this->size, array_fill(0, $this->size, false));

        $this->drawFunctionPatterns();
        $allCodewords = $this->addEccAndInterleave($dataCodewords);
        $this->drawCodewords($allCodewords);

        if ($mask === -1) {
            $minPenalty = PHP_INT_MAX;
            for ($i = 0; $i < 8; $i++) {
                $this->applyMask($i);
                $this->drawFormatBits($i);
                $penalty = $this->getPenaltyScore();
                if ($penalty < $minPenalty) {
                    $minPenalty = $penalty;
                    $mask = $i;
                }
                $this->applyMask($i);
            }
        }
        $this->mask = $mask;
        $this->applyMask($mask);
        $this->drawFormatBits($mask);
    }

    /**
     * Public static entrypoint to generate standalone vector SVG string.
     */
    public static function generate(string $text, int $size = 220, int $margin = 4): string
    {
        $qr = self::encodeText($text, self::ECC_MEDIUM);

        return $qr->toSvgString($margin, $size);
    }

    public static function encodeText(string $text, int $ecc = self::ECC_MEDIUM): self
    {
        $bytes = array_values(unpack('C*', $text));

        return self::encodeBinary($bytes, $ecc);
    }

    public static function encodeBinary(array $data, int $ecc = self::ECC_MEDIUM): self
    {
        $dataLen = count($data);
        for ($ver = 1; $ver <= 40; $ver++) {
            $capacity = self::getNumDataCodewords($ver, $ecc);
            $headerBits = 4 + ($ver < 10 ? 8 : 16);
            if ($dataLen * 8 + $headerBits <= $capacity * 8) {
                break;
            }
        }
        if ($ver > 40) {
            throw new RangeException('Data too long for QR Code');
        }

        $bb = [];
        // Mode: 0100 (byte mode)
        self::appendBits(4, 4, $bb);
        // Character count indicator
        self::appendBits($dataLen, $ver < 10 ? 8 : 16, $bb);
        // Data payload
        foreach ($data as $b) {
            self::appendBits($b, 8, $bb);
        }

        // Terminator: up to 4 zeroes
        $capacityBits = self::getNumDataCodewords($ver, $ecc) * 8;
        $terminator = min(4, $capacityBits - count($bb));
        for ($i = 0; $i < $terminator; $i++) {
            $bb[] = 0;
        }

        // Pad to 8-bit byte boundary
        while (count($bb) % 8 !== 0) {
            $bb[] = 0;
        }

        // Alternating pad bytes 0xEC, 0x11
        $pad = [0xEC, 0x11];
        $padIdx = 0;
        while (count($bb) < $capacityBits) {
            self::appendBits($pad[$padIdx % 2], 8, $bb);
            $padIdx++;
        }

        $dataCodewords = [];
        for ($i = 0; $i < count($bb); $i += 8) {
            $val = 0;
            for ($j = 0; $j < 8; $j++) {
                $val = ($val << 1) | $bb[$i + $j];
            }
            $dataCodewords[] = $val;
        }

        return new self($ver, $ecc, $dataCodewords);
    }

    private static function appendBits(int $val, int $len, array &$bb): void
    {
        for ($i = $len - 1; $i >= 0; $i--) {
            $bb[] = ($val >> $i) & 1;
        }
    }

    public function toSvgString(int $border = 4, int $size = 220): string
    {
        $total = $this->size + $border * 2;
        $svg = [];
        $svg[] = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" shape-rendering="crispEdges">',
            $total,
            $total,
            $size,
            $size
        );
        $svg[] = '<rect width="100%" height="100%" fill="#ffffff"/>';
        $path = '';
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->modules[$y][$x]) {
                    $px = $x + $border;
                    $py = $y + $border;
                    $path .= "M{$px},{$py}h1v1h-1z ";
                }
            }
        }
        if ($path !== '') {
            $svg[] = sprintf('<path d="%s" fill="#000000"/>', trim($path));
        }
        $svg[] = '</svg>';

        return implode("\n", $svg);
    }

    private function drawFunctionPatterns(): void
    {
        // Timing patterns (must run FIRST so finder patterns properly overwrite timing in the corners)
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunctionModule(6, $i, $i % 2 === 0);
            $this->setFunctionModule($i, 6, $i % 2 === 0);
        }

        // 3 Finder patterns
        $this->drawFinderPattern(3, 3);
        $this->drawFinderPattern($this->size - 4, 3);
        $this->drawFinderPattern(3, $this->size - 4);

        // Alignment patterns
        $alignPos = self::getAlignmentPatternPositions($this->version);
        $numAlign = count($alignPos);
        for ($i = 0; $i < $numAlign; $i++) {
            for ($j = 0; $j < $numAlign; $j++) {
                if (
                    ($i === 0 && $j === 0) ||
                    ($i === 0 && $j === $numAlign - 1) ||
                    ($i === $numAlign - 1 && $j === 0)
                ) {
                    continue;
                }
                $this->drawAlignmentPattern($alignPos[$i], $alignPos[$j]);
            }
        }

        // Format info dummy reservation
        $this->drawFormatBits(0);

        // Version info reservation
        $this->drawVersion();
    }

    private function drawFinderPattern(int $x, int $y): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $px = $x + $dx;
                $py = $y + $dy;
                if ($px >= 0 && $px < $this->size && $py >= 0 && $py < $this->size) {
                    $this->setFunctionModule($px, $py, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    private function drawAlignmentPattern(int $x, int $y): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $this->setFunctionModule($x + $dx, $y + $dy, $dist !== 1);
            }
        }
    }

    private function drawFormatBits(int $mask): void
    {
        $data = match ($this->errorCorrectionLevel) {
            self::ECC_LOW      => 1,
            self::ECC_MEDIUM   => 0,
            self::ECC_QUARTILE => 3,
            self::ECC_HIGH     => 2,
        } << 3 | $mask;

        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        // Top-left copy
        for ($i = 0; $i <= 5; $i++) {
            $this->setFunctionModule(8, $i, (($bits >> $i) & 1) !== 0);
        }
        $this->setFunctionModule(8, 7, (($bits >> 6) & 1) !== 0);
        $this->setFunctionModule(8, 8, (($bits >> 7) & 1) !== 0);
        $this->setFunctionModule(7, 8, (($bits >> 8) & 1) !== 0);
        for ($i = 9; $i < 15; $i++) {
            $this->setFunctionModule(14 - $i, 8, (($bits >> $i) & 1) !== 0);
        }

        // Second copy
        for ($i = 0; $i < 8; $i++) {
            $this->setFunctionModule($this->size - 1 - $i, 8, (($bits >> $i) & 1) !== 0);
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunctionModule(8, $this->size - 15 + $i, (($bits >> $i) & 1) !== 0);
        }
        $this->setFunctionModule(8, $this->size - 8, true); // Dark module
    }

    private function drawVersion(): void
    {
        if ($this->version < 7) {
            return;
        }

        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($this->version << 12) | $rem;

        for ($i = 0; $i < 18; $i++) {
            $bit = (($bits >> $i) & 1) !== 0;
            $a = $this->size - 11 + ($i % 3);
            $b = (int) floor($i / 3);
            $this->setFunctionModule($a, $b, $bit);
            $this->setFunctionModule($b, $a, $bit);
        }
    }

    private function setFunctionModule(int $x, int $y, bool $isDark): void
    {
        $this->modules[$y][$x] = $isDark;
        $this->isFunction[$y][$x] = true;
    }

    private function addEccAndInterleave(array $data): array
    {
        $numBlocks = self::NUM_ERROR_CORRECTION_BLOCKS[$this->errorCorrectionLevel][$this->version];
        $blockEccLen = self::ECC_CODEWORDS_PER_BLOCK[$this->errorCorrectionLevel][$this->version];
        $rawCodewords = (int) floor(self::getNumRawDataModules($this->version) / 8);
        $numShortBlocks = $numBlocks - ($rawCodewords % $numBlocks);
        $shortBlockLen = (int) floor($rawCodewords / $numBlocks);

        $blocks = [];
        $rs = self::reedSolomonComputeDivisor($blockEccLen);
        $k = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $blockDataLen = $shortBlockLen - $blockEccLen + ($i >= $numShortBlocks ? 1 : 0);
            $dat = array_slice($data, $k, $blockDataLen);
            $k += $blockDataLen;
            $ecc = self::reedSolomonComputeRemainder($dat, $rs);
            $blocks[] = ['data' => $dat, 'ecc' => $ecc];
        }

        $result = [];
        for ($i = 0; $i < $shortBlockLen - $blockEccLen + 1; $i++) {
            foreach ($blocks as $j => $b) {
                if ($i < count($b['data'])) {
                    $result[] = $b['data'][$i];
                }
            }
        }
        for ($i = 0; $i < $blockEccLen; $i++) {
            foreach ($blocks as $b) {
                $result[] = $b['ecc'][$i];
            }
        }

        return $result;
    }

    private function drawCodewords(array $allCodewords): void
    {
        $bitLen = count($allCodewords) * 8;
        $i = 0;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $bitLen) {
                        $this->modules[$y][$x] = (($allCodewords[$i >> 3] >> (7 - ($i & 7))) & 1) !== 0;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => (($x + $y) % 2 === 0),
                    1 => ($y % 2 === 0),
                    2 => ($x % 3 === 0),
                    3 => (($x + $y) % 3 === 0),
                    4 => ((intdiv($x, 3) + intdiv($y, 2)) % 2 === 0),
                    5 => (($x * $y) % 2 + ($x * $y) % 3 === 0),
                    6 => ((($x * $y) % 2 + ($x * $y) % 3) % 2 === 0),
                    7 => ((($x + $y) % 2 + ($x * $y) % 3) % 2 === 0),
                    default => false,
                };
                if ($invert) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    private function getPenaltyScore(): int
    {
        $result = 0;

        for ($y = 0; $y < $this->size; $y++) {
            $runColor = false;
            $runVal = 0;
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->modules[$y][$x] === $runColor) {
                    $runVal++;
                    if ($runVal === 5) {
                        $result += 3;
                    } elseif ($runVal > 5) {
                        $result++;
                    }
                } else {
                    $runColor = $this->modules[$y][$x];
                    $runVal = 1;
                }
            }
        }

        for ($x = 0; $x < $this->size; $x++) {
            $runColor = false;
            $runVal = 0;
            for ($y = 0; $y < $this->size; $y++) {
                if ($this->modules[$y][$x] === $runColor) {
                    $runVal++;
                    if ($runVal === 5) {
                        $result += 3;
                    } elseif ($runVal > 5) {
                        $result++;
                    }
                } else {
                    $runColor = $this->modules[$y][$x];
                    $runVal = 1;
                }
            }
        }

        for ($y = 0; $y < $this->size - 1; $y++) {
            for ($x = 0; $x < $this->size - 1; $x++) {
                $color = $this->modules[$y][$x];
                if (
                    $color === $this->modules[$y][$x + 1] &&
                    $color === $this->modules[$y + 1][$x] &&
                    $color === $this->modules[$y + 1][$x + 1]
                ) {
                    $result += 3;
                }
            }
        }

        return $result;
    }

    private static function reedSolomonComputeDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;

        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::reedSolomonMultiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::reedSolomonMultiply($root, 0x02);
        }

        return $result;
    }

    private static function reedSolomonComputeRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coef) {
                $result[$i] ^= self::reedSolomonMultiply($coef, $factor);
            }
        }

        return $result;
    }

    private static function reedSolomonMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z;
    }

    private static function getNumDataCodewords(int $ver, int $ecc): int
    {
        return (int) floor(self::getNumRawDataModules($ver) / 8) -
            self::ECC_CODEWORDS_PER_BLOCK[$ecc][$ver] *
            self::NUM_ERROR_CORRECTION_BLOCKS[$ecc][$ver];
    }

    private static function getNumRawDataModules(int $ver): int
    {
        $size = $ver * 4 + 17;
        $result = $size * $size;
        $result -= 64 * 3;
        $result -= 15 * 2 + 1;
        $result -= ($size - 16) * 2;

        $numAlign = count(self::getAlignmentPatternPositions($ver));
        if ($numAlign > 0) {
            $result -= ($numAlign * $numAlign - 3) * 25;
            $result += ($numAlign - 2) * 2 * 5;
        }
        if ($ver >= 7) {
            $result -= 18 * 2;
        }

        return $result;
    }

    private static function getAlignmentPatternPositions(int $ver): array
    {
        if ($ver === 1) {
            return [];
        }
        $num = (int) floor($ver / 7) + 2;
        $step = ($ver === 32) ? 26 : (int) ceil(($ver * 4 + 4) / ($num * 2 - 2)) * 2;
        $result = [6];
        for ($pos = $ver * 4 + 10, $i = 0; $i < $num - 1; $pos -= $step, $i++) {
            array_splice($result, 1, 0, [$pos]);
        }

        return $result;
    }

    private const NUM_ERROR_CORRECTION_BLOCKS = [
        [0, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        [0, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        [0, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        [0, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];

    private const ECC_CODEWORDS_PER_BLOCK = [
        [0, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [0, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        [0, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [0, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];
}
