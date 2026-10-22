import { usePage } from '@inertiajs/react';
import type { Auth } from '@/types';

/**
 * Whether the signed-in person holds a permission in some scope. This only
 * decides what to show; the server checks every request.
 */
export function useCan(permission: string): boolean {
    const { auth } = usePage<{ auth: Auth }>().props;

    return auth.abilities?.permissions.includes(permission) ?? false;
}
