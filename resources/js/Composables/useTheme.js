import { ref, watch } from 'vue';

const STORAGE_KEY = 'mosannen.theme';
const theme = ref(localStorage.getItem(STORAGE_KEY) || 'system');

function apply(value) {
    const dark =
        value === 'dark' ||
        (value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
}

apply(theme.value);
watch(theme, (v) => {
    localStorage.setItem(STORAGE_KEY, v);
    apply(v);
});

// Follow the OS while the user is on "system".
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (theme.value === 'system') apply('system');
});

export function useTheme() {
    return {
        theme,
        setTheme: (v) => (theme.value = v),
        cycle: () => {
            const order = ['light', 'dark', 'system'];
            theme.value = order[(order.indexOf(theme.value) + 1) % order.length];
        },
    };
}
