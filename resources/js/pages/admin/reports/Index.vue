<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronLeft, ChevronRight, Download, FileText, RefreshCw, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import * as AdminReportActions from '@/actions/App/Http/Controllers/Admin/ReportController';
import ActivityTypeBadge from '@/components/ActivityTypeBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { dashboard } from '@/routes';
import type { Assignment } from '@/types';

type ReportType = 'SSR' | 'BAST' | 'DAILY';
type TabKey = ReportType | 'HISTORY';

interface ReportSite {
    site_code: string;
    location_name: string;
    activity_type: string;
}

interface ReportRow {
    id: number;
    name: string;
    report_type: ReportType;
    assignments_count: number;
    created_at: string;
    sites: ReportSite[];
}

const props = defineProps<{
    ssrAssignments: Assignment[];
    bastAssignments: Assignment[];
    recentReports: ReportRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Reports', href: AdminReportActions.index().url },
        ],
    },
});

// ── Tabs ──────────────────────────────────────────────────────────────────────
interface Tab { key: TabKey; label: string; description: string }

const tabs: Tab[] = [
    { key: 'SSR',     label: 'Site Survey Report', description: 'Verified survey assignments' },
    { key: 'BAST',    label: 'BAST Report',         description: 'Verified BAST assignments (EVCS & BSS)' },
    { key: 'DAILY',   label: 'Daily Report',        description: 'All verified assignments snapshot' },
    { key: 'HISTORY', label: 'Report History',      description: 'Previously generated reports' },
];

const activeTab = ref<TabKey>('SSR');

// ── Site type filter (BAST tab only) ──────────────────────────────────────────
type SiteTypeFilter = 'ALL' | 'EVCS' | 'BSS';
const siteTypeFilter = ref<SiteTypeFilter>('ALL');

const activeAssignments = computed<Assignment[]>(() => {
    if (activeTab.value === 'HISTORY') return [];
    let list: Assignment[];
    switch (activeTab.value) {
        case 'SSR':   list = props.ssrAssignments; break;
        case 'BAST':  list = props.bastAssignments; break;
        case 'DAILY': list = [...props.ssrAssignments, ...props.bastAssignments]; break;
    }
    if (activeTab.value === 'BAST' && siteTypeFilter.value !== 'ALL') {
        list = list.filter((a) => a.site?.site_type?.name === siteTypeFilter.value);
    }
    return list;
});

function switchTab(tab: TabKey): void {
    activeTab.value = tab;
    selectedIds.value = [];
}

function setSiteTypeFilter(f: SiteTypeFilter): void {
    siteTypeFilter.value = f;
    selectedIds.value = [];
}

function siteTypeName(assignment: Assignment): string {
    return assignment.site?.site_type?.name ?? '';
}

// ── Pagination ────────────────────────────────────────────────────────────────
const PAGE_SIZE = 20;
const currentPage = ref(1);

watch(activeAssignments, () => { currentPage.value = 1; });

const totalPages = computed(() => Math.ceil(activeAssignments.value.length / PAGE_SIZE));

const pagedAssignments = computed<Assignment[]>(() => {
    const start = (currentPage.value - 1) * PAGE_SIZE;
    return activeAssignments.value.slice(start, start + PAGE_SIZE);
});

const pageStart = computed(() => (currentPage.value - 1) * PAGE_SIZE + 1);
const pageEnd = computed(() => Math.min(currentPage.value * PAGE_SIZE, activeAssignments.value.length));

// ── Selection ─────────────────────────────────────────────────────────────────
const selectedIds = ref<number[]>([]);

const allSelected = computed(
    () => activeAssignments.value.length > 0 && selectedIds.value.length === activeAssignments.value.length,
);

const someSelected = computed(
    () => selectedIds.value.length > 0 && !allSelected.value,
);

const headerChecked = computed<boolean | 'indeterminate'>(() => {
    if (allSelected.value) return true;
    if (someSelected.value) return 'indeterminate';
    return false;
});

function toggleAll(): void {
    selectedIds.value = allSelected.value ? [] : activeAssignments.value.map((a) => a.id);
}

function toggleId(id: number): void {
    const idx = selectedIds.value.indexOf(id);
    if (idx >= 0) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
}

// ── Report row expansion ──────────────────────────────────────────────────────
const expandedReportId = ref<number | null>(null);

function toggleReportExpand(id: number): void {
    expandedReportId.value = expandedReportId.value === id ? null : id;
}

// ── Generate report ───────────────────────────────────────────────────────────
const form = useForm({
    report_type: '' as ReportType,
    assignment_ids: [] as number[],
});

function generateReport(): void {
    if (activeTab.value === 'HISTORY') return;
    form.report_type = activeTab.value as ReportType;
    form.assignment_ids = [...selectedIds.value];
    form.post(AdminReportActions.store().url, {
        onSuccess: () => {
            selectedIds.value = [];
        },
    });
}

const regeneratingId = ref<number | null>(null);

function regenerateReport(report: ReportRow): void {
    regeneratingId.value = report.id;
    router.post(AdminReportActions.regenerate(report).url, {}, {
        onFinish: () => { regeneratingId.value = null; },
    });
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const reportTypeBadgeClass: Record<string, string> = {
    SSR:   'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    BAST:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    DAILY: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
};

const reportTypeLabel: Record<string, string> = {
    SSR:      'SSR',
    BAST:     'BAST',
    BAST_EVCS:'BAST',
    BAST_BSS: 'BAST',
    DAILY:    'Daily',
};

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Reports" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <!-- Header -->
        <div class="flex flex-col gap-1">
            <h1 class="text-xl font-semibold tracking-tight">Reports</h1>
            <p class="text-sm text-muted-foreground">Select verified assignments and generate reports (SSR → PDF, BAST/Daily → Excel)</p>
        </div>

        <!-- Tab switcher -->
        <div class="flex flex-wrap gap-1 rounded-xl border border-sidebar-border/70 bg-card p-1 dark:border-sidebar-border">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="flex flex-1 items-center justify-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                :class="
                    activeTab === tab.key
                        ? 'bg-primary text-primary-foreground shadow-sm'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                "
                @click="switchTab(tab.key)"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Assignment table (SSR / BAST / DAILY tabs) -->
        <div
            v-if="activeTab !== 'HISTORY'"
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                <div class="flex flex-col gap-1.5">
                    <p class="text-sm font-medium">{{ tabs.find((t) => t.key === activeTab)?.description }}</p>
                    <p class="text-xs text-muted-foreground">{{ activeAssignments.length }} available · {{ selectedIds.length }} selected</p>
                    <!-- Site type filter (BAST tab only) -->
                    <div v-if="activeTab === 'BAST'" class="flex gap-1">
                        <button
                            v-for="f in (['ALL', 'EVCS', 'BSS'] as const)"
                            :key="f"
                            type="button"
                            class="rounded px-2 py-0.5 text-[11px] font-medium transition-colors"
                            :class="
                                siteTypeFilter === f
                                    ? 'bg-primary text-primary-foreground'
                                    : 'border border-sidebar-border/70 text-muted-foreground hover:bg-muted hover:text-foreground dark:border-sidebar-border'
                            "
                            @click="setSiteTypeFilter(f)"
                        >
                            {{ f === 'ALL' ? 'All Types' : f }}
                        </button>
                    </div>
                </div>
                <Button :disabled="selectedIds.length === 0 || form.processing" @click="generateReport">
                    <FileText class="size-4" />
                    Generate {{ tabs.find((t) => t.key === activeTab)?.label }}
                    <span v-if="selectedIds.length > 0">({{ selectedIds.length }})</span>
                </Button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left">
                                <Checkbox :checked="headerChecked" @update:checked="toggleAll" />
                            </th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Site</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Type</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Subcontractor</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Verified At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="assignment in pagedAssignments"
                            :key="assignment.id"
                            class="cursor-pointer border-t border-sidebar-border/70 transition-colors hover:bg-muted/30 dark:border-sidebar-border"
                            :class="{ 'bg-muted/20': selectedIds.includes(assignment.id) }"
                            @click="toggleId(assignment.id)"
                        >
                            <td class="px-4 py-3" @click.stop>
                                <Checkbox
                                    :checked="selectedIds.includes(assignment.id)"
                                    @update:checked="toggleId(assignment.id)"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span class="font-mono text-xs font-semibold">{{ assignment.site?.site_code ?? '—' }}</span>
                                    <span class="text-xs text-muted-foreground">{{ assignment.site?.location_name ?? '—' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-1">
                                    <ActivityTypeBadge :activity-type="assignment.activity_type" />
                                    <span
                                        v-if="activeTab === 'BAST' && siteTypeName(assignment)"
                                        class="inline-flex w-fit items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                        :class="
                                            siteTypeName(assignment) === 'BSS'
                                                ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
                                                : 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300'
                                        "
                                    >
                                        {{ siteTypeName(assignment) }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ assignment.subcontractor?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ assignment.verified_at ? formatDate(assignment.verified_at) : '—' }}
                            </td>
                        </tr>

                        <tr v-if="activeAssignments.length === 0">
                            <td colspan="5" class="px-4 py-12 text-center text-sm text-muted-foreground">
                                <div class="flex flex-col items-center gap-2 py-4">
                                    <Search class="size-8 text-muted-foreground/60" />
                                    <p class="font-medium">No verified assignments</p>
                                    <p class="text-xs">Assignments must be verified before they appear here.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination bar -->
            <div
                v-if="totalPages > 1"
                class="flex items-center justify-between border-t border-sidebar-border/70 px-4 py-2 dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">
                    Showing {{ pageStart }}–{{ pageEnd }} of {{ activeAssignments.length }}
                </p>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="inline-flex size-7 items-center justify-center rounded border border-sidebar-border/70 text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40 dark:border-sidebar-border"
                        :disabled="currentPage === 1"
                        @click="currentPage--"
                    >
                        <ChevronLeft class="size-3.5" />
                    </button>
                    <span class="px-2 text-xs text-muted-foreground">{{ currentPage }} / {{ totalPages }}</span>
                    <button
                        type="button"
                        class="inline-flex size-7 items-center justify-center rounded border border-sidebar-border/70 text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40 dark:border-sidebar-border"
                        :disabled="currentPage === totalPages"
                        @click="currentPage++"
                    >
                        <ChevronRight class="size-3.5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Report History tab -->
        <div
            v-else
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                <h2 class="text-sm font-semibold">Report History</h2>
                <p class="text-xs text-muted-foreground">Last 15 generated reports · click a row to see its sites</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Type</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Name</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Assignments</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Generated</th>
                            <th class="px-4 py-3 text-right font-medium text-muted-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="report in recentReports" :key="report.id">
                            <tr
                                class="cursor-pointer border-t border-sidebar-border/70 transition-colors hover:bg-muted/30 dark:border-sidebar-border"
                                @click="toggleReportExpand(report.id)"
                            >
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                        :class="reportTypeBadgeClass[report.report_type] ?? 'bg-muted text-muted-foreground'"
                                    >
                                        {{ reportTypeLabel[report.report_type] ?? report.report_type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs">{{ report.name }}</td>
                                <td class="px-4 py-3 text-xs text-muted-foreground">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-foreground">
                                        <component :is="expandedReportId === report.id ? ChevronDown : ChevronRight" class="size-3" />
                                        {{ report.assignments_count }} assignment{{ report.assignments_count !== 1 ? 's' : '' }}
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-xs text-muted-foreground">{{ formatDate(report.created_at) }}</td>
                                <td class="px-4 py-3 text-right" @click.stop>
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-md border border-sidebar-border/70 px-2.5 py-1.5 text-xs font-medium transition-colors hover:bg-muted disabled:opacity-50 dark:border-sidebar-border"
                                            title="Re-generate this report with the same assignments"
                                            :disabled="regeneratingId === report.id"
                                            @click="regenerateReport(report)"
                                        >
                                            <RefreshCw class="size-3.5" :class="{ 'animate-spin': regeneratingId === report.id }" />
                                            Re-generate
                                        </button>
                                        <a
                                            :href="AdminReportActions.download(report).url"
                                            class="inline-flex items-center gap-1 rounded-md border border-sidebar-border/70 px-2.5 py-1.5 text-xs font-medium transition-colors hover:bg-muted dark:border-sidebar-border"
                                            :title="report.report_type === 'SSR' ? 'Download PDF' : 'Download XLSX'"
                                        >
                                            <Download class="size-3.5" />
                                            {{ report.report_type === 'SSR' ? 'PDF' : 'XLSX' }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="expandedReportId === report.id" class="border-t border-sidebar-border/70 dark:border-sidebar-border">
                                <td colspan="5" class="bg-muted/20 px-8 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="site in report.sites"
                                            :key="site.site_code + site.activity_type"
                                            class="inline-flex items-center gap-1 rounded border border-sidebar-border/70 bg-card px-2 py-0.5 text-[11px] dark:border-sidebar-border"
                                        >
                                            <span class="font-mono font-semibold">{{ site.site_code }}</span>
                                            <span class="text-muted-foreground">{{ site.location_name }}</span>
                                            <span class="rounded bg-muted px-1 text-[10px] uppercase text-muted-foreground">{{ site.activity_type }}</span>
                                        </span>
                                        <span v-if="report.sites.length === 0" class="text-xs text-muted-foreground">No sites found.</span>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <tr v-if="recentReports.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                No reports generated yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
