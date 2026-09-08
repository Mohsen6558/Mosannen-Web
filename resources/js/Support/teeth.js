/**
 * Tooth catalogue in FDI two-digit notation — the mirror of App\Support\Teeth.
 *
 * Quadrants, viewed as the clinician faces the patient (so the patient's
 * right appears on the viewer's left in an odontogram):
 *   permanent  1x upper-right  2x upper-left  3x lower-left  4x lower-right
 *   primary    5x upper-right  6x upper-left  7x lower-left  8x lower-right
 */

export const PERMANENT_QUADRANTS = { UR: 1, UL: 2, LL: 3, LR: 4 };
export const PRIMARY_QUADRANTS = { UR: 5, UL: 6, LL: 7, LR: 8 };

/** Anatomical name per position, for tooltips. */
const PERMANENT_NAMES = [
    'ثنایای میانی', 'ثنایای جانبی', 'نیش', 'آسیای کوچک اول', 'آسیای کوچک دوم',
    'آسیای بزرگ اول', 'آسیای بزرگ دوم', 'آسیای بزرگ سوم (عقل)',
];

const PRIMARY_NAMES = [
    'ثنایای میانی شیری', 'ثنایای جانبی شیری', 'نیش شیری',
    'آسیای اول شیری', 'آسیای دوم شیری',
];

const ARCH_LABEL = {
    1: 'بالا راست', 2: 'بالا چپ', 3: 'پایین چپ', 4: 'پایین راست',
    5: 'بالا راست', 6: 'بالا چپ', 7: 'پایین چپ', 8: 'پایین راست',
};

function build(quadrant, count) {
    return Array.from({ length: count }, (_, i) => String(quadrant * 10 + i + 1));
}

export const PERMANENT = {
    UR: build(1, 8), UL: build(2, 8), LL: build(3, 8), LR: build(4, 8),
};

export const PRIMARY = {
    UR: build(5, 5), UL: build(6, 5), LL: build(7, 5), LR: build(8, 5),
};

export const ALL_TEETH = [
    ...PERMANENT.UR, ...PERMANENT.UL, ...PERMANENT.LL, ...PERMANENT.LR,
    ...PRIMARY.UR, ...PRIMARY.UL, ...PRIMARY.LL, ...PRIMARY.LR,
];

export function isPrimary(code) {
    return Number(String(code)[0]) >= 5;
}

export function isUpper(code) {
    return ['1', '2', '5', '6'].includes(String(code)[0]);
}

export function toothName(code) {
    const q = Number(String(code)[0]);
    const n = Number(String(code)[1]);
    const name = isPrimary(code) ? PRIMARY_NAMES[n - 1] : PERMANENT_NAMES[n - 1];

    return `${name} ${ARCH_LABEL[q] ?? ''}`.trim();
}

/** Sort a set of codes into a stable, clinically conventional order. */
export function sortTeeth(codes) {
    return [...codes].sort((a, b) => Number(a) - Number(b));
}

/** Compact label for a list: "۱۳، ۱۴، ۳۶" */
export function summarize(codes, toPersian) {
    return sortTeeth(codes).map((c) => (toPersian ? toPersian(c) : c)).join('، ');
}
