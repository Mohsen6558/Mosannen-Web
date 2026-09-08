import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';

import AppLayout from '@/Layouts/AppLayout.vue';
import { formatMoney, formatNumber, toPersianDigits } from '@/Support/format';
import { jalali } from '@/Support/jalali';

const appName = document.querySelector('title')?.textContent?.trim() || 'مسنن';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),

    resolve: async (name) => {
        const page = await resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        );

        // Auth screens opt out of the app shell by exporting `layout = null`.
        if (page.default.layout === undefined) {
            page.default.layout = AppLayout;
        }

        return page;
    },

    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        // Global formatting helpers — used constantly in templates, so they
        // earn a place on the global config rather than an import per file.
        app.config.globalProperties.$money = formatMoney;
        app.config.globalProperties.$num = formatNumber;
        app.config.globalProperties.$fa = toPersianDigits;
        app.config.globalProperties.$jalali = jalali;

        app.mount(el);
    },

    progress: {
        color: '#21a094',
        showSpinner: false,
    },
});

// Keep the browser tab's theme colour in step with the active theme.
router.on('navigate', () => {
    const dark = document.documentElement.classList.contains('dark');
    document
        .querySelector('meta[name="theme-color"]')
        ?.setAttribute('content', dark ? '#171614' : '#0f766e');
});
