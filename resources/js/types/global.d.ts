import type { Auth } from '@/types/auth';
import type { PlatformShared } from '@/types/platform';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            platform: PlatformShared;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
