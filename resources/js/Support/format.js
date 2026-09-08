const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

/** Convert ASCII digits in a string to Persian digits. */
export function toPersianDigits(value) {
    if (value === null || value === undefined) return '';
    return String(value).replace(/\d/g, (d) => FA_DIGITS[+d]);
}

/** Convert Persian/Arabic digits back to ASCII, for parsing user input. */
export function toEnglishDigits(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 1776))
        .replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 1632));
}

/** Group a number with thousands separators. */
export function formatNumber(value, { persian = true } = {}) {
    if (value === null || value === undefined || value === '') return '';
    const n = Number(toEnglishDigits(String(value)).replace(/,/g, ''));
    if (Number.isNaN(n)) return String(value);
    const out = n.toLocaleString('en-US');
    return persian ? toPersianDigits(out) : out;
}

/**
 * Money is stored as whole Rial in the database. `display: 'toman'` divides by
 * ten for presentation only — never round-trip a Toman value back to the API.
 */
export function formatMoney(rial, { display = 'rial', suffix = true, persian = true } = {}) {
    if (rial === null || rial === undefined || rial === '') return '';
    const n = Number(rial);
    if (Number.isNaN(n)) return String(rial);

    const amount = display === 'toman' ? Math.round(n / 10) : n;
    const text = formatNumber(amount, { persian });
    if (!suffix) return text;

    return `${text} ${display === 'toman' ? 'تومان' : 'ریال'}`;
}

/** Strip separators from a money input so it can be posted as an integer. */
export function parseMoney(value) {
    const cleaned = toEnglishDigits(String(value ?? '')).replace(/[^\d-]/g, '');
    return cleaned === '' ? null : Number(cleaned);
}

/** Iranian mobile numbers, normalised to 09xxxxxxxxx. */
export function normalizeMobile(value) {
    let v = toEnglishDigits(String(value ?? '')).replace(/[^\d+]/g, '');
    if (v.startsWith('+98')) v = '0' + v.slice(3);
    else if (v.startsWith('0098')) v = '0' + v.slice(4);
    else if (v.startsWith('98') && v.length === 12) v = '0' + v.slice(2);
    else if (v.startsWith('9') && v.length === 10) v = '0' + v;
    return v;
}

export function initials(name = '') {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((p) => p[0] ?? '')
        .join('');
}
