<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, LockKeyhole, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import type { ActivityType, Assignment, AssignmentStatus, PaginatedData } from '@/types';

interface SiteRow {
    id: number;
    site_code: string;
    location_name: string;
    city: string | null;
    province: string | null;
    site_type: { id: number; name: string } | null;
    project: { id: number; name: string } | null;
    assignments: Assignment[];
}

interface Subcontractor {
    id: number;
    name: string;
    code: string;
}

interface MainContractor {
    id: number;
    name: string;
}

interface Project {
    id: number;
    name: string;
}

type Filters = {
    search?: string;
    status?: string;
    activity_type?: string;
    subcontractor_id?: number;
    main_contractor_id?: number;
    project_id?: number;
};

const props = defineProps<{
    sites: PaginatedData<SiteRow>;
    subcontractors: Subcontractor[];
    projects: Project[];
    mainContractors: MainContractor[] | null;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Assignments', href: '/admin/assignments' },
        ],
    },
});

const ALL = '__all__';

const search = ref<string>('');
const status = ref<string>(ALL);
const activityType = ref<string>(ALL);
const subcontractorId = ref<string>(ALL);
const mainContractorId = ref<string>(ALL);
const projectId = ref<string>(ALL);

watch(
    () => props.filters,
    (next) => {
        if (next && typeof next === 'object') {
            search.value = (next as any).search ?? '';
            status.value = (next as any).status ?? ALL;
            activityType.value = (next as any).activity_type ?? ALL;
            subcontractorId.value = (next as any).subcontractor_id?.toString() ?? ALL;
            mainContractorId.value = (next as any).main_contractor_id?.toString() ?? ALL;
            projectId.value = (next as any).project_id?.toString() ?? ALL;
        } else {
            search.value = '';
            status.value = ALL;
            activityType.value = ALL;
            subcontractorId.value = ALL;
            mainContractorId.value = ALL;
            projectId.value = ALL;
        }
    },
    { immediate: true },
);

const statusOptions: { value: AssignmentStatus; label: string }[] = [
    { value: 'PENDING', label: 'Pending' },
    { value: 'DROP', label: 'Drop' },
    // Survey
    { value: 'SURVEY', label: 'Survey' },
    { value: 'DOCUMENT', label: 'Document' },
    // Construction
    { value: 'CONSTRUCTION', label: 'Construction' },
    { value: 'MACHINE_ONSITE', label: 'Machine Onsite' },
    { value: 'DONE', label: 'Done' },
    { value: 'LIVE', label: 'Live' },
    // PLN
    { value: 'REGISTRATION', label: 'Registration' },
    { value: 'BILLING', label: 'Billing' },
    { value: 'CONNECTION', label: 'Connection' },
    { value: 'KWH_DONE', label: 'KWH Done' },
    // BAST
    { value: 'COMPLETED', label: 'Completed' },
    { value: 'REVISION', label: 'Revision' },
    // Shared final
    { value: 'VERIFIED', label: 'Verified' },
    { value: 'REPORTED', label: 'Reported' },
];

const hasActiveFilters = computed(
    () =>
        search.value !== '' ||
        status.value !== ALL ||
        activityType.value !== ALL ||
        subcontractorId.value !== ALL ||
        mainContractorId.value !== ALL ||
        projectId.value !== ALL,
);

function applyFilters(): void {
    const query: Record<string, string> = {};

    if (search.value.trim()) {
        query.search = search.value.trim();
    }
    if (status.value !== ALL) {
        query.status = status.value;
    }
    if (activityType.value !== ALL) {
        query.activity_type = activityType.value;
    }
    if (subcontractorId.value !== ALL) {
        query.subcontractor_id = subcontractorId.value;
    }
    if (mainContractorId.value !== ALL) {
        query.main_contractor_id = mainContractorId.value;
    }
    if (projectId.value !== ALL) {
        query.project_id = projectId.value;
    }

    router.get('/admin/assignments', query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function onMainContractorChange(): void {
    subcontractorId.value = ALL;
    projectId.value = ALL;
    applyFilters();
}

function resetFilters(): void {
    search.value = '';
    status.value = ALL;
    activityType.value = ALL;
    subcontractorId.value = ALL;
    mainContractorId.value = ALL;
    projectId.value = ALL;
    applyFilters();
}

let searchTimeout: number | null = null;
function onSearchInput() {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = window.setTimeout(() => {
        applyFilters();
    }, 300);
}

function getAssignment(assignments: any, activityType: string): any | null {
    if (!assignments) {
        return null;
    }
    const arr = Array.from(assignments);
    return arr.find((a: any) => a && a.activity_type === activityType) || null;
}

const statusDotMap: Record<string, string> = {
    PENDING:        'bg-gray-400',
    DROP:           'bg-red-500',
    SURVEY:         'bg-sky-500',
    DOCUMENT:       'bg-indigo-500',
    CONSTRUCTION:   'bg-orange-500',
    MACHINE_ONSITE: 'bg-amber-500',
    DONE:           'bg-lime-500',
    LIVE:           'bg-green-500',
    REGISTRATION:   'bg-teal-500',
    BILLING:        'bg-cyan-500',
    CONNECTION:     'bg-blue-500',
    KWH_DONE:       'bg-violet-500',
    COMPLETED:      'bg-blue-500',
    REVISION:       'bg-amber-500',
    VERIFIED:       'bg-emerald-500',
    REPORTED:       'bg-purple-500',
};

const statusLabelMap: Record<string, string> = {
    PENDING:        'Pending',
    DROP:           'Drop',
    SURVEY:         'Survey',
    DOCUMENT:       'Document',
    CONSTRUCTION:   'Construction',
    MACHINE_ONSITE: 'Machine Onsite',
    DONE:           'Done',
    LIVE:           'Live',
    REGISTRATION:   'Registration',
    BILLING:        'Billing',
    CONNECTION:     'Connection',
    KWH_DONE:       'KWH Done',
    COMPLETED:      'Completed',
    REVISION:       'Revision',
    VERIFIED:       'Verified',
    REPORTED:       'Reported',
};

const activityColumns: { type: ActivityType; label: string }[] = [
    { type: 'SURVEY', label: 'Survey' },
    { type: 'CONSTRUCTION', label: 'Construction' },
    { type: 'PLN_CONNECTION', label: 'PLN' },
    { type: 'BAST', label: 'BAST' },
];
</script>

<template>
    <Head title="Assignments" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-xl font-semibold tracking-tight">Assignments</h1>
            <p class="text-sm text-muted-foreground">Grouped by site</p>
        </div>

        <div
            class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:flex-row sm:items-end dark:border-sidebar-border"
        >
            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div class="grid gap-1.5">
                    <label class="text-xs font-medium text-muted-foreground">Search Site</label>
                    <div class="relative">
                        <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
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
                    <label class="text-xs font-medium text-muted-foreground">Activity</label>
                    <Select v-model="activityType" @update:model-value="applyFilters">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="All activities" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All activities</SelectItem>
                            <SelectItem v-for="col in activityColumns" :key="col.type" :value="col.type">
                                {{ col.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <label class="text-xs font-medium text-muted-foreground">Status</label>
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
                <div class="grid gap-1.5">
                    <label class="text-xs font-medium text-muted-foreground">Project</label>
                    <Select v-model="projectId" @update:model-value="applyFilters">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="All projects" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All projects</SelectItem>
                            <SelectItem
                                v-for="proj in projects"
                                :key="proj.id"
                                :value="proj.id.toString()"
                            >
                                {{ proj.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <label class="text-xs font-medium text-muted-foreground">Subcontractor</label>
                    <Select v-model="subcontractorId" @update:model-value="applyFilters">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="All subcons" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All subcons</SelectItem>
                            <SelectItem
                                v-for="sc in subcontractors"
                                :key="sc.id"
                                :value="sc.id.toString()"
                            >
                                {{ sc.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div v-if="mainContractors" class="grid gap-1.5">
                    <label class="text-xs font-medium text-muted-foreground">Main Contractor</label>
                    <Select v-model="mainContractorId" @update:model-value="onMainContractorChange">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="All contractors" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All contractors</SelectItem>
                            <SelectItem
                                v-for="mc in mainContractors"
                                :key="mc.id"
                                :value="mc.id.toString()"
                            >
                                {{ mc.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="flex gap-2">
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
        </div>

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">
                                Site Code
                            </th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">
                                Location
                            </th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">
                                Project
                            </th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">
                                Survey
                            </th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">
                                Construction
                            </th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">
                                PLN
                            </th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">
                                BAST
                            </th>
                            <th class="px-4 py-3 text-right font-medium text-muted-foreground">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="site in (sites?.data || [])"
                            :key="site.id"
                            class="border-t border-sidebar-border/70 transition-colors hover:bg-muted/30 dark:border-sidebar-border"
                        >
                            <td class="px-4 py-3 font-mono text-xs font-semibold">
                                {{ site.site_code }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ site.location_name }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ [site.city, site.province].filter(Boolean).join(', ') || '—' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ (site.project && site.project.name) ? site.project.name : '—' }}
                            </td>

                            <template v-for="col in activityColumns" :key="col.type">
                                <td class="px-4 py-3">
                                    <div v-if="site && site.assignments">
                                        <div v-for="assignment in [getAssignment(site.assignments, col.type)]" :key="site.id + '-' + col.type">
                                            <div v-if="assignment && assignment.status" class="flex items-center gap-1.5">
                                                <span
                                                    class="size-2 shrink-0 rounded-full"
                                                    :class="statusDotMap[assignment.status] ?? 'bg-gray-400'"
                                                />
                                                <span class="text-xs">
                                                    {{ statusLabelMap[assignment.status] ?? assignment.status }}
                                                </span>
                                                <span
                                                    v-if="col.type === 'CONSTRUCTION' && assignment.status === 'PENDING' && assignment.construction_data && !assignment.construction_data.cons_wo_number"
                                                    class="ml-0.5"
                                                    title="Sub-contractor locked — WO number not set"
                                                >
                                                    <LockKeyhole class="size-3 text-amber-500" />
                                                </span>
                                            </div>
                                            <span v-else class="text-xs text-muted-foreground">—</span>
                                        </div>
                                    </div>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>
                            </template>

                            <td class="px-4 py-3 text-right">
                                <Button as-child variant="outline" size="sm">
                                    <Link :href="'/admin/assignments/sites/' + site.id">
                                        <ArrowRight class="size-4" />
                                    </Link>
                                </Button>
                            </td>
                        </tr>

                        <tr v-if="!sites || !sites.data || sites.data.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-12 text-center text-sm text-muted-foreground"
                            >
                                <div class="flex flex-col items-center gap-2 py-4">
                                    <Search class="size-8 text-muted-foreground/60" />
                                    <p class="font-medium">No sites found</p>
                                    <p class="text-xs">
                                        Try adjusting your filters or check again later.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <PaginationLinks
                v-if="sites && sites.data && sites.data.length > 0"
                :links="sites.links"
                :from="sites.from"
                :to="sites.to"
                :total="sites.total"
            />
        </div>
    </div>
</template>
