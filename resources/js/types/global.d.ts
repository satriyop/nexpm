import type { Auth } from '@/types/auth';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            notifications: Array<{
                id: string;
                data: {
                    assignment_id: number;
                    site_code: string;
                    location_name: string;
                    activity_type: string;
                    message: string;
                    url: string;
                    revision_comment?: string;
                };
                read_at: string | null;
                created_at: string;
            }>;
            unread_notifications_count: number;
            sla: {
                slow_days: number;
                stalled_days: number;
            };
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
