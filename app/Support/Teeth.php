<?php

namespace App\Support;

/**
 * Tooth catalogue in FDI two-digit notation, and the translation from the
 * legacy application's `ToothName` strings.
 *
 * Legacy format was a comma-separated list of control names such as
 * "_3UR,_EUL,_6LL,_bitewing", where the first token is the tooth (1-8 for
 * permanent, A-E for primary) and the last two letters are the quadrant.
 */
final class Teeth
{
    /** Quadrant prefixes, permanent dentition. */
    private const PERMANENT_QUADRANT = ['UR' => 1, 'UL' => 2, 'LL' => 3, 'LR' => 4];

    /** Quadrant prefixes, primary dentition. */
    private const PRIMARY_QUADRANT = ['UR' => 5, 'UL' => 6, 'LL' => 7, 'LR' => 8];

    private const PRIMARY_LETTERS = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5];

    /** @return list<string> every valid permanent tooth code */
    public static function permanent(): array
    {
        $out = [];
        foreach (self::PERMANENT_QUADRANT as $q) {
            for ($i = 1; $i <= 8; $i++) {
                $out[] = (string) ($q * 10 + $i);
            }
        }

        return $out;
    }

    /** @return list<string> every valid primary (deciduous) tooth code */
    public static function primary(): array
    {
        $out = [];
        foreach (self::PRIMARY_QUADRANT as $q) {
            for ($i = 1; $i <= 5; $i++) {
                $out[] = (string) ($q * 10 + $i);
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return [...self::permanent(), ...self::primary()];
    }

    public static function isValid(string $code): bool
    {
        return in_array($code, self::all(), true);
    }

    public static function isPrimary(string $code): bool
    {
        return in_array($code, self::primary(), true);
    }

    /** Human label, e.g. "۱۳ (نیش بالا راست)" is built in the UI; this is the bare arch. */
    public static function quadrantLabel(string $code): string
    {
        return match ($code[0] ?? '') {
            '1', '5' => 'بالا راست',
            '2', '6' => 'بالا چپ',
            '3', '7' => 'پایین چپ',
            '4', '8' => 'پایین راست',
            default => '',
        };
    }

    /**
     * Translate one legacy token ("_3UR", "_EUL") to an FDI code.
     * Returns null for tokens that are not teeth, such as "_bitewing".
     */
    public static function fromLegacyToken(string $token): ?string
    {
        $token = ltrim(trim($token), '_');

        if ($token === '' || ! preg_match('/^([1-8A-Ea-e])(UR|UL|LL|LR)$/', $token, $m)) {
            return null;
        }

        $tooth = strtoupper($m[1]);
        $quadrant = strtoupper($m[2]);

        if (isset(self::PRIMARY_LETTERS[$tooth])) {
            return (string) (self::PRIMARY_QUADRANT[$quadrant] * 10 + self::PRIMARY_LETTERS[$tooth]);
        }

        return (string) (self::PERMANENT_QUADRANT[$quadrant] * 10 + (int) $tooth);
    }

    /**
     * Translate a whole legacy `ToothName` value into FDI codes.
     *
     * @return array{teeth: list<string>, flags: list<string>}
     *         `flags` collects non-tooth markers (currently only "bitewing")
     *         so the importer can preserve them in the record's notes.
     */
    public static function fromLegacyString(?string $value): array
    {
        $teeth = [];
        $flags = [];

        foreach (preg_split('/[,\s]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            if ($code = self::fromLegacyToken($token)) {
                $teeth[] = $code;
            } elseif (($clean = strtolower(ltrim(trim($token), '_'))) !== '') {
                $flags[] = $clean;
            }
        }

        return [
            'teeth' => array_values(array_unique($teeth)),
            'flags' => array_values(array_unique($flags)),
        ];
    }
}
