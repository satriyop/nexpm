<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Archive,
    ArrowRight,
    Download,
    LockKeyhole,
    Search,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import * as AdminAssignmentActions from '@/actions/App/Http/Controllers/Admin/AssignmentController';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import type {
    ActivityType,
    Assignment,
    AssignmentStatus,
    PaginatedData,
} from '@/types';

interface MachineType {
    id: number;
    name: string;
}

interface SiteRow {
    id: number;
    site_code: string;
    location_name: string;
    city: string | null;
    province: string | null;
    site_type: { id: number; name: string } | null;
    machine_type: { id: number; name: string } | null;
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
    machine_type_id?: number;
};

const props = defineProps<{
    sites: PaginatedData<SiteRow>;
    subcontractors: Subcontractor[];
    projects: Project[];
    machineTypes: MachineType[];
    mainContractors: MainContractor[] | null;
    filters: Filters;
    per_page: number;
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
const machineTypeId = ref<string>(ALL);

watch(
    () => props.filters,
    (next) => {
        if (next && typeof next === 'object') {
            search.value = (next as any).search ?? '';
            status.value = (next as any).status ?? ALL;
            activityType.value = (next as any).activity_type ?? ALL;
            subcontractorId.value =
                (next as any).subcontractor_id?.toString() ?? ALL;
            mainContractorId.value =
                (next as any).main_contractor_id?.toString() ?? ALL;
            projectId.value = (next as any).project_id?.toString() ?? ALL;
            machineTypeId.value =
                (next as any).machine_type_id?.toString() ?? ALL;
        } else {
            search.value = '';
            status.value = ALL;
            activityType.value = ALL;
            subcontractorId.value = ALL;
            mainContractorId.value = ALL;
            projectId.value = ALL;
            machineTypeId.value = ALL;
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
    { value: 'SUBMITTED', label: 'Submitted' },
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
        projectId.value !== ALL ||
        machineTypeId.value !== ALL,
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

    if (machineTypeId.value !== ALL) {
        query.machine_type_id = machineTypeId.value;
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
    machineTypeId.value = ALL;
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

function getAssignment(assignments: any, activityType: string): any | null {
    if (!assignments) {
        return null;
    }

    const arr = Array.from(assignments);

    return arr.find((a: any) => a && a.activity_type === activityType) || null;
}

const statusDotMap: Record<string, string> = {
    PENDING: 'bg-gray-400',
    DROP: 'bg-red-500',
    SURVEY: 'bg-sky-500',
    DOCUMENT: 'bg-indigo-500',
    CONSTRUCTION: 'bg-orange-500',
    MACHINE_ONSITE: 'bg-amber-500',
    DONE: 'bg-lime-500',
    LIVE: 'bg-green-500',
    REGISTRATION: 'bg-teal-500',
    BILLING: 'bg-cyan-500',
    CONNECTION: 'bg-blue-500',
    KWH_DONE: 'bg-violet-500',
    SUBMITTED: 'bg-blue-500',
    REVISION: 'bg-amber-500',
    VERIFIED: 'bg-emerald-500',
    REPORTED: 'bg-purple-500',
};

const statusLabelMap: Record<string, string> = {
    PENDING: 'Pending',
    DROP: 'Drop',
    SURVEY: 'Survey',
    DOCUMENT: 'Document',
    CONSTRUCTION: 'Construction',
    MACHINE_ONSITE: 'Machine Onsite',
    DONE: 'Done',
    LIVE: 'Live',
    REGISTRATION: 'Registration',
    BILLING: 'Billing',
    CONNECTION: 'Connection',
    KWH_DONE: 'KWH Done',
    SUBMITTED: 'Submitted',
    REVISION: 'Revision',
    VERIFIED: 'Verified',
    REPORTED: 'Reported',
};

const activityColumns: { type: ActivityType; label: string }[] = [
    { type: 'SURVEY', label: 'Survey' },
    { type: 'CONSTRUCTION', label: 'Construction' },
    { type: 'PLN_CONNECTION', label: 'PLN' },
    { type: 'BAST', label: 'BAST' },
];

// --- Stall indicator ---
const TERMINAL_STATUSES = ['VERIFIED', 'REPORTED', 'DROP'];
const sla = usePage().props.sla;

function daysStalled(assignment: Assignment): number | null {
    if (TERMINAL_STATUSES.includes(assignment.status)) {
        return null;
    }

    if (!assignment.updated_at) {
        return null;
    }

    return Math.floor(
        (Date.now() - new Date(assignment.updated_at).getTime()) / 86_400_000,
    );
}

// --- Bulk selection ---
const selectedSiteIds = ref<Set<number>>(new Set());

const pageIds = computed(() => (props.sites?.data ?? []).map((s) => s.id));

const allOnPageSelected = computed(
    () =>
        pageIds.value.length > 0 &&
        pageIds.value.every((id) => selectedSiteIds.value.has(id)),
);
const someOnPageSelected = computed(
    () =>
        pageIds.value.some((id) => selectedSiteIds.value.has(id)) &&
        !allOnPageSelected.value,
);

function toggleSelectAll(): void {
    if (allOnPageSelected.value) {
        pageIds.value.forEach((id) => selectedSiteIds.value.delete(id));
    } else {
        pageIds.value.forEach((id) => selectedSiteIds.value.add(id));
    }

    selectedSiteIds.value = new Set(selectedSiteIds.value);
}

function toggleSite(id: number): void {
    if (selectedSiteIds.value.has(id)) {
        selectedSiteIds.value.delete(id);
    } else {
        selectedSiteIds.value.add(id);
    }

    selectedSiteIds.value = new Set(selectedSiteIds.value);
}

// Clear selection on filter/page change
watch(
    () => props.sites?.data,
    () => {
        selectedSiteIds.value = new Set();
    },
);

const { canEdit } = usePermissions();

const bulkDropForm = useForm({ assignment_ids: [] as number[] });

function bulkDropSelected(): void {
    const sites = (props.sites?.data ?? []).filter((s) =>
        selectedSiteIds.value.has(s.id),
    );
    const assignmentIds = sites.flatMap((s) => s.assignments.map((a) => a.id));

    if (assignmentIds.length === 0) {
        return;
    }

    bulkDropForm.assignment_ids = assignmentIds;
    bulkDropForm.post(AdminAssignmentActions.bulkDrop().url, {
        onSuccess: () => {
            selectedSiteIds.value = new Set();
        },
    });
}

function exportSelected(): void {
    const ids = [...selectedSiteIds.value];
    const url =
        AdminAssignmentActions.exportMethod().url +
        (ids.length ? `?site_ids=${ids.join(',')}` : '');
    window.location.href = url;
}
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
            <div
                class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
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
                        >Activity</label
                    >
                    <Select
                        v-model="activityType"
                        @update:model-value="applyFilters"
                    >
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="All activities" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All activities</SelectItem>
                            <SelectItem
                                v-for="col in activityColumns"
                                :key="col.type"
                                :value="col.type"
                            >
                                {{ col.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
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
                <div class="grid gap-1.5">
                    <label class="text-xs font-medium text-muted-foreground"
                        >Project</label
                    >
                    <Select
                        v-model="projectId"
                        @update:model-value="applyFilters"
                    >
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
                    <label class="text-xs font-medium text-muted-foreground"
                        >Machine Type</label
                    >
                    <Select
                        v-model="machineTypeId"
                        @update:model-value="applyFilters"
                    >
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="All types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All types</SelectItem>
                            <SelectItem
                                v-for="mt in machineTypes"
                                :key="mt.id"
                                :value="mt.id.toString()"
                                >{{ mt.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <label class="text-xs font-medium text-muted-foreground"
                        >Subcontractor</label
                    >
                    <Select
                        v-model="subcontractorId"
                        @update:model-value="applyFilters"
                    >
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
                    <label class="text-xs font-medium text-muted-foreground"
                        >Main Contractor</label
                    >
                    <Select
                        v-model="mainContractorId"
                        @update:model-value="onMainContractorChange"
                    >
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="All contractors" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL"
                                >All contractors</SelectItem
                            >
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

        <!-- Bulk action toolbar -->
        <Transition
            enter-active-class="transition-all duration-150"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-100"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="selectedSiteIds.size > 0"
                class="flex items-center gap-3 rounded-xl border border-primary/30 bg-primary/5 px-4 py-2.5"
            >
                <span class="text-sm font-medium text-primary"
                    >{{ selectedSiteIds.size }} site(s) selected</span
                >
                <div class="ml-auto flex items-center gap-2">
                    <Button
                        v-if="canEdit"
                        size="sm"
                        variant="outline"
                        :disabled="bulkDropForm.processing"
                        @click="bulkDropSelected"
                    >
                        <Archive class="mr-1.5 h-3.5 w-3.5" />
                        {{
                            bulkDropForm.processing ? 'Archiving…' : 'Bulk Drop'
                        }}
                    </Button>
                    <Button size="sm" variant="outline" @click="exportSelected">
                        <Download class="mr-1.5 h-3.5 w-3.5" />
                        Export CSV
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        @click="selectedSiteIds = new Set()"
                    >
                        <X class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>
        </Transition>

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-xs tracking-wide uppercase">
                        <tr>
                            <th class="w-10 px-4 py-3">
                                <Checkbox
                                    :checked="
                                        someOnPageSelected
                                            ? 'indeterminate'
                                            : allOnPageSelected
                                    "
                                    @update:checked="toggleSelectAll"
                                />
                            </th>
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
                                Machine Type
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Survey
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Construction
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                PLN
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                BAST
                            </th>
                            <th
                                class="px-4 py-3 text-right font-medium text-muted-foreground"
                            >
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="site in sites?.data || []"
                            :key="site.id"
                            class="cursor-pointer border-t border-sidebar-border/70 transition-colors hover:bg-muted/30 dark:border-sidebar-border"
                            :class="{
                                'bg-primary/5': selectedSiteIds.has(site.id),
                            }"
                            @click="
                                router.visit(
                                    '/admin/assignments/sites/' + site.id,
                                )
                            "
                            @mouseenter="
                                router.prefetch(
                                    AdminAssignmentActions.siteAssignments(site)
                                        .url,
                                )
                            "
                        >
                            <td class="w-10 px-4 py-3" @click.stop>
                                <Checkbox
                                    :checked="selectedSiteIds.has(site.id)"
                                    @update:checked="toggleSite(site.id)"
                                />
                            </td>
                            <td
                                class="px-4 py-3 font-mono text-xs font-semibold text-primary underline-offset-2 hover:underline"
                            >
                                {{ site.site_code }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span class="font-medium">{{
                                        site.location_name
                                    }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        {{
                                            [site.city, site.province]
                                                .filter(Boolean)
                                                .join(', ') || '—'
                                        }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{
                                    site.project && site.project.name
                                        ? site.project.name
                                        : '—'
                                }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ site.machine_type?.name ?? '—' }}
                            </td>

                            <template
                                v-for="col in activityColumns"
                                :key="col.type"
                            >
                                <td class="px-4 py-3">
                                    <div v-if="site && site.assignments">
                                        <div
                                            v-for="assignment in [
                                                getAssignment(
                                                    site.assignments,
                                                    col.type,
                                                ),
                                            ]"
                                            :key="
                                                assignment?.id ??
                                                `${site.id}-${col.type}`
                                            "
                                        >
                                            <div
                                                v-if="
                                                    assignment &&
                                                    assignment.status
                                                "
                                                class="flex flex-col gap-0.5"
                                            >
                                                <div
                                                    class="flex items-center gap-1.5"
                                                >
                                                    <span
                                                        class="size-2 shrink-0 rounded-full"
                                                        :class="
                                                            statusDotMap[
                                                                assignment
                                                                    .status
                                                            ] ?? 'bg-gray-400'
                                                        "
                                                    />
                                                    <span class="text-xs">
                                                        {{
                                                            statusLabelMap[
                                                                assignment
                                                                    .status
                                                            ] ??
                                                            assignment.status
                                                        }}
                                                    </span>
                                                    <span
                                                        v-if="
                                                            col.type ===
                                                                'CONSTRUCTION' &&
                                                            assignment.status ===
                                                                'PENDING' &&
                                                            assignment.construction_data &&
                                                            !assignment
                                                                .construction_data
                                                                .cons_wo_number
                                                        "
                                                        class="ml-0.5"
                                                        title="Sub-contractor locked — WO number not set"
                                                    >
                                                        <LockKeyhole
                                                            class="size-3 text-amber-500"
                                                        />
                                                    </span>
                                                </div>
                                                <span
                                                    v-if="
                                                        daysStalled(
                                                            assignment,
                                                        ) !== null &&
                                                        daysStalled(
                                                            assignment,
                                                        )! >= sla.stalled_days
                                                    "
                                                    class="inline-flex w-fit items-center rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-medium text-red-800 dark:bg-red-900/40 dark:text-red-200"
                                                >
                                                    Stalled ·
                                                    {{
                                                        daysStalled(assignment)
                                                    }}d
                                                </span>
                                                <span
                                                    v-else-if="
                                                        daysStalled(
                                                            assignment,
                                                        ) !== null &&
                                                        daysStalled(
                                                            assignment,
                                                        )! >= sla.slow_days
                                                    "
                                                    class="inline-flex w-fit items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                                                >
                                                    Slow ·
                                                    {{
                                                        daysStalled(assignment)
                                                    }}d
                                                </span>
                                            </div>
                                            <span
                                                v-else
                                                class="text-xs text-muted-foreground"
                                                >—</span
                                            >
                                        </div>
                                    </div>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                        >—</span
                                    >
                                </td>
                            </template>

                            <td class="px-4 py-3 text-right" @click.stop>
                                <Button as-child variant="outline" size="sm">
                                    <Link
                                        :href="
                                            '/admin/assignments/sites/' +
                                            site.id
                                        "
                                    >
                                        <ArrowRight class="size-4" />
                                    </Link>
                                </Button>
                            </td>
                        </tr>

                        <tr
                            v-if="
                                !sites || !sites.data || sites.data.length === 0
                            "
                        >
                            <td
                                colspan="9"
                                class="px-4 py-12 text-center text-sm text-muted-foreground"
                            >
                                <div
                                    class="flex flex-col items-center gap-2 py-4"
                                >
                                    <Search
                                        class="size-8 text-muted-foreground/60"
                                    />
                                    <p class="font-medium">No sites found</p>
                                    <p class="text-xs">
                                        Try adjusting your filters or check
                                        again later.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <PaginationLinks
                v-if="sites && sites.data && sites.data.length > 0"
                :data="sites"
                :per-page="props.per_page"
            />
        </div>
    </div>
</template>
