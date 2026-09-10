/**
 * Chart palette and chrome.
 *
 * The categorical slots were validated with the data-viz validator against
 * both surfaces this app renders on — the white card in light mode and
 * #24221f in dark — and pass every gate in both, so the two modes share one
 * set of hues rather than an automatic flip:
 *
 *   light (surface #ffffff): lightness PASS · chroma PASS ·
 *     worst adjacent CVD ΔE 14.0 (protan) · normal-vision ΔE 23.8 · contrast PASS
 *   dark  (surface #24221f): same four, all gates PASS
 *
 * Slot 1 is the brand teal at 500. The brand's own 600 step (#178078) was
 * rejected by the chroma floor — as a categorical slot it reads grey.
 *
 * Only the ink, grid and axis colours differ per mode.
 */

export const SERIES = ['#21a094', '#d97706', '#2563eb', '#e11d48'];

/** Sequential ramp for magnitude (tooth heat map). One hue, light → dark. */
export const SEQUENTIAL = [
    '#d3f5ee', '#abe9df', '#74d7ca', '#3dbcae', '#21a094', '#178078', '#166761',
];

/** Reserved for state, never for "series 5". Always shipped with a label. */
export const STATUS = {
    good: '#16a34a',
    warning: '#d97706',
    serious: '#ea580c',
    critical: '#e11d48',
};

const CHROME = {
    light: {
        ink: '#1f1e1c',
        muted: '#78746e',
        grid: '#eeece8',
        axis: '#d6d1c9',
        tooltipBg: 'rgba(255,255,255,0.98)',
        tooltipBorder: '#e8e5e0',
    },
    dark: {
        ink: '#f7f7f6',
        muted: '#8b8378',
        grid: '#332f2b',
        axis: '#3a3733',
        tooltipBg: 'rgba(36,34,31,0.98)',
        tooltipBorder: '#3a3733',
    },
};

export function chrome(isDark) {
    return isDark ? CHROME.dark : CHROME.light;
}

/**
 * Pick a step from the sequential ramp for a 0..1 magnitude.
 * Zero returns null so "no data" stays visually absent rather than pale-teal.
 */
export function rampStep(ratio) {
    if (!ratio || ratio <= 0) return null;
    const i = Math.min(SEQUENTIAL.length - 1, Math.max(1, Math.round(ratio * (SEQUENTIAL.length - 1))));
    return SEQUENTIAL[i];
}
