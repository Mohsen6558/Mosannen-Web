import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8123';
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const page = await browser.newPage({ viewport: { width: 1500, height: 1000 }, deviceScaleFactor: 2 });

const issues = [];
page.on('pageerror', (e) => issues.push('PAGEERROR: ' + e.message));
page.on('response', (r) => { if (r.status() >= 400) issues.push(`HTTP ${r.status()} ${r.url()}`); });

await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
await page.fill('input[autocomplete="username"]', 'admin');
await page.fill('input[autocomplete="current-password"]', 'password');
await Promise.all([
    page.waitForResponse((r) => r.request().method() === 'POST' && r.url().includes('/login')),
    page.click('button[type="submit"]'),
]);
await page.waitForTimeout(1400);

const tabs = [
    ['financial', 'r-1-financial'],
    ['receivables', 'r-2-receivables'],
    ['clinical', 'r-3-clinical'],
    ['practitioners', 'r-4-practitioners'],
    ['patients', 'r-5-patients'],
    ['appointments', 'r-6-appointments'],
];

for (const [tab, name] of tabs) {
    await page.goto(`${BASE}/reports?report=${tab}&from=2025-01-01&to=2026-09-10`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500); // let ECharts finish its animation
    const canvases = await page.locator('canvas').count();
    await page.screenshot({ path: `/tmp/shots/${name}.png`, fullPage: true });
    console.log(`  ${name.padEnd(20)} canvases: ${canvases}`);
}

await browser.close();
console.log(issues.length ? '\nISSUES:\n' + [...new Set(issues)].join('\n') : '\nno errors');
