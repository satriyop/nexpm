<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    Clipboard,
    ExternalLink,
    LockKeyhole,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import * as AdminAssignmentActions from '@/actions/App/Http/Controllers/Admin/AssignmentController';
import * as ProjectActions from '@/actions/App/Http/Controllers/Admin/ProjectController';
import * as SiteActions from '@/actions/App/Http/Controllers/Admin/SiteController';
import ActivityTypeBadge from '@/components/ActivityTypeBadge.vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import type { ActivityType, Assignment } from '@/types';

interface SiteType {
    id: number;
    name: string;
}
interface MachineType {
    id: number;
    name: string;
}
interface Project {
    id: number;
    name: string;
    client: { name: string } | null;
    main_contractor: { name: string } | null;
}
interface ProjectOption {
    id: number;
    name: string;
    main_contractor: { name: string } | null;
}
interface Subcontractor {
    id: number;
    name: string;
}
interface Site {
    id: number;
    site_code: string;
    location_name: string;
    address: string;
    province: string;
    city: string;
    google_map_url: string | null;
    bd_pic: string | null;
    site_type_id: number | null;
    machine_type_id: number | null;
    ss_wo_number: string | null;
    cable_length_to_panel: string | null;
    cable_length_panel_to_charger: string | null;
    charging_station_count: number | null;
    power_kva: string | null;
    ssr_url: string | null;
    nidi_slo_bpujl_url: string | null;
    sik_url: string | null;
    latest_remark: string | null;
    approved_budget: string | null;
    invoice_submission_date: string | null;
    dp_35_date: string | null;
    dp_35_inv_number: string | null;
    dp_35_dpp_amount: string | null;
    invoice_60_submission_date: string | null;
    payment_60_date: string | null;
    invoice_60_inv_number: string | null;
    invoice_60_dpp_amount: string | null;
    invoice_5_submission_date: string | null;
    payment_5_date: string | null;
    invoice_5_inv_number: string | null;
    invoice_5_dpp_amount: string | null;
    invoice_url: string | null;
    project: Project;
}

const props = defineProps<{
    site: Site;
    siteTypes: SiteType[];
    machineTypes: MachineType[];
    assignments: Assignment[];
    subcontractors: Subcontractor[];
    projects: ProjectOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Projects', href: ProjectActions.index().url },
            { title: 'Detail', href: '#' },
            { title: 'Edit Site', href: '#' },
        ],
    },
});

// --- Tab state ---
type Tab = 'site' | 'financials' | 'assignments';
const activeTab = ref<Tab>('site');

onMounted(() => {
    const params = new URLSearchParams(window.location.search);

    if (params.get('tab') === 'assignments') {
        activeTab.value = 'assignments';
    } else if (params.get('tab') === 'financials') {
        activeTab.value = 'financials';
    }
});

const financialFields = [
    'approved_budget',
    'invoice_submission_date',
    'dp_35_date',
    'dp_35_inv_number',
    'dp_35_dpp_amount',
    'invoice_60_submission_date',
    'payment_60_date',
    'invoice_60_inv_number',
    'invoice_60_dpp_amount',
    'invoice_5_submission_date',
    'payment_5_date',
    'invoice_5_inv_number',
    'invoice_5_dpp_amount',
    'invoice_url',
] as const;
const hasFinancialErrors = computed(() =>
    financialFields.some((f) => !!form.errors[f]),
);

const NONE = '__none__';

// --- Site edit form ---
const form = useForm({
    location_name: props.site.location_name,
    address: props.site.address,
    province: props.site.province,
    city: props.site.city,
    google_map_url: props.site.google_map_url ?? '',
    bd_pic: props.site.bd_pic ?? '',
    site_type_id: props.site.site_type_id
        ? String(props.site.site_type_id)
        : NONE,
    machine_type_id: props.site.machine_type_id
        ? String(props.site.machine_type_id)
        : NONE,
    ss_wo_number: props.site.ss_wo_number ?? '',
    cable_length_to_panel: props.site.cable_length_to_panel ?? '',
    cable_length_panel_to_charger:
        props.site.cable_length_panel_to_charger ?? '',
    charging_station_count: props.site.charging_station_count ?? '',
    power_kva: props.site.power_kva ?? '',
    ssr_url: props.site.ssr_url ?? '',
    nidi_slo_bpujl_url: props.site.nidi_slo_bpujl_url ?? '',
    sik_url: props.site.sik_url ?? '',
    latest_remark: props.site.latest_remark ?? '',
    approved_budget: props.site.approved_budget ?? '',
    invoice_submission_date: props.site.invoice_submission_date ?? '',
    dp_35_date: props.site.dp_35_date ?? '',
    dp_35_inv_number: props.site.dp_35_inv_number ?? '',
    dp_35_dpp_amount: props.site.dp_35_dpp_amount ?? '',
    invoice_60_submission_date: props.site.invoice_60_submission_date ?? '',
    payment_60_date: props.site.payment_60_date ?? '',
    invoice_60_inv_number: props.site.invoice_60_inv_number ?? '',
    invoice_60_dpp_amount: props.site.invoice_60_dpp_amount ?? '',
    invoice_5_submission_date: props.site.invoice_5_submission_date ?? '',
    payment_5_date: props.site.payment_5_date ?? '',
    invoice_5_inv_number: props.site.invoice_5_inv_number ?? '',
    invoice_5_dpp_amount: props.site.invoice_5_dpp_amount ?? '',
    invoice_url: props.site.invoice_url ?? '',
});

const isSubmittingSiteForm = ref(false);

function submit(): void {
    isSubmittingSiteForm.value = true;

    form.transform((data) => ({
        ...data,
        site_type_id: data.site_type_id === NONE ? null : data.site_type_id,
        machine_type_id:
            data.machine_type_id === NONE ? null : data.machine_type_id,
    })).patch(SiteActions.update(props.site).url, {
        onSuccess: () => {
            form.defaults();
        },
        onFinish: () => {
            isSubmittingSiteForm.value = false;
        },
    });
}

// Auto-calculate DPP amounts from approved budget
watch(
    () => form.approved_budget,
    (budget) => {
        const b = Number(budget) || 0;
        form.dp_35_dpp_amount = b > 0 ? String(Math.round(b * 0.35)) : '';
        form.invoice_60_dpp_amount = b > 0 ? String(Math.round(b * 0.6)) : '';
        form.invoice_5_dpp_amount = b > 0 ? String(Math.round(b * 0.05)) : '';
    },
);

// Superadmin role check
const page = usePage();
const isSuperAdmin = computed(
    () => (page.props.auth as any)?.user?.role === 'super_admin',
);

// Reassign project form (superadmin only)
const reassignForm = useForm({
    project_id: String(props.site.project.id),
});
function submitReassign(): void {
    reassignForm.patch(SiteActions.reassignProject(props.site).url);
}

// Copy to clipboard with brief ✓ feedback
const copiedField = ref<string | null>(null);
function copyToClipboard(value: string | number, field: string): void {
    navigator.clipboard.writeText(String(value)).then(() => {
        copiedField.value = field;
        setTimeout(() => {
            copiedField.value = null;
        }, 1500);
    });
}

function relativeTime(dateStr: string | null | undefined): string {
    if (!dateStr) {
        return 'No activity yet';
    }

    const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
    const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

    if (diff < 60) {
        return rtf.format(-Math.round(diff), 'second');
    }

    if (diff < 3600) {
        return rtf.format(-Math.round(diff / 60), 'minute');
    }

    if (diff < 86400) {
        return rtf.format(-Math.round(diff / 3600), 'hour');
    }

    if (diff < 2592000) {
        return rtf.format(-Math.round(diff / 86400), 'day');
    }

    if (diff < 31536000) {
        return rtf.format(-Math.round(diff / 2592000), 'month');
    }

    return rtf.format(-Math.round(diff / 31536000), 'year');
}

// --- Dirty form guard ---
function handleBeforeUnload(e: BeforeUnloadEvent): void {
    if (form.isDirty) {
        e.preventDefault();
        e.returnValue = '';
    }
}

const removeNavGuard = router.on('before', (e) => {
    if (
        !isSubmittingSiteForm.value &&
        form.isDirty &&
        !window.confirm('You have unsaved changes. Leave anyway?')
    ) {
        e.preventDefault();
    }
});

window.addEventListener('beforeunload', handleBeforeUnload);

onUnmounted(() => {
    removeNavGuard();
    window.removeEventListener('beforeunload', handleBeforeUnload);
});

// --- Assignment helpers ---
function getAssignment(activityType: ActivityType): Assignment | null {
    return (
        props.assignments.find((a) => a.activity_type === activityType) ?? null
    );
}

const surveyAssignment = computed(() => getAssignment('SURVEY'));
const constructionAssignment = computed(() => getAssignment('CONSTRUCTION'));
const plnAssignment = computed(() => getAssignment('PLN_CONNECTION'));
const bastAssignment = computed(() => getAssignment('BAST'));

// Assign forms (unassigned cards)
const surveyAssignForm = useForm({
    activity_type: 'SURVEY',
    subcontractor_id: '',
});
const constructionAssignForm = useForm({
    activity_type: 'CONSTRUCTION',
    subcontractor_id: '',
});
const plnAssignForm = useForm({
    activity_type: 'PLN_CONNECTION',
    subcontractor_id: '',
});
const bastAssignForm = useForm({ activity_type: 'BAST', subcontractor_id: '' });

function submitAssign(form: ReturnType<typeof useForm>): void {
    form.post(AdminAssignmentActions.storeForSite(props.site).url, {
        preserveScroll: true,
    });
}

// Reassign forms (assigned cards)
const surveyReassignForm = useForm({ subcontractor_id: '' });
const constructionReassignForm = useForm({ subcontractor_id: '' });
const plnReassignForm = useForm({ subcontractor_id: '' });
const bastReassignForm = useForm({ subcontractor_id: '' });

watch(
    surveyAssignment,
    (a) => {
        surveyReassignForm.subcontractor_id =
            a?.subcontractor_id?.toString() ?? '';
    },
    { immediate: true },
);
watch(
    constructionAssignment,
    (a) => {
        constructionReassignForm.subcontractor_id =
            a?.subcontractor_id?.toString() ?? '';
    },
    { immediate: true },
);
watch(
    plnAssignment,
    (a) => {
        plnReassignForm.subcontractor_id =
            a?.subcontractor_id?.toString() ?? '';
    },
    { immediate: true },
);
watch(
    bastAssignment,
    (a) => {
        bastReassignForm.subcontractor_id =
            a?.subcontractor_id?.toString() ?? '';
    },
    { immediate: true },
);

function submitReassign(
    form: ReturnType<typeof useForm>,
    assignment: Assignment,
): void {
    form.patch(AdminAssignmentActions.reassign(assignment.id).url, {
        preserveScroll: true,
    });
}

// Construction WO form
const woForm = useForm({
    cons_wo_number:
        constructionAssignment.value?.construction_data?.cons_wo_number ?? '',
});

function submitWo(): void {
    if (!constructionAssignment.value) {
        return;
    }

    router.visit(
        AdminAssignmentActions.updateConstructionPrerequisite(
            constructionAssignment.value.id,
        ).url,
        {
            method: 'patch',
            data: { cons_wo_number: woForm.cons_wo_number },
            preserveScroll: true,
            onError: (errors) => {
                woForm.setError(errors as Record<string, string>);
            },
            onStart: () => {
                woForm.processing = true;
                woForm.clearErrors();
            },
            onFinish: () => {
                woForm.processing = false;
            },
        },
    );
}

function removeAssignment(assignment: Assignment): void {
    router.delete(AdminAssignmentActions.destroy(assignment.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Edit ${site.site_code}`" />

    <div class="space-y-6 p-4 md:p-6">
        <!-- Page Header -->
        <div class="flex items-center gap-3">
            <a :href="ProjectActions.show(site.project).url">
                <Button variant="ghost" size="icon"
                    ><ArrowLeft class="h-4 w-4"
                /></Button>
            </a>
            <div class="flex items-center gap-3">
                <span
                    class="rounded bg-muted px-2 py-0.5 font-mono text-sm font-semibold tracking-wide"
                    >{{ site.site_code }}</span
                >
                <h1 class="text-2xl font-semibold">{{ site.location_name }}</h1>
            </div>
        </div>

        <!-- Tab bar -->
        <div class="flex border-b">
            <button
                type="button"
                class="px-4 py-2 text-sm font-medium transition-colors"
                :class="
                    activeTab === 'site'
                        ? 'border-b-2 border-primary text-primary'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = 'site'"
            >
                Site Details
            </button>
            <button
                type="button"
                class="relative px-4 py-2 text-sm font-medium transition-colors"
                :class="
                    activeTab === 'financials'
                        ? 'border-b-2 border-primary text-primary'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = 'financials'"
            >
                Financials
                <span
                    v-if="hasFinancialErrors"
                    class="absolute top-1 right-1 h-1.5 w-1.5 rounded-full bg-destructive"
                />
            </button>
            <button
                type="button"
                class="px-4 py-2 text-sm font-medium transition-colors"
                :class="
                    activeTab === 'assignments'
                        ? 'border-b-2 border-primary text-primary'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = 'assignments'"
            >
                Assignments
                <span
                    class="ml-1.5 rounded-full bg-muted px-1.5 py-0.5 text-xs text-muted-foreground"
                >
                    {{ assignments.length }}
                </span>
            </button>
        </div>

        <!-- ── Tab 1: Site Details ── -->
        <form
            v-if="activeTab === 'site'"
            class="space-y-6"
            @submit.prevent="submit"
            novalidate
        >
            <!-- Card 1: Basic Info -->
            <Card>
                <CardHeader class="pb-2"
                    ><h2 class="text-base font-semibold">
                        Basic Info
                    </h2></CardHeader
                >
                <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="location_name"
                            >Location Name
                            <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="location_name"
                            v-model="form.location_name"
                            required
                        />
                        <InputError :message="form.errors.location_name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="bd_pic">BD PIC</Label>
                        <Input id="bd_pic" v-model="form.bd_pic" />
                        <InputError :message="form.errors.bd_pic" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="province"
                            >Province
                            <span class="text-destructive">*</span></Label
                        >
                        <Input id="province" v-model="form.province" required />
                        <InputError :message="form.errors.province" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="city"
                            >City <span class="text-destructive">*</span></Label
                        >
                        <Input id="city" v-model="form.city" required />
                        <InputError :message="form.errors.city" />
                    </div>
                    <div class="col-span-full grid gap-1.5">
                        <Label for="address"
                            >Address
                            <span class="text-destructive">*</span></Label
                        >
                        <textarea
                            id="address"
                            v-model="form.address"
                            required
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <InputError :message="form.errors.address" />
                    </div>
                    <div class="col-span-full grid gap-1.5">
                        <Label for="google_map_url">Google Map URL</Label>
                        <Input
                            id="google_map_url"
                            v-model="form.google_map_url"
                            type="url"
                        />
                        <InputError :message="form.errors.google_map_url" />
                    </div>
                </CardContent>
            </Card>

            <!-- Card 2: Survey & Technical -->
            <Card>
                <CardHeader class="pb-2"
                    ><h2 class="text-base font-semibold">
                        Survey &amp; Technical
                    </h2></CardHeader
                >
                <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label>Site Type</Label>
                        <Select v-model="form.site_type_id">
                            <SelectTrigger
                                ><SelectValue placeholder="Select site type"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NONE">— None —</SelectItem>
                                <SelectItem
                                    v-for="st in siteTypes"
                                    :key="st.id"
                                    :value="String(st.id)"
                                    >{{ st.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.site_type_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Machine Type</Label>
                        <Select v-model="form.machine_type_id">
                            <SelectTrigger
                                ><SelectValue placeholder="Select machine type"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NONE">— None —</SelectItem>
                                <SelectItem
                                    v-for="mt in machineTypes"
                                    :key="mt.id"
                                    :value="String(mt.id)"
                                    >{{ mt.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.machine_type_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ss_wo_number">SS WO Number</Label>
                        <Input id="ss_wo_number" v-model="form.ss_wo_number" />
                        <InputError :message="form.errors.ss_wo_number" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="charging_station_count"
                            >Charging Station Count</Label
                        >
                        <Input
                            id="charging_station_count"
                            v-model="form.charging_station_count"
                            type="number"
                            min="0"
                            step="1"
                        />
                        <InputError
                            :message="form.errors.charging_station_count"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cable_length_to_panel"
                            >Cable Length to Panel — m</Label
                        >
                        <Input
                            id="cable_length_to_panel"
                            v-model="form.cable_length_to_panel"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <InputError
                            :message="form.errors.cable_length_to_panel"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cable_length_panel_to_charger"
                            >Cable Length Panel to Charger — m</Label
                        >
                        <Input
                            id="cable_length_panel_to_charger"
                            v-model="form.cable_length_panel_to_charger"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <InputError
                            :message="form.errors.cable_length_panel_to_charger"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Power / Daya (kVA)</Label>
                        <Select v-model="form.power_kva">
                            <SelectTrigger
                                ><SelectValue placeholder="Select power"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="7.7kVA">7.7 kVA</SelectItem>
                                <SelectItem value="11kVA">11 kVA</SelectItem>
                                <SelectItem value="23kVA">23 kVA</SelectItem>
                                <SelectItem value="33kVA">33 kVA</SelectItem>
                                <SelectItem value="66kVA">66 kVA</SelectItem>
                                <SelectItem value="132kVA">132 kVA</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.power_kva" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ssr_url">SSR URL</Label>
                        <Input id="ssr_url" v-model="form.ssr_url" type="url" />
                        <InputError :message="form.errors.ssr_url" />
                    </div>
                </CardContent>
            </Card>

            <!-- Card 3: Permits & Legal -->
            <Card>
                <CardHeader class="pb-2"
                    ><h2 class="text-base font-semibold">
                        Permits &amp; Legal
                    </h2></CardHeader
                >
                <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="nidi_slo_bpujl_url"
                            >NIDI SLO / BPUJL URL</Label
                        >
                        <Input
                            id="nidi_slo_bpujl_url"
                            v-model="form.nidi_slo_bpujl_url"
                            type="url"
                        />
                        <InputError :message="form.errors.nidi_slo_bpujl_url" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="sik_url">SIK URL</Label>
                        <Input id="sik_url" v-model="form.sik_url" type="url" />
                        <InputError :message="form.errors.sik_url" />
                    </div>
                </CardContent>
            </Card>

            <!-- Card 4: Notes -->
            <Card>
                <CardHeader class="pb-2"
                    ><h2 class="text-base font-semibold">Notes</h2></CardHeader
                >
                <CardContent>
                    <div class="grid gap-1.5">
                        <Label for="latest_remark">Latest Remark / Notes</Label>
                        <textarea
                            id="latest_remark"
                            v-model="form.latest_remark"
                            rows="4"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <InputError :message="form.errors.latest_remark" />
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    class="min-w-40"
                >
                    {{ form.processing ? 'Saving…' : 'Save Masterdata' }}
                </Button>
            </div>
        </form>

        <!-- ── Superadmin: Reassign Project ── -->
        <Card
            v-if="isSuperAdmin && projects.length > 0"
            class="mt-6 border-amber-200 dark:border-amber-800"
        >
            <CardHeader class="pb-2">
                <h2 class="text-base font-semibold">Reassign to Project</h2>
                <p class="text-sm text-muted-foreground">
                    Superadmin only — moves this site (and all its assignments) to a different project.
                </p>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                    Current project: <span class="font-medium">{{ site.project.name }}</span>
                    <span v-if="site.project.main_contractor" class="text-muted-foreground"> ({{ site.project.main_contractor.name }})</span>
                </div>
                <div class="grid max-w-sm gap-1.5">
                    <Label for="reassign-project">Move to Project</Label>
                    <Select v-model="reassignForm.project_id">
                        <SelectTrigger id="reassign-project">
                            <SelectValue placeholder="Select a project…" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="p in projects"
                                :key="p.id"
                                :value="String(p.id)"
                            >
                                {{ p.name }}
                                <span v-if="p.main_contractor" class="text-muted-foreground"> · {{ p.main_contractor.name }}</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="reassignForm.errors.project_id" />
                </div>
                <Button
                    variant="outline"
                    :disabled="reassignForm.processing || reassignForm.project_id === String(site.project.id)"
                    @click="submitReassign"
                >
                    {{ reassignForm.processing ? 'Reassigning…' : 'Reassign Site' }}
                </Button>
            </CardContent>
        </Card>

        <!-- ── Tab 2: Financials ── -->
        <form
            v-else-if="activeTab === 'financials'"
            class="space-y-6"
            @submit.prevent="submit"
            novalidate
        >
            <Card>
                <CardHeader class="pb-2">
                    <h2 class="text-base font-semibold">Budget</h2>
                </CardHeader>
                <CardContent>
                    <div class="grid max-w-xs gap-1.5">
                        <Label for="approved_budget"
                            >Approved Budget (Rp)</Label
                        >
                        <Input
                            id="approved_budget"
                            v-model="form.approved_budget"
                            type="number"
                            min="0"
                            step="1"
                            placeholder="0"
                        />
                        <InputError :message="form.errors.approved_budget" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <h2 class="text-base font-semibold">
                        Invoice &amp; Payment Tracking
                    </h2>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- 35% tier -->
                    <div>
                        <p
                            class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            35% (Down Payment)
                        </p>
                        <div
                            class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
                        >
                            <div class="grid gap-1.5">
                                <Label for="invoice_submission_date"
                                    >35% Submission Date</Label
                                >
                                <Input
                                    id="invoice_submission_date"
                                    v-model="form.invoice_submission_date"
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        form.errors.invoice_submission_date
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="dp_35_date">35% DP Date</Label>
                                <Input
                                    id="dp_35_date"
                                    v-model="form.dp_35_date"
                                    type="date"
                                />
                                <InputError :message="form.errors.dp_35_date" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="dp_35_inv_number"
                                    >35% Inv Number</Label
                                >
                                <Input
                                    id="dp_35_inv_number"
                                    v-model="form.dp_35_inv_number"
                                    placeholder="Invoice number"
                                />
                                <InputError
                                    :message="form.errors.dp_35_inv_number"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="dp_35_dpp_amount"
                                    >35% DPP Amount</Label
                                >
                                <div class="relative">
                                    <Input
                                        id="dp_35_dpp_amount"
                                        v-model="form.dp_35_dpp_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="0"
                                        class="pr-9"
                                    />
                                    <button
                                        type="button"
                                        class="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                        :title="
                                            copiedField === 'dp_35'
                                                ? 'Copied!'
                                                : 'Copy'
                                        "
                                        @click="
                                            copyToClipboard(
                                                form.dp_35_dpp_amount,
                                                'dp_35',
                                            )
                                        "
                                    >
                                        <Check
                                            v-if="copiedField === 'dp_35'"
                                            class="h-4 w-4 text-green-500"
                                        />
                                        <Clipboard v-else class="h-4 w-4" />
                                    </button>
                                </div>
                                <InputError
                                    :message="form.errors.dp_35_dpp_amount"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- 60% tier -->
                    <div>
                        <p
                            class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            60%
                        </p>
                        <div
                            class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
                        >
                            <div class="grid gap-1.5">
                                <Label for="invoice_60_submission_date"
                                    >60% Submission Date</Label
                                >
                                <Input
                                    id="invoice_60_submission_date"
                                    v-model="form.invoice_60_submission_date"
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        form.errors.invoice_60_submission_date
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="payment_60_date"
                                    >60% Payment Date</Label
                                >
                                <Input
                                    id="payment_60_date"
                                    v-model="form.payment_60_date"
                                    type="date"
                                />
                                <InputError
                                    :message="form.errors.payment_60_date"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="invoice_60_inv_number"
                                    >60% Inv Number</Label
                                >
                                <Input
                                    id="invoice_60_inv_number"
                                    v-model="form.invoice_60_inv_number"
                                    placeholder="Invoice number"
                                />
                                <InputError
                                    :message="form.errors.invoice_60_inv_number"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="invoice_60_dpp_amount"
                                    >60% DPP Amount</Label
                                >
                                <div class="relative">
                                    <Input
                                        id="invoice_60_dpp_amount"
                                        v-model="form.invoice_60_dpp_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="0"
                                        class="pr-9"
                                    />
                                    <button
                                        type="button"
                                        class="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                        :title="
                                            copiedField === 'inv_60'
                                                ? 'Copied!'
                                                : 'Copy'
                                        "
                                        @click="
                                            copyToClipboard(
                                                form.invoice_60_dpp_amount,
                                                'inv_60',
                                            )
                                        "
                                    >
                                        <Check
                                            v-if="copiedField === 'inv_60'"
                                            class="h-4 w-4 text-green-500"
                                        />
                                        <Clipboard v-else class="h-4 w-4" />
                                    </button>
                                </div>
                                <InputError
                                    :message="form.errors.invoice_60_dpp_amount"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- 5% tier -->
                    <div>
                        <p
                            class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            5% (Retention)
                        </p>
                        <div
                            class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
                        >
                            <div class="grid gap-1.5">
                                <Label for="invoice_5_submission_date"
                                    >5% Submission Date</Label
                                >
                                <Input
                                    id="invoice_5_submission_date"
                                    v-model="form.invoice_5_submission_date"
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        form.errors.invoice_5_submission_date
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="payment_5_date"
                                    >5% Payment Date</Label
                                >
                                <Input
                                    id="payment_5_date"
                                    v-model="form.payment_5_date"
                                    type="date"
                                />
                                <InputError
                                    :message="form.errors.payment_5_date"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="invoice_5_inv_number"
                                    >5% Inv Number</Label
                                >
                                <Input
                                    id="invoice_5_inv_number"
                                    v-model="form.invoice_5_inv_number"
                                    placeholder="Invoice number"
                                />
                                <InputError
                                    :message="form.errors.invoice_5_inv_number"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="invoice_5_dpp_amount"
                                    >5% DPP Amount</Label
                                >
                                <div class="relative">
                                    <Input
                                        id="invoice_5_dpp_amount"
                                        v-model="form.invoice_5_dpp_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="0"
                                        class="pr-9"
                                    />
                                    <button
                                        type="button"
                                        class="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                        :title="
                                            copiedField === 'inv_5'
                                                ? 'Copied!'
                                                : 'Copy'
                                        "
                                        @click="
                                            copyToClipboard(
                                                form.invoice_5_dpp_amount,
                                                'inv_5',
                                            )
                                        "
                                    >
                                        <Check
                                            v-if="copiedField === 'inv_5'"
                                            class="h-4 w-4 text-green-500"
                                        />
                                        <Clipboard v-else class="h-4 w-4" />
                                    </button>
                                </div>
                                <InputError
                                    :message="form.errors.invoice_5_dpp_amount"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Invoice URL -->
                    <div class="grid gap-1.5">
                        <Label for="invoice_url">Invoice URL</Label>
                        <Input
                            id="invoice_url"
                            v-model="form.invoice_url"
                            type="url"
                            placeholder="https://"
                        />
                        <InputError :message="form.errors.invoice_url" />
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    class="min-w-40"
                >
                    {{ form.processing ? 'Saving…' : 'Save Masterdata' }}
                </Button>
            </div>
        </form>

        <!-- ── Tab 3: Assignments ── -->
        <div
            v-else-if="activeTab === 'assignments'"
            class="grid gap-4 md:grid-cols-2"
        >
            <!-- SURVEY card -->
            <Card>
                <CardHeader class="pb-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <ActivityTypeBadge activity-type="SURVEY" />
                            <StatusBadge
                                v-if="surveyAssignment"
                                :status="surveyAssignment.status"
                            />
                        </div>
                        <Button
                            v-if="
                                surveyAssignment &&
                                surveyAssignment.status === 'PENDING'
                            "
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-7 text-destructive hover:text-destructive"
                            @click="removeAssignment(surveyAssignment)"
                        >
                            <span class="i-lucide-trash-2 size-3.5" />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="pb-3">
                    <div v-if="surveyAssignment" class="flex flex-col gap-3">
                        <Alert
                            v-if="
                                surveyAssignment.status === 'REVISION' &&
                                surveyAssignment.revision_comment
                            "
                            class="border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700/60 dark:bg-amber-900/20 dark:text-amber-100"
                        >
                            <AlertTriangle
                                class="size-4 text-amber-600 dark:text-amber-300"
                            />
                            <AlertTitle class="text-sm"
                                >Revision Requested</AlertTitle
                            >
                            <AlertDescription class="text-xs">{{
                                surveyAssignment.revision_comment
                            }}</AlertDescription>
                        </Alert>
                        <div v-if="surveyAssignment.survey_data?.surveyor_name">
                            <p class="text-xs text-muted-foreground">
                                Surveyor
                            </p>
                            <p class="text-sm font-medium">
                                {{ surveyAssignment.survey_data.surveyor_name }}
                            </p>
                        </div>
                        <div class="flex items-end gap-2 border-t pt-3">
                            <div class="flex flex-1 flex-col gap-1">
                                <p class="text-xs text-muted-foreground">
                                    Subcontractor
                                </p>
                                <Select
                                    v-model="
                                        surveyReassignForm.subcontractor_id
                                    "
                                >
                                    <SelectTrigger class="h-8 text-xs"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="sc in subcontractors"
                                            :key="sc.id"
                                            :value="sc.id.toString()"
                                            >{{ sc.name }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="h-8 text-xs"
                                :disabled="
                                    surveyReassignForm.processing ||
                                    surveyReassignForm.subcontractor_id ===
                                        surveyAssignment.subcontractor_id?.toString()
                                "
                                @click="
                                    submitReassign(
                                        surveyReassignForm,
                                        surveyAssignment,
                                    )
                                "
                                >Change</Button
                            >
                        </div>
                    </div>
                    <div v-else class="flex flex-col gap-2">
                        <p class="text-sm text-muted-foreground">
                            Not assigned
                        </p>
                        <div class="flex items-end gap-2">
                            <Select
                                v-model="surveyAssignForm.subcontractor_id"
                                class="flex-1"
                            >
                                <SelectTrigger class="h-8 text-xs"
                                    ><SelectValue
                                        placeholder="Select subcontractor"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="sc in subcontractors"
                                        :key="sc.id"
                                        :value="sc.id.toString()"
                                        >{{ sc.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <Button
                                type="button"
                                size="sm"
                                class="h-8 text-xs"
                                :disabled="
                                    surveyAssignForm.processing ||
                                    !surveyAssignForm.subcontractor_id
                                "
                                @click="submitAssign(surveyAssignForm)"
                                >Assign</Button
                            >
                        </div>
                        <InputError
                            :message="surveyAssignForm.errors.subcontractor_id"
                        />
                    </div>
                </CardContent>
                <CardFooter v-if="surveyAssignment" class="border-t pt-0">
                    <div
                        class="flex w-full items-center justify-between gap-2 pt-3"
                    >
                        <span class="text-xs text-muted-foreground">
                            Updated
                            {{
                                relativeTime(
                                    surveyAssignment.survey_data?.updated_at ??
                                        surveyAssignment.updated_at,
                                )
                            }}
                        </span>
                        <Button
                            as-child
                            size="sm"
                            variant="outline"
                            class="h-7 gap-1.5 text-xs font-medium"
                        >
                            <Link
                                :href="
                                    AdminAssignmentActions.show(
                                        surveyAssignment.id,
                                    ).url
                                "
                            >
                                <ExternalLink class="size-3" />
                                Details
                            </Link>
                        </Button>
                    </div>
                </CardFooter>
            </Card>

            <!-- CONSTRUCTION card -->
            <Card>
                <CardHeader class="pb-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <ActivityTypeBadge activity-type="CONSTRUCTION" />
                            <StatusBadge
                                v-if="constructionAssignment"
                                :status="constructionAssignment.status"
                            />
                        </div>
                        <Button
                            v-if="
                                constructionAssignment &&
                                constructionAssignment.status === 'PENDING'
                            "
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-7 text-destructive hover:text-destructive"
                            @click="removeAssignment(constructionAssignment)"
                        >
                            <span class="i-lucide-trash-2 size-3.5" />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="pb-3">
                    <div
                        v-if="constructionAssignment"
                        class="flex flex-col gap-3"
                    >
                        <Alert
                            v-if="
                                constructionAssignment.status === 'REVISION' &&
                                constructionAssignment.revision_comment
                            "
                            class="border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700/60 dark:bg-amber-900/20 dark:text-amber-100"
                        >
                            <AlertTriangle
                                class="size-4 text-amber-600 dark:text-amber-300"
                            />
                            <AlertTitle class="text-sm"
                                >Revision Requested</AlertTitle
                            >
                            <AlertDescription class="text-xs">{{
                                constructionAssignment.revision_comment
                            }}</AlertDescription>
                        </Alert>
                        <div
                            v-if="
                                constructionAssignment.construction_data
                                    ?.cons_wo_number
                            "
                            class="flex flex-col gap-2"
                        >
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    WO Number
                                </p>
                                <p class="font-mono text-sm font-medium">
                                    {{
                                        constructionAssignment.construction_data
                                            .cons_wo_number
                                    }}
                                </p>
                            </div>
                            <div
                                v-if="
                                    constructionAssignment.construction_data
                                        .cons_actual_start_date ||
                                    constructionAssignment.construction_data
                                        .cons_actual_done_date
                                "
                                class="grid grid-cols-2 gap-2"
                            >
                                <div
                                    v-if="
                                        constructionAssignment.construction_data
                                            .cons_actual_start_date
                                    "
                                >
                                    <p class="text-xs text-muted-foreground">
                                        Actual Start
                                    </p>
                                    <p class="text-sm">
                                        {{
                                            new Date(
                                                constructionAssignment
                                                    .construction_data
                                                    .cons_actual_start_date,
                                            ).toLocaleDateString()
                                        }}
                                    </p>
                                </div>
                                <div
                                    v-if="
                                        constructionAssignment.construction_data
                                            .cons_actual_done_date
                                    "
                                >
                                    <p class="text-xs text-muted-foreground">
                                        Actual Done
                                    </p>
                                    <p class="text-sm">
                                        {{
                                            new Date(
                                                constructionAssignment
                                                    .construction_data
                                                    .cons_actual_done_date,
                                            ).toLocaleDateString()
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex flex-col gap-3">
                            <div
                                class="flex items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-700/60 dark:bg-amber-900/20 dark:text-amber-200"
                            >
                                <LockKeyhole class="size-3.5 shrink-0" />
                                Sub-contractor is locked until WO is set
                            </div>
                            <form
                                class="flex items-start gap-2"
                                @submit.prevent="submitWo"
                            >
                                <div class="flex flex-1 flex-col gap-1">
                                    <Label for="wo_number" class="text-xs"
                                        >WO Number</Label
                                    >
                                    <Input
                                        id="wo_number"
                                        v-model="woForm.cons_wo_number"
                                        placeholder="e.g. WO-2025-0001"
                                        class="h-8 text-xs"
                                        required
                                    />
                                    <InputError
                                        :message="woForm.errors.cons_wo_number"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="outline"
                                    class="mt-5 h-8 text-xs"
                                    :disabled="woForm.processing"
                                    >Set WO</Button
                                >
                            </form>
                        </div>
                        <div class="flex items-end gap-2 border-t pt-3">
                            <div class="flex flex-1 flex-col gap-1">
                                <p class="text-xs text-muted-foreground">
                                    Subcontractor
                                </p>
                                <Select
                                    v-model="
                                        constructionReassignForm.subcontractor_id
                                    "
                                >
                                    <SelectTrigger class="h-8 text-xs"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="sc in subcontractors"
                                            :key="sc.id"
                                            :value="sc.id.toString()"
                                            >{{ sc.name }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="h-8 text-xs"
                                :disabled="
                                    constructionReassignForm.processing ||
                                    constructionReassignForm.subcontractor_id ===
                                        constructionAssignment.subcontractor_id?.toString()
                                "
                                @click="
                                    submitReassign(
                                        constructionReassignForm,
                                        constructionAssignment,
                                    )
                                "
                                >Change</Button
                            >
                        </div>
                    </div>
                    <div v-else class="flex flex-col gap-2">
                        <p class="text-sm text-muted-foreground">
                            Not assigned
                        </p>
                        <div class="flex items-end gap-2">
                            <Select
                                v-model="
                                    constructionAssignForm.subcontractor_id
                                "
                                class="flex-1"
                            >
                                <SelectTrigger class="h-8 text-xs"
                                    ><SelectValue
                                        placeholder="Select subcontractor"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="sc in subcontractors"
                                        :key="sc.id"
                                        :value="sc.id.toString()"
                                        >{{ sc.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <Button
                                type="button"
                                size="sm"
                                class="h-8 text-xs"
                                :disabled="
                                    constructionAssignForm.processing ||
                                    !constructionAssignForm.subcontractor_id
                                "
                                @click="submitAssign(constructionAssignForm)"
                                >Assign</Button
                            >
                        </div>
                        <InputError
                            :message="
                                constructionAssignForm.errors.subcontractor_id
                            "
                        />
                    </div>
                </CardContent>
                <CardFooter v-if="constructionAssignment" class="border-t pt-0">
                    <div
                        class="flex w-full items-center justify-between gap-2 pt-3"
                    >
                        <span class="text-xs text-muted-foreground">
                            Updated
                            {{
                                relativeTime(
                                    constructionAssignment.construction_data
                                        ?.updated_at ??
                                        constructionAssignment.updated_at,
                                )
                            }}
                        </span>
                        <Button
                            as-child
                            size="sm"
                            variant="outline"
                            class="h-7 gap-1.5 text-xs font-medium"
                        >
                            <Link
                                :href="
                                    AdminAssignmentActions.show(
                                        constructionAssignment.id,
                                    ).url
                                "
                            >
                                <ExternalLink class="size-3" />
                                Details
                            </Link>
                        </Button>
                    </div>
                </CardFooter>
            </Card>

            <!-- PLN CONNECTION card -->
            <Card>
                <CardHeader class="pb-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <ActivityTypeBadge activity-type="PLN_CONNECTION" />
                            <StatusBadge
                                v-if="plnAssignment"
                                :status="plnAssignment.status"
                            />
                        </div>
                        <Button
                            v-if="
                                plnAssignment &&
                                plnAssignment.status === 'PENDING'
                            "
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-7 text-destructive hover:text-destructive"
                            @click="removeAssignment(plnAssignment)"
                        >
                            <span class="i-lucide-trash-2 size-3.5" />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="pb-3">
                    <div v-if="plnAssignment" class="flex flex-col gap-3">
                        <Alert
                            v-if="
                                plnAssignment.status === 'REVISION' &&
                                plnAssignment.revision_comment
                            "
                            class="border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700/60 dark:bg-amber-900/20 dark:text-amber-100"
                        >
                            <AlertTriangle
                                class="size-4 text-amber-600 dark:text-amber-300"
                            />
                            <AlertTitle class="text-sm"
                                >Revision Requested</AlertTitle
                            >
                            <AlertDescription class="text-xs">{{
                                plnAssignment.revision_comment
                            }}</AlertDescription>
                        </Alert>
                        <div v-if="plnAssignment.pln_data?.pln_status">
                            <p class="text-xs text-muted-foreground">
                                PLN Status
                            </p>
                            <p class="text-sm font-medium">
                                {{ plnAssignment.pln_data.pln_status }}
                            </p>
                        </div>
                        <p v-else class="text-xs text-muted-foreground">
                            No PLN data submitted yet.
                        </p>
                        <div class="flex items-end gap-2 border-t pt-3">
                            <div class="flex flex-1 flex-col gap-1">
                                <p class="text-xs text-muted-foreground">
                                    Subcontractor
                                </p>
                                <Select
                                    v-model="plnReassignForm.subcontractor_id"
                                >
                                    <SelectTrigger class="h-8 text-xs"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="sc in subcontractors"
                                            :key="sc.id"
                                            :value="sc.id.toString()"
                                            >{{ sc.name }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="h-8 text-xs"
                                :disabled="
                                    plnReassignForm.processing ||
                                    plnReassignForm.subcontractor_id ===
                                        plnAssignment.subcontractor_id?.toString()
                                "
                                @click="
                                    submitReassign(
                                        plnReassignForm,
                                        plnAssignment,
                                    )
                                "
                                >Change</Button
                            >
                        </div>
                    </div>
                    <div v-else class="flex flex-col gap-2">
                        <p class="text-sm text-muted-foreground">
                            Not assigned
                        </p>
                        <div class="flex items-end gap-2">
                            <Select
                                v-model="plnAssignForm.subcontractor_id"
                                class="flex-1"
                            >
                                <SelectTrigger class="h-8 text-xs"
                                    ><SelectValue
                                        placeholder="Select subcontractor"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="sc in subcontractors"
                                        :key="sc.id"
                                        :value="sc.id.toString()"
                                        >{{ sc.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <Button
                                type="button"
                                size="sm"
                                class="h-8 text-xs"
                                :disabled="
                                    plnAssignForm.processing ||
                                    !plnAssignForm.subcontractor_id
                                "
                                @click="submitAssign(plnAssignForm)"
                                >Assign</Button
                            >
                        </div>
                        <InputError
                            :message="plnAssignForm.errors.subcontractor_id"
                        />
                    </div>
                </CardContent>
                <CardFooter v-if="plnAssignment" class="border-t pt-0">
                    <div
                        class="flex w-full items-center justify-between gap-2 pt-3"
                    >
                        <span class="text-xs text-muted-foreground">
                            Updated
                            {{
                                relativeTime(
                                    plnAssignment.pln_data?.updated_at ??
                                        plnAssignment.updated_at,
                                )
                            }}
                        </span>
                        <Button
                            as-child
                            size="sm"
                            variant="outline"
                            class="h-7 gap-1.5 text-xs font-medium"
                        >
                            <Link
                                :href="
                                    AdminAssignmentActions.show(
                                        plnAssignment.id,
                                    ).url
                                "
                            >
                                <ExternalLink class="size-3" />
                                Details
                            </Link>
                        </Button>
                    </div>
                </CardFooter>
            </Card>

            <!-- BAST card -->
            <Card>
                <CardHeader class="pb-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <ActivityTypeBadge activity-type="BAST" />
                            <StatusBadge
                                v-if="bastAssignment"
                                :status="bastAssignment.status"
                            />
                        </div>
                        <Button
                            v-if="
                                bastAssignment &&
                                bastAssignment.status === 'PENDING'
                            "
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-7 text-destructive hover:text-destructive"
                            @click="removeAssignment(bastAssignment)"
                        >
                            <span class="i-lucide-trash-2 size-3.5" />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="pb-3">
                    <div v-if="bastAssignment" class="flex flex-col gap-3">
                        <Alert
                            v-if="
                                bastAssignment.status === 'REVISION' &&
                                bastAssignment.revision_comment
                            "
                            class="border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700/60 dark:bg-amber-900/20 dark:text-amber-100"
                        >
                            <AlertTriangle
                                class="size-4 text-amber-600 dark:text-amber-300"
                            />
                            <AlertTitle class="text-sm"
                                >Revision Requested</AlertTitle
                            >
                            <AlertDescription class="text-xs">{{
                                bastAssignment.revision_comment
                            }}</AlertDescription>
                        </Alert>
                        <div v-if="bastAssignment.bast_data?.plant_name">
                            <p class="text-xs text-muted-foreground">
                                Plant Name
                            </p>
                            <p class="text-sm font-medium">
                                {{ bastAssignment.bast_data.plant_name }}
                            </p>
                        </div>
                        <p v-else class="text-xs text-muted-foreground">
                            No BAST data submitted yet.
                        </p>
                        <div class="flex items-end gap-2 border-t pt-3">
                            <div class="flex flex-1 flex-col gap-1">
                                <p class="text-xs text-muted-foreground">
                                    Subcontractor
                                </p>
                                <Select
                                    v-model="bastReassignForm.subcontractor_id"
                                >
                                    <SelectTrigger class="h-8 text-xs"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="sc in subcontractors"
                                            :key="sc.id"
                                            :value="sc.id.toString()"
                                            >{{ sc.name }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="h-8 text-xs"
                                :disabled="
                                    bastReassignForm.processing ||
                                    bastReassignForm.subcontractor_id ===
                                        bastAssignment.subcontractor_id?.toString()
                                "
                                @click="
                                    submitReassign(
                                        bastReassignForm,
                                        bastAssignment,
                                    )
                                "
                                >Change</Button
                            >
                        </div>
                    </div>
                    <div v-else class="flex flex-col gap-2">
                        <p class="text-sm text-muted-foreground">
                            Not assigned
                        </p>
                        <div class="flex items-end gap-2">
                            <Select
                                v-model="bastAssignForm.subcontractor_id"
                                class="flex-1"
                            >
                                <SelectTrigger class="h-8 text-xs"
                                    ><SelectValue
                                        placeholder="Select subcontractor"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="sc in subcontractors"
                                        :key="sc.id"
                                        :value="sc.id.toString()"
                                        >{{ sc.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <Button
                                type="button"
                                size="sm"
                                class="h-8 text-xs"
                                :disabled="
                                    bastAssignForm.processing ||
                                    !bastAssignForm.subcontractor_id
                                "
                                @click="submitAssign(bastAssignForm)"
                                >Assign</Button
                            >
                        </div>
                        <InputError
                            :message="bastAssignForm.errors.subcontractor_id"
                        />
                    </div>
                </CardContent>
                <CardFooter v-if="bastAssignment" class="border-t pt-0">
                    <div
                        class="flex w-full items-center justify-between gap-2 pt-3"
                    >
                        <span class="text-xs text-muted-foreground">
                            Updated
                            {{
                                relativeTime(
                                    bastAssignment.bast_data?.updated_at ??
                                        bastAssignment.updated_at,
                                )
                            }}
                        </span>
                        <Button
                            as-child
                            size="sm"
                            variant="outline"
                            class="h-7 gap-1.5 text-xs font-medium"
                        >
                            <Link
                                :href="
                                    AdminAssignmentActions.show(
                                        bastAssignment.id,
                                    ).url
                                "
                            >
                                <ExternalLink class="size-3" />
                                Details
                            </Link>
                        </Button>
                    </div>
                </CardFooter>
            </Card>
        </div>
    </div>
</template>
