<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { onKeyStroke, useStorage } from '@vueuse/core';
import UiFlash from '@/Components/UiFlash.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useTheme } from '@/Composables/useTheme';
import { initials } from '@/Support/format';
import { jalali, todayIso } from '@/Support/jalali';

const page = usePage();
const { can, canAny } = usePermissions();
const { theme, cycle } = useTheme();

const mobileOpen = ref(false);
const collapsed = useStorage('mosannen.sidebar.collapsed', false);
const userMenuOpen = ref(false);

const user = computed(() => page.props.auth?.user);
const clinic = computed(() => page.props.clinic ?? {});
const today = jalali(todayIso(), { full: true, withWeekday: true });

const NAV = [
    {
        heading: null,
        items: [
            { name: 'داشبورد', route: 'dashboard', icon: 'grid', permission: null },
        ],
    },
    {
        heading: 'درمانگاه',
        items: [
            { name: 'پرونده بیماران', route: 'patients.index', icon: 'users', permission: 'patients.view' },
            { name: 'درمان‌ها', route: 'treatments.index', icon: 'tooth', permission: 'treatments.view' },
            { name: 'پرداخت‌ها', route: 'payments.index', icon: 'wallet', permission: 'payments.view' },
            { name: 'عکس‌برداری', route: 'images.index', icon: 'image', permission: 'images.view' },
            { name: 'نسخه‌ها', route: 'prescriptions.index', icon: 'pill', permission: 'prescriptions.view' },
        ],
    },
    {
        heading: 'مدیریت',
        items: [
            { name: 'انبار', route: 'stock.index', icon: 'box', permission: 'stock.view' },
            { name: 'پیامک', route: 'sms.index', icon: 'chat', permission: 'sms.view' },
            { name: 'گزارش‌ها', route: 'reports.index', icon: 'chart', permission: 'reports.view' },
        ],
    },
    {
        heading: 'اطلاعات پایه',
        items: [
            { name: 'خدمات و تعرفه', route: 'catalog.treatments.index', icon: 'list', permission: 'catalog.manage' },
            { name: 'داروها', route: 'catalog.drugs.index', icon: 'pill', permission: 'catalog.manage' },
            { name: 'بیمه‌ها', route: 'catalog.insurances.index', icon: 'shield', permission: 'catalog.manage' },
            { name: 'نحوه پرداخت', route: 'catalog.payment-types.index', icon: 'card', permission: 'catalog.manage' },
            { name: 'کاربران', route: 'users.index', icon: 'key', permission: 'users.manage' },
        ],
    },
];

const ICONS = {
    grid: 'M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z',
    users: 'M17 20h5v-2a3 3 0 00-5.36-1.86M17 20H7m10 0v-2c0-.66-.13-1.3-.36-1.86m0 0A5 5 0 007 18v2m10-8a3 3 0 11-6 0 3 3 0 016 0zm6-3a2 2 0 11-4 0 2 2 0 014 0zM7 20H2v-2a3 3 0 015.36-1.86M7 20v-2c0-.66.13-1.3.36-1.86m0 0a5 5 0 00-5.36 1.86M7 9a2 2 0 11-4 0 2 2 0 014 0z',
    tooth: 'M12 3c-2.2 0-3 1-4.5 1S5 3.4 4.2 4.6C3.2 6.1 3.6 8.6 4.3 11c.6 2 .7 3.4.9 5.2.2 1.7.5 3.8 1.9 3.8 1.3 0 1.5-1.6 1.8-3.4.3-1.8.6-3.1 1.6-3.1s1.3 1.3 1.6 3.1c.3 1.8.5 3.4 1.8 3.4 1.4 0 1.7-2.1 1.9-3.8.2-1.8.3-3.2.9-5.2.7-2.4 1.1-4.9.1-6.4C17.5 3.4 16 4 14.5 4S14.2 3 12 3z',
    wallet: 'M3 10h18M3 10a2 2 0 012-2h14a2 2 0 012 2M3 10v8a2 2 0 002 2h14a2 2 0 002-2v-8M16 14h2',
    image: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
    pill: 'M10.5 20.5a5 5 0 01-7-7l6-6a5 5 0 017 7l-6 6zM8 11l5 5',
    box: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    chat: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
    chart: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    list: 'M4 6h16M4 12h16M4 18h7',
    shield: 'M9 12l2 2 4-4M12 3l7 4v5c0 4.42-3.05 8.55-7 9.75C8.05 20.55 5 16.42 5 12V7l7-4z',
    card: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    key: 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.74 5.74L13 15l-2 2-2 2H7v-2l2-2-2-2 .26-.26A6 6 0 1121 9z',
};

const visibleNav = computed(() =>
    NAV.map((g) => ({
        ...g,
        items: g.items.filter((i) => !i.permission || can(i.permission)),
    })).filter((g) => g.items.length),
);

function isActive(name) {
    // Highlight the parent while on any nested route (patients.index ↔ patients.show)
    const base = name.replace(/\.index$/, '');
    return route().current(name) || route().current(`${base}.*`);
}

onKeyStroke('k', (e) => {
    if (e.metaKey || e.ctrlKey) {
        e.preventDefault();
        router.visit(route('patients.index', { focus: 'search' }));
    }
});

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="min-h-full">
        <UiFlash />

        <!-- Mobile overlay -->
        <Transition name="fade">
            <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-surface-950/50 lg:hidden" @click="mobileOpen = false" />
        </Transition>

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 start-0 z-50 flex flex-col border-e border-surface-200 bg-white transition-[width,transform] duration-200 dark:border-surface-800 dark:bg-surface-900"
            :class="[
                collapsed ? 'w-[68px]' : 'w-64',
                mobileOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0',
            ]"
        >
            <div class="flex h-16 shrink-0 items-center gap-2.5 border-b border-surface-200 px-4 dark:border-surface-800">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white">
                    <svg class="size-5" viewBox="0 0 24 24" fill="currentColor"><path :d="ICONS.tooth" /></svg>
                </div>
                <div v-if="!collapsed" class="min-w-0">
                    <p class="truncate text-sm font-bold text-ink-900 dark:text-ink-50">{{ clinic.name || 'مسنن' }}</p>
                    <p class="truncate text-[11px] text-ink-500">سامانه مدیریت کلینیک</p>
                </div>
            </div>

            <nav class="flex-1 space-y-4 overflow-y-auto px-3 py-4">
                <div v-for="(group, gi) in visibleNav" :key="gi">
                    <p
                        v-if="group.heading && !collapsed"
                        class="mb-1.5 px-2 text-[11px] font-semibold tracking-wide text-ink-300 uppercase"
                    >
                        {{ group.heading }}
                    </p>
                    <ul class="space-y-0.5">
                        <li v-for="item in group.items" :key="item.route">
                            <Link
                                :href="route(item.route)"
                                class="group flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors"
                                :class="isActive(item.route)
                                    ? 'bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-200'
                                    : 'text-ink-700 hover:bg-surface-100 dark:text-ink-100 dark:hover:bg-surface-800'"
                                :title="collapsed ? item.name : null"
                                @click="mobileOpen = false"
                            >
                                <svg
                                    class="size-5 shrink-0"
                                    :class="isActive(item.route) ? 'text-brand-600 dark:text-brand-300' : 'text-ink-500'"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="ICONS[item.icon]" />
                                </svg>
                                <span v-if="!collapsed" class="truncate">{{ item.name }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="shrink-0 border-t border-surface-200 p-3 dark:border-surface-800">
                <button
                    type="button"
                    class="hidden w-full items-center gap-3 rounded-lg px-2.5 py-2 text-sm text-ink-500 transition-colors hover:bg-surface-100 lg:flex dark:hover:bg-surface-800"
                    @click="collapsed = !collapsed"
                >
                    <svg class="size-5 shrink-0 transition-transform" :class="collapsed ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                    </svg>
                    <span v-if="!collapsed">جمع کردن</span>
                </button>
            </div>
        </aside>

        <!-- Main -->
        <div class="transition-[padding] duration-200" :class="collapsed ? 'lg:pe-[68px]' : 'lg:pe-64'">
            <header
                class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-surface-200 bg-surface-50/80 px-4 backdrop-blur-md sm:px-6 dark:border-surface-800 dark:bg-surface-950/80"
            >
                <button
                    type="button"
                    class="rounded-lg p-2 text-ink-700 hover:bg-surface-200 lg:hidden dark:text-ink-100 dark:hover:bg-surface-800"
                    aria-label="منو"
                    @click="mobileOpen = true"
                >
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>

                <div class="min-w-0 flex-1">
                    <slot name="header">
                        <h1 class="truncate text-base font-semibold text-ink-900 dark:text-ink-50">
                            {{ $page.props.title ?? '' }}
                        </h1>
                    </slot>
                </div>

                <p class="hidden text-xs text-ink-500 md:block">{{ today }}</p>

                <button
                    type="button"
                    class="rounded-lg p-2 text-ink-700 transition-colors hover:bg-surface-200 dark:text-ink-100 dark:hover:bg-surface-800"
                    :title="`تم: ${theme}`"
                    aria-label="تغییر تم"
                    @click="cycle"
                >
                    <svg v-if="theme === 'dark'" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                    <svg v-else-if="theme === 'light'" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    <svg v-else class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                </button>

                <div class="relative">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg p-1 transition-colors hover:bg-surface-200 dark:hover:bg-surface-800"
                        @click="userMenuOpen = !userMenuOpen"
                    >
                        <span class="flex size-8 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white">
                            {{ initials(user?.full_name || user?.name || '') }}
                        </span>
                        <span class="hidden max-w-32 truncate text-sm font-medium sm:block">{{ user?.full_name || user?.name }}</span>
                    </button>

                    <Transition name="pop">
                        <div
                            v-if="userMenuOpen"
                            class="absolute end-0 z-40 mt-2 w-52 overflow-hidden rounded-lg border border-surface-200 bg-white py-1 shadow-[var(--shadow-overlay)] dark:border-surface-700 dark:bg-surface-900"
                            @click="userMenuOpen = false"
                        >
                            <div class="border-b border-surface-200 px-3 py-2 dark:border-surface-800">
                                <p class="truncate text-sm font-medium">{{ user?.full_name || user?.name }}</p>
                                <p class="truncate text-[11px] text-ink-500">{{ (user?.roles ?? []).join('، ') }}</p>
                            </div>
                            <Link :href="route('profile.edit')" class="block px-3 py-2 text-sm hover:bg-surface-100 dark:hover:bg-surface-800">
                                تغییر رمز عبور
                            </Link>
                            <button type="button" class="block w-full px-3 py-2 text-start text-sm text-danger-500 hover:bg-danger-50 dark:hover:bg-danger-600/10" @click="logout">
                                خروج
                            </button>
                        </div>
                    </Transition>
                </div>
            </header>

            <main class="p-4 sm:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
