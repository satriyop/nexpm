<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    Activity,
    AlertTriangle,
    Bot,
    Clock,
    HelpCircle,
    MapPin,
    ShieldAlert,
    TrendingDown,
    TrendingUp,
    Wrench,
    XCircle,
} from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
import ActivityMatrixChart from '@/components/ActivityMatrixChart.vue';
import ActivityTypeBadge from '@/components/ActivityTypeBadge.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import VelocitySparkline from '@/components/VelocitySparkline.vue';
import WorkloadChart from '@/components/WorkloadChart.vue';
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
    SUBMITTED?: number;
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

interface ChartDataset {
    label: string;
    backgroundColor: string;
    data: number[];
}

interface ActivityChartData {
    labels: string[];
    datasets: ChartDataset[];
}

interface DeadlineRisk {
    id: number;
    name: string;
    total: number;
    completed: number;
    completion_pct: number;
    days_elapsed: number | null;
    days_remaining: number | null;
    expected_pct: number | null;
    gap: number | null;
    risk_level: 'on_track' | 'at_risk' | 'behind' | 'overdue' | 'no_deadline';
}

interface SubcontractorStat {
    id: number;
    name: string;
    total: number;
    completed: number;
    completion_pct: number;
    avg_cycle_days: number | null;
    revision_count: number;
    in_revision: number;
    score: number;
}

interface VelocityWeek {
    week_start: string;
    label: string;
    count: number;
}

interface VelocityTrendData {
    weeks: VelocityWeek[];
    this_week: number;
    last_week: number;
    delta: number;
    four_week_avg: number;
}

interface AgingHeatmap {
    statuses: string[];
    buckets: string[];
    bucket_keys: string[];
    cells: Record<string, Record<string, number>>;
    total_stalled: number;
}

interface WorkloadItem {
    id: number;
    name: string;
    pending: number;
    in_progress: number;
    revision: number;
    completed: number;
    total: number;
}

interface ForecastItem {
    id: number;
    name: string;
    remaining: number;
    weekly_rate: number;
    weeks_to_finish: number | null;
    projected_finish: string | null;
    end_date: string | null;
    on_track: boolean | null;
    risk_level: 'done' | 'on_track' | 'at_risk' | 'late' | 'stalled';
    delay_days: number | null;
    confidence: 'high' | 'medium' | 'low';
    blocker_count: number;
    main_cause: string;
    recommended_action: string;
}

interface ControlTowerItem {
    severity: 'critical' | 'high' | 'medium';
    severity_score: number;
    type: string;
    project_id: number;
    project: string;
    site_id: number;
    site_code: string;
    site_name: string;
    assignment_id: number;
    activity_type: string;
    status: string;
    owner: string;
    problem: string;
    recommended_action: string;
    url: string;
    ai_prompt: string;
}

interface ControlTower {
    generated_at: string;
    metrics: {
        critical_actions: number;
        projects_at_risk: number;
        ready_for_report: number;
        stalled: number;
    };
    priority_queue: ControlTowerItem[];
}

type SiteOverallStatus =
    | 'done'
    | 'blocked'
    | 'stalled'
    | 'needs_review'
    | 'ready_for_report'
    | 'not_started'
    | 'dropped'
    | 'in_progress';

type SiteIssueSeverity = 'critical' | 'high' | 'medium' | 'low' | null;

interface SiteWorkstream {
    assignment_id: number;
    activity_type: string;
    label: string;
    status: string;
    subcontractor: string | null;
    wo_number: string | null;
    age_days: number | null;
    url: string;
}

interface SiteIssue {
    severity: Exclude<SiteIssueSeverity, null>;
    severity_score: number;
    type: string;
    problem: string;
    owner: string;
    recommended_action: string;
    assignment_id: number | null;
    activity_type: string | null;
    status: string | null;
    age_days: number | null;
    site_id: number;
}

interface SiteOperationsRow {
    site_id: number;
    site_code: string;
    location_name: string;
    project_id: number;
    project: string;
    main_contractor: string;
    machine_type: string | null;
    construction_wo_number: string | null;
    overall_status: SiteOverallStatus;
    completion_pct: number;
    active_assignment_count: number;
    workstreams: Record<string, SiteWorkstream | null>;
    main_issue: string;
    issue_type: string | null;
    issue_severity: SiteIssueSeverity;
    issues: SiteIssue[];
    severity_score: number;
    owner: string | null;
    age_days: number | null;
    next_action: string;
    url: string;
    ai_prompt: string;
}

interface SiteOperations {
    generated_at: string;
    metrics: {
        total_sites: number;
        done_sites: number;
        blocked_sites: number;
        stalled_sites: number;
        needs_review_sites: number;
        ready_for_report_sites: number;
        not_started_sites: number;
    };
    problem_breakdown: Record<string, number>;
    filter_options: {
        statuses: string[];
        issue_types: string[];
        machine_types: string[];
        owners: string[];
        wo_numbers: string[];
    };
    site_rows: SiteOperationsRow[];
}

const props = defineProps<{
    siteOperations: SiteOperations | null;
    controlTower: ControlTower | null;
    deadlineRisk: DeadlineRisk[] | null;
    statusCounts: StatusCounts | null;
    activityMatrix: ActivityMatrix | null;
    projectBreakdowns: ProjectBreakdown[] | null;
    recentActivity: RecentActivityItem[] | null;
    activityChart: ActivityChartData | null;
    subcontractorLeaderboard: SubcontractorStat[] | null;
    velocityTrend: VelocityTrendData | null;
    agingHeatmap: AgingHeatmap | null;
    workloadDistribution: WorkloadItem[] | null;
    completionForecast: ForecastItem[] | null;
    mainContractors: MainContractor[] | null;
    projects: Project[] | null;
    filters: { main_contractor_id?: string | null; project_id?: string | null };
}>();

usePoll(30_000, {
    only: [
        'statusCounts',
        'activityMatrix',
        'projectBreakdowns',
        'recentActivity',
        'activityChart',
        'siteOperations',
        'controlTower',
        'deadlineRisk',
        'subcontractorLeaderboard',
        'velocityTrend',
        'agingHeatmap',
        'workloadDistribution',
        'completionForecast',
    ],
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const ALL = '__all__';
const mainContractorId = ref<string>(props.filters?.main_contractor_id ?? ALL);
const projectId = ref<string>(props.filters?.project_id ?? ALL);

const selectedContractorName = computed(
    () =>
        props.mainContractors?.find(
            (mc) => mc.id.toString() === mainContractorId.value,
        )?.name ?? null,
);

const selectedProjectName = computed(
    () =>
        props.projects?.find((p) => p.id.toString() === projectId.value)
            ?.name ?? null,
);

function buildQuery(): Record<string, string> {
    const q: Record<string, string> = {};

    if (mainContractorId.value !== ALL) {
        q.main_contractor_id = mainContractorId.value;
    }

    if (projectId.value !== ALL) {
        q.project_id = projectId.value;
    }

    return q;
}

function applyContractorFilter(value: AcceptableValue): void {
    if (value == null) {
        return;
    }

    const filterValue = String(value);
    mainContractorId.value = filterValue;
    projectId.value = ALL;
    router.get(
        dashboard(),
        filterValue !== ALL ? { main_contractor_id: filterValue } : {},
        {
            preserveState: false,
            replace: true,
        },
    );
}

function applyProjectFilter(value: AcceptableValue): void {
    if (value == null) {
        return;
    }

    projectId.value = String(value);
    const q = buildQuery();
    router.get(dashboard(), q, { preserveState: false, replace: true });
}

const statuses: {
    key: keyof StatusCounts;
    label: string;
    borderClass: string;
    textClass: string;
    dotClass: string;
    bgClass: string;
}[] = [
    {
        key: 'PENDING',
        label: 'Pending',
        borderClass: 'border-l-gray-400',
        textClass: 'text-gray-600 dark:text-gray-400',
        dotClass: 'bg-gray-400',
        bgClass: 'bg-gray-50 dark:bg-gray-900/20',
    },
    {
        key: 'SURVEY',
        label: 'Survey',
        borderClass: 'border-l-sky-500',
        textClass: 'text-sky-600 dark:text-sky-400',
        dotClass: 'bg-sky-500',
        bgClass: 'bg-sky-50 dark:bg-sky-900/20',
    },
    {
        key: 'DOCUMENT',
        label: 'Document',
        borderClass: 'border-l-indigo-500',
        textClass: 'text-indigo-600 dark:text-indigo-400',
        dotClass: 'bg-indigo-500',
        bgClass: 'bg-indigo-50 dark:bg-indigo-900/20',
    },
    {
        key: 'CONSTRUCTION',
        label: 'Construction',
        borderClass: 'border-l-orange-500',
        textClass: 'text-orange-600 dark:text-orange-400',
        dotClass: 'bg-orange-500',
        bgClass: 'bg-orange-50 dark:bg-orange-900/20',
    },
    {
        key: 'MACHINE_ONSITE',
        label: 'Machine Onsite',
        borderClass: 'border-l-amber-500',
        textClass: 'text-amber-600 dark:text-amber-400',
        dotClass: 'bg-amber-500',
        bgClass: 'bg-amber-50 dark:bg-amber-900/20',
    },
    {
        key: 'DONE',
        label: 'Done',
        borderClass: 'border-l-lime-500',
        textClass: 'text-lime-600 dark:text-lime-400',
        dotClass: 'bg-lime-500',
        bgClass: 'bg-lime-50 dark:bg-lime-900/20',
    },
    {
        key: 'LIVE',
        label: 'Live',
        borderClass: 'border-l-green-500',
        textClass: 'text-green-600 dark:text-green-400',
        dotClass: 'bg-green-500',
        bgClass: 'bg-green-50 dark:bg-green-900/20',
    },
    {
        key: 'REGISTRATION',
        label: 'Registration',
        borderClass: 'border-l-teal-500',
        textClass: 'text-teal-600 dark:text-teal-400',
        dotClass: 'bg-teal-500',
        bgClass: 'bg-teal-50 dark:bg-teal-900/20',
    },
    {
        key: 'BILLING',
        label: 'Billing',
        borderClass: 'border-l-cyan-500',
        textClass: 'text-cyan-600 dark:text-cyan-400',
        dotClass: 'bg-cyan-500',
        bgClass: 'bg-cyan-50 dark:bg-cyan-900/20',
    },
    {
        key: 'CONNECTION',
        label: 'Connection',
        borderClass: 'border-l-blue-500',
        textClass: 'text-blue-600 dark:text-blue-400',
        dotClass: 'bg-blue-500',
        bgClass: 'bg-blue-50 dark:bg-blue-900/20',
    },
    {
        key: 'KWH_DONE',
        label: 'KWH Done',
        borderClass: 'border-l-violet-500',
        textClass: 'text-violet-600 dark:text-violet-400',
        dotClass: 'bg-violet-500',
        bgClass: 'bg-violet-50 dark:bg-violet-900/20',
    },
    {
        key: 'SUBMITTED',
        label: 'Submitted',
        borderClass: 'border-l-blue-500',
        textClass: 'text-blue-600 dark:text-blue-400',
        dotClass: 'bg-blue-500',
        bgClass: 'bg-blue-50 dark:bg-blue-900/20',
    },
    {
        key: 'REVISION',
        label: 'Revision',
        borderClass: 'border-l-amber-500',
        textClass: 'text-amber-600 dark:text-amber-400',
        dotClass: 'bg-amber-500',
        bgClass: 'bg-amber-50 dark:bg-amber-900/20',
    },
    {
        key: 'VERIFIED',
        label: 'Verified',
        borderClass: 'border-l-emerald-500',
        textClass: 'text-emerald-600 dark:text-emerald-400',
        dotClass: 'bg-emerald-500',
        bgClass: 'bg-emerald-50 dark:bg-emerald-900/20',
    },
    {
        key: 'REPORTED',
        label: 'Reported',
        borderClass: 'border-l-purple-500',
        textClass: 'text-purple-600 dark:text-purple-400',
        dotClass: 'bg-purple-500',
        bgClass: 'bg-purple-50 dark:bg-purple-900/20',
    },
    {
        key: 'DROP',
        label: 'Drop',
        borderClass: 'border-l-red-500',
        textClass: 'text-red-600 dark:text-red-400',
        dotClass: 'bg-red-500',
        bgClass: 'bg-red-50 dark:bg-red-900/20',
    },
];

const activityRows: { key: string; label: string }[] = [
    { key: 'SURVEY', label: 'Survey' },
    { key: 'PLN_CONNECTION', label: 'PLN Connection' },
    { key: 'CONSTRUCTION', label: 'Construction' },
    { key: 'BAST', label: 'BAST' },
];

const visibleActivityRows = computed(() =>
    activityRows.filter((row) => matrixRowTotal(row.key) > 0),
);

const visibleStatuses = computed(() =>
    statuses.filter((s) => matrixColTotal(s.key) > 0),
);

function getCount(
    counts: StatusCounts | Record<string, number>,
    key: string,
): number {
    return (counts as Record<string, number>)[key] ?? 0;
}

const INTERMEDIATE_STATUSES: (keyof StatusCounts)[] = [
    'SURVEY',
    'DOCUMENT',
    'CONSTRUCTION',
    'MACHINE_ONSITE',
    'DONE',
    'LIVE',
    'REGISTRATION',
    'BILLING',
    'CONNECTION',
    'KWH_DONE',
    'SUBMITTED',
    'REVISION',
];

function projectInProgress(counts: StatusCounts): number {
    return INTERMEDIATE_STATUSES.reduce((sum, k) => sum + (counts[k] ?? 0), 0);
}

function projectTotal(counts: StatusCounts): number {
    const drop = counts.DROP ?? 0;

    return (
        Object.values(counts as Record<string, number>).reduce(
            (sum, v) => sum + v,
            0,
        ) - drop
    );
}

function projectCompletion(counts: StatusCounts): number {
    const total = projectTotal(counts);

    if (total === 0) {
        return 0;
    }

    return Math.round(
        (((counts.VERIFIED ?? 0) + (counts.REPORTED ?? 0)) / total) * 100,
    );
}

function matrixRowTotal(activityKey: string): number {
    if (!props.activityMatrix) {
        return 0;
    }

    const row = props.activityMatrix[activityKey] ?? {};

    return Object.values(row).reduce((sum, v) => sum + v, 0);
}

function matrixColTotal(statusKey: string): number {
    if (!props.activityMatrix) {
        return 0;
    }

    return visibleActivityRows.value.reduce(
        (sum, row) =>
            sum + getCount(props.activityMatrix![row.key] ?? {}, statusKey),
        0,
    );
}

function matrixGrandTotal(): number {
    return statuses.reduce((sum, s) => sum + matrixColTotal(s.key), 0);
}

function assignmentFilterUrl(params: Record<string, string>): string {
    const q = new URLSearchParams(params).toString();

    return `/admin/assignments${q ? '?' + q : ''}`;
}

function askAi(prompt: string): void {
    window.dispatchEvent(
        new CustomEvent('nexpm:ai-assistant:ask', {
            detail: { prompt },
        }),
    );
}

const siteRows = computed(() => props.siteOperations?.site_rows ?? []);
const siteStatusFilter = ref<string>(ALL);
const siteIssueFilter = ref<string>(ALL);
const siteMachineFilter = ref<string>(ALL);
const siteOwnerFilter = ref<string>(ALL);
const siteWoFilter = ref<string>(ALL);
const siteSearch = ref('');
const selectedSite = ref<SiteOperationsRow | null>(null);
const siteDetailOpen = ref(false);
const siteWorkstreamOrder: { key: string; label: string }[] = [
    { key: 'survey', label: 'Survey' },
    { key: 'pln', label: 'PLN' },
    { key: 'construction', label: 'Construction' },
    { key: 'bast', label: 'BAST' },
];
const problemBreakdownRows = computed(() =>
    Object.entries(props.siteOperations?.problem_breakdown ?? {})
        .slice(0, 5)
        .map(([type, count]) => ({
            type,
            count,
            label: issueTypeLabel(type),
        })),
);
const filteredSiteRows = computed(() => {
    const search = siteSearch.value.trim().toLowerCase();

    return siteRows.value.filter((row) => {
        if (
            siteStatusFilter.value !== ALL &&
            row.overall_status !== siteStatusFilter.value
        ) {
            return false;
        }

        if (
            siteIssueFilter.value !== ALL &&
            row.issue_type !== siteIssueFilter.value
        ) {
            return false;
        }

        if (
            siteMachineFilter.value !== ALL &&
            row.machine_type !== siteMachineFilter.value
        ) {
            return false;
        }

        if (siteOwnerFilter.value !== ALL && row.owner !== siteOwnerFilter.value) {
            return false;
        }

        if (
            siteWoFilter.value !== ALL &&
            row.construction_wo_number !== siteWoFilter.value
        ) {
            return false;
        }

        if (search === '') {
            return true;
        }

        return [
            row.site_code,
            row.location_name,
            row.project,
            row.main_contractor,
            row.machine_type,
            row.construction_wo_number,
            row.main_issue,
            row.owner,
        ]
            .filter(Boolean)
            .some((value) => value!.toLowerCase().includes(search));
    });
});
const hiddenSiteCount = computed(
    () => siteRows.value.length - filteredSiteRows.value.length,
);

function severityClass(severity: ControlTowerItem['severity']): string {
    return {
        critical: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
        high: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
        medium: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    }[severity];
}

function siteSeverityClass(severity: SiteIssueSeverity): string {
    return {
        critical: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
        high: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
        medium: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
        low: 'bg-muted text-muted-foreground',
    }[severity ?? 'low'];
}

function siteStatusClass(status: SiteOverallStatus): string {
    return {
        blocked: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
        stalled:
            'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
        needs_review:
            'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
        ready_for_report:
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
        not_started: 'bg-muted text-muted-foreground',
        in_progress:
            'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
        dropped: 'bg-red-50 text-red-500 dark:bg-red-900/10 dark:text-red-400',
        done: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    }[status];
}

function siteStatusLabel(status: SiteOverallStatus | string): string {
    const labels: Record<string, string> = {
        blocked: 'Blocked',
        stalled: 'Stalled',
        needs_review: 'Needs Review',
        ready_for_report: 'Ready Report',
        not_started: 'Not Started',
        in_progress: 'In Progress',
        dropped: 'Dropped',
        done: 'Done',
    };

    return labels[status] ?? status;
}

function issueTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        construction_missing_wo: 'Missing WO',
        site_missing_power: 'Missing Power',
        revision_pending: 'Revision',
        stalled_assignment: 'Stalled',
        ready_for_verification: 'Ready Verify',
        verified_not_reported: 'Not Reported',
        no_assignment_started: 'No Assignment',
        site_dropped: 'Dropped',
    };

    return labels[type] ?? type.replaceAll('_', ' ');
}

function siteWorkstream(
    row: SiteOperationsRow,
    key: string,
): SiteWorkstream | null {
    return row.workstreams[key] ?? null;
}

function workstreamClass(workstream: SiteWorkstream | null): string {
    if (!workstream) {
        return 'border-dashed text-muted-foreground';
    }

    if (['VERIFIED', 'REPORTED'].includes(workstream.status)) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300';
    }

    if (workstream.status === 'REVISION') {
        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300';
    }

    if (workstream.status === 'DROP') {
        return 'border-red-200 bg-red-50 text-red-600 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300';
    }

    return 'border-border bg-background text-foreground';
}

function openSiteDetail(row: SiteOperationsRow): void {
    selectedSite.value = row;
    siteDetailOpen.value = true;
}

function clearSiteFilters(): void {
    siteStatusFilter.value = ALL;
    siteIssueFilter.value = ALL;
    siteMachineFilter.value = ALL;
    siteOwnerFilter.value = ALL;
    siteWoFilter.value = ALL;
    siteSearch.value = '';
}

function forecastRiskClass(riskLevel: ForecastItem['risk_level']): string {
    return {
        done: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
        on_track:
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
        at_risk:
            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
        late: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
        stalled:
            'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
    }[riskLevel];
}

function cellHighlight(statusKey: string, count: number): string {
    if (count === 0) {
        return '';
    }

    const highlights: Record<string, string> = {
        REVISION:
            'bg-amber-50 font-semibold text-amber-700 dark:bg-amber-900/20 dark:text-amber-400',
        SUBMITTED:
            'bg-blue-50 font-semibold text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
        LIVE: 'bg-green-50 font-semibold text-green-700 dark:bg-green-900/20 dark:text-green-400',
        KWH_DONE:
            'bg-violet-50 font-semibold text-violet-700 dark:bg-violet-900/20 dark:text-violet-400',
        DOCUMENT:
            'bg-indigo-50 font-semibold text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-400',
        DROP: 'bg-red-50 font-semibold text-red-700 dark:bg-red-900/20 dark:text-red-400',
    };

    return highlights[statusKey] ?? '';
}

// ── KPI strip ────────────────────────────────────────────────────────────────
const kpiTotal = computed(() =>
    props.statusCounts
        ? Object.values(props.statusCounts as Record<string, number>).reduce(
              (s, v) => s + v,
              0,
          )
        : 0,
);
const kpiCompleted = computed(() =>
    props.statusCounts
        ? (props.statusCounts.VERIFIED ?? 0) +
          (props.statusCounts.REPORTED ?? 0)
        : 0,
);
const kpiInProgress = computed(() =>
    props.statusCounts
        ? INTERMEDIATE_STATUSES.reduce(
              (s, k) => s + (props.statusCounts![k] ?? 0),
              0,
          )
        : 0,
);
const kpiDropped = computed(() => props.statusCounts?.DROP ?? 0);
const kpiCompletionPct = computed(() =>
    kpiTotal.value
        ? Math.round((kpiCompleted.value / kpiTotal.value) * 100)
        : 0,
);

// ── Status legend dialog ──────────────────────────────────────────────────────
const legendOpen = ref(false);
const statusDescriptions: {
    key: keyof StatusCounts;
    label: string;
    description: string;
}[] = [
    {
        key: 'PENDING',
        label: 'Pending',
        description: 'Assignment created, awaiting subcontractor action.',
    },
    {
        key: 'SURVEY',
        label: 'Survey',
        description: 'Survey has been scheduled (date set by subcontractor).',
    },
    {
        key: 'DOCUMENT',
        label: 'Document',
        description: 'Survey complete; all required documents submitted.',
    },
    {
        key: 'CONSTRUCTION',
        label: 'Construction',
        description: 'Construction work is in progress.',
    },
    {
        key: 'MACHINE_ONSITE',
        label: 'Machine Onsite',
        description: 'Charger machine has arrived on site.',
    },
    { key: 'DONE', label: 'Done', description: 'Construction work completed.' },
    {
        key: 'LIVE',
        label: 'Live',
        description: 'Charger is live and operational.',
    },
    {
        key: 'REGISTRATION',
        label: 'Registration',
        description: 'PLN connection registration in progress.',
    },
    {
        key: 'BILLING',
        label: 'Billing',
        description: 'PLN billing setup in progress.',
    },
    {
        key: 'CONNECTION',
        label: 'Connection',
        description: 'PLN connection established.',
    },
    {
        key: 'KWH_DONE',
        label: 'kWh Done',
        description: 'kWh meter installed and verified.',
    },
    {
        key: 'SUBMITTED',
        label: 'Submitted',
        description: 'Subcontractor submitted for admin review.',
    },
    {
        key: 'REVISION',
        label: 'Revision',
        description:
            'Admin requested a revision — subcontractor must resubmit.',
    },
    {
        key: 'VERIFIED',
        label: 'Verified',
        description: 'Admin verified all data; ready for report generation.',
    },
    {
        key: 'REPORTED',
        label: 'Reported',
        description: 'Included in a generated report.',
    },
    {
        key: 'DROP',
        label: 'Dropped',
        description: 'Assignment archived / removed from active tracking.',
    },
];

const projectBreakdownItems = computed(() => props.projectBreakdowns ?? []);
const recentActivityItems = computed(() => props.recentActivity ?? []);

// ── Aging heatmap helpers ─────────────────────────────────────────────────────
const bucketBgClass: Record<string, string> = {
    '0_7': 'bg-transparent',
    '7_14': 'bg-amber-50 dark:bg-amber-900/10',
    '14_30': 'bg-amber-100 dark:bg-amber-900/20',
    '30_60': 'bg-orange-200 dark:bg-orange-900/30',
    '60p': 'bg-red-200 dark:bg-red-900/40',
};

const bucketTextClass: Record<string, string> = {
    '0_7': 'text-foreground',
    '7_14': 'text-amber-700 dark:text-amber-400',
    '14_30': 'text-amber-800 dark:text-amber-300',
    '30_60': 'text-orange-800 dark:text-orange-300',
    '60p': 'text-red-800 dark:text-red-300',
};

function agingCellCount(status: string, bucketKey: string): number {
    return props.agingHeatmap?.cells?.[status]?.[bucketKey] ?? 0;
}

const statusLabelMapFull: Record<string, string> = {
    PENDING: 'Pending',
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
};

function timeAgo(isoString: string): string {
    const diff = Date.now() - new Date(isoString).getTime();
    const seconds = Math.floor(diff / 1000);

    if (seconds < 60) {
        return 'just now';
    }

    const minutes = Math.floor(seconds / 60);

    if (minutes < 60) {
        return `${minutes}m ago`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return `${hours}h ago`;
    }

    const days = Math.floor(hours / 24);

    if (days < 30) {
        return `${days}d ago`;
    }

    const months = Math.floor(days / 30);

    if (months < 12) {
        return `${months}mo ago`;
    }

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
                    <span
                        v-if="selectedContractorName"
                        class="font-medium text-foreground"
                    >
                        — {{ selectedContractorName }}</span
                    >
                    <span
                        v-if="selectedProjectName"
                        class="font-medium text-foreground"
                    >
                        / {{ selectedProjectName }}</span
                    >
                </p>
            </div>
            <div
                v-if="mainContractors"
                class="flex flex-wrap items-center gap-2"
            >
                <Select
                    :model-value="mainContractorId"
                    @update:model-value="applyContractorFilter"
                >
                    <SelectTrigger class="w-48">
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
                <Select
                    v-if="projects && projects.length > 0"
                    :model-value="projectId"
                    @update:model-value="applyProjectFilter"
                >
                    <SelectTrigger class="w-48">
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
        </div>

        <!-- KPI Strip -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-5">
            <template v-if="statusCounts != null">
                <div
                    class="flex flex-col gap-1 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <div
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <Activity class="size-3.5" />
                        Total Assignments
                    </div>
                    <p class="text-3xl font-bold tracking-tight">
                        {{ kpiTotal }}
                    </p>
                </div>
                <div
                    class="flex flex-col gap-1 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <div
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <TrendingUp class="size-3.5" />
                        In Progress
                    </div>
                    <p
                        class="text-3xl font-bold tracking-tight text-blue-600 dark:text-blue-400"
                    >
                        {{ kpiInProgress }}
                    </p>
                </div>
                <div
                    class="flex flex-col gap-1 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <div
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <CheckCircle2 class="size-3.5" />
                        Completed
                    </div>
                    <div class="flex items-end gap-2">
                        <p
                            class="text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400"
                        >
                            {{ kpiCompleted }}
                        </p>
                        <p
                            class="mb-0.5 text-sm font-medium text-muted-foreground"
                        >
                            {{ kpiCompletionPct }}%
                        </p>
                    </div>
                </div>
                <div
                    class="flex flex-col gap-1 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <div
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <XCircle class="size-3.5" />
                        Dropped
                    </div>
                    <p
                        class="text-3xl font-bold tracking-tight"
                        :class="
                            kpiDropped > 0
                                ? 'text-red-600 dark:text-red-400'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ kpiDropped }}
                    </p>
                </div>
            </template>
            <template v-else>
                <div
                    v-for="n in 4"
                    :key="n"
                    class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <div class="h-3 w-24 animate-pulse rounded bg-muted" />
                    <div class="h-9 w-16 animate-pulse rounded bg-muted" />
                </div>
            </template>

            <!-- Velocity card (5th) -->
            <div
                class="col-span-2 flex flex-col gap-1 rounded-xl border border-sidebar-border/70 bg-card p-4 md:col-span-4 xl:col-span-1 dark:border-sidebar-border"
            >
                <template v-if="velocityTrend != null">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground"
                            >Velocity (12w)</span
                        >
                        <span
                            class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                            :class="
                                velocityTrend.delta > 0
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                    : velocityTrend.delta < 0
                                      ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                      : 'bg-muted text-muted-foreground'
                            "
                        >
                            <TrendingUp
                                v-if="velocityTrend.delta > 0"
                                class="size-2.5"
                            />
                            <TrendingDown
                                v-else-if="velocityTrend.delta < 0"
                                class="size-2.5"
                            />
                            {{ velocityTrend.delta > 0 ? '+' : ''
                            }}{{ velocityTrend.delta }}
                        </span>
                    </div>
                    <div class="h-14 w-full">
                        <VelocitySparkline :weeks="velocityTrend.weeks" />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        <span class="font-semibold text-foreground">{{
                            velocityTrend.this_week
                        }}</span>
                        this week · {{ velocityTrend.four_week_avg }} avg/wk
                    </p>
                </template>
                <template v-else>
                    <div class="h-3 w-24 animate-pulse rounded bg-muted" />
                    <div class="h-14 animate-pulse rounded bg-muted" />
                    <div class="h-3 w-32 animate-pulse rounded bg-muted" />
                </template>
            </div>
        </div>

        <!-- Section: Location Operations -->
        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div
                class="flex flex-wrap items-start justify-between gap-3 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <div>
                    <h2 class="text-sm font-semibold">Location Operations</h2>
                    <p class="text-xs text-muted-foreground">
                        Site-first view of blocked, stalled, review, and
                        report-ready locations
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1.5 text-xs font-medium transition hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="siteOperations == null"
                    @click="
                        askAi(
                            'Lokasi mana yang belum selesai dan apa blocker utamanya?',
                        )
                    "
                >
                    <Bot class="size-3.5" />
                    Ask AI
                </button>
            </div>

            <template v-if="siteOperations != null">
                <div
                    class="grid grid-cols-2 gap-3 border-b border-sidebar-border/70 p-4 md:grid-cols-3 xl:grid-cols-6 dark:border-sidebar-border"
                >
                    <div class="rounded-md border border-border p-3">
                        <div
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <MapPin class="size-3.5" />
                            Locations
                        </div>
                        <p class="mt-1 text-2xl font-semibold">
                            {{ siteOperations.metrics.total_sites }}
                        </p>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <div
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <ShieldAlert class="size-3.5" />
                            Blocked
                        </div>
                        <p class="mt-1 text-2xl font-semibold text-red-600">
                            {{ siteOperations.metrics.blocked_sites }}
                        </p>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <div
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <Clock class="size-3.5" />
                            Stalled
                        </div>
                        <p class="mt-1 text-2xl font-semibold text-orange-600">
                            {{ siteOperations.metrics.stalled_sites }}
                        </p>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <div
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <Wrench class="size-3.5" />
                            Review
                        </div>
                        <p class="mt-1 text-2xl font-semibold text-blue-600">
                            {{ siteOperations.metrics.needs_review_sites }}
                        </p>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <div
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <CheckCircle2 class="size-3.5" />
                            Ready Report
                        </div>
                        <p
                            class="mt-1 text-2xl font-semibold text-emerald-600"
                        >
                            {{ siteOperations.metrics.ready_for_report_sites }}
                        </p>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <div
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <CheckCircle2 class="size-3.5" />
                            Done
                        </div>
                        <p
                            class="mt-1 text-2xl font-semibold text-emerald-600"
                        >
                            {{ siteOperations.metrics.done_sites }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="problemBreakdownRows.length > 0"
                    class="flex flex-wrap gap-2 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                >
                    <span
                        class="inline-flex items-center text-xs font-medium text-muted-foreground"
                    >
                        Main issues
                    </span>
                    <span
                        v-for="item in problemBreakdownRows"
                        :key="item.type"
                        class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-1 text-xs"
                    >
                        {{ item.label }}
                        <span class="font-semibold tabular-nums">{{
                            item.count
                        }}</span>
                    </span>
                </div>

                <div
                    class="grid gap-2 border-b border-sidebar-border/70 p-4 md:grid-cols-2 xl:grid-cols-6 dark:border-sidebar-border"
                >
                    <input
                        v-model="siteSearch"
                        type="search"
                        placeholder="Search site, project, issue, owner"
                        class="h-9 rounded-md border border-border bg-background px-3 text-sm outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20 md:col-span-2 xl:col-span-1"
                    />
                    <Select v-model="siteStatusFilter">
                        <SelectTrigger class="h-9">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All statuses</SelectItem>
                            <SelectItem
                                v-for="status in siteOperations.filter_options.statuses"
                                :key="status"
                                :value="status"
                            >
                                {{ siteStatusLabel(status) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="siteIssueFilter">
                        <SelectTrigger class="h-9">
                            <SelectValue placeholder="Issue" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All issues</SelectItem>
                            <SelectItem
                                v-for="issue in siteOperations.filter_options.issue_types"
                                :key="issue"
                                :value="issue"
                            >
                                {{ issueTypeLabel(issue) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="siteMachineFilter">
                        <SelectTrigger class="h-9">
                            <SelectValue placeholder="Machine" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All machines</SelectItem>
                            <SelectItem
                                v-for="machine in siteOperations.filter_options.machine_types"
                                :key="machine"
                                :value="machine"
                            >
                                {{ machine }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="siteWoFilter">
                        <SelectTrigger class="h-9">
                            <SelectValue placeholder="WO" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All WO</SelectItem>
                            <SelectItem
                                v-for="woNumber in siteOperations.filter_options.wo_numbers"
                                :key="woNumber"
                                :value="woNumber"
                            >
                                {{ woNumber }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <div class="flex gap-2">
                        <Select v-model="siteOwnerFilter">
                            <SelectTrigger class="h-9 min-w-0 flex-1">
                                <SelectValue placeholder="Owner" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="ALL">All owners</SelectItem>
                                <SelectItem
                                    v-for="owner in siteOperations.filter_options.owners"
                                    :key="owner"
                                    :value="owner"
                                >
                                    {{ owner }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <button
                            type="button"
                            class="h-9 rounded-md border border-border px-3 text-xs font-medium transition hover:bg-accent"
                            @click="clearSiteFilters"
                        >
                            Clear
                        </button>
                    </div>
                    <div
                        class="text-xs text-muted-foreground md:col-span-2 xl:col-span-6"
                    >
                        Showing {{ filteredSiteRows.length }} of
                        {{ siteRows.length }} priority locations
                        <span v-if="hiddenSiteCount > 0">
                            · {{ hiddenSiteCount }} hidden by filters</span
                        >
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/40 text-xs tracking-wide uppercase"
                        >
                            <tr>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Location
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Status
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Workstreams
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Why Not Done
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Owner / Age
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Next Action
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
                                v-for="row in filteredSiteRows"
                                :key="row.site_id"
                                class="border-t border-sidebar-border/70 transition-colors hover:bg-muted/30 dark:border-sidebar-border"
                            >
                                <td class="min-w-56 px-4 py-3">
                                    <Link
                                        :href="row.url"
                                        class="font-medium hover:underline"
                                    >
                                        {{ row.site_code }}
                                    </Link>
                                    <div class="text-xs text-muted-foreground">
                                        {{ row.location_name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ row.project }} ·
                                        {{ row.machine_type ?? 'No machine' }}
                                    </div>
                                    <div
                                        v-if="row.construction_wo_number"
                                        class="text-xs text-muted-foreground"
                                    >
                                        WO {{ row.construction_wo_number }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase"
                                        :class="
                                            siteStatusClass(row.overall_status)
                                        "
                                    >
                                        {{
                                            siteStatusLabel(row.overall_status)
                                        }}
                                    </span>
                                    <div
                                        class="mt-2 h-1.5 w-24 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full bg-emerald-500"
                                            :style="{
                                                width:
                                                    row.completion_pct + '%',
                                            }"
                                        />
                                    </div>
                                    <div
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ row.completion_pct }}% ·
                                        {{ row.active_assignment_count }}
                                        active
                                    </div>
                                </td>
                                <td class="min-w-72 px-4 py-3">
                                    <div class="grid grid-cols-2 gap-1.5">
                                        <template
                                            v-for="stream in siteWorkstreamOrder"
                                            :key="stream.key"
                                        >
                                            <Link
                                                v-if="
                                                    siteWorkstream(
                                                        row,
                                                        stream.key,
                                                    )
                                                "
                                                :href="
                                                    siteWorkstream(
                                                        row,
                                                        stream.key,
                                                    )!.url
                                                "
                                                class="rounded-md border px-2 py-1 text-xs transition hover:bg-accent"
                                                :class="
                                                    workstreamClass(
                                                        siteWorkstream(
                                                            row,
                                                            stream.key,
                                                        ),
                                                    )
                                                "
                                            >
                                                <span class="font-medium">{{
                                                    stream.label
                                                }}</span>
                                                <span
                                                    class="ml-1 text-muted-foreground"
                                                >
                                                    {{
                                                        siteWorkstream(
                                                            row,
                                                            stream.key,
                                                        )!.status
                                                    }}
                                                </span>
                                                <span
                                                    v-if="
                                                        siteWorkstream(
                                                            row,
                                                            stream.key,
                                                        )!.wo_number
                                                    "
                                                    class="ml-1 text-muted-foreground"
                                                >
                                                    · WO
                                                    {{
                                                        siteWorkstream(
                                                            row,
                                                            stream.key,
                                                        )!.wo_number
                                                    }}
                                                </span>
                                            </Link>
                                            <span
                                                v-else
                                                class="rounded-md border border-dashed px-2 py-1 text-xs text-muted-foreground"
                                                :class="
                                                    workstreamClass(null)
                                                "
                                            >
                                                {{ stream.label }} -
                                            </span>
                                        </template>
                                    </div>
                                </td>
                                <td class="min-w-64 px-4 py-3">
                                    <div class="flex flex-col gap-1">
                                        <span>{{ row.main_issue }}</span>
                                        <span
                                            v-if="row.issue_severity"
                                            class="w-fit rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase"
                                            :class="
                                                siteSeverityClass(
                                                    row.issue_severity,
                                                )
                                            "
                                        >
                                            {{ row.issue_severity }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div>{{ row.owner ?? '-' }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            row.age_days != null
                                                ? row.age_days + 'd since update'
                                                : 'No age signal'
                                        }}
                                    </div>
                                </td>
                                <td class="min-w-64 px-4 py-3">
                                    {{ row.next_action }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-1.5">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs transition hover:bg-accent"
                                            @click="openSiteDetail(row)"
                                        >
                                            Detail
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs transition hover:bg-accent"
                                            @click="askAi(row.ai_prompt)"
                                        >
                                            <Bot class="size-3" />
                                            Ask
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredSiteRows.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                                >
                                    No location data available.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <div v-else class="flex flex-col gap-2 p-4">
                <div
                    v-for="n in 5"
                    :key="n"
                    class="h-10 animate-pulse rounded bg-muted"
                />
            </div>
        </div>

        <!-- Section: Project Control Tower -->
        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div
                class="flex flex-wrap items-start justify-between gap-3 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <div>
                    <h2 class="text-sm font-semibold">
                        Project Control Tower
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Today's highest-impact blockers, owners, and next
                        actions
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1.5 text-xs font-medium transition hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="controlTower == null"
                    @click="askAi('Briefing Project Control Tower hari ini')"
                >
                    <Bot class="size-3.5" />
                    Ask AI
                </button>
            </div>
            <template v-if="controlTower != null">
                <div class="grid grid-cols-2 gap-3 border-b p-4 lg:grid-cols-4">
                    <div class="rounded-md border border-border p-3">
                        <div
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <AlertTriangle class="size-3.5" />
                            Critical Actions
                        </div>
                        <p class="mt-1 text-2xl font-semibold text-red-600">
                            {{ controlTower.metrics.critical_actions }}
                        </p>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <div
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <TrendingDown class="size-3.5" />
                            Projects At Risk
                        </div>
                        <p class="mt-1 text-2xl font-semibold text-amber-600">
                            {{ controlTower.metrics.projects_at_risk }}
                        </p>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <div
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <CheckCircle2 class="size-3.5" />
                            Ready for Report
                        </div>
                        <p
                            class="mt-1 text-2xl font-semibold text-emerald-600"
                        >
                            {{ controlTower.metrics.ready_for_report }}
                        </p>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <div
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <Clock class="size-3.5" />
                            Stalled
                        </div>
                        <p class="mt-1 text-2xl font-semibold text-orange-600">
                            {{ controlTower.metrics.stalled }}
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/40 text-xs tracking-wide uppercase"
                        >
                            <tr>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Severity
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Project / Site
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Problem
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Owner
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Recommended Action
                                </th>
                                <th
                                    class="px-4 py-3 text-right font-medium text-muted-foreground"
                                >
                                    AI
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in controlTower.priority_queue"
                                :key="`${item.type}-${item.assignment_id}`"
                                class="border-t border-sidebar-border/70 transition-colors hover:bg-muted/30 dark:border-sidebar-border"
                            >
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase"
                                        :class="severityClass(item.severity)"
                                    >
                                        {{ item.severity }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <Link
                                        :href="item.url"
                                        class="font-medium hover:underline"
                                    >
                                        {{ item.project }}
                                    </Link>
                                    <div class="text-xs text-muted-foreground">
                                        {{ item.site_code }} ·
                                        {{ item.activity_type }} ·
                                        {{ item.status }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ item.problem }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ item.owner }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ item.recommended_action }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs transition hover:bg-accent"
                                        @click="askAi(item.ai_prompt)"
                                    >
                                        <Bot class="size-3" />
                                        Ask
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="controlTower.priority_queue.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                                >
                                    No critical control tower actions right now.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
            <div v-else class="flex flex-col gap-2 p-4">
                <div
                    v-for="n in 4"
                    :key="n"
                    class="h-10 animate-pulse rounded bg-muted"
                />
            </div>
        </div>

        <!-- Section: Deadline Risk -->
        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div
                class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-semibold">Project Deadline Risk</h2>
                <p class="text-xs text-muted-foreground">
                    Actual completion pace vs expected pace — click a project to
                    drill down
                </p>
            </div>
            <div class="overflow-x-auto">
                <template v-if="deadlineRisk != null">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/40 text-xs tracking-wide uppercase"
                        >
                            <tr>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Project
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Risk
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Progress
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Completed
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Expected
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Days Left
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="project in deadlineRisk"
                                :key="project.id"
                                class="cursor-pointer border-t border-sidebar-border/70 transition-colors hover:bg-muted/30 dark:border-sidebar-border"
                                @click="
                                    router.visit(
                                        assignmentFilterUrl({
                                            project_id: project.id.toString(),
                                        }),
                                    )
                                "
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ project.name }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase"
                                        :class="{
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400':
                                                project.risk_level ===
                                                'on_track',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400':
                                                project.risk_level ===
                                                'at_risk',
                                            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400':
                                                project.risk_level ===
                                                    'behind' ||
                                                project.risk_level ===
                                                    'overdue',
                                            'bg-muted text-muted-foreground':
                                                project.risk_level ===
                                                'no_deadline',
                                        }"
                                    >
                                        {{
                                            project.risk_level === 'on_track'
                                                ? '✓ On Track'
                                                : project.risk_level ===
                                                    'at_risk'
                                                  ? '⚠ At Risk'
                                                  : project.risk_level ===
                                                      'behind'
                                                    ? '✕ Behind'
                                                    : project.risk_level ===
                                                        'overdue'
                                                      ? '✕ Overdue'
                                                      : '— No Deadline'
                                        }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        class="relative h-2 w-40 overflow-hidden rounded-full bg-muted"
                                    >
                                        <!-- Expected pace marker -->
                                        <div
                                            v-if="project.expected_pct !== null"
                                            class="absolute top-0 h-full w-0.5 bg-foreground/20"
                                            :style="{
                                                left:
                                                    project.expected_pct + '%',
                                            }"
                                        />
                                        <!-- Actual completion bar -->
                                        <div
                                            class="h-full rounded-full transition-all"
                                            :class="{
                                                'bg-emerald-500':
                                                    project.risk_level ===
                                                    'on_track',
                                                'bg-amber-500':
                                                    project.risk_level ===
                                                    'at_risk',
                                                'bg-red-500':
                                                    project.risk_level ===
                                                        'behind' ||
                                                    project.risk_level ===
                                                        'overdue',
                                                'bg-muted-foreground/40':
                                                    project.risk_level ===
                                                    'no_deadline',
                                            }"
                                            :style="{
                                                width:
                                                    project.completion_pct +
                                                    '%',
                                            }"
                                        />
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums">
                                    {{ project.completion_pct }}%
                                    <span
                                        class="ml-1 text-xs text-muted-foreground"
                                        >({{ project.completed }}/{{
                                            project.total
                                        }})</span
                                    >
                                </td>
                                <td
                                    class="px-4 py-3 text-center text-muted-foreground tabular-nums"
                                >
                                    {{
                                        project.expected_pct !== null
                                            ? project.expected_pct + '%'
                                            : '—'
                                    }}
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums">
                                    <span
                                        v-if="project.days_remaining !== null"
                                        :class="{
                                            'text-emerald-600 dark:text-emerald-400':
                                                project.days_remaining > 30,
                                            'text-amber-600 dark:text-amber-400':
                                                project.days_remaining > 0 &&
                                                project.days_remaining <= 30,
                                            'text-red-600 dark:text-red-400':
                                                project.days_remaining <= 0,
                                        }"
                                    >
                                        {{
                                            project.days_remaining < 0
                                                ? Math.abs(
                                                      project.days_remaining,
                                                  ) + 'd overdue'
                                                : project.days_remaining + 'd'
                                        }}
                                    </span>
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </td>
                            </tr>
                            <tr v-if="deadlineRisk.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                                >
                                    No project data available.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
                <div v-else class="flex flex-col gap-2 p-4">
                    <div
                        v-for="n in 3"
                        :key="n"
                        class="h-10 animate-pulse rounded bg-muted"
                    />
                </div>
            </div>
        </div>

        <!-- Section: Completion Forecast -->
        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div
                class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-semibold">Completion Forecast</h2>
                <p class="text-xs text-muted-foreground">
                    Projected finish date per project based on 4-week rolling
                    pace — update weekly to stay accurate
                </p>
            </div>
            <div class="overflow-x-auto">
                <template v-if="completionForecast != null">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/40 text-xs tracking-wide uppercase"
                        >
                            <tr>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Project
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Remaining
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Pace / week
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Projected Finish
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Deadline
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Risk
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Main Cause
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in completionForecast"
                                :key="item.id"
                                class="cursor-pointer border-t border-sidebar-border/70 transition-colors hover:bg-muted/30 dark:border-sidebar-border"
                                @click="
                                    router.visit(
                                        assignmentFilterUrl({
                                            project_id: item.id.toString(),
                                        }),
                                    )
                                "
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{ item.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ item.recommended_action }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums">
                                    {{ item.remaining }}
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums">
                                    <span v-if="item.weekly_rate > 0"
                                        >{{ item.weekly_rate }}/wk</span
                                    >
                                    <span
                                        v-else
                                        class="text-xs text-amber-600 dark:text-amber-400"
                                        >No recent progress</span
                                    >
                                </td>
                                <td class="px-4 py-3 text-center font-medium">
                                    <span
                                        v-if="item.projected_finish === 'Done'"
                                        class="text-emerald-600 dark:text-emerald-400"
                                        >Done</span
                                    >
                                    <span v-else-if="item.projected_finish">{{
                                        item.projected_finish
                                    }}</span>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                        >Insufficient data</span
                                    >
                                    <div
                                        v-if="item.delay_days && item.delay_days > 0"
                                        class="text-xs text-red-600 dark:text-red-400"
                                    >
                                        +{{ item.delay_days }}d delay
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 text-center text-muted-foreground"
                                >
                                    {{ item.end_date ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase"
                                        :class="
                                            forecastRiskClass(item.risk_level)
                                        "
                                        >{{
                                            item.risk_level.replace('_', ' ')
                                        }}</span
                                    >
                                    <div class="mt-1 text-xs text-muted-foreground">
                                        {{ item.confidence }} confidence
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div>{{ item.main_cause }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ item.blocker_count }} blocker signals
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="completionForecast.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                                >
                                    No project data available.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
                <div v-else class="flex flex-col gap-2 p-4">
                    <div
                        v-for="n in 3"
                        :key="n"
                        class="h-10 animate-pulse rounded bg-muted"
                    />
                </div>
            </div>
        </div>

        <!-- Section 1: Status Summary Cards (clickable, zero-count cards hidden) -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-8">
            <template v-if="statusCounts != null">
                <template v-for="stat in statuses" :key="stat.key">
                    <Link
                        v-if="getCount(props.statusCounts!, stat.key) > 0"
                        :href="assignmentFilterUrl({ status: stat.key })"
                        class="rounded-xl border border-l-4 border-sidebar-border/70 bg-card p-4 transition-colors hover:bg-muted/30 dark:border-sidebar-border"
                        :class="stat.borderClass"
                    >
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-1.5">
                                <span
                                    class="size-2 shrink-0 rounded-full"
                                    :class="stat.dotClass"
                                />
                                <span
                                    class="text-xs font-medium text-muted-foreground"
                                    >{{ stat.label }}</span
                                >
                            </div>
                            <p
                                class="text-3xl font-bold tracking-tight"
                                :class="stat.textClass"
                            >
                                {{ getCount(props.statusCounts!, stat.key) }}
                            </p>
                        </div>
                    </Link>
                </template>
            </template>
            <template v-else>
                <div
                    v-for="n in 8"
                    :key="n"
                    class="h-20 animate-pulse rounded-xl bg-muted"
                />
            </template>
        </div>

        <!-- Section: Assignment Aging Heatmap -->
        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div
                class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-semibold">Assignment Aging</h2>
                <p class="text-xs text-muted-foreground">
                    Active assignments by status and time since last update —
                    redder = older
                </p>
            </div>

            <template v-if="agingHeatmap != null">
                <!-- Stalled warning banner -->
                <div
                    v-if="agingHeatmap.total_stalled > 0"
                    class="flex items-center gap-2 border-b border-sidebar-border/70 bg-red-50 px-4 py-2 text-sm text-red-700 dark:border-sidebar-border dark:bg-red-900/20 dark:text-red-300"
                >
                    <span class="font-semibold"
                        >⚠ {{ agingHeatmap.total_stalled }} assignment{{
                            agingHeatmap.total_stalled === 1 ? '' : 's'
                        }}</span
                    >
                    <span>with no progress in 30+ days</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/40 text-xs tracking-wide uppercase"
                        >
                            <tr>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Status
                                </th>
                                <th
                                    v-for="(
                                        bucket, idx
                                    ) in agingHeatmap.buckets"
                                    :key="bucket"
                                    class="px-4 py-3 text-center font-medium"
                                    :class="
                                        idx >= 3
                                            ? 'text-red-500 dark:text-red-400'
                                            : idx >= 2
                                              ? 'text-orange-500 dark:text-orange-400'
                                              : idx >= 1
                                                ? 'text-amber-600 dark:text-amber-400'
                                                : 'text-muted-foreground'
                                    "
                                >
                                    {{ bucket }}
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="status in agingHeatmap.statuses"
                                :key="status"
                                class="border-t border-sidebar-border/70 dark:border-sidebar-border"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ statusLabelMapFull[status] ?? status }}
                                </td>
                                <td
                                    v-for="bucketKey in agingHeatmap.bucket_keys"
                                    :key="bucketKey"
                                    class="px-4 py-2 text-center"
                                    :class="bucketBgClass[bucketKey]"
                                >
                                    <Link
                                        v-if="
                                            agingCellCount(status, bucketKey) >
                                            0
                                        "
                                        :href="assignmentFilterUrl({ status })"
                                        class="inline-block min-w-8 rounded px-2 py-0.5 font-semibold tabular-nums transition-colors hover:underline"
                                        :class="bucketTextClass[bucketKey]"
                                        @click.stop
                                    >
                                        {{ agingCellCount(status, bucketKey) }}
                                    </Link>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground/40"
                                        >—</span
                                    >
                                </td>
                                <td
                                    class="px-4 py-3 text-center font-semibold text-muted-foreground tabular-nums"
                                >
                                    {{
                                        agingHeatmap.bucket_keys.reduce(
                                            (s, k) =>
                                                s + agingCellCount(status, k),
                                            0,
                                        )
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
            <div v-else class="grid grid-cols-5 gap-2 p-4">
                <div
                    v-for="n in 20"
                    :key="n"
                    class="h-8 animate-pulse rounded bg-muted"
                />
            </div>
        </div>

        <!-- Section 2: Activity × Status Pipeline Matrix -->
        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div
                class="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <div>
                    <h2 class="text-sm font-semibold">Activity Pipeline</h2>
                    <p class="text-xs text-muted-foreground">
                        Assignment counts by activity type and status — click
                        any cell to drill down
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    @click="legendOpen = true"
                >
                    <HelpCircle class="size-3.5" />
                    Status guide
                </button>
            </div>

            <div class="overflow-x-auto">
                <template v-if="activityMatrix != null">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/40 text-xs tracking-wide uppercase"
                        >
                            <tr>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Activity
                                </th>
                                <th
                                    v-for="stat in visibleStatuses"
                                    :key="stat.key"
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <span
                                            class="size-1.5 rounded-full"
                                            :class="stat.dotClass"
                                        />
                                        {{ stat.label }}
                                    </span>
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in visibleActivityRows"
                                :key="row.key"
                                class="border-t border-sidebar-border/70 dark:border-sidebar-border"
                            >
                                <td class="px-4 py-3 font-medium">
                                    <ActivityTypeBadge
                                        :activity-type="row.key"
                                    />
                                </td>
                                <td
                                    v-for="stat in visibleStatuses"
                                    :key="stat.key"
                                    class="px-4 py-3 text-center"
                                >
                                    <Link
                                        :href="
                                            assignmentFilterUrl({
                                                activity_type: row.key,
                                                status: stat.key,
                                            })
                                        "
                                        class="inline-block min-w-8 rounded px-2 py-0.5 text-sm transition-colors hover:ring-1 hover:ring-sidebar-border"
                                        :class="
                                            cellHighlight(
                                                stat.key,
                                                getCount(
                                                    activityMatrix![row.key] ??
                                                        {},
                                                    stat.key,
                                                ),
                                            )
                                        "
                                    >
                                        {{
                                            getCount(
                                                activityMatrix![row.key] ?? {},
                                                stat.key,
                                            )
                                        }}
                                    </Link>
                                </td>
                                <td
                                    class="px-4 py-3 text-center font-semibold text-muted-foreground"
                                >
                                    {{ matrixRowTotal(row.key) }}
                                </td>
                            </tr>

                            <!-- Column totals -->
                            <tr
                                class="border-t-2 border-sidebar-border/70 bg-muted/20 dark:border-sidebar-border"
                            >
                                <td
                                    class="px-4 py-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Total
                                </td>
                                <td
                                    v-for="stat in visibleStatuses"
                                    :key="stat.key"
                                    class="px-4 py-3 text-center font-semibold"
                                >
                                    <Link
                                        :href="
                                            assignmentFilterUrl({
                                                status: stat.key,
                                            })
                                        "
                                        class="inline-block min-w-8 rounded px-2 py-0.5 transition-colors hover:bg-muted"
                                        :class="stat.textClass"
                                    >
                                        {{ matrixColTotal(stat.key) }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-center font-bold">
                                    {{ matrixGrandTotal() }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
                <div v-else class="flex flex-col gap-2 p-4">
                    <div
                        v-for="n in 4"
                        :key="n"
                        class="h-10 animate-pulse rounded bg-muted"
                    />
                </div>
            </div>
        </div>

        <!-- Section 3: Per-project breakdown -->
        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div
                class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-semibold">Project Breakdown</h2>
                <p class="text-xs text-muted-foreground">
                    Assignment status per project
                </p>
            </div>

            <div class="overflow-x-auto">
                <template v-if="projectBreakdowns != null">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/40 text-xs tracking-wide uppercase"
                        >
                            <tr>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Project
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Pending
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    In Progress
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Verified
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Reported
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Total
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Completion
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
                                v-for="project in projectBreakdownItems"
                                :key="project.id"
                                class="border-t border-sidebar-border/70 transition-colors hover:bg-muted/30 dark:border-sidebar-border"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ project.name }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <span
                                            class="size-1.5 rounded-full bg-gray-400"
                                        />
                                        {{
                                            getCount(project.counts, 'PENDING')
                                        }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <span
                                            class="size-1.5 rounded-full bg-blue-500"
                                        />
                                        {{ projectInProgress(project.counts) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <span
                                            class="size-1.5 rounded-full bg-emerald-500"
                                        />
                                        {{
                                            getCount(project.counts, 'VERIFIED')
                                        }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <span
                                            class="size-1.5 rounded-full bg-purple-500"
                                        />
                                        {{
                                            getCount(project.counts, 'REPORTED')
                                        }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center font-semibold">
                                    {{ projectTotal(project.counts) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="h-1.5 w-20 overflow-hidden rounded-full bg-muted"
                                        >
                                            <div
                                                class="h-full rounded-full bg-emerald-500 transition-all"
                                                :style="{
                                                    width:
                                                        projectCompletion(
                                                            project.counts,
                                                        ) + '%',
                                                }"
                                            />
                                        </div>
                                        <span
                                            class="text-xs text-muted-foreground tabular-nums"
                                        >
                                            {{
                                                projectCompletion(
                                                    project.counts,
                                                )
                                            }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="
                                            assignmentFilterUrl({
                                                project_id:
                                                    project.id.toString(),
                                            })
                                        "
                                        class="inline-flex items-center gap-1 rounded-md border border-sidebar-border/70 px-2.5 py-1.5 text-xs font-medium transition-colors hover:bg-muted dark:border-sidebar-border"
                                    >
                                        View
                                        <ArrowRight class="size-3" />
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="projectBreakdownItems.length === 0">
                                <td
                                    colspan="8"
                                    class="px-4 py-12 text-center text-sm text-muted-foreground"
                                >
                                    No project data available.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
                <div v-else class="flex flex-col gap-2 p-4">
                    <div
                        v-for="n in 3"
                        :key="n"
                        class="h-12 animate-pulse rounded bg-muted"
                    />
                </div>
            </div>
        </div>

        <!-- Section 4: Activity Chart -->
        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div
                class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-semibold">
                    Activity Progress by Project
                </h2>
                <p class="text-xs text-muted-foreground">
                    Stacked bar chart of active assignments per activity type
                </p>
            </div>
            <div class="p-4">
                <template
                    v-if="
                        activityChart != null && activityChart.labels.length > 0
                    "
                >
                    <div class="h-64">
                        <ActivityMatrixChart
                            :labels="activityChart.labels"
                            :datasets="activityChart.datasets"
                        />
                    </div>
                </template>
                <div
                    v-else-if="activityChart != null"
                    class="flex h-32 items-center justify-center text-sm text-muted-foreground"
                >
                    No data to display.
                </div>
                <div v-else class="h-64 animate-pulse rounded bg-muted" />
            </div>
        </div>

        <!-- Section: Subcontractor Leaderboard -->
        <div
            v-if="
                subcontractorLeaderboard == null ||
                subcontractorLeaderboard.length > 0
            "
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div
                class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-semibold">Subcontractor Performance</h2>
                <p class="text-xs text-muted-foreground">
                    Ranked by score — cycle time, completion rate, and revision
                    history
                </p>
            </div>
            <div class="overflow-x-auto">
                <template v-if="subcontractorLeaderboard != null">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/40 text-xs tracking-wide uppercase"
                        >
                            <tr>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    #
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Subcontractor
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Score
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Assigned
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Completed
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Avg Cycle
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Revisions
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    In Revision
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(sub, idx) in subcontractorLeaderboard"
                                :key="sub.id"
                                class="border-t border-sidebar-border/70 transition-colors hover:bg-muted/30 dark:border-sidebar-border"
                            >
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    {{ idx + 1 }}
                                </td>
                                <td class="px-4 py-3 font-medium">
                                    {{ sub.name }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex gap-0.5">
                                        <span
                                            v-for="i in 5"
                                            :key="i"
                                            class="size-2 rounded-full"
                                            :class="
                                                i <= sub.score
                                                    ? 'bg-emerald-500'
                                                    : 'bg-muted'
                                            "
                                        />
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums">
                                    {{ sub.total }}
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums">
                                    <span
                                        :class="
                                            sub.completion_pct >= 80
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : sub.completion_pct >= 50
                                                  ? 'text-amber-600 dark:text-amber-400'
                                                  : 'text-red-600 dark:text-red-400'
                                        "
                                    >
                                        {{ sub.completion_pct }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums">
                                    <span
                                        v-if="sub.avg_cycle_days !== null"
                                        :class="
                                            sub.avg_cycle_days <= 21
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : sub.avg_cycle_days <= 45
                                                  ? 'text-amber-600 dark:text-amber-400'
                                                  : 'text-red-600 dark:text-red-400'
                                        "
                                    >
                                        {{ sub.avg_cycle_days }}d
                                    </span>
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums">
                                    <span
                                        :class="
                                            sub.revision_count === 0
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : sub.revision_count <= 2
                                                  ? 'text-amber-600 dark:text-amber-400'
                                                  : 'text-red-600 dark:text-red-400'
                                        "
                                    >
                                        {{ sub.revision_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums">
                                    <span
                                        :class="
                                            sub.in_revision > 0
                                                ? 'font-semibold text-amber-600 dark:text-amber-400'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{ sub.in_revision }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
                <div v-else class="flex flex-col gap-2 p-4">
                    <div
                        v-for="n in 5"
                        :key="n"
                        class="h-10 animate-pulse rounded bg-muted"
                    />
                </div>
            </div>
        </div>

        <!-- Section: Workload Distribution -->
        <div
            v-if="
                workloadDistribution == null || workloadDistribution.length > 0
            "
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div
                class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-semibold">
                    Subcontractor Workload Distribution
                </h2>
                <p class="text-xs text-muted-foreground">
                    Active assignment load per subcontractor — top 20 by volume
                </p>
            </div>
            <div class="p-4">
                <template v-if="workloadDistribution != null">
                    <WorkloadChart :items="workloadDistribution" />
                </template>
                <div v-else class="h-64 animate-pulse rounded bg-muted" />
            </div>
        </div>

        <!-- Section 5: Recent Activity -->
        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div
                class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-semibold">Recent Activity</h2>
                <p class="text-xs text-muted-foreground">
                    Last 10 updated assignments
                </p>
            </div>

            <div
                class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
            >
                <template v-if="recentActivity != null">
                    <Link
                        v-for="item in recentActivityItems"
                        :key="item.id"
                        :href="'/admin/assignments/' + item.id"
                        class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/30"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-xs font-semibold">
                                    {{ item.site?.site_code ?? '—' }}
                                </span>
                                <ActivityTypeBadge
                                    :activity-type="item.activity_type"
                                />
                                <StatusBadge :status="item.status" />
                            </div>
                            <div
                                class="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <span>{{
                                    item.site?.location_name ?? '—'
                                }}</span>
                                <span
                                    v-if="item.subcontractor"
                                    class="text-muted-foreground/60"
                                    >·</span
                                >
                                <span v-if="item.subcontractor">{{
                                    item.subcontractor.name
                                }}</span>
                            </div>
                        </div>
                        <div
                            class="flex shrink-0 items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span>{{ timeAgo(item.updated_at) }}</span>
                            <ArrowRight class="size-3.5" />
                        </div>
                    </Link>

                    <div
                        v-if="recentActivityItems.length === 0"
                        class="px-4 py-12 text-center text-sm text-muted-foreground"
                    >
                        No recent activity.
                    </div>
                </template>
                <template v-else>
                    <div
                        v-for="n in 5"
                        :key="n"
                        class="flex items-center gap-3 px-4 py-3"
                    >
                        <div class="flex-1 space-y-2">
                            <div
                                class="h-3 w-40 animate-pulse rounded bg-muted"
                            />
                            <div
                                class="h-3 w-56 animate-pulse rounded bg-muted"
                            />
                        </div>
                        <div class="h-3 w-12 animate-pulse rounded bg-muted" />
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Status legend dialog -->
    <Dialog :open="siteDetailOpen" @update:open="siteDetailOpen = $event">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle>
                    {{ selectedSite?.site_code }} ·
                    {{ selectedSite?.location_name }}
                </DialogTitle>
            </DialogHeader>

            <div v-if="selectedSite" class="grid gap-4">
                <div class="grid gap-3 md:grid-cols-5">
                    <div class="rounded-md border border-border p-3">
                        <div class="text-xs text-muted-foreground">Status</div>
                        <span
                            class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase"
                            :class="
                                siteStatusClass(selectedSite.overall_status)
                            "
                        >
                            {{ siteStatusLabel(selectedSite.overall_status) }}
                        </span>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <div class="text-xs text-muted-foreground">
                            Completion
                        </div>
                        <div class="mt-1 text-xl font-semibold">
                            {{ selectedSite.completion_pct }}%
                        </div>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <div class="text-xs text-muted-foreground">Project</div>
                        <div class="mt-1 text-sm font-medium">
                            {{ selectedSite.project }}
                        </div>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <div class="text-xs text-muted-foreground">
                            Machine
                        </div>
                        <div class="mt-1 text-sm font-medium">
                            {{ selectedSite.machine_type ?? 'No machine' }}
                        </div>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <div class="text-xs text-muted-foreground">WO</div>
                        <div class="mt-1 text-sm font-medium">
                            {{
                                selectedSite.construction_wo_number ??
                                'No WO'
                            }}
                        </div>
                    </div>
                </div>

                <div class="rounded-md border border-border">
                    <div
                        class="border-b border-border px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                    >
                        Workstreams
                    </div>
                    <div class="grid gap-2 p-3 md:grid-cols-2">
                        <template
                            v-for="stream in siteWorkstreamOrder"
                            :key="stream.key"
                        >
                            <Link
                                v-if="siteWorkstream(selectedSite, stream.key)"
                                :href="
                                    siteWorkstream(selectedSite, stream.key)!
                                        .url
                                "
                                class="rounded-md border p-3 text-sm transition hover:bg-accent"
                                :class="
                                    workstreamClass(
                                        siteWorkstream(
                                            selectedSite,
                                            stream.key,
                                        ),
                                    )
                                "
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-medium">{{
                                        stream.label
                                    }}</span>
                                    <span class="text-xs">{{
                                        siteWorkstream(selectedSite, stream.key)!
                                            .status
                                    }}</span>
                                </div>
                                <div
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{
                                        siteWorkstream(selectedSite, stream.key)!
                                            .subcontractor ?? 'No subcontractor'
                                    }}
                                    <span
                                        v-if="
                                            siteWorkstream(
                                                selectedSite,
                                                stream.key,
                                            )!.wo_number
                                        "
                                    >
                                        · WO
                                        {{
                                            siteWorkstream(
                                                selectedSite,
                                                stream.key,
                                            )!.wo_number
                                        }}
                                    </span>
                                    <span
                                        v-if="
                                            siteWorkstream(
                                                selectedSite,
                                                stream.key,
                                            )!.age_days != null
                                        "
                                    >
                                        ·
                                        {{
                                            siteWorkstream(
                                                selectedSite,
                                                stream.key,
                                            )!.age_days
                                        }}d
                                    </span>
                                </div>
                            </Link>
                            <div
                                v-else
                                class="rounded-md border border-dashed p-3 text-sm text-muted-foreground"
                            >
                                <div class="font-medium">
                                    {{ stream.label }}
                                </div>
                                <div class="mt-1 text-xs">
                                    Assignment not started
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="rounded-md border border-border">
                    <div
                        class="border-b border-border px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                    >
                        Why Not Done
                    </div>
                    <div class="divide-y divide-border">
                        <div
                            v-for="issue in selectedSite.issues"
                            :key="`${issue.type}-${issue.assignment_id ?? 'site'}`"
                            class="grid gap-2 p-3 md:grid-cols-[8rem_1fr_12rem]"
                        >
                            <div>
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase"
                                    :class="
                                        siteSeverityClass(issue.severity)
                                    "
                                >
                                    {{ issue.severity }}
                                </span>
                                <div
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ issueTypeLabel(issue.type) }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm">
                                    {{ issue.problem }}
                                </div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    {{ issue.recommended_action }}
                                </div>
                            </div>
                            <div class="text-xs text-muted-foreground">
                                <div>{{ issue.owner }}</div>
                                <div>
                                    {{
                                        issue.age_days != null
                                            ? issue.age_days + 'd since update'
                                            : 'No age signal'
                                    }}
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="selectedSite.issues.length === 0"
                            class="p-3 text-sm text-muted-foreground"
                        >
                            No blocker signal detected for this location.
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <Link
                        :href="selectedSite.url"
                        class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                    >
                        Open Location
                        <ArrowRight class="size-3" />
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                        @click="askAi(selectedSite.ai_prompt)"
                    >
                        <Bot class="size-3.5" />
                        Ask AI
                    </button>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <Dialog :open="legendOpen" @update:open="legendOpen = $event">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Assignment Status Guide</DialogTitle>
            </DialogHeader>
            <div class="grid gap-2 py-2">
                <div
                    v-for="s in statusDescriptions"
                    :key="s.key"
                    class="flex items-start gap-3 rounded-lg px-2 py-1.5"
                >
                    <span
                        class="mt-1 size-2.5 shrink-0 rounded-full"
                        :class="
                            statuses.find((st) => st.key === s.key)?.dotClass ??
                            'bg-gray-400'
                        "
                    />
                    <div>
                        <p class="text-sm font-medium">{{ s.label }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ s.description }}
                        </p>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
