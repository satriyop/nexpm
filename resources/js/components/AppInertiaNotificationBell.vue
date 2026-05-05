<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';
import * as NotificationActions from '@/actions/App/Http/Controllers/NotificationController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const page = usePage();

const notifications = computed(
    () =>
        (
            page.props as {
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
            }
        ).notifications ?? [],
);

const unreadCount = computed(() => (page.props as { unread_notifications_count: number }).unread_notifications_count ?? 0);

function relativeTime(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffSecs < 60) return 'just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return date.toLocaleDateString();
}

function handleNotificationClick(notification: (typeof notifications.value)[number]) {
    router.visit(NotificationActions.markRead({ id: notification.id }).url, {
        method: 'patch',
        preserveScroll: true,
        onFinish: () => {
            router.visit(notification.data.url);
        },
    });
}

function markAllRead() {
    router.visit(NotificationActions.markAllRead().url, {
        method: 'patch',
        preserveScroll: true,
    });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative">
                <Bell class="h-5 w-5" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-0.5 text-[10px] font-bold text-white"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-80 p-0">
            <div class="flex items-center justify-between px-3 py-2">
                <DropdownMenuLabel class="p-0 text-sm font-semibold">Notifications</DropdownMenuLabel>
                <Button
                    v-if="unreadCount > 0"
                    variant="ghost"
                    size="sm"
                    class="h-auto px-2 py-1 text-xs text-muted-foreground hover:text-foreground"
                    @click="markAllRead"
                >
                    Mark all read
                </Button>
            </div>

            <DropdownMenuSeparator class="my-0" />

            <div class="max-h-96 overflow-y-auto">
                <div
                    v-for="notification in notifications"
                    :key="notification.id"
                    class="cursor-pointer p-3 hover:bg-muted"
                    :class="{ 'bg-blue-50/50 dark:bg-blue-950/20': !notification.read_at }"
                    @click="handleNotificationClick(notification)"
                >
                    <p class="text-sm leading-snug">{{ notification.data.message }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ relativeTime(notification.created_at) }}</p>
                </div>

                <div v-if="notifications.length === 0" class="p-6 text-center">
                    <Bell class="mx-auto mb-2 h-8 w-8 text-muted-foreground/40" />
                    <p class="text-sm text-muted-foreground">No new notifications</p>
                </div>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
