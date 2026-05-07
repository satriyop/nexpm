<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PaginatedData } from '@/types';

defineProps<{
    data: PaginatedData<unknown>;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-between gap-3 border-t border-sidebar-border/70 px-4 py-3 sm:flex-row dark:border-sidebar-border"
    >
        <p
            v-if="data.total !== undefined"
            class="text-xs text-muted-foreground sm:text-sm"
        >
            Showing
            <span class="font-medium">{{ data.from ?? 0 }}</span>
            to
            <span class="font-medium">{{ data.to ?? 0 }}</span>
            of
            <span class="font-medium">{{ data.total }}</span>
            results
        </p>

        <nav
            v-if="data.links.length > 3"
            class="inline-flex flex-wrap items-center gap-1"
            aria-label="Pagination"
        >
            <template v-for="(link, index) in data.links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    :class="[
                        'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2.5 text-xs font-medium transition-colors',
                        link.active
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-input bg-background text-foreground hover:bg-accent hover:text-accent-foreground',
                    ]"
                    v-html="link.label"
                />
                <span
                    v-else
                    class="inline-flex h-8 min-w-8 cursor-not-allowed items-center justify-center rounded-md border border-input px-2.5 text-xs font-medium text-muted-foreground opacity-60"
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
