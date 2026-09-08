import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * UI-level permission checks. These only hide controls — every action is
 * authorised again on the server, so a stale prop can never grant access.
 */
export function usePermissions() {
    const page = usePage();

    const permissions = computed(() => page.props.auth?.user?.permissions ?? []);
    const roles = computed(() => page.props.auth?.user?.roles ?? []);
    const isAdmin = computed(() => roles.value.includes('admin'));

    const can = (permission) =>
        isAdmin.value || permissions.value.includes(permission);

    const canAny = (list) => list.some((p) => can(p));

    return { permissions, roles, isAdmin, can, canAny };
}
