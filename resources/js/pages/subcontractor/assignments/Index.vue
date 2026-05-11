<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Eye, PencilLine, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import * as SubAssignmentActions from '@/actions/App/Http/Controllers/Subcontractor/AssignmentController';
import ActivityTypeBadge from '@/components/ActivityTypeBadge.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Assignment, AssignmentStatus, PaginatedData } from '@/types';

type Filters = {
    search?: string;
    status?: string;
};

const props = defineProps<{
    assignments: PaginatedData<Assignment>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Assignments',
                href: SubAssignmentActions.index().url,
            },
        ],
    },
});

const ALL = '__all__';

const search = ref<string>(props.filters.search ?? '');
const status = ref<string>(props.filters.status ?? ALL);

watch(
    () => props.filters,
    (next) => {
        search.value = next.search ?? '';
        status.value = next.status ?? ALL;
    },
);

const statusOptions: { value: AssignmentStatus; label: string }[] = [
    { value: 'PENDING', label: 'Pending' },
    { value: 'SURVEY', label: 'Survey' },
    { value: 'DOCUMENT', label: 'Document' },
    { value: 'CONSTRUCTION', label: 'Construction' },
    { value: 'MACHINE_ONSITE', label: 'Machine Onsite' },
    { value: 'DONE', label: 'Done' },
    { value: 'LIVE', label: 'Live' },
    { value: 'REGISTRATION', label: 'Registration' },
    { value: 'BILLING', label: 'Billing' },
    { value: 'CONNECTION', label: 'Connection' },
    { value: 'KWH_DONE', label: 'KWH Done' },
    { value: 'COMPLETED', label: 'Completed' },
    { value: 'REVISION', label: 'Revision' },
    { value: 'VERIFIED', label: 'Verified' },
    { value: 'REPORTED', label: 'Reported' },
    { value: 'DROP', label: 'Drop' },
];

const hasActiveFilters = computed(
    () => search.value !== '' || status.value !== ALL,
);

function applyFilters(): void {
    const query: Record<string, string> = {};

    if (search.value.trim()) {
        query.search = search.value.trim();
    }

    if (status.value !== ALL) {
        query.status = status.value;
    }

    router.get(SubAssignmentActions.index().url, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters(): void {
    search.value = '';
    status.value = ALL;
    applyFilters();
}

let searchTimeout: number | null = null;
function onSearchInput() {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = window.setTimeout(() => {
        applyFilters();
    }, 300);
}

function isFillable(s: AssignmentStatus): boolean {
    return !['VERIFIED', 'REPORTED', 'DROP'].includes(s);
}

function assignmentHref(assignment: Assignment): string {
    return SubAssignmentActions.show(assignment.id).url;
}

function openAssignment(assignment: Assignment): void {
    router.visit(assignmentHref(assignment));
}

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString();
}

function lastUpdated(assignment: Assignment): string {
    const ts =
        assignment.survey_data?.updated_at ??
        assignment.pln_data?.updated_at ??
        assignment.construction_data?.updated_at ??
        assignment.bast_data?.updated_at ??
        null;

    return formatDate(ts);
}

function isStarted(assignment: Assignment): boolean {
    return !!(
        assignment.survey_data ??
        assignment.pln_data ??
        assignment.construction_data ??
        assignment.bast_data
    );
}

const TERMINAL_STATUSES = ['VERIFIED', 'REPORTED', 'DROP'];
const sla = usePage().props.sla;

function daysStalled(assignment: Assignment): number | null {
    if (TERMINAL_STATUSES.includes(assignment.status)) {
        return null;
    }

    const ts =
        assignment.survey_data?.updated_at ??
        assignment.pln_data?.updated_at ??
        assignment.construction_data?.updated_at ??
        assignment.bast_data?.updated_at ??
        null;

    if (!ts) {
        return null;
    }

    return Math.floor((Date.now() - new Date(ts).getTime()) / 86_400_000);
}
</script>

<template>
    <Head title="My Assignments" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-xl font-semibold tracking-tight">My Assignments</h1>
            <p class="text-sm text-muted-foreground">
                Submit field data for the sites assigned to you.
            </p>
        </div>

        <div
            class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:flex-row sm:items-end dark:border-sidebar-border"
        >
            <div
                class="grid flex-1 grid-cols-1 gap-3 sm:max-w-md sm:grid-cols-2"
            >
                <div class="grid gap-1.5">
                    <label class="text-xs font-medium text-muted-foreground"
                        >Search Site</label
                    >
                    <div class="relative">
                        <Search
                            class="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            type="search"
                            placeholder="Site code, location..."
                            class="pl-9"
                            @input="onSearchInput"
                        />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <label class="text-xs font-medium text-muted-foreground"
                        >Status</label
                    >
                    <Select v-model="status" @update:model-value="applyFilters">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All statuses</SelectItem>
                            <SelectItem
                                v-for="opt in statusOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <Button
                v-if="hasActiveFilters"
                type="button"
                variant="outline"
                @click="resetFilters"
            >
                <X class="size-4" />
                Reset
            </Button>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-xs tracking-wide uppercase">
                        <tr>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Site Code
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Location
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Project
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Activity
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Status
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Last Updated
                            </th>
                            <th
                                class="px-4 py-3 text-right font-medium text-muted-foreground"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="assignment in assignments.data"
                            :key="assignment.id"
                            class="cursor-pointer border-t border-sidebar-border/70 transition-colors hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset dark:border-sidebar-border"
                            role="link"
                            tabindex="0"
                            @click="openAssignment(assignment)"
                            @keydown.enter="openAssignment(assignment)"
                            @keydown.space.prevent="openAssignment(assignment)"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ assignment.site?.site_code }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span>{{
                                        assignment.site?.location_name
                                    }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ assignment.site?.city }},
                                        {{ assignment.site?.province }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span>
                                        {{
                                            assignment.site?.project?.name ??
                                            '—'
                                        }}
                                    </span>
                                    <span class="text-xs text-muted-foreground">
                                        {{
                                            assignment.site?.project
                                                ?.main_contractor?.name ?? '—'
                                        }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <ActivityTypeBadge
                                    :activity-type="assignment.activity_type"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <StatusBadge :status="assignment.status" />
                                    <span
                                        v-if="assignment.status === 'REVISION'"
                                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium tracking-wide text-amber-800 uppercase dark:bg-amber-900/40 dark:text-amber-200"
                                    >
                                        <AlertTriangle class="size-3" />
                                        Revision Requested
                                    </span>
                                    <span
                                        v-if="
                                            assignment.status === 'PENDING' &&
                                            !isStarted(assignment)
                                        "
                                        class="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        Not started
                                    </span>
                                    <span
                                        v-if="
                                            daysStalled(assignment) !== null &&
                                            daysStalled(assignment)! >=
                                                sla.stalled_days
                                        "
                                        class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium tracking-wide text-red-800 uppercase dark:bg-red-900/40 dark:text-red-200"
                                    >
                                        Stalled · {{ daysStalled(assignment) }}d
                                    </span>
                                    <span
                                        v-else-if="
                                            daysStalled(assignment) !== null &&
                                            daysStalled(assignment)! >=
                                                sla.slow_days
                                        "
                                        class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium tracking-wide text-amber-800 uppercase dark:bg-amber-900/40 dark:text-amber-200"
                                    >
                                        Slow · {{ daysStalled(assignment) }}d
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ lastUpdated(assignment) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button as-child variant="outline" size="sm">
                                    <Link
                                        :href="assignmentHref(assignment)"
                                        @click.stop
                                    >
                                        <component
                                            :is="
                                                isFillable(assignment.status)
                                                    ? PencilLine
                                                    : Eye
                                            "
                                            class="size-4"
                                        />
                                        {{
                                            isFillable(assignment.status)
                                                ? 'Fill'
                                                : 'View'
                                        }}
                                    </Link>
                                </Button>
                            </td>
                        </tr>

                        <tr v-if="assignments.data.length === 0">
                            <td
                                colspan="7"
                                class="px-4 py-12 text-center text-sm text-muted-foreground"
                            >
                                <div
                                    class="flex flex-col items-center gap-2 py-4"
                                >
                                    <Search
                                        class="size-8 text-muted-foreground/60"
                                    />
                                    <p class="font-medium">
                                        No assignments found
                                    </p>
                                    <p class="text-xs">
                                        You currently have no assignments. Check
                                        back later.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <PaginationLinks
                v-if="assignments.data.length > 0"
                :data="assignments"
            />
        </div>
    </div>
</template>
