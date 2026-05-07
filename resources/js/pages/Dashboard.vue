<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ActivityTypeBadge from '@/components/ActivityTypeBadge.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import * as AdminAssignmentActions from '@/actions/App/Http/Controllers/Admin/AssignmentController';
import { dashboard } from '@/routes';

interface StatusCounts {
    PENDING?: number;
    DROP?: number;
    // Survey
    SURVEY?: number;
    DOCUMENT?: number;
    // Construction
    CONSTRUCTION?: number;
    MACHINE_ONSITE?: number;
    DONE?: number;
    LIVE?: number;
    // PLN
    REGISTRATION?: number;
    BILLING?: number;
    CONNECTION?: number;
    KWH_DONE?: number;
    // BAST
    COMPLETED?: number;
    REVISION?: number;
    // Shared final
    VERIFIED?: number;
    REPORTED?: number;
}

type ActivityMatrix = Record<string, Record<string, number>>;

interface ProjectBreakdown {
    id: number;
    name: string;
    counts: StatusCounts;
}

interface MainContractor {
    id: number;
    name: string;
}

interface Project {
    id: number;
    name: string;
}

interface RecentActivityItem {
    id: number;
    activity_type: string;
    status: string;
    updated_at: string;
    site: { site_code: string; location_name: string } | null;
    subcontractor: { name: string } | null;
}

const props = defineProps<{
    statusCounts: StatusCounts;
    activityMatrix: ActivityMatrix;
    projectBreakdowns: ProjectBreakdown[];
    recentActivity: RecentActivityItem[];
    mainContractors: MainContractor[] | null;
    projects: Project[] | null;
    filters: { main_contractor_id?: string | null; project_id?: string | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const ALL = '';
const mainContractorId = ref<string>(props.filters?.main_contractor_id ?? ALL);
const projectId = ref<string>(props.filters?.project_id ?? ALL);

const selectedContractorName = computed(() =>
    props.mainContractors?.find((mc) => mc.id.toString() === mainContractorId.value)?.name ?? null,
);

const selectedProjectName = computed(() =>
    props.projects?.find((p) => p.id.toString() === projectId.value)?.name ?? null,
);

function buildQuery(): Record<string, string> {
    const q: Record<string, string> = {};
    if (mainContractorId.value) { q.main_contractor_id = mainContractorId.value; }
    if (projectId.value) { q.project_id = projectId.value; }
    return q;
}

function applyContractorFilter(value: string): void {
    mainContractorId.value = value;
    projectId.value = ALL;
    router.get(dashboard(), value ? { main_contractor_id: value } : {}, {
        preserveState: false,
        replace: true,
    });
}

function applyProjectFilter(value: string): void {
    projectId.value = value;
    const q = buildQuery();
    router.get(dashboard(), q, { preserveState: false, replace: true });
}

const statuses: { key: keyof StatusCounts; label: string; borderClass: string; textClass: string; dotClass: string; bgClass: string }[] = [
    { key: 'PENDING',        label: 'Pending',        borderClass: 'border-l-gray-400',    textClass: 'text-gray-600 dark:text-gray-400',       dotClass: 'bg-gray-400',    bgClass: 'bg-gray-50 dark:bg-gray-900/20' },
    { key: 'SURVEY',         label: 'Survey',         borderClass: 'border-l-sky-500',     textClass: 'text-sky-600 dark:text-sky-400',         dotClass: 'bg-sky-500',     bgClass: 'bg-sky-50 dark:bg-sky-900/20' },
    { key: 'DOCUMENT',       label: 'Document',       borderClass: 'border-l-indigo-500',  textClass: 'text-indigo-600 dark:text-indigo-400',   dotClass: 'bg-indigo-500',  bgClass: 'bg-indigo-50 dark:bg-indigo-900/20' },
    { key: 'CONSTRUCTION',   label: 'Construction',   borderClass: 'border-l-orange-500',  textClass: 'text-orange-600 dark:text-orange-400',   dotClass: 'bg-orange-500',  bgClass: 'bg-orange-50 dark:bg-orange-900/20' },
    { key: 'MACHINE_ONSITE', label: 'Machine Onsite', borderClass: 'border-l-amber-500',   textClass: 'text-amber-600 dark:text-amber-400',     dotClass: 'bg-amber-500',   bgClass: 'bg-amber-50 dark:bg-amber-900/20' },
    { key: 'DONE',           label: 'Done',           borderClass: 'border-l-lime-500',    textClass: 'text-lime-600 dark:text-lime-400',       dotClass: 'bg-lime-500',    bgClass: 'bg-lime-50 dark:bg-lime-900/20' },
    { key: 'LIVE',           label: 'Live',           borderClass: 'border-l-green-500',   textClass: 'text-green-600 dark:text-green-400',     dotClass: 'bg-green-500',   bgClass: 'bg-green-50 dark:bg-green-900/20' },
    { key: 'REGISTRATION',   label: 'Registration',   borderClass: 'border-l-teal-500',    textClass: 'text-teal-600 dark:text-teal-400',       dotClass: 'bg-teal-500',    bgClass: 'bg-teal-50 dark:bg-teal-900/20' },
    { key: 'BILLING',        label: 'Billing',        borderClass: 'border-l-cyan-500',    textClass: 'text-cyan-600 dark:text-cyan-400',       dotClass: 'bg-cyan-500',    bgClass: 'bg-cyan-50 dark:bg-cyan-900/20' },
    { key: 'CONNECTION',     label: 'Connection',     borderClass: 'border-l-blue-500',    textClass: 'text-blue-600 dark:text-blue-400',       dotClass: 'bg-blue-500',    bgClass: 'bg-blue-50 dark:bg-blue-900/20' },
    { key: 'KWH_DONE',       label: 'KWH Done',       borderClass: 'border-l-violet-500',  textClass: 'text-violet-600 dark:text-violet-400',   dotClass: 'bg-violet-500',  bgClass: 'bg-violet-50 dark:bg-violet-900/20' },
    { key: 'COMPLETED',      label: 'Completed',      borderClass: 'border-l-blue-500',    textClass: 'text-blue-600 dark:text-blue-400',       dotClass: 'bg-blue-500',    bgClass: 'bg-blue-50 dark:bg-blue-900/20' },
    { key: 'REVISION',       label: 'Revision',       borderClass: 'border-l-amber-500',   textClass: 'text-amber-600 dark:text-amber-400',     dotClass: 'bg-amber-500',   bgClass: 'bg-amber-50 dark:bg-amber-900/20' },
    { key: 'VERIFIED',       label: 'Verified',       borderClass: 'border-l-emerald-500', textClass: 'text-emerald-600 dark:text-emerald-400', dotClass: 'bg-emerald-500', bgClass: 'bg-emerald-50 dark:bg-emerald-900/20' },
    { key: 'REPORTED',       label: 'Reported',       borderClass: 'border-l-purple-500',  textClass: 'text-purple-600 dark:text-purple-400',   dotClass: 'bg-purple-500',  bgClass: 'bg-purple-50 dark:bg-purple-900/20' },
    { key: 'DROP',           label: 'Drop',           borderClass: 'border-l-red-500',     textClass: 'text-red-600 dark:text-red-400',         dotClass: 'bg-red-500',     bgClass: 'bg-red-50 dark:bg-red-900/20' },
];

const activityRows: { key: string; label: string }[] = [
    { key: 'SURVEY',         label: 'Survey' },
    { key: 'PLN_CONNECTION', label: 'PLN Connection' },
    { key: 'CONSTRUCTION',   label: 'Construction' },
    { key: 'BAST',           label: 'BAST' },
];

const visibleActivityRows = computed(() =>
    activityRows.filter((row) => matrixRowTotal(row.key) > 0),
);

const visibleStatuses = computed(() =>
    statuses.filter((s) => matrixColTotal(s.key) > 0),
);

function getCount(counts: StatusCounts | Record<string, number>, key: string): number {
    return (counts as Record<string, number>)[key] ?? 0;
}

const INTERMEDIATE_STATUSES: (keyof StatusCounts)[] = [
    'SURVEY', 'DOCUMENT',
    'CONSTRUCTION', 'MACHINE_ONSITE', 'DONE', 'LIVE',
    'REGISTRATION', 'BILLING', 'CONNECTION', 'KWH_DONE',
    'COMPLETED', 'REVISION',
];

function projectInProgress(counts: StatusCounts): number {
    return INTERMEDIATE_STATUSES.reduce((sum, k) => sum + (counts[k] ?? 0), 0);
}

function projectTotal(counts: StatusCounts): number {
    const drop = counts.DROP ?? 0;
    return Object.values(counts as Record<string, number>).reduce((sum, v) => sum + v, 0) - drop;
}

function projectCompletion(counts: StatusCounts): number {
    const total = projectTotal(counts);
    if (total === 0) { return 0; }
    return Math.round(((counts.VERIFIED ?? 0) + (counts.REPORTED ?? 0)) / total * 100);
}

function matrixRowTotal(activityKey: string): number {
    const row = props.activityMatrix[activityKey] ?? {};
    return Object.values(row).reduce((sum, v) => sum + v, 0);
}

function matrixColTotal(statusKey: string): number {
    return visibleActivityRows.value.reduce((sum, row) => sum + getCount(props.activityMatrix[row.key] ?? {}, statusKey), 0);
}

function matrixGrandTotal(): number {
    return statuses.reduce((sum, s) => sum + matrixColTotal(s.key), 0);
}

function assignmentFilterUrl(params: Record<string, string>): string {
    const q = new URLSearchParams(params).toString();
    return `/admin/assignments${q ? '?' + q : ''}`;
}

function cellHighlight(statusKey: string, count: number): string {
    if (count === 0) { return ''; }
    const highlights: Record<string, string> = {
        REVISION:       'bg-amber-50 font-semibold text-amber-700 dark:bg-amber-900/20 dark:text-amber-400',
        COMPLETED:      'bg-blue-50 font-semibold text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
        LIVE:           'bg-green-50 font-semibold text-green-700 dark:bg-green-900/20 dark:text-green-400',
        KWH_DONE:       'bg-violet-50 font-semibold text-violet-700 dark:bg-violet-900/20 dark:text-violet-400',
        DOCUMENT:       'bg-indigo-50 font-semibold text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-400',
        DROP:           'bg-red-50 font-semibold text-red-700 dark:bg-red-900/20 dark:text-red-400',
    };
    return highlights[statusKey] ?? '';
}

function timeAgo(isoString: string): string {
    const diff = Date.now() - new Date(isoString).getTime();
    const seconds = Math.floor(diff / 1000);
    if (seconds < 60) { return 'just now'; }
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) { return `${minutes}m ago`; }
    const hours = Math.floor(minutes / 60);
    if (hours < 24) { return `${hours}h ago`; }
    const days = Math.floor(hours / 24);
    if (days < 30) { return `${days}d ago`; }
    const months = Math.floor(days / 30);
    if (months < 12) { return `${months}mo ago`; }
    return `${Math.floor(months / 12)}y ago`;
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h1 class="text-xl font-semibold tracking-tight">Dashboard</h1>
                <p class="text-sm text-muted-foreground">
                    Overview of EV charging station deployment progress
                    <span v-if="selectedContractorName" class="font-medium text-foreground"> — {{ selectedContractorName }}</span>
                    <span v-if="selectedProjectName" class="font-medium text-foreground"> / {{ selectedProjectName }}</span>
                </p>
            </div>
            <div v-if="mainContractors" class="flex flex-wrap items-center gap-2">
                <Select :model-value="mainContractorId" @update:model-value="applyContractorFilter">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="All contractors" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All contractors</SelectItem>
                        <SelectItem v-for="mc in mainContractors" :key="mc.id" :value="mc.id.toString()">
                            {{ mc.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select v-if="projects && projects.length > 0" :model-value="projectId" @update:model-value="applyProjectFilter">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="All projects" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All projects</SelectItem>
                        <SelectItem v-for="proj in projects" :key="proj.id" :value="proj.id.toString()">
                            {{ proj.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <!-- Section 1: Status Summary Cards (clickable, zero-count cards hidden) -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-8">
            <template v-for="stat in statuses" :key="stat.key">
                <Link
                    v-if="getCount(props.statusCounts, stat.key) > 0"
                    :href="assignmentFilterUrl({ status: stat.key })"
                    class="rounded-xl border border-sidebar-border/70 bg-card border-l-4 p-4 transition-colors hover:bg-muted/30 dark:border-sidebar-border"
                    :class="stat.borderClass"
                >
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-1.5">
                            <span class="size-2 shrink-0 rounded-full" :class="stat.dotClass" />
                            <span class="text-xs font-medium text-muted-foreground">{{ stat.label }}</span>
                        </div>
                        <p class="text-3xl font-bold tracking-tight" :class="stat.textClass">
                            {{ getCount(props.statusCounts, stat.key) }}
                        </p>
                    </div>
                </Link>
            </template>
        </div>

        <!-- Section 2: Activity × Status Pipeline Matrix -->
        <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
            <div class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                <h2 class="text-sm font-semibold">Activity Pipeline</h2>
                <p class="text-xs text-muted-foreground">Assignment counts by activity type and status — click any cell to drill down</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Activity</th>
                            <th v-for="stat in visibleStatuses" :key="stat.key" class="px-4 py-3 text-center font-medium text-muted-foreground">
                                <span class="inline-flex items-center gap-1">
                                    <span class="size-1.5 rounded-full" :class="stat.dotClass" />
                                    {{ stat.label }}
                                </span>
                            </th>
                            <th class="px-4 py-3 text-center font-medium text-muted-foreground">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in visibleActivityRows"
                            :key="row.key"
                            class="border-t border-sidebar-border/70 dark:border-sidebar-border"
                        >
                            <td class="px-4 py-3 font-medium">
                                <ActivityTypeBadge :activity-type="row.key" />
                            </td>
                            <td
                                v-for="stat in visibleStatuses"
                                :key="stat.key"
                                class="px-4 py-3 text-center"
                            >
                                <Link
                                    :href="assignmentFilterUrl({ activity_type: row.key, status: stat.key })"
                                    class="inline-block min-w-8 rounded px-2 py-0.5 text-sm transition-colors hover:ring-1 hover:ring-sidebar-border"
                                    :class="cellHighlight(stat.key, getCount(activityMatrix[row.key] ?? {}, stat.key))"
                                >
                                    {{ getCount(activityMatrix[row.key] ?? {}, stat.key) }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-muted-foreground">
                                {{ matrixRowTotal(row.key) }}
                            </td>
                        </tr>

                        <!-- Column totals -->
                        <tr class="border-t-2 border-sidebar-border/70 bg-muted/20 dark:border-sidebar-border">
                            <td class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total</td>
                            <td v-for="stat in visibleStatuses" :key="stat.key" class="px-4 py-3 text-center font-semibold">
                                <Link
                                    :href="assignmentFilterUrl({ status: stat.key })"
                                    class="inline-block min-w-8 rounded px-2 py-0.5 transition-colors hover:bg-muted"
                                    :class="stat.textClass"
                                >
                                    {{ matrixColTotal(stat.key) }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-center font-bold">{{ matrixGrandTotal() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 3: Per-project breakdown -->
        <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
            <div class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                <h2 class="text-sm font-semibold">Project Breakdown</h2>
                <p class="text-xs text-muted-foreground">Assignment status per project</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Project</th>
                            <th class="px-4 py-3 text-center font-medium text-muted-foreground">Pending</th>
                            <th class="px-4 py-3 text-center font-medium text-muted-foreground">In Progress</th>
                            <th class="px-4 py-3 text-center font-medium text-muted-foreground">Verified</th>
                            <th class="px-4 py-3 text-center font-medium text-muted-foreground">Reported</th>
                            <th class="px-4 py-3 text-center font-medium text-muted-foreground">Total</th>
                            <th class="px-4 py-3 text-center font-medium text-muted-foreground">Completion</th>
                            <th class="px-4 py-3 text-right font-medium text-muted-foreground">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="project in props.projectBreakdowns"
                            :key="project.id"
                            class="border-t border-sidebar-border/70 transition-colors hover:bg-muted/30 dark:border-sidebar-border"
                        >
                            <td class="px-4 py-3 font-medium">{{ project.name }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1">
                                    <span class="size-1.5 rounded-full bg-gray-400" />
                                    {{ getCount(project.counts, 'PENDING') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1">
                                    <span class="size-1.5 rounded-full bg-blue-500" />
                                    {{ projectInProgress(project.counts) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1">
                                    <span class="size-1.5 rounded-full bg-emerald-500" />
                                    {{ getCount(project.counts, 'VERIFIED') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1">
                                    <span class="size-1.5 rounded-full bg-purple-500" />
                                    {{ getCount(project.counts, 'REPORTED') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center font-semibold">
                                {{ projectTotal(project.counts) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 w-20 overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="h-full rounded-full bg-emerald-500 transition-all"
                                            :style="{ width: projectCompletion(project.counts) + '%' }"
                                        />
                                    </div>
                                    <span class="text-xs tabular-nums text-muted-foreground">
                                        {{ projectCompletion(project.counts) }}%
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="assignmentFilterUrl({ project_id: project.id.toString() })"
                                    class="inline-flex items-center gap-1 rounded-md border border-sidebar-border/70 px-2.5 py-1.5 text-xs font-medium transition-colors hover:bg-muted dark:border-sidebar-border"
                                >
                                    View
                                    <ArrowRight class="size-3" />
                                </Link>
                            </td>
                        </tr>

                        <tr v-if="props.projectBreakdowns.length === 0">
                            <td colspan="8" class="px-4 py-12 text-center text-sm text-muted-foreground">
                                No project data available.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 4: Recent Activity -->
        <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
            <div class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                <h2 class="text-sm font-semibold">Recent Activity</h2>
                <p class="text-xs text-muted-foreground">Last 10 updated assignments</p>
            </div>

            <div class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                <Link
                    v-for="item in props.recentActivity"
                    :key="item.id"
                    :href="'/admin/assignments/' + item.id"
                    class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/30"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs font-semibold">
                                {{ item.site?.site_code ?? '—' }}
                            </span>
                            <ActivityTypeBadge :activity-type="item.activity_type" />
                            <StatusBadge :status="item.status" />
                        </div>
                        <div class="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span>{{ item.site?.location_name ?? '—' }}</span>
                            <span v-if="item.subcontractor" class="text-muted-foreground/60">·</span>
                            <span v-if="item.subcontractor">{{ item.subcontractor.name }}</span>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
                        <span>{{ timeAgo(item.updated_at) }}</span>
                        <ArrowRight class="size-3.5" />
                    </div>
                </Link>

                <div v-if="props.recentActivity.length === 0" class="px-4 py-12 text-center text-sm text-muted-foreground">
                    No recent activity.
                </div>
            </div>
        </div>
    </div>
</template>
