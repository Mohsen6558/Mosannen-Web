<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

/**
 * Jalali ⇄ Gregorian conversion.
 *
 * The database stores Gregorian dates. This class exists for two callers:
 * the legacy importer (which reads Jalali strings out of SQL Server) and the
 * PDF/print templates (which must render Jalali).
 */
final class JalaliDate
{
    private const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const AR_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /** Normalise Persian/Arabic digits to ASCII. */
    public static function toEnglishDigits(?string $value): string
    {
        return str_replace(
            [...self::FA_DIGITS, ...self::AR_DIGITS],
            [...range(0, 9), ...range(0, 9)],
            (string) $value,
        );
    }

    public static function toPersianDigits(string|int|null $value): string
    {
        return str_replace(range(0, 9), self::FA_DIGITS, (string) $value);
    }

    /**
     * Parse a legacy Jalali date string into a Gregorian date.
     *
     * Handles the formats the old application actually wrote:
     *   "1403/05/12", "1403/5/2", "۱۴۰۳/۰۵/۱۲", "1403-05-12",
     *   and values with a trailing time ("1403/05/12 14:30:00").
     *
     * Returns null for empty, malformed or out-of-range values rather than
     * throwing — the importer records them as skipped rows instead of
     * aborting a multi-hour run.
     */
    public static function parse(?string $value): ?CarbonImmutable
    {
        $value = trim(self::toEnglishDigits($value));

        if ($value === '') {
            return null;
        }

        // Drop any time component; dates and times were separate columns.
        $value = preg_split('/[\sT]/', $value)[0];

        if (! preg_match('/^(\d{2,4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $value, $m)) {
            return null;
        }

        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];

        // Two-digit years appear in a handful of very old rows.
        if ($y < 1000) {
            $y += 1300;
        }

        if ($mo < 1 || $mo > 12 || $d < 1 || $d > 31) {
            return null;
        }

        if (! CalendarUtils::checkDate($y, $mo, $d, true)) {
            return null;
        }

        return CarbonImmutable::instance((new Jalalian($y, $mo, $d))->toCarbon())->startOfDay();
    }

    /** Format a Gregorian date as a Jalali string, e.g. "۱۴۰۳/۰۵/۱۲". */
    public static function format(
        \DateTimeInterface|string|null $date,
        string $format = 'Y/m/d',
        bool $persianDigits = true,
    ): string {
        if ($date === null || $date === '') {
            return '';
        }

        $carbon = $date instanceof \DateTimeInterface
            ? Carbon::instance($date)
            : Carbon::parse($date);

        $out = Jalalian::fromCarbon($carbon)->format($format);

        return $persianDigits ? self::toPersianDigits($out) : $out;
    }

    /** Long form used on printed documents: "۱۲ مرداد ۱۴۰۳". */
    public static function long(\DateTimeInterface|string|null $date): string
    {
        return self::format($date, 'j F Y');
    }
}
