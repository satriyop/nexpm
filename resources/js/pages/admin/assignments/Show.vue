<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    BadgeCheck,
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    Download,
    ExternalLink,
    FileText,
    ImageIcon,
    LockKeyhole,
    MapPin,
    Pencil,
    RefreshCw,
    RotateCw,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';
import * as AdminAssignmentActions from '@/actions/App/Http/Controllers/Admin/AssignmentController';
import ActivityTypeBadge from '@/components/ActivityTypeBadge.vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { dashboard } from '@/routes';
import type { Assignment } from '@/types';

interface SubcontractorOption {
    id: number;
    name: string;
    code: string;
}

interface AuditLog {
    id: number;
    event: string;
    payload: Record<string, unknown> | null;
    user: { id: number; name: string } | null;
    created_at: string | null;
}

const props = defineProps<{
    assignment: Assignment;
    subcontractors: SubcontractorOption[];
    auditLogs: AuditLog[] | null;
}>();

defineOptions({
    layout: (page: { assignment: Assignment }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Assignments',
                href: AdminAssignmentActions.siteAssignments(
                    page.assignment.site.id,
                ).url,
            },
            { title: page.assignment.site.site_code, href: '#' },
        ],
    }),
});

const survey = computed(() => props.assignment.survey_data);
const pln = computed(() => props.assignment.pln_data);
const construction = computed(() => props.assignment.construction_data);
const bast = computed(() => props.assignment.bast_data);

const isConstruction = computed(
    () => props.assignment.activity_type === 'CONSTRUCTION',
);

const isWoMissing = computed(
    () => isConstruction.value && !construction.value?.cons_wo_number,
);

// --- Verify dialog ---
const verifyOpen = ref(false);
const verifyForm = useForm({});

const canVerify = computed(
    () =>
        props.assignment.status === 'DOCUMENT' ||
        props.assignment.status === 'COMPLETED',
);

const canRevise = computed(
    () =>
        props.assignment.activity_type === 'BAST' &&
        props.assignment.status === 'COMPLETED',
);

const isDropped = computed(() => props.assignment.status === 'DROP');

function openVerifyDialog(): void {
    verifyOpen.value = true;
}

function submitVerify(): void {
    verifyForm.post(AdminAssignmentActions.verify(props.assignment.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            verifyOpen.value = false;
        },
    });
}

// --- Revise dialog ---
const reviseOpen = ref(false);
const reviseForm = useForm({
    revision_comment: '',
});

function openReviseDialog(): void {
    reviseForm.reset();
    reviseForm.clearErrors();
    reviseOpen.value = true;
}

function submitRevise(): void {
    reviseForm.post(AdminAssignmentActions.revise(props.assignment.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            reviseOpen.value = false;
            reviseForm.reset();
        },
    });
}

// --- Drop / Restore ---
const dropForm = useForm({});
const restoreForm = useForm({});
const dropDialogOpen = ref(false);

function confirmDrop(): void {
    dropForm.patch(AdminAssignmentActions.drop(props.assignment.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            dropDialogOpen.value = false;
        },
    });
}

function submitRestore(): void {
    restoreForm.patch(AdminAssignmentActions.restore(props.assignment.id).url, {
        preserveScroll: true,
    });
}

// --- Construction prerequisite form ---
const prereqForm = useForm({
    cons_wo_number: construction.value?.cons_wo_number ?? '',
    project_status: construction.value?.project_status ?? '',
    setup_approval_date: construction.value?.setup_approval_date ?? '',
});

function submitPrerequisite(): void {
    router.visit(
        AdminAssignmentActions.updateConstructionPrerequisite(
            props.assignment.id,
        ).url,
        {
            method: 'patch',
            data: {
                cons_wo_number: prereqForm.cons_wo_number,
                project_status: prereqForm.project_status,
                setup_approval_date: prereqForm.setup_approval_date,
            },
            preserveScroll: true,
            onError: (errors) => {
                prereqForm.setError(errors);
            },
            onStart: () => {
                prereqForm.processing = true;
                prereqForm.clearErrors();
            },
            onFinish: () => {
                prereqForm.processing = false;
            },
        },
    );
}

// --- Admin edit toggles ---
const showSurveyEdit = ref(false);
const showPlnEdit = ref(false);
const showConstructionEdit = ref(false);
const showBastEdit = ref(false);
const isSaving = ref(false);

// --- Admin survey edit form ---
const adminSurveyForm = useForm({
    _method: 'patch',
    surveyor_name: survey.value?.surveyor_name ?? '',
    pic_location_name: survey.value?.pic_location_name ?? '',
    pic_location_phone: survey.value?.pic_location_phone ?? '',
    charger_type: survey.value?.charger_type ?? '',
    ss_schedule_date: survey.value?.ss_schedule_date ?? '',
    cable_pulling_type: survey.value?.cable_pulling_type ?? '',
    pln_network_type: survey.value?.pln_network_type ?? '',
    parking_slot: survey.value?.parking_slot ?? '',
    additional_info: survey.value?.additional_info ?? '',
    ss_report_submission_date: survey.value?.ss_report_submission_date ?? '',
    photo_overall_site: null as File | null,
    photo_parking_evcs: null as File | null,
    photo_access_route: null as File | null,
    photo_pln_network: null as File | null,
    photo_satellite_gmaps: null as File | null,
    file_mockup_3d: null as File | null,
    file_site_plan: null as File | null,
    file_ba_survey: null as File | null,
});

function submitAdminSurvey(): void {
    isSaving.value = true;
    adminSurveyForm
        .transform((data) => ({
            ...data,
            _method: 'patch',
        }))
        .post(AdminAssignmentActions.updateSurveyData(props.assignment).url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                showSurveyEdit.value = false;
            },
            onFinish: () => {
                isSaving.value = false;
            },
        });
}

// --- Admin PLN edit form ---
const adminPlnForm = useForm({
    pln_status: pln.value?.pln_status ?? '',
    nidi_slo_date_acquired: pln.value?.nidi_slo_date_acquired ?? '',
    type_rate: pln.value?.type_rate ?? '',
    kwh_meter_installation_date: pln.value?.kwh_meter_installation_date ?? '',
    id_pelanggan: pln.value?.id_pelanggan ?? '',
    catatan_progres: pln.value?.catatan_progres ?? '',
    email_bpujl_req_date: pln.value?.email_bpujl_req_date ?? '',
    bpujl_acquired_date: pln.value?.bpujl_acquired_date ?? '',
    file_slo: null as File | null,
    file_nidi: null as File | null,
    file_reg: null as File | null,
});

function submitAdminPln(): void {
    isSaving.value = true;
    adminPlnForm.patch(
        AdminAssignmentActions.updatePlnData(props.assignment).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                showPlnEdit.value = false;
            },
            onFinish: () => {
                isSaving.value = false;
            },
        },
    );
}

// --- Admin construction subcon edit form ---
const adminConstructionForm = useForm({
    cons_actual_start_date: construction.value?.cons_actual_start_date ?? '',
    cons_actual_done_date: construction.value?.cons_actual_done_date ?? '',
    machine_serial_number: construction.value?.machine_serial_number ?? '',
    catatan_progres: construction.value?.catatan_progres ?? '',
});

function submitAdminConstruction(): void {
    isSaving.value = true;
    router.visit(
        AdminAssignmentActions.updateConstructionSubconData(props.assignment)
            .url,
        {
            method: 'patch',
            data: { ...adminConstructionForm.data() },
            preserveScroll: true,
            onError: (errors) => {
                adminConstructionForm.setError(errors);
            },
            onStart: () => {
                adminConstructionForm.processing = true;
                adminConstructionForm.clearErrors();
            },
            onFinish: () => {
                adminConstructionForm.processing = false;
                isSaving.value = false;
            },
            onSuccess: () => {
                showConstructionEdit.value = false;
            },
        },
    );
}

// --- Admin BAST edit form ---
const adminBastForm = useForm({
    plant_name: bast.value?.plant_name ?? '',
    plant_address: bast.value?.plant_address ?? '',
    plant_coordinate: bast.value?.plant_coordinate ?? '',
    gmaps_link: bast.value?.gmaps_link ?? '',
    charger_type: bast.value?.charger_type ?? '',
    sn_unit: bast.value?.sn_unit ?? '',
    id_pln: bast.value?.id_pln ?? '',
    sim_provider: bast.value?.sim_provider ?? '',
    installation_vendor: bast.value?.installation_vendor ?? '',
    pic_vendor_contact: bast.value?.pic_vendor_contact ?? '',
    installation_date: bast.value?.installation_date ?? '',
    commissioning_date: bast.value?.commissioning_date ?? '',
    customer: bast.value?.customer ?? '',
    nomor_simcard: bast.value?.nomor_simcard ?? '',
    go_live_date_pln_pass: bast.value?.go_live_date_pln_pass ?? '',
    go_live_date_pln: bast.value?.go_live_date_pln ?? '',
});

function submitAdminBast(): void {
    isSaving.value = true;
    router.visit(AdminAssignmentActions.updateBastData(props.assignment).url, {
        method: 'patch',
        data: { ...adminBastForm.data() },
        preserveScroll: true,
        onError: (errors) => {
            adminBastForm.setError(errors);
        },
        onStart: () => {
            adminBastForm.processing = true;
            adminBastForm.clearErrors();
        },
        onFinish: () => {
            adminBastForm.processing = false;
            isSaving.value = false;
        },
        onSuccess: () => {
            showBastEdit.value = false;
        },
    });
}

const BAST_SECTION_LABELS: Record<string, string> = {
    device: 'EV Charger / Device',
    sim_card: 'SIM Card',
    grounding: 'Grounding System',
    fire_extinguisher: 'Fire Extinguisher',
    kwh_meter: 'KWH Meter',
    ac_panel: 'AC Panel',
    measurements: 'Measurements',
    cables: 'Cables',
};

const BAST_CHECKPOINT_LABELS: Record<string, string> = {
    device_front_view_open: 'Front View (Open)',
    device_side_view_right: 'Side View (Right)',
    device_front_view_close: 'Front View (Close)',
    device_side_view_left: 'Side View (Left)',
    device_foundation_depth: 'Foundation at 40cm Depth',
    device_foundation_concrete: 'Concrete Foundation (8cm Depth from Ground)',
    device_parking_space: 'Parking Space',
    device_sticker: 'Sticker',
    device_name_plate: 'Name Plate',
    device_ac_cable_termination: 'AC Cable Termination',
    device_emergency_button_cover: 'Emergency Button Cover',
    device_grounding_termination: 'Grounding Termination (Cover)',
    device_cable_entry_panel: 'AC/DC Cable Entry Termination Panel',
    device_visible_safety_sign: 'Visible Safety Sign',
    sim_kartu_perdana: 'Kartu Perdana',
    sim_installed_sim_card: 'Installed SIM Card',
    sim_signal_bar: 'Signal Bar',
    sim_url: 'URL',
    sim_start_charge_immediately: 'Start Charge Immediately (CE-LCD)',
    sim_user_interface_lcd: 'User Interface (CE-LCD)',
    grounding_rod_connection: 'Grounding Rod Connection',
    grounding_rod_to_earth_1: 'Grounding Rod to Earth — Spot 1',
    grounding_rod_to_earth_2: 'Grounding Rod to Earth — Spot 2',
    grounding_busbar_panel: 'Grounding Rod Busbar (in Cable Route)',
    grounding_cable_route: 'Grounding Test — Cable Route (OHM)',
    grounding_test_ac_panel: 'Grounding Test AC Panel',
    fire_ext_front_view: 'Front View',
    fire_ext_pressure: 'Pressure',
    fire_ext_placement: 'Placement',
    fire_ext_specification: 'Specification',
    kwh_kwh_meter: 'KWH Meter',
    kwh_mcb_pln: 'MCB PLN',
    ac_front_view_open: 'Front View (Open)',
    ac_safety_sign: 'Safety Sign',
    ac_front_view_close: 'Front View (Close)',
    ac_pilot_lamps: 'Pilot Lamps',
    ac_side_view_right: 'Side View (Right)',
    ac_locking_system: 'Locking System',
    ac_side_view_left: 'Side View (Left)',
    ac_mcb: 'MCB',
    ac_panel_wiring: 'Panel Wiring',
    ac_phase_marking: 'Phase Marking',
    measurement_voltage_rs: 'Voltage R-S',
    measurement_voltage_rt: 'Voltage R-T',
    measurement_voltage_st: 'Voltage S-T',
    measurement_frequency: 'Frequency',
    measurement_voltage_ng: 'Voltage N-G',
    measurement_voltage_rn: 'Voltage R-N',
    measurement_voltage_rg: 'Voltage R-G',
    measurement_grounding_ac: 'Grounding to AC Panel',
    cable_spec: 'Cable Spec',
    cable_routing_1: 'Cable Routing (1)',
    cable_routing_2: 'Cable Routing (2)',
    cable_routing_3: 'Cable Routing (3)',
};

const BAST_SECTION_ORDER = [
    'device',
    'sim_card',
    'grounding',
    'fire_extinguisher',
    'kwh_meter',
    'ac_panel',
    'measurements',
    'cables',
];

const bastPhotosBySection = computed(() => {
    const photos = bast.value?.bast_photos ?? [];
    const map: Record<string, typeof photos> = {};

    for (const photo of photos) {
        if (!map[photo.section]) {
            map[photo.section] = [];
        }

        map[photo.section].push(photo);
    }

    return map;
});

// --- Reassign dialog ---
const reassignOpen = ref(false);
const reassignForm = useForm({ subcontractor_id: '' });

function submitReassign(): void {
    router.visit(AdminAssignmentActions.reassign(props.assignment).url, {
        method: 'patch',
        data: { subcontractor_id: reassignForm.subcontractor_id },
        preserveScroll: true,
        onError: (errors) => {
            reassignForm.setError(errors);
        },
        onStart: () => {
            reassignForm.processing = true;
            reassignForm.clearErrors();
        },
        onFinish: () => {
            reassignForm.processing = false;
        },
        onSuccess: () => {
            reassignOpen.value = false;
            reassignForm.reset();
        },
    });
}

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function formatDateOnly(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString();
}

function storageUrl(path: string | null | undefined): string {
    if (!path) {
        return '#';
    }

    // If it's already an external URL, return it directly
    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }

    return `/storage/${path}`;
}

// --- Navigation guard (warn if any edit panel is open) ---
const anyEditOpen = computed(
    () =>
        showSurveyEdit.value ||
        showPlnEdit.value ||
        showConstructionEdit.value ||
        showBastEdit.value,
);

function handleBeforeUnload(e: BeforeUnloadEvent): void {
    if (anyEditOpen.value) {
        e.preventDefault();
        e.returnValue = '';
    }
}

const removeNavGuard = router.on('before', (e) => {
    if (isSaving.value) {
        return;
    }

    if (e.detail.visit.method !== 'get') {
        return;
    }

    if (
        anyEditOpen.value &&
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
</script>

<template>
    <Head :title="`Assignment #${assignment.id}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col gap-2">
            <Button
                as-child
                variant="ghost"
                size="sm"
                class="-ml-2 w-fit text-muted-foreground"
            >
                <Link
                    :href="
                        AdminAssignmentActions.siteAssignments(
                            assignment.site.id,
                        ).url
                    "
                >
                    <ArrowLeft class="size-4" />
                    Back to Assignments
                </Link>
            </Button>
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-2 text-2xl font-semibold">
                        <span>{{ assignment.site?.site_code }}</span>
                        <span class="text-muted-foreground">·</span>
                        <span class="font-normal text-muted-foreground">
                            {{ assignment.site?.location_name }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <ActivityTypeBadge
                            :activity-type="assignment.activity_type"
                        />
                        <StatusBadge :status="assignment.status" />
                        <span class="text-xs text-muted-foreground">
                            Assignment #{{ assignment.id }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <Alert
            v-if="
                assignment.status === 'REVISION' && assignment.revision_comment
            "
            class="border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700/60 dark:bg-amber-900/20 dark:text-amber-100"
        >
            <AlertTriangle class="size-4 text-amber-600 dark:text-amber-300" />
            <AlertTitle>Revision Requested</AlertTitle>
            <AlertDescription class="text-amber-900/90 dark:text-amber-100/90">
                {{ assignment.revision_comment }}
            </AlertDescription>
        </Alert>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <MapPin class="size-4" />
                            Site Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Site Code
                            </p>
                            <p class="font-medium">
                                {{ assignment.site?.site_code }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Location Name
                            </p>
                            <p class="font-medium">
                                {{ assignment.site?.location_name }}
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-xs text-muted-foreground">Address</p>
                            <p class="font-medium">
                                {{ assignment.site?.address }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">City</p>
                            <p class="font-medium">
                                {{ assignment.site?.city }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Province
                            </p>
                            <p class="font-medium">
                                {{ assignment.site?.province }}
                            </p>
                        </div>
                        <div
                            v-if="assignment.site?.google_map_url"
                            class="sm:col-span-2"
                        >
                            <a
                                :href="assignment.site.google_map_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 text-sm text-primary hover:underline"
                            >
                                <ExternalLink class="size-3.5" />
                                View on Google Maps
                            </a>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between gap-2"
                    >
                        <CardTitle>Sub-Contractor</CardTitle>
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            @click="reassignOpen = true"
                        >
                            <RefreshCw class="size-3.5" />
                            Reassign
                        </Button>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs text-muted-foreground">Name</p>
                            <p class="font-medium">
                                {{ assignment.subcontractor?.name }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Code</p>
                            <p class="font-medium">
                                {{ assignment.subcontractor?.code }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- SURVEY -->
                <Card v-if="assignment.activity_type === 'SURVEY'">
                    <CardHeader>
                        <CardTitle>Survey Data</CardTitle>
                        <CardDescription>
                            Submitted information from the field surveyor.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <div
                            v-if="!survey"
                            class="text-sm text-muted-foreground"
                        >
                            No survey data submitted yet.
                        </div>
                        <div v-else class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Surveyor Name
                                </p>
                                <p class="font-medium">
                                    {{ survey.surveyor_name ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    PIC Location Name
                                </p>
                                <p class="font-medium">
                                    {{ survey.pic_location_name ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    PIC Phone
                                </p>
                                <p class="font-medium">
                                    {{ survey.pic_location_phone ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Charger Type
                                </p>
                                <p class="font-medium">
                                    {{ survey.charger_type ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    SS Schedule Date
                                </p>
                                <p class="font-medium">
                                    {{
                                        formatDateOnly(survey.ss_schedule_date)
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Cable Pulling Type
                                </p>
                                <p class="font-medium">
                                    {{ survey.cable_pulling_type ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Required Power (kVA)
                                </p>
                                <p class="font-medium">
                                    {{ assignment.site.power_kva ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Available PLN Network Type
                                </p>
                                <p class="font-medium">
                                    {{ survey.pln_network_type ?? '—' }}
                                </p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="text-xs text-muted-foreground">
                                    Additional Info
                                </p>
                                <p
                                    class="text-sm whitespace-pre-line text-foreground"
                                >
                                    {{ survey.additional_info ?? '—' }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Parking Slot
                                </p>
                                <p class="font-medium">
                                    {{ survey.parking_slot ?? '—' }}
                                </p>
                            </div>

                            <div class="sm:col-span-2">
                                <p
                                    class="mb-2 text-xs font-medium text-muted-foreground uppercase"
                                >
                                    Photos
                                </p>
                                <div
                                    class="grid grid-cols-2 gap-3 sm:grid-cols-3"
                                >
                                    <div
                                        v-for="(photo, key) in {
                                            photo_overall_site: 'Overall Site',
                                            photo_parking_evcs:
                                                'Parking / EVCS',
                                            photo_access_route:
                                                'Jalur Akses Menuju Lokasi',
                                            photo_pln_network: 'PLN Network',
                                            photo_satellite_gmaps:
                                                'Satellite / GMaps',
                                        }"
                                        :key="key"
                                        class="flex flex-col gap-1"
                                    >
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >{{ photo }}</span
                                        >
                                        <a
                                            v-if="
                                                survey[
                                                    key as keyof typeof survey
                                                ]
                                            "
                                            :href="
                                                storageUrl(
                                                    survey[
                                                        key as keyof typeof survey
                                                    ] as string,
                                                )
                                            "
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <img
                                                :src="
                                                    storageUrl(
                                                        survey[
                                                            key as keyof typeof survey
                                                        ] as string,
                                                    )
                                                "
                                                :alt="String(photo)"
                                                class="h-24 w-full rounded-md border border-sidebar-border/70 object-cover dark:border-sidebar-border"
                                            />
                                        </a>
                                        <span
                                            v-else
                                            class="flex h-24 items-center justify-center rounded-md border border-dashed border-sidebar-border/70 text-xs text-muted-foreground dark:border-sidebar-border"
                                        >
                                            Not uploaded
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="sm:col-span-2">
                                <p
                                    class="mb-2 text-xs font-medium text-muted-foreground uppercase"
                                >
                                    Files
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <a
                                        v-if="survey.file_mockup_3d"
                                        :href="storageUrl(survey.file_mockup_3d)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-1.5 text-xs hover:bg-accent"
                                    >
                                        <FileText class="size-3.5" />
                                        Mockup 3D
                                    </a>
                                    <a
                                        v-if="survey.file_site_plan"
                                        :href="storageUrl(survey.file_site_plan)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-1.5 text-xs hover:bg-accent"
                                    >
                                        <ImageIcon class="size-3.5" />
                                        Site Plan
                                    </a>
                                    <a
                                        v-if="survey.file_ba_survey"
                                        :href="storageUrl(survey.file_ba_survey)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-1.5 text-xs hover:bg-accent"
                                    >
                                        <ImageIcon class="size-3.5" />
                                        BA Survey
                                    </a>
                                    <span
                                        v-if="
                                            !survey.file_mockup_3d &&
                                            !survey.file_site_plan &&
                                            !survey.file_ba_survey
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        No files uploaded.
                                    </span>
                                </div>
                            </div>
                        </div>
                        <Separator class="my-2" />
                        <div class="flex justify-end">
                            <Button
                                variant="ghost"
                                size="sm"
                                type="button"
                                @click="showSurveyEdit = !showSurveyEdit"
                            >
                                <Pencil class="size-3.5" />
                                {{
                                    showSurveyEdit ? 'Cancel Edit' : 'Edit Data'
                                }}
                            </Button>
                        </div>
                        <form
                            v-if="showSurveyEdit"
                            class="grid gap-4 pt-2 sm:grid-cols-2"
                            @submit.prevent="submitAdminSurvey"
                        >
                            <div class="grid gap-1.5">
                                <Label>Surveyor Name</Label>
                                <Input
                                    v-model="adminSurveyForm.surveyor_name"
                                />
                                <InputError
                                    :message="
                                        adminSurveyForm.errors.surveyor_name
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>PIC Location Name</Label>
                                <Input
                                    v-model="adminSurveyForm.pic_location_name"
                                />
                                <InputError
                                    :message="
                                        adminSurveyForm.errors.pic_location_name
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>PIC Phone</Label>
                                <Input
                                    v-model="adminSurveyForm.pic_location_phone"
                                />
                                <InputError
                                    :message="
                                        adminSurveyForm.errors
                                            .pic_location_phone
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Charger Type</Label>
                                <Input v-model="adminSurveyForm.charger_type" />
                                <InputError
                                    :message="
                                        adminSurveyForm.errors.charger_type
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>SS Schedule Date</Label>
                                <Input
                                    v-model="adminSurveyForm.ss_schedule_date"
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        adminSurveyForm.errors.ss_schedule_date
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Cable Pulling Type</Label>
                                <Input
                                    v-model="adminSurveyForm.cable_pulling_type"
                                />
                                <InputError
                                    :message="
                                        adminSurveyForm.errors
                                            .cable_pulling_type
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Required Power (kVA)</Label>
                                <div
                                    class="rounded-md border bg-muted/50 px-3 py-2 text-sm font-medium"
                                >
                                    {{
                                        assignment.site.power_kva ??
                                        survey?.power_kva ??
                                        '—'
                                    }}
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Available PLN Network Type</Label>
                                <Select
                                    v-model="adminSurveyForm.pln_network_type"
                                >
                                    <SelectTrigger
                                        ><SelectValue
                                            placeholder="Select phase"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1 Phase"
                                            >1 Phase</SelectItem
                                        >
                                        <SelectItem value="3 Phase"
                                            >3 Phase</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="
                                        adminSurveyForm.errors.pln_network_type
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Parking Slot</Label>
                                <Input v-model="adminSurveyForm.parking_slot" />
                                <InputError
                                    :message="
                                        adminSurveyForm.errors.parking_slot
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5 sm:col-span-2">
                                <Label>Additional Info</Label>
                                <textarea
                                    v-model="adminSurveyForm.additional_info"
                                    rows="3"
                                    class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError
                                    :message="
                                        adminSurveyForm.errors.additional_info
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>SS Report Submission Date</Label>
                                <Input
                                    v-model="
                                        adminSurveyForm.ss_report_submission_date
                                    "
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        adminSurveyForm.errors
                                            .ss_report_submission_date
                                    "
                                />
                            </div>
                            <!-- Survey photos -->
                            <div class="grid gap-1.5 sm:col-span-2">
                                <Label
                                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >Photos</Label
                                >
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div
                                        v-for="[key, label] in [
                                            [
                                                'photo_overall_site',
                                                'Tampak Keseluruhan Site',
                                            ],
                                            [
                                                'photo_parking_evcs',
                                                'Lahan Parkir EVCS/BSS',
                                            ],
                                            [
                                                'photo_access_route',
                                                'Jalur Akses Menuju Lokasi',
                                            ],
                                            [
                                                'photo_pln_network',
                                                'Jaringan PLN Terdekat',
                                            ],
                                            [
                                                'photo_satellite_gmaps',
                                                'Satelit GMaps',
                                            ],
                                        ] as const"
                                        :key="key"
                                        class="grid gap-1"
                                    >
                                        <span class="text-xs font-medium">{{
                                            label
                                        }}</span>
                                        <a
                                            v-if="survey?.[key]"
                                            :href="storageUrl(survey[key]!)"
                                            target="_blank"
                                            class="mb-1 inline-flex items-center gap-1 text-xs text-muted-foreground underline-offset-2 hover:underline"
                                        >
                                            Current photo
                                        </a>
                                        <input
                                            type="file"
                                            accept="image/*"
                                            class="text-xs"
                                            @change="
                                                (e) => {
                                                    const f = (
                                                        e.target as HTMLInputElement
                                                    ).files?.[0];
                                                    if (f)
                                                        adminSurveyForm[key] =
                                                            f;
                                                }
                                            "
                                        />
                                        <InputError
                                            :message="
                                                adminSurveyForm.errors[key]
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                            <!-- Survey files -->
                            <div class="grid gap-1.5 sm:col-span-2">
                                <Label
                                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >Files</Label
                                >
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div
                                        v-for="[key, label, accept] in [
                                            ['file_mockup_3d', '3D Mockup', '.pdf,.jpg,.jpeg,.png,.dwg'],
                                            ['file_site_plan', 'Site Plan', 'image/*'],
                                            ['file_ba_survey', 'BA Survey', 'image/*'],
                                        ] as const"
                                        :key="key"
                                        class="grid gap-1"
                                    >
                                        <span class="text-xs font-medium">{{
                                            label
                                        }}</span>
                                        <a
                                            v-if="survey?.[key]"
                                            :href="storageUrl(survey[key]!)"
                                            target="_blank"
                                            class="mb-1 inline-flex items-center gap-1 text-xs text-muted-foreground underline-offset-2 hover:underline"
                                        >
                                            Current file
                                        </a>
                                        <input
                                            type="file"
                                            :accept="accept"
                                            class="text-xs"
                                            @change="
                                                (e) => {
                                                    const f = (
                                                        e.target as HTMLInputElement
                                                    ).files?.[0];
                                                    if (f)
                                                        adminSurveyForm[key] =
                                                            f;
                                                }
                                            "
                                        />
                                        <InputError
                                            :message="
                                                adminSurveyForm.errors[key]
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-end sm:col-span-2">
                                <Button
                                    type="submit"
                                    :disabled="adminSurveyForm.processing"
                                >
                                    {{
                                        adminSurveyForm.processing
                                            ? 'Saving…'
                                            : 'Save Changes'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- PLN -->
                <Card v-if="assignment.activity_type === 'PLN_CONNECTION'">
                    <CardHeader>
                        <CardTitle>PLN Connection Data</CardTitle>
                        <CardDescription>
                            PLN registration progress and supporting documents.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="!pln" class="text-sm text-muted-foreground">
                            No PLN data submitted yet.
                        </div>
                        <div v-else class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    PLN Status
                                </p>
                                <p class="font-medium">
                                    {{ pln.pln_status ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    NIDI/SLO Date
                                </p>
                                <p class="font-medium">
                                    {{
                                        formatDateOnly(
                                            pln.nidi_slo_date_acquired,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Type / Rate
                                </p>
                                <p class="font-medium">
                                    {{ pln.type_rate ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    kWh Meter Installation
                                </p>
                                <p class="font-medium">
                                    {{
                                        formatDateOnly(
                                            pln.kwh_meter_installation_date,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    ID Pelanggan
                                </p>
                                <p class="font-medium">
                                    {{ pln.id_pelanggan ?? '—' }}
                                </p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="text-xs text-muted-foreground">
                                    Catatan Progres
                                </p>
                                <p
                                    class="text-sm whitespace-pre-line text-foreground"
                                >
                                    {{ pln.catatan_progres ?? '—' }}
                                </p>
                            </div>
                            <div class="sm:col-span-2">
                                <p
                                    class="mb-2 text-xs font-medium text-muted-foreground uppercase"
                                >
                                    Files
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <a
                                        v-if="pln.file_slo"
                                        :href="storageUrl(pln.file_slo)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-1.5 text-xs hover:bg-accent"
                                    >
                                        <FileText class="size-3.5" />
                                        SLO
                                    </a>
                                    <a
                                        v-if="pln.file_nidi"
                                        :href="storageUrl(pln.file_nidi)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-1.5 text-xs hover:bg-accent"
                                    >
                                        <FileText class="size-3.5" />
                                        NIDI
                                    </a>
                                    <a
                                        v-if="pln.file_reg"
                                        :href="storageUrl(pln.file_reg)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-1.5 text-xs hover:bg-accent"
                                    >
                                        <FileText class="size-3.5" />
                                        Registration
                                    </a>
                                    <span
                                        v-if="
                                            !pln.file_slo &&
                                            !pln.file_nidi &&
                                            !pln.file_reg
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        No files uploaded.
                                    </span>
                                </div>
                            </div>
                        </div>
                        <Separator class="my-2" />
                        <div class="flex justify-end">
                            <Button
                                variant="ghost"
                                size="sm"
                                type="button"
                                @click="showPlnEdit = !showPlnEdit"
                            >
                                <Pencil class="size-3.5" />
                                {{ showPlnEdit ? 'Cancel Edit' : 'Edit Data' }}
                            </Button>
                        </div>
                        <form
                            v-if="showPlnEdit"
                            class="grid gap-4 pt-2 sm:grid-cols-2"
                            @submit.prevent="submitAdminPln"
                        >
                            <div class="grid gap-1.5">
                                <Label>PLN Status</Label>
                                <Input v-model="adminPlnForm.pln_status" />
                                <InputError
                                    :message="adminPlnForm.errors.pln_status"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>NIDI/SLO Date</Label>
                                <Input
                                    v-model="
                                        adminPlnForm.nidi_slo_date_acquired
                                    "
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        adminPlnForm.errors
                                            .nidi_slo_date_acquired
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Type / Rate</Label>
                                <Input v-model="adminPlnForm.type_rate" />
                                <InputError
                                    :message="adminPlnForm.errors.type_rate"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>kWh Meter Installation Date</Label>
                                <Input
                                    v-model="
                                        adminPlnForm.kwh_meter_installation_date
                                    "
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        adminPlnForm.errors
                                            .kwh_meter_installation_date
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>ID Pelanggan</Label>
                                <Input v-model="adminPlnForm.id_pelanggan" />
                                <InputError
                                    :message="adminPlnForm.errors.id_pelanggan"
                                />
                            </div>
                            <div class="grid gap-1.5 sm:col-span-2">
                                <Label>Catatan Progres</Label>
                                <textarea
                                    v-model="adminPlnForm.catatan_progres"
                                    rows="3"
                                    class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError
                                    :message="
                                        adminPlnForm.errors.catatan_progres
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Email BPUJL Request Date</Label>
                                <Input
                                    v-model="adminPlnForm.email_bpujl_req_date"
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        adminPlnForm.errors.email_bpujl_req_date
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>BPUJL Acquired Date</Label>
                                <Input
                                    v-model="adminPlnForm.bpujl_acquired_date"
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        adminPlnForm.errors.bpujl_acquired_date
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5 sm:col-span-2">
                                <Label
                                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >Files</Label
                                >
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div
                                        v-for="[key, label] in [
                                            ['file_slo', 'SLO'],
                                            ['file_nidi', 'NIDI'],
                                            ['file_reg', 'Registration'],
                                        ] as const"
                                        :key="key"
                                        class="grid gap-1"
                                    >
                                        <span class="text-xs font-medium">{{
                                            label
                                        }}</span>
                                        <a
                                            v-if="pln?.[key]"
                                            :href="storageUrl(pln[key]!)"
                                            target="_blank"
                                            class="mb-1 inline-flex items-center gap-1 text-xs text-muted-foreground underline-offset-2 hover:underline"
                                        >
                                            Current file
                                        </a>
                                        <input
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            class="text-xs"
                                            @change="
                                                (e) => {
                                                    const f = (
                                                        e.target as HTMLInputElement
                                                    ).files?.[0];
                                                    if (f)
                                                        adminPlnForm[key] = f;
                                                }
                                            "
                                        />
                                        <InputError
                                            :message="adminPlnForm.errors[key]"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-end sm:col-span-2">
                                <Button
                                    type="submit"
                                    :disabled="adminPlnForm.processing"
                                >
                                    {{
                                        adminPlnForm.processing
                                            ? 'Saving…'
                                            : 'Save Changes'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- CONSTRUCTION -->
                <Card v-if="isConstruction">
                    <CardHeader>
                        <CardTitle>Construction Data</CardTitle>
                        <CardDescription>
                            Combined view of admin prerequisites and
                            sub-contractor work.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="!construction"
                            class="text-sm text-muted-foreground"
                        >
                            No construction data yet.
                        </div>
                        <div v-else class="flex flex-col gap-6">
                            <div>
                                <h3
                                    class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Admin Prerequisites
                                </h3>
                                <div class="grid gap-4 sm:grid-cols-3">
                                    <div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            WO Number
                                        </p>
                                        <p class="font-medium">
                                            {{
                                                construction.cons_wo_number ??
                                                '—'
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Project Status
                                        </p>
                                        <p class="font-medium">
                                            {{
                                                construction.project_status ??
                                                '—'
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Setup Approval
                                        </p>
                                        <p class="font-medium">
                                            {{
                                                formatDateOnly(
                                                    construction.setup_approval_date,
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <Separator />

                            <div>
                                <h3
                                    class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Sub-Contractor Submission
                                </h3>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Actual Start
                                        </p>
                                        <p class="font-medium">
                                            {{
                                                formatDateOnly(
                                                    construction.cons_actual_start_date,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Actual Done
                                        </p>
                                        <p class="font-medium">
                                            {{
                                                formatDateOnly(
                                                    construction.cons_actual_done_date,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Machine Serial
                                        </p>
                                        <p class="font-medium">
                                            {{
                                                construction.machine_serial_number ??
                                                '—'
                                            }}
                                        </p>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Catatan Progres
                                        </p>
                                        <p
                                            class="text-sm whitespace-pre-line text-foreground"
                                        >
                                            {{
                                                construction.catatan_progres ??
                                                '—'
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <Separator />

                            <div>
                                <h3
                                    class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Progress Photos
                                </h3>
                                <div
                                    v-if="
                                        construction.construction_photos
                                            .length === 0
                                    "
                                    class="text-sm text-muted-foreground"
                                >
                                    No progress photos uploaded.
                                </div>
                                <div
                                    v-else
                                    class="grid grid-cols-2 gap-3 sm:grid-cols-4"
                                >
                                    <a
                                        v-for="photo in construction.construction_photos"
                                        :key="photo.id"
                                        :href="storageUrl(photo.path)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <img
                                            :src="storageUrl(photo.path)"
                                            class="h-24 w-full rounded-md border border-sidebar-border/70 object-cover dark:border-sidebar-border"
                                            alt="Construction progress"
                                        />
                                    </a>
                                </div>
                            </div>
                            <Separator />
                            <div class="flex justify-end">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    type="button"
                                    @click="
                                        showConstructionEdit =
                                            !showConstructionEdit
                                    "
                                >
                                    <Pencil class="size-3.5" />
                                    {{
                                        showConstructionEdit
                                            ? 'Cancel Edit'
                                            : 'Edit Data'
                                    }}
                                </Button>
                            </div>
                            <form
                                v-if="showConstructionEdit"
                                class="grid gap-4 pt-2 sm:grid-cols-2"
                                @submit.prevent="submitAdminConstruction"
                            >
                                <div class="grid gap-1.5">
                                    <Label>Actual Start Date</Label>
                                    <Input
                                        v-model="
                                            adminConstructionForm.cons_actual_start_date
                                        "
                                        type="date"
                                    />
                                    <InputError
                                        :message="
                                            adminConstructionForm.errors
                                                .cons_actual_start_date
                                        "
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Actual Done Date</Label>
                                    <Input
                                        v-model="
                                            adminConstructionForm.cons_actual_done_date
                                        "
                                        type="date"
                                    />
                                    <InputError
                                        :message="
                                            adminConstructionForm.errors
                                                .cons_actual_done_date
                                        "
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Machine Serial Number</Label>
                                    <Input
                                        v-model="
                                            adminConstructionForm.machine_serial_number
                                        "
                                    />
                                    <InputError
                                        :message="
                                            adminConstructionForm.errors
                                                .machine_serial_number
                                        "
                                    />
                                </div>
                                <div class="grid gap-1.5 sm:col-span-2">
                                    <Label>Catatan Progres</Label>
                                    <textarea
                                        v-model="
                                            adminConstructionForm.catatan_progres
                                        "
                                        rows="3"
                                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    />
                                    <InputError
                                        :message="
                                            adminConstructionForm.errors
                                                .catatan_progres
                                        "
                                    />
                                </div>
                                <div class="flex justify-end sm:col-span-2">
                                    <Button
                                        type="submit"
                                        :disabled="
                                            adminConstructionForm.processing
                                        "
                                    >
                                        {{
                                            adminConstructionForm.processing
                                                ? 'Saving…'
                                                : 'Save Changes'
                                        }}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </CardContent>
                </Card>

                <!-- BAST -->
                <Card v-if="assignment.activity_type === 'BAST'">
                    <CardHeader
                        class="flex flex-row items-start justify-between gap-2"
                    >
                        <CardTitle>BAST Commissioning Data</CardTitle>
                        <a
                            v-if="
                                ['COMPLETED', 'VERIFIED', 'REPORTED'].includes(
                                    assignment.status,
                                )
                            "
                            :href="
                                AdminAssignmentActions.downloadBastReport(
                                    assignment,
                                ).url
                            "
                            target="_blank"
                        >
                            <Button variant="outline" size="sm" type="button">
                                <Download class="mr-1.5 h-4 w-4" />
                                Download Report
                            </Button>
                        </a>
                    </CardHeader>
                    <CardContent>
                        <div v-if="!bast" class="text-sm text-muted-foreground">
                            No BAST data submitted yet.
                        </div>
                        <div v-else class="flex flex-col gap-6">
                            <!-- Plant info summary -->
                            <div
                                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                            >
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        Plant Name
                                    </p>
                                    <p class="font-medium">
                                        {{ bast.plant_name ?? '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        Customer
                                    </p>
                                    <p class="font-medium">
                                        {{ bast.customer ?? '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        Charger Type
                                    </p>
                                    <p class="font-medium">
                                        {{ bast.charger_type ?? '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        Serial Number
                                    </p>
                                    <p class="font-medium">
                                        {{ bast.sn_unit ?? '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        ID PLN
                                    </p>
                                    <p class="font-medium">
                                        {{ bast.id_pln ?? '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        SIM Provider
                                    </p>
                                    <p class="font-medium">
                                        {{ bast.sim_provider ?? '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        Installation Date
                                    </p>
                                    <p class="font-medium">
                                        {{
                                            formatDateOnly(
                                                bast.installation_date,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        Commissioning Date
                                    </p>
                                    <p class="font-medium">
                                        {{
                                            formatDateOnly(
                                                bast.commissioning_date,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        Photos uploaded
                                    </p>
                                    <p class="font-medium">
                                        {{ bast.bast_photos?.length ?? 0 }}
                                    </p>
                                </div>
                            </div>

                            <!-- Photo gallery grouped by section -->
                            <div
                                v-for="sectionKey in BAST_SECTION_ORDER"
                                v-show="bastPhotosBySection[sectionKey]?.length"
                                :key="sectionKey"
                                class="flex flex-col gap-3"
                            >
                                <h3
                                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    {{
                                        BAST_SECTION_LABELS[sectionKey] ??
                                        sectionKey
                                    }}
                                </h3>
                                <div
                                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
                                >
                                    <div
                                        v-for="photo in bastPhotosBySection[
                                            sectionKey
                                        ]"
                                        :key="photo.id"
                                        class="flex flex-col gap-1"
                                    >
                                        <a
                                            :href="storageUrl(photo.photo_path)"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="block overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                                        >
                                            <img
                                                :src="
                                                    storageUrl(photo.photo_path)
                                                "
                                                :alt="
                                                    BAST_CHECKPOINT_LABELS[
                                                        photo.checkpoint_key
                                                    ] ?? photo.checkpoint_key
                                                "
                                                class="aspect-square w-full object-cover transition-opacity hover:opacity-80"
                                            />
                                        </a>
                                        <p
                                            class="text-[11px] leading-tight text-muted-foreground"
                                        >
                                            {{
                                                BAST_CHECKPOINT_LABELS[
                                                    photo.checkpoint_key
                                                ] ?? photo.checkpoint_key
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="bast.bast_photos?.length === 0"
                                class="rounded-lg border border-dashed border-sidebar-border/70 py-8 text-center text-sm text-muted-foreground dark:border-sidebar-border"
                            >
                                No photos submitted yet.
                            </div>
                        </div>
                        <Separator class="my-2" />
                        <div class="flex justify-end">
                            <Button
                                variant="ghost"
                                size="sm"
                                type="button"
                                @click="showBastEdit = !showBastEdit"
                            >
                                <Pencil class="size-3.5" />
                                {{ showBastEdit ? 'Cancel Edit' : 'Edit Data' }}
                            </Button>
                        </div>
                        <form
                            v-if="showBastEdit"
                            class="grid gap-4 pt-2 sm:grid-cols-2"
                            @submit.prevent="submitAdminBast"
                        >
                            <div class="grid gap-1.5">
                                <Label>Plant Name</Label>
                                <Input v-model="adminBastForm.plant_name" />
                                <InputError
                                    :message="adminBastForm.errors.plant_name"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Customer</Label>
                                <Input v-model="adminBastForm.customer" />
                                <InputError
                                    :message="adminBastForm.errors.customer"
                                />
                            </div>
                            <div class="grid gap-1.5 sm:col-span-2">
                                <Label>Plant Address</Label>
                                <textarea
                                    v-model="adminBastForm.plant_address"
                                    rows="2"
                                    class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError
                                    :message="
                                        adminBastForm.errors.plant_address
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Plant Coordinate</Label>
                                <Input
                                    v-model="adminBastForm.plant_coordinate"
                                />
                                <InputError
                                    :message="
                                        adminBastForm.errors.plant_coordinate
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Google Maps Link</Label>
                                <Input v-model="adminBastForm.gmaps_link" />
                                <InputError
                                    :message="adminBastForm.errors.gmaps_link"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Charger Type</Label>
                                <Input v-model="adminBastForm.charger_type" />
                                <InputError
                                    :message="adminBastForm.errors.charger_type"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Serial Number (Unit)</Label>
                                <Input v-model="adminBastForm.sn_unit" />
                                <InputError
                                    :message="adminBastForm.errors.sn_unit"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>ID PLN</Label>
                                <Input v-model="adminBastForm.id_pln" />
                                <InputError
                                    :message="adminBastForm.errors.id_pln"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>SIM Provider</Label>
                                <Input v-model="adminBastForm.sim_provider" />
                                <InputError
                                    :message="adminBastForm.errors.sim_provider"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Installation Vendor</Label>
                                <Input
                                    v-model="adminBastForm.installation_vendor"
                                />
                                <InputError
                                    :message="
                                        adminBastForm.errors.installation_vendor
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>PIC Vendor Contact</Label>
                                <Input
                                    v-model="adminBastForm.pic_vendor_contact"
                                />
                                <InputError
                                    :message="
                                        adminBastForm.errors.pic_vendor_contact
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Installation Date</Label>
                                <Input
                                    v-model="adminBastForm.installation_date"
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        adminBastForm.errors.installation_date
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Commissioning Date</Label>
                                <Input
                                    v-model="adminBastForm.commissioning_date"
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        adminBastForm.errors.commissioning_date
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Nomor SIM Card</Label>
                                <Input v-model="adminBastForm.nomor_simcard" />
                                <InputError
                                    :message="
                                        adminBastForm.errors.nomor_simcard
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Go Live Date PLN Pass</Label>
                                <Input
                                    v-model="
                                        adminBastForm.go_live_date_pln_pass
                                    "
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        adminBastForm.errors
                                            .go_live_date_pln_pass
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Go Live Date PLN</Label>
                                <Input
                                    v-model="adminBastForm.go_live_date_pln"
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        adminBastForm.errors.go_live_date_pln
                                    "
                                />
                            </div>
                            <div class="flex justify-end sm:col-span-2">
                                <Button
                                    type="submit"
                                    :disabled="adminBastForm.processing"
                                >
                                    {{
                                        adminBastForm.processing
                                            ? 'Saving…'
                                            : 'Save Changes'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <!-- Right column: Admin actions and prerequisite -->
            <div
                class="flex flex-col gap-6 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:self-start lg:overflow-y-auto"
            >
                <Card>
                    <CardHeader>
                        <CardTitle>Verification</CardTitle>
                        <CardDescription>
                            Review the submitted data and decide the next step.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <div
                            v-if="canVerify || assignment.status === 'REVISION'"
                            class="flex flex-col gap-2"
                        >
                            <Button
                                type="button"
                                class="w-full"
                                :disabled="!canVerify"
                                @click="openVerifyDialog"
                            >
                                <CheckCircle2 class="size-4" />
                                Verify Assignment
                            </Button>
                            <p
                                v-if="assignment.status === 'REVISION'"
                                class="text-xs text-muted-foreground"
                            >
                                Verification will be enabled once the
                                sub-contractor resubmits.
                            </p>
                            <Button
                                v-if="canRevise"
                                type="button"
                                variant="outline"
                                class="w-full"
                                @click="openReviseDialog"
                            >
                                <RotateCw class="size-4" />
                                Send for Revision
                            </Button>
                        </div>

                        <div
                            v-else-if="assignment.status === 'VERIFIED'"
                            class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm dark:border-emerald-800 dark:bg-emerald-900/20"
                        >
                            <div
                                class="flex items-center gap-2 font-medium text-emerald-800 dark:text-emerald-200"
                            >
                                <BadgeCheck class="size-4" />
                                Verified
                            </div>
                            <p
                                class="mt-1 text-xs text-emerald-800/90 dark:text-emerald-200/90"
                            >
                                {{ formatDate(assignment.verified_at) }}
                            </p>
                            <p
                                v-if="assignment.verified_by"
                                class="mt-1 text-xs text-emerald-800/90 dark:text-emerald-200/90"
                            >
                                by {{ assignment.verified_by.name }}
                            </p>
                        </div>

                        <div
                            v-else-if="assignment.status === 'REPORTED'"
                            class="rounded-md border border-purple-200 bg-purple-50 p-3 text-sm dark:border-purple-800 dark:bg-purple-900/20"
                        >
                            <div
                                class="flex items-center gap-2 font-medium text-purple-800 dark:text-purple-200"
                            >
                                <CalendarClock class="size-4" />
                                Reported
                            </div>
                            <p
                                class="mt-1 text-xs text-purple-800/90 dark:text-purple-200/90"
                            >
                                {{ formatDate(assignment.reported_at) }}
                            </p>
                        </div>

                        <div
                            v-else-if="isDropped"
                            class="rounded-md border border-red-200 bg-red-50 p-3 text-sm dark:border-red-800 dark:bg-red-900/20"
                        >
                            <div
                                class="flex items-center gap-2 font-medium text-red-800 dark:text-red-200"
                            >
                                <AlertTriangle class="size-4" />
                                Dropped
                            </div>
                            <p
                                class="mt-1 text-xs text-red-800/90 dark:text-red-200/90"
                            >
                                This assignment has been archived.
                            </p>
                        </div>

                        <p v-else class="text-sm text-muted-foreground">
                            Awaiting submission from the sub-contractor.
                        </p>

                        <Separator />

                        <div class="flex flex-col gap-2">
                            <Button
                                v-if="!isDropped"
                                type="button"
                                variant="destructive"
                                class="w-full"
                                @click="dropDialogOpen = true"
                            >
                                Archive (Drop) Assignment
                            </Button>
                            <Button
                                v-if="isDropped"
                                type="button"
                                variant="outline"
                                class="w-full"
                                :disabled="restoreForm.processing"
                                @click="submitRestore"
                            >
                                <RefreshCw class="size-4" />
                                Restore Assignment
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="isConstruction">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            Work Order (Admin)
                            <span
                                v-if="isWoMissing"
                                class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                            >
                                <LockKeyhole class="size-3" />
                                Locked
                            </span>
                        </CardTitle>
                        <CardDescription>
                            Sub-contractor cannot submit construction data until
                            the WO Number is set.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            class="flex flex-col gap-4"
                            @submit.prevent="submitPrerequisite"
                        >
                            <div class="grid gap-1.5">
                                <Label for="cons_wo_number"
                                    >WO Number
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                >
                                <Input
                                    id="cons_wo_number"
                                    v-model="prereqForm.cons_wo_number"
                                    type="text"
                                    placeholder="e.g. WO-2025-0001"
                                    required
                                />
                                <InputError
                                    :message="prereqForm.errors.cons_wo_number"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="project_status"
                                    >Project Status</Label
                                >
                                <Input
                                    id="project_status"
                                    v-model="prereqForm.project_status"
                                    type="text"
                                    placeholder="e.g. In Progress"
                                />
                                <InputError
                                    :message="prereqForm.errors.project_status"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="setup_approval_date"
                                    >Setup Approval Date</Label
                                >
                                <Input
                                    id="setup_approval_date"
                                    v-model="prereqForm.setup_approval_date"
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        prereqForm.errors.setup_approval_date
                                    "
                                />
                            </div>
                            <Button
                                type="submit"
                                :disabled="prereqForm.processing"
                            >
                                Save Prerequisite
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Audit Log -->
        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
        >
            <div
                class="flex items-center gap-2 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <ClipboardList class="size-4 text-muted-foreground" />
                <h2 class="text-sm font-semibold">Audit Log</h2>
                <span
                    v-if="auditLogs"
                    class="ml-auto text-xs text-muted-foreground"
                    >{{ auditLogs.length }} event(s)</span
                >
            </div>

            <template v-if="auditLogs != null">
                <div
                    v-if="auditLogs.length === 0"
                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    No audit events recorded.
                </div>
                <ul
                    v-else
                    class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                >
                    <li
                        v-for="log in auditLogs"
                        :key="log.id"
                        class="flex items-start gap-3 px-4 py-3 text-sm"
                    >
                        <span
                            class="mt-0.5 size-2 shrink-0 rounded-full bg-muted-foreground/40 ring-4 ring-muted/30"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="font-medium capitalize">{{
                                    log.event.replace(/_/g, ' ')
                                }}</span>
                                <span
                                    v-if="log.user"
                                    class="text-muted-foreground"
                                    >by {{ log.user.name }}</span
                                >
                            </div>
                            <div
                                v-if="
                                    log.payload &&
                                    Object.keys(log.payload).length
                                "
                                class="mt-1 rounded-md bg-muted/50 px-2 py-1 font-mono text-xs text-muted-foreground"
                            >
                                <span
                                    v-for="(val, key) in log.payload"
                                    :key="String(key)"
                                >
                                    {{ key }}: {{ String(val) }}
                                </span>
                            </div>
                        </div>
                        <span class="shrink-0 text-xs text-muted-foreground">
                            {{
                                log.created_at
                                    ? new Date(log.created_at).toLocaleString()
                                    : '—'
                            }}
                        </span>
                    </li>
                </ul>
            </template>
            <template v-else>
                <div class="flex flex-col gap-2 p-4">
                    <div
                        v-for="n in 3"
                        :key="n"
                        class="h-10 animate-pulse rounded bg-muted"
                    />
                </div>
            </template>
        </div>

        <!-- Verify Dialog -->
        <Dialog v-model:open="verifyOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Verify Assignment?</DialogTitle>
                    <DialogDescription>
                        This will mark the assignment as verified. The
                        sub-contractor will no longer be able to edit it.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="verifyOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        :disabled="verifyForm.processing"
                        @click="submitVerify"
                    >
                        <CheckCircle2 class="size-4" />
                        Confirm Verify
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Revise Dialog -->
        <Dialog v-model:open="reviseOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Send for Revision</DialogTitle>
                    <DialogDescription>
                        Provide a clear reason. The sub-contractor will receive
                        this comment.
                    </DialogDescription>
                </DialogHeader>
                <form
                    class="flex flex-col gap-3"
                    @submit.prevent="submitRevise"
                >
                    <div class="grid gap-1.5">
                        <Label for="revision_comment">Revision Comment</Label>
                        <textarea
                            id="revision_comment"
                            v-model="reviseForm.revision_comment"
                            rows="4"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            placeholder="Describe what needs to be revised..."
                            required
                        />
                        <InputError
                            :message="reviseForm.errors.revision_comment"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="reviseOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="reviseForm.processing">
                            Send Revision Request
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Reassign Dialog -->
        <Dialog v-model:open="reassignOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reassign Sub-Contractor</DialogTitle>
                    <DialogDescription>
                        This will clear all submitted data and reset the
                        assignment to PENDING. The new sub-contractor must fill
                        everything from scratch.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-3 py-2">
                    <Label>New Sub-Contractor</Label>
                    <Select v-model="reassignForm.subcontractor_id">
                        <SelectTrigger>
                            <SelectValue placeholder="Select sub-contractor…" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="sub in subcontractors"
                                :key="sub.id"
                                :value="String(sub.id)"
                            >
                                {{ sub.name }} ({{ sub.code }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError
                        :message="reassignForm.errors.subcontractor_id"
                    />
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        @click="reassignOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="
                            reassignForm.processing ||
                            !reassignForm.subcontractor_id
                        "
                        @click="submitReassign"
                    >
                        Confirm Reassign
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Drop confirmation dialog -->
        <Dialog v-model:open="dropDialogOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Archive Assignment?</DialogTitle>
                    <DialogDescription>
                        This assignment will be marked as
                        <strong>DROPPED</strong> and hidden from the active
                        list. You can restore it later from this page.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="dropForm.processing"
                        @click="dropDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="dropForm.processing"
                        @click="confirmDrop"
                    >
                        {{
                            dropForm.processing
                                ? 'Archiving…'
                                : 'Archive Assignment'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
