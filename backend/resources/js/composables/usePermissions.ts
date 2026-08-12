import { computed } from 'vue';
import { useAuth } from './useAuth';

export function usePermissions() {
    const { user } = useAuth();

    const permissions = computed(() => user.value?.permissions ?? []);

    function can(permission: string): boolean {
        return permissions.value.includes(permission);
    }

    function canAny(required: string[]): boolean {
        return required.some((p) => permissions.value.includes(p));
    }

    function canAll(required: string[]): boolean {
        return required.every((p) => permissions.value.includes(p));
    }

    return {
        permissions,
        can,
        canAny,
        canAll,
    };
}
