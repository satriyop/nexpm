<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Download, FileText, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import * as AdminReportActions from '@/actions/App/Http/Controllers/Admin/ReportController';
import ActivityTypeBadge from '@/components/ActivityTypeBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { dashboard } from '@/routes';
import type { Assignment } from '@/types';

type ReportType = 'SSR' | 'BAST' | 'DAILY';

interface ReportRow {
    id: number;
    name: string;
    report_type: ReportType;
    assignments_count: number;
    created_at: string;
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
type Tab = { key: ReportType; label: string; description: string };

const tabs: Tab[] = [
    { key: 'SSR', label: 'Site Survey Report', description: 'Verified survey assignments' },
    { key: 'BAST', label: 'BAST Report', description: 'Verified BAST assignments (EVCS & BSS)' },
    { key: 'DAILY', label: 'Daily Report', description: 'All verified assignments snapshot' },
];

const activeTab = ref<ReportType>('SSR');

const activeAssignments = computed<Assignment[]>(() => {
    switch (activeTab.value) {
        case 'SSR':   return props.ssrAssignments;
        case 'BAST':  return props.bastAssignments;
        case 'DAILY': return [...props.ssrAssignments, ...props.bastAssignments];
    }
});

function switchTab(tab: ReportType): void {
    activeTab.value = tab;
    selectedIds.value = [];
}

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

// ── Generate report ───────────────────────────────────────────────────────────
const form = useForm({
    report_type: '' as ReportType,
    assignment_ids: [] as number[],
});

function generateReport(): void {
    form.report_type = activeTab.value;
    form.assignment_ids = [...selectedIds.value];
    form.post(AdminReportActions.store().url, {
        onSuccess: () => {
            selectedIds.value = [];
        },
    });
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const reportTypeBadgeClass: Record<string, string> = {
    SSR: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    BAST: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    DAILY: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
};

const reportTypeLabel: Record<string, string> = {
    SSR: 'SSR',
    BAST: 'BAST',
    BAST_EVCS: 'BAST',
    BAST_BSS: 'BAST',
    DAILY: 'Daily',
};

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Reports" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <!-- Header -->
        <div class="flex flex-col gap-1">
            <h1 class="text-xl font-semibold tracking-tight">Reports</h1>
            <p class="text-sm text-muted-foreground">Select verified assignments and generate Excel reports</p>
        </div>

        <!-- Tab switcher -->
        <div
            class="flex flex-wrap gap-1 rounded-xl border border-sidebar-border/70 bg-card p-1 dark:border-sidebar-border"
        >
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

        <!-- Assignment table -->
        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div class="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                <div>
                    <p class="text-sm font-medium">{{ tabs.find((t) => t.key === activeTab)?.description }}</p>
                    <p class="text-xs text-muted-foreground">{{ activeAssignments.length }} available · {{ selectedIds.length }} selected</p>
                </div>
                <Button
                    :disabled="selectedIds.length === 0 || form.processing"
                    @click="generateReport"
                >
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
                                <!-- Wrapper div handles the click reliably regardless of Checkbox internals -->
                                <div class="cursor-pointer" @click="toggleAll">
                                    <Checkbox :checked="headerChecked" class="pointer-events-none" />
                                </div>
                            </th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Site</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Type</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Subcontractor</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Verified At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="assignment in activeAssignments"
                            :key="assignment.id"
                            class="cursor-pointer border-t border-sidebar-border/70 transition-colors hover:bg-muted/30 dark:border-sidebar-border"
                            :class="{ 'bg-muted/20': selectedIds.includes(assignment.id) }"
                            @click="toggleId(assignment.id)"
                        >
                            <td class="px-4 py-3">
                                <!-- Wrapper div stops row propagation and drives selection -->
                                <div class="cursor-pointer" @click.stop="toggleId(assignment.id)">
                                    <Checkbox
                                        :checked="selectedIds.includes(assignment.id)"
                                        class="pointer-events-none"
                                    />
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span class="font-mono text-xs font-semibold">{{ assignment.site?.site_code ?? '—' }}</span>
                                    <span class="text-xs text-muted-foreground">{{ assignment.site?.location_name ?? '—' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <ActivityTypeBadge :activity-type="assignment.activity_type" />
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
        </div>

        <!-- Recent Reports table -->
        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                <h2 class="text-sm font-semibold">Recent Reports</h2>
                <p class="text-xs text-muted-foreground">Last 15 generated reports</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Type</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Name</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Assignments</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Generated</th>
                            <th class="px-4 py-3 text-right font-medium text-muted-foreground">Download</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="report in recentReports"
                            :key="report.id"
                            class="border-t border-sidebar-border/70 dark:border-sidebar-border"
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
                                {{ report.assignments_count }} assignment{{ report.assignments_count !== 1 ? 's' : '' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ formatDate(report.created_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    :href="AdminReportActions.download(report).url"
                                    class="inline-flex items-center gap-1 rounded-md border border-sidebar-border/70 px-2.5 py-1.5 text-xs font-medium transition-colors hover:bg-muted dark:border-sidebar-border"
                                    title="Download XLSX"
                                >
                                    <Download class="size-3.5" />
                                    XLSX
                                </a>
                            </td>
                        </tr>

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
