import { isValidJalaaliDate, jalaaliMonthLength, toGregorian as gregorianOf, toJalaali } from 'jalaali-js';
import { toEnglishDigits, toPersianDigits } from '@/Support/format';

export const MONTH_NAMES = [
    'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
];

// Saturday-first, matching the Iranian week.
export const WEEKDAY_NAMES = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
export const WEEKDAY_SHORT = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

const pad = (n) => String(n).padStart(2, '0');

/**
 * The API always speaks ISO Gregorian dates (YYYY-MM-DD). Jalali exists only
 * at the presentation edge — this module is the single place that converts.
 */
export function toJalali(iso) {
    if (!iso) return null;
    const d = iso instanceof Date ? iso : new Date(String(iso).slice(0, 10) + 'T00:00:00');
    if (Number.isNaN(d.getTime())) return null;
    const { jy, jm, jd } = toJalaali(d.getFullYear(), d.getMonth() + 1, d.getDate());
    return { jy, jm, jd };
}

export function toGregorian(jy, jm, jd) {
    const { gy, gm, gd } = gregorianOf(jy, jm, jd);
    return `${gy}-${pad(gm)}-${pad(gd)}`;
}

/** Format an ISO date as a Jalali string. `full` adds the month name. */
export function jalali(iso, { full = false, persian = true, withWeekday = false } = {}) {
    const j = toJalali(iso);
    if (!j) return '';

    let out = full
        ? `${j.jd} ${MONTH_NAMES[j.jm - 1]} ${j.jy}`
        : `${j.jy}/${pad(j.jm)}/${pad(j.jd)}`;

    if (withWeekday) {
        const d = new Date(String(iso).slice(0, 10) + 'T00:00:00');
        out = `${WEEKDAY_NAMES[(d.getDay() + 1) % 7]}، ${out}`;
    }

    return persian ? toPersianDigits(out) : out;
}

/** Format an ISO datetime as Jalali date + HH:mm. */
export function jalaliDateTime(iso, opts = {}) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return jalali(iso, opts);
    const time = `${pad(d.getHours())}:${pad(d.getMinutes())}`;
    const date = jalali(iso, opts);
    return `${date} ${opts.persian === false ? time : toPersianDigits(time)}`;
}

/** Parse "۱۴۰۳/۰۵/۱۲" or "1403-5-12" into an ISO Gregorian date. */
export function parseJalali(input) {
    if (!input) return null;
    const parts = toEnglishDigits(String(input)).split(/[/\-.]/).map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) return null;
    const [jy, jm, jd] = parts;
    if (!isValidJalaaliDate(jy, jm, jd)) return null;
    return toGregorian(jy, jm, jd);
}

export function todayJalali() {
    const now = new Date();
    return toJalaali(now.getFullYear(), now.getMonth() + 1, now.getDate());
}

export function todayIso() {
    const n = new Date();
    return `${n.getFullYear()}-${pad(n.getMonth() + 1)}-${pad(n.getDate())}`;
}

export function daysInJalaliMonth(jy, jm) {
    return jalaaliMonthLength(jy, jm);
}

/** Weekday index (0 = Saturday) of the 1st of a Jalali month. */
export function firstWeekdayOfMonth(jy, jm) {
    const { gy, gm, gd } = gregorianOf(jy, jm, 1);
    return (new Date(gy, gm - 1, gd).getDay() + 1) % 7;
}

/** Human relative label used in lists: "امروز" / "دیروز" / full date. */
export function relativeDay(iso) {
    if (!iso) return '';
    const today = todayIso();
    if (String(iso).slice(0, 10) === today) return 'امروز';
    const y = new Date();
    y.setDate(y.getDate() - 1);
    const yIso = `${y.getFullYear()}-${pad(y.getMonth() + 1)}-${pad(y.getDate())}`;
    if (String(iso).slice(0, 10) === yIso) return 'دیروز';
    return jalali(iso, { full: true });
}
