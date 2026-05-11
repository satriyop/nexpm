<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PaginatedData } from '@/types';

const props = defineProps<{
    data: PaginatedData<unknown>;
    perPage?: number;
    perPageOptions?: number[];
}>();

const pageSizes = props.perPageOptions ?? [10, 25, 50, 100];

function changePerPage(value: AcceptableValue): void {
    if (value == null) {
        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.set('per_page', String(value));
    url.searchParams.delete('page');
    router.visit(url.toString(), {
        preserveState: true,
        preserveScroll: false,
    });
}
</script>

<template>
    <div
        class="flex flex-col items-center justify-between gap-3 border-t border-sidebar-border/70 px-4 py-3 sm:flex-row dark:border-sidebar-border"
    >
        <div class="flex items-center gap-3">
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

            <div v-if="perPage !== undefined" class="flex items-center gap-1.5">
                <span class="text-xs text-muted-foreground">per page</span>
                <Select
                    :model-value="String(perPage)"
                    @update:model-value="changePerPage"
                >
                    <SelectTrigger class="h-7 w-16 text-xs">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="size in pageSizes"
                            :key="size"
                            :value="String(size)"
                            class="text-xs"
                        >
                            {{ size }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

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
                >
                    <span v-html="link.label" />
                </Link>
                <span
                    v-else
                    class="inline-flex h-8 min-w-8 cursor-not-allowed items-center justify-center rounded-md border border-input px-2.5 text-xs font-medium text-muted-foreground opacity-60"
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
