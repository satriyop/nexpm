<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle, Lock } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import * as SubActions from '@/actions/App/Http/Controllers/Subcontractor/AssignmentController';
import ActivityTypeBadge from '@/components/ActivityTypeBadge.vue';
import FileUpload from '@/components/FileUpload.vue';
import InputError from '@/components/InputError.vue';
import PhotoUpload from '@/components/PhotoUpload.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import * as SubAssignmentIndex from '@/actions/App/Http/Controllers/Subcontractor/AssignmentController';
import type { Assignment } from '@/types';

const props = defineProps<{
    assignment: Assignment;
    isLocked: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My Assignments', href: SubAssignmentIndex.index().url },
            { title: 'Assignment Detail', href: '#' },
        ],
    },
});

const isReadOnly = computed(() =>
    props.assignment.status === 'VERIFIED' || props.assignment.status === 'REPORTED',
);

// ── Survey form ───────────────────────────────────────────────────────────────
const surveyForm = useForm({
    surveyor_name: props.assignment.survey_data?.surveyor_name ?? '',
    pic_location_name: props.assignment.survey_data?.pic_location_name ?? '',
    pic_location_phone: props.assignment.survey_data?.pic_location_phone ?? '',
    charger_type: props.assignment.survey_data?.charger_type ?? '',
    ss_schedule_date: props.assignment.survey_data?.ss_schedule_date ?? '',
    cable_pulling_type: props.assignment.survey_data?.cable_pulling_type ?? '',
    power_kva: props.assignment.survey_data?.power_kva ?? '',
    pln_network_type: props.assignment.survey_data?.pln_network_type ?? '',
    parking_slot: props.assignment.survey_data?.parking_slot ?? '',
    additional_info: props.assignment.survey_data?.additional_info ?? '',
    photo_overall_site: null as File | null,
    photo_parking_evcs: null as File | null,
    photo_other_angle: null as File | null,
    photo_pln_network: null as File | null,
    photo_satellite_gmaps: null as File | null,
    file_mockup_3d: null as File | null,
    file_ba_survey: null as File | null,
});

function submitSurvey() {
    surveyForm.post(SubActions.updateSurveyData(props.assignment).url, {
        forceFormData: true,
        onSuccess: () => surveyForm.reset('photo_overall_site', 'photo_parking_evcs', 'photo_other_angle', 'photo_pln_network', 'photo_satellite_gmaps', 'file_mockup_3d', 'file_ba_survey'),
    });
}

// ── PLN form ──────────────────────────────────────────────────────────────────
const plnForm = useForm({
    pln_status: props.assignment.pln_data?.pln_status ?? '',
    nidi_slo_date_acquired: props.assignment.pln_data?.nidi_slo_date_acquired ?? '',
    type_rate: props.assignment.pln_data?.type_rate ?? '',
    file_slo: null as File | null,
    file_nidi: null as File | null,
    file_reg: null as File | null,
    kwh_meter_installation_date: props.assignment.pln_data?.kwh_meter_installation_date ?? '',
    id_pelanggan: props.assignment.pln_data?.id_pelanggan ?? '',
    catatan_progres: props.assignment.pln_data?.catatan_progres ?? '',
});

function submitPln() {
    plnForm.post(SubActions.updatePlnData(props.assignment).url, {
        forceFormData: true,
        onSuccess: () => plnForm.reset('file_slo', 'file_nidi', 'file_reg'),
    });
}

// ── Construction form ─────────────────────────────────────────────────────────
const constructionForm = useForm({
    cons_actual_start_date: props.assignment.construction_data?.cons_actual_start_date ?? '',
    cons_actual_done_date: props.assignment.construction_data?.cons_actual_done_date ?? '',
    machine_serial_number: props.assignment.construction_data?.machine_serial_number ?? '',
    catatan_progres: props.assignment.construction_data?.catatan_progres ?? '',
});

function submitConstruction() {
    constructionForm.patch(SubActions.updateConstructionData(props.assignment).url);
}

const photoFile = ref<File | null>(null);
const uploadingPhoto = ref(false);
const constructionUploadKey = ref(0);

function uploadConstructionPhoto() {
    if (!photoFile.value) return;
    const form = useForm({ photo: photoFile.value });
    uploadingPhoto.value = true;
    form.post(SubActions.storeConstructionPhoto(props.assignment).url, {
        forceFormData: true,
        onFinish: () => {
            uploadingPhoto.value = false;
            photoFile.value = null;
            constructionUploadKey.value++;
        },
    });
}

function onConstructionPhotoSelected(file: File | null) {
    if (!file) return;
    photoFile.value = file;
    uploadConstructionPhoto();
}

function destroyConstructionPhoto(photoId: number) {
    const form = useForm({});
    form.delete(SubActions.destroyConstructionPhoto(props.assignment, photoId).url);
}

// ── BAST form ─────────────────────────────────────────────────────────────────
const siteTypeName = computed(() => props.assignment.site.site_type?.name ?? 'EVCS');
const isBss = computed(() => siteTypeName.value === 'BSS');

const bastForm = useForm({
    plant_name: props.assignment.bast_data?.plant_name ?? '',
    plant_address: props.assignment.bast_data?.plant_address ?? '',
    plant_coordinate: props.assignment.bast_data?.plant_coordinate ?? '',
    gmaps_link: props.assignment.bast_data?.gmaps_link ?? '',
    charger_type: props.assignment.bast_data?.charger_type ?? '',
    sn_unit: props.assignment.bast_data?.sn_unit ?? '',
    id_pln: props.assignment.bast_data?.id_pln ?? '',
    sim_provider: props.assignment.bast_data?.sim_provider ?? '',
    installation_vendor: props.assignment.bast_data?.installation_vendor ?? '',
    pic_vendor_contact: props.assignment.bast_data?.pic_vendor_contact ?? '',
    installation_date: props.assignment.bast_data?.installation_date ?? '',
    commissioning_date: props.assignment.bast_data?.commissioning_date ?? '',
    customer: props.assignment.bast_data?.customer ?? '',
    measurements: props.assignment.bast_data?.measurements ?? {},
    nomor_simcard: props.assignment.bast_data?.nomor_simcard ?? '',
    go_live_date_pln_pass: props.assignment.bast_data?.go_live_date_pln_pass ?? '',
    go_live_date_pln: props.assignment.bast_data?.go_live_date_pln ?? '',
});

function submitBast() {
    bastForm.patch(SubActions.updateBastData(props.assignment).url);
}

// Photo helpers
const bastPhotos = computed(() => props.assignment.bast_data?.bast_photos ?? []);

function getPhoto(checkpointKey: string) {
    return bastPhotos.value.find((p) => p.checkpoint_key === checkpointKey) ?? null;
}

const uploadingCheckpoint = ref<string | null>(null);

function uploadBastPhoto(section: string, checkpointKey: string, file: File) {
    uploadingCheckpoint.value = checkpointKey;
    const form = useForm({ section, checkpoint_key: checkpointKey, photo: file });
    form.post(SubActions.storeBastPhoto(props.assignment).url, {
        forceFormData: true,
        onFinish: () => { uploadingCheckpoint.value = null; },
    });
}

function destroyBastPhoto(photoId: number) {
    const form = useForm({});
    form.delete(SubActions.destroyBastPhoto(props.assignment, photoId).url);
}

interface Checkpoint { key: string; label: string; optional?: boolean }

const deviceCheckpoints = computed<Checkpoint[]>(() => isBss.value
    ? [
        { key: 'device_front_view_open', label: 'Front View (Open)' },
        { key: 'device_front_view_close', label: 'Front View (Close)' },
        { key: 'device_side_view_right', label: 'Side View (Right)' },
        { key: 'device_side_view_left', label: 'Side View (Left)' },
        { key: 'device_foundation_depth', label: 'Foundation at 40cm Depth', optional: true },
        { key: 'device_foundation_concrete', label: 'Concrete Foundation 20cm', optional: true },
        { key: 'device_sticker', label: 'Sticker', optional: true },
        { key: 'device_name_plate', label: 'Name Plate' },
        { key: 'device_ac_cable_termination', label: 'AC Cable Termination' },
        { key: 'device_grounding_termination', label: 'Grounding Termination' },
        { key: 'device_cable_entry_panel', label: 'AC-DC Cable Entry to Panel' },
        { key: 'device_visible_safety_sign', label: 'Visible Safety Sign' },
    ]
    : [
        { key: 'device_front_view_open', label: 'Front View (Open)' },
        { key: 'device_front_view_close', label: 'Front View (Close)' },
        { key: 'device_side_view_right', label: 'Side View (Right)' },
        { key: 'device_side_view_left', label: 'Side View (Left)' },
        { key: 'device_foundation_depth', label: 'Foundation at 40cm Depth' },
        { key: 'device_foundation_concrete', label: 'Concrete Foundation 20cm' },
        { key: 'device_parking_space', label: 'Parking Space' },
        { key: 'device_sticker', label: 'Sticker' },
        { key: 'device_name_plate', label: 'Name Plate' },
        { key: 'device_ac_cable_termination', label: 'AC Cable Termination' },
        { key: 'device_emergency_button_cover', label: 'Emergency Button Cover' },
        { key: 'device_grounding_termination', label: 'Grounding Termination' },
        { key: 'device_cable_entry_panel', label: 'AC-DC Cable Entry to Panel' },
        { key: 'device_visible_safety_sign', label: 'Visible Safety Sign' },
    ]
);

const simCheckpoints = computed<Checkpoint[]>(() => [
    { key: 'sim_kartu_perdana', label: 'Kartu Perdana' },
    { key: 'sim_installed_sim_card', label: 'Installed SIM Card' },
    { key: 'sim_signal_bar', label: 'Signal Bar' },
    { key: 'sim_url', label: 'URL' },
    ...(isBss.value ? [] : [{ key: 'sim_start_charge_immediately', label: 'Start Charge Immediately - OFF' }]),
    { key: 'sim_user_interface_lcd', label: 'User Interface (LCD)' },
]);

const groundingCheckpoints: Checkpoint[] = [
    { key: 'grounding_rod_connection', label: 'Grounding Rod Connection' },
    { key: 'grounding_connected_to_device', label: 'Grounding Connected to Device' },
    { key: 'grounding_rod_to_earth_1', label: 'Grounding Rod to Earth (Spot 1)' },
    { key: 'grounding_rod_to_earth_2', label: 'Grounding Rod to Earth (Spot 2)', optional: true },
    { key: 'grounding_busbar_panel', label: 'Grounding Busbar in Panel AC' },
    { key: 'grounding_cable_route', label: 'Grounding Cable Route' },
];

const fireExtCheckpoints: Checkpoint[] = [
    { key: 'fire_ext_front_view', label: 'Front View' },
    { key: 'fire_ext_pressure', label: 'Pressure' },
    { key: 'fire_ext_placement', label: 'Placement' },
    { key: 'fire_ext_specification', label: 'Specification' },
];

const kwhCheckpoints: Checkpoint[] = [
    { key: 'kwh_kwh_meter', label: 'KWH Meter' },
    { key: 'kwh_mcb_pln', label: 'MCB PLN' },
];

const acPanelCheckpoints: Checkpoint[] = [
    { key: 'ac_front_view_open', label: 'Front View (Open)' },
    { key: 'ac_front_view_close', label: 'Front View (Close)' },
    { key: 'ac_side_view_right', label: 'Side View (Right)' },
    { key: 'ac_side_view_left', label: 'Side View (Left)' },
    { key: 'ac_safety_sign', label: 'Safety Sign' },
    { key: 'ac_pilot_lamps', label: 'Pilot Lamps' },
    { key: 'ac_locking_system', label: 'Locking System' },
    { key: 'ac_mcb', label: 'MCB' },
    { key: 'ac_panel_wiring', label: 'Panel Wiring' },
    { key: 'ac_phase_marking', label: 'Phase Marking' },
];

const cableCheckpoints: Checkpoint[] = [
    { key: 'cable_spec', label: 'Cable Spec' },
    { key: 'cable_routing_1', label: 'Cable Routing (1)' },
    { key: 'cable_routing_2', label: 'Cable Routing (2)' },
    { key: 'cable_routing_3', label: 'Cable Routing (3)' },
];

function storageUrl(path: string) {
    if (!path) {
        return '#';
    }
    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }
    return `/storage/${path}`;
}
</script>

<template>
    <Head :title="`Assignment — ${assignment.site.site_code}`" />

    <div class="space-y-6 p-4">
        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">{{ assignment.site.location_name }}</h1>
                <p class="text-sm text-muted-foreground">{{ assignment.site.site_code }} · {{ assignment.site.city }}, {{ assignment.site.province }}</p>
            </div>
            <div class="flex items-center gap-2">
                <ActivityTypeBadge :activity-type="assignment.activity_type" />
                <StatusBadge :status="assignment.status" />
            </div>
        </div>

        <!-- Revision alert -->
        <Alert v-if="assignment.status === 'REVISION'" variant="destructive" class="border-amber-300 bg-amber-50 text-amber-800">
            <AlertTriangle class="h-4 w-4 !text-amber-600" />
            <AlertDescription class="space-y-2">
                <p class="font-semibold">Revision Requested</p>
                <p class="whitespace-pre-wrap text-sm leading-relaxed">{{ assignment.revision_comment }}</p>
            </AlertDescription>
        </Alert>

        <!-- Locked state -->
        <Alert v-if="isLocked" class="border-yellow-300 bg-yellow-50 text-yellow-800">
            <Lock class="h-4 w-4 !text-yellow-600" />
            <AlertDescription>
                This assignment is locked pending Work Order assignment from Admin. Please wait until the WO Number is assigned.
            </AlertDescription>
        </Alert>

        <!-- Verified / Reported state -->
        <Alert v-else-if="isReadOnly" class="border-green-300 bg-green-50 text-green-800">
            <CheckCircle class="h-4 w-4 !text-green-600" />
            <AlertDescription>
                This assignment has been <strong>{{ assignment.status === 'VERIFIED' ? 'verified' : 'reported' }}</strong>. No further changes can be made.
            </AlertDescription>
        </Alert>

        <!-- ── SURVEY FORM ───────────────────────────────────────────── -->
        <Card v-if="assignment.activity_type === 'SURVEY' && !isLocked">
            <CardHeader>
                <CardTitle>Survey Data</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="space-y-4" @submit.prevent="submitSurvey">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Surveyor Name</Label>
                            <Input v-model="surveyForm.surveyor_name" :disabled="isReadOnly" placeholder="Nama Surveyor" />
                            <InputError :message="surveyForm.errors.surveyor_name" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>PIC Location Name</Label>
                            <Input v-model="surveyForm.pic_location_name" :disabled="isReadOnly" placeholder="Nama PIC Lokasi" />
                            <InputError :message="surveyForm.errors.pic_location_name" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>PIC Location Phone</Label>
                            <Input v-model="surveyForm.pic_location_phone" :disabled="isReadOnly" placeholder="No. HP PIC" />
                            <InputError :message="surveyForm.errors.pic_location_phone" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Charger Type</Label>
                            <Select v-model="surveyForm.charger_type" :disabled="isReadOnly">
                                <SelectTrigger><SelectValue placeholder="Select type" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="EVCS">EVCS</SelectItem>
                                    <SelectItem value="BSS">BSS</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="surveyForm.errors.charger_type" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>SS Schedule Date (w/ Landlord)</Label>
                            <Input v-model="surveyForm.ss_schedule_date" type="date" :disabled="isReadOnly" />
                            <InputError :message="surveyForm.errors.ss_schedule_date" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Cable Pulling Type</Label>
                            <Select v-model="surveyForm.cable_pulling_type" :disabled="isReadOnly">
                                <SelectTrigger><SelectValue placeholder="Select type" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="New Power">New Power</SelectItem>
                                    <SelectItem value="Existing Power">Existing Power</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="surveyForm.errors.cable_pulling_type" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Power / Daya (kVA)</Label>
                            <Select v-model="surveyForm.power_kva" :disabled="isReadOnly">
                                <SelectTrigger><SelectValue placeholder="Select power" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="7.7kVA">7.7 kVA</SelectItem>
                                    <SelectItem value="22kVA">22 kVA</SelectItem>
                                    <SelectItem value="50kVA">50 kVA</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="surveyForm.errors.power_kva" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>PLN Network Type</Label>
                            <Select v-model="surveyForm.pln_network_type" :disabled="isReadOnly">
                                <SelectTrigger><SelectValue placeholder="Select phase" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1 Phase">1 Phase</SelectItem>
                                    <SelectItem value="3 Phase">3 Phase</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="surveyForm.errors.pln_network_type" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Parking Slot</Label>
                            <Input v-model="surveyForm.parking_slot" :disabled="isReadOnly" placeholder="e.g. B1-12" />
                            <InputError :message="surveyForm.errors.parking_slot" />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label>Additional Information</Label>
                        <textarea
                            v-model="surveyForm.additional_info"
                            :disabled="isReadOnly"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Tambahan Informasi Lain-Lain"
                        />
                    </div>

                    <Separator />
                    <p class="text-sm font-medium text-muted-foreground">Photos & Files</p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <template v-for="field in [
                            { key: 'photo_overall_site', label: 'Foto Tampak Keseluruhan Site', isImage: true },
                            { key: 'photo_parking_evcs', label: 'Foto Lahan Parkir EVCS / Lokasi BSS', isImage: true },
                            { key: 'photo_other_angle', label: 'Foto Lahan Sudut Pandang Lain', isImage: true },
                            { key: 'photo_pln_network', label: 'Foto Jaringan PLN Terdekat', isImage: true },
                            { key: 'photo_satellite_gmaps', label: 'Foto Satelit GMaps', isImage: true },
                            { key: 'file_mockup_3d', label: 'Mock Up 3D', isImage: false },
                            { key: 'file_ba_survey', label: 'BA Survey', isImage: false },
                        ]" :key="field.key">
                            <div class="grid gap-1.5">
                                <Label>{{ field.label }}</Label>
                                <PhotoUpload
                                    v-if="field.isImage"
                                    :model-value="(surveyForm as any)[field.key]"
                                    :current-url="assignment.survey_data?.[field.key as keyof typeof assignment.survey_data] ? storageUrl(String(assignment.survey_data[field.key as keyof typeof assignment.survey_data])) : null"
                                    :readonly="isReadOnly"
                                    @update:model-value="(surveyForm as any)[field.key] = $event"
                                />
                                <FileUpload
                                    v-else
                                    :model-value="(surveyForm as any)[field.key]"
                                    :current-url="assignment.survey_data?.[field.key as keyof typeof assignment.survey_data] ? storageUrl(String(assignment.survey_data[field.key as keyof typeof assignment.survey_data])) : null"
                                    accept=".pdf,.doc,.docx,image/*"
                                    :readonly="isReadOnly"
                                    @update:model-value="(surveyForm as any)[field.key] = $event"
                                />
                                <InputError :message="(surveyForm.errors as any)[field.key]" />
                            </div>
                        </template>
                    </div>

                    <div v-if="!isReadOnly" class="flex justify-end pt-2">
                        <Button type="submit" :disabled="surveyForm.processing">
                            {{ surveyForm.processing ? 'Saving…' : 'Save Survey Data' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- ── PLN CONNECTION FORM ──────────────────────────────────── -->
        <Card v-else-if="assignment.activity_type === 'PLN_CONNECTION' && !isLocked">
            <CardHeader>
                <CardTitle>PLN Connection Data</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="space-y-4" @submit.prevent="submitPln">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>PLN Status</Label>
                            <Select v-model="plnForm.pln_status" :disabled="isReadOnly">
                                <SelectTrigger><SelectValue placeholder="Select status" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="DONE KWH">Done KWH</SelectItem>
                                    <SelectItem value="NOT YET REGISTERED">Not Yet Registered</SelectItem>
                                    <SelectItem value="IN PROGRESS">In Progress</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="plnForm.errors.pln_status" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>NIDI SLO Date Acquired</Label>
                            <Input v-model="plnForm.nidi_slo_date_acquired" type="date" :disabled="isReadOnly" />
                            <InputError :message="plnForm.errors.nidi_slo_date_acquired" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Type Rate</Label>
                            <Input v-model="plnForm.type_rate" :disabled="isReadOnly" placeholder="Type Rate" />
                            <InputError :message="plnForm.errors.type_rate" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>kWh Meter Installation Date</Label>
                            <Input v-model="plnForm.kwh_meter_installation_date" type="date" :disabled="isReadOnly" />
                            <InputError :message="plnForm.errors.kwh_meter_installation_date" />
                        </div>
                        <div class="grid gap-1.5 sm:col-span-2">
                            <Label>ID Pelanggan (ID PLN)</Label>
                            <Input v-model="plnForm.id_pelanggan" :disabled="isReadOnly" placeholder="ID Pelanggan" />
                            <InputError :message="plnForm.errors.id_pelanggan" />
                        </div>
                    </div>

                    <Separator />
                    <p class="text-sm font-medium text-muted-foreground">Document Uploads</p>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <template v-for="field in [
                            { key: 'file_slo', label: 'File SLO' },
                            { key: 'file_nidi', label: 'File NIDI' },
                            { key: 'file_reg', label: 'File Reg' },
                        ]" :key="field.key">
                            <div class="grid gap-1.5">
                                <Label>{{ field.label }}</Label>
                                <FileUpload
                                    :model-value="(plnForm as any)[field.key]"
                                    :current-url="assignment.pln_data?.[field.key as keyof typeof assignment.pln_data] ? storageUrl(String(assignment.pln_data[field.key as keyof typeof assignment.pln_data])) : null"
                                    accept=".pdf,.doc,.docx,image/*"
                                    :readonly="isReadOnly"
                                    @update:model-value="(plnForm as any)[field.key] = $event"
                                />
                                <InputError :message="(plnForm.errors as any)[field.key]" />
                            </div>
                        </template>
                    </div>

                    <div class="grid gap-1.5">
                        <Label>Catatan Progres</Label>
                        <textarea
                            v-model="plnForm.catatan_progres"
                            :disabled="isReadOnly"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Progress notes…"
                        />
                    </div>

                    <div v-if="!isReadOnly" class="flex justify-end pt-2">
                        <Button type="submit" :disabled="plnForm.processing">
                            {{ plnForm.processing ? 'Saving…' : 'Save PLN Data' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- ── CONSTRUCTION FORM ────────────────────────────────────── -->
        <template v-else-if="assignment.activity_type === 'CONSTRUCTION' && !isLocked">
            <!-- Admin prerequisite (read-only) -->
            <Card class="border-muted bg-muted/30">
                <CardHeader>
                    <CardTitle class="text-base text-muted-foreground">Work Order (filled by Admin)</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-3 sm:grid-cols-3 text-sm">
                        <div>
                            <dt class="font-medium text-muted-foreground">WO Number</dt>
                            <dd>{{ assignment.construction_data?.cons_wo_number ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-muted-foreground">Project Status</dt>
                            <dd>{{ assignment.construction_data?.project_status ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-muted-foreground">Setup Approval Date</dt>
                            <dd>{{ assignment.construction_data?.setup_approval_date ?? '—' }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <!-- Sub-con fields -->
            <Card>
                <CardHeader>
                    <CardTitle>Construction Data</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitConstruction">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label>Actual Start Date</Label>
                                <Input v-model="constructionForm.cons_actual_start_date" type="date" :disabled="isReadOnly" />
                                <InputError :message="constructionForm.errors.cons_actual_start_date" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Actual Done Date</Label>
                                <Input v-model="constructionForm.cons_actual_done_date" type="date" :disabled="isReadOnly" />
                                <InputError :message="constructionForm.errors.cons_actual_done_date" />
                            </div>
                            <div class="grid gap-1.5 sm:col-span-2">
                                <Label>Machine Serial Number</Label>
                                <Input v-model="constructionForm.machine_serial_number" :disabled="isReadOnly" placeholder="Machine SN" />
                                <InputError :message="constructionForm.errors.machine_serial_number" />
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Catatan Progres</Label>
                            <textarea
                                v-model="constructionForm.catatan_progres"
                                :disabled="isReadOnly"
                                rows="3"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Progress notes…"
                            />
                        </div>
                        <div v-if="!isReadOnly" class="flex justify-end">
                            <Button type="submit" :disabled="constructionForm.processing">
                                {{ constructionForm.processing ? 'Saving…' : 'Save Construction Data' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Progress photos -->
            <Card>
                <CardHeader>
                    <CardTitle>Progress Photos</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="assignment.construction_data?.construction_photos?.length" class="flex flex-wrap gap-3">
                        <div
                            v-for="photo in assignment.construction_data.construction_photos"
                            :key="photo.id"
                            class="relative"
                        >
                            <img
                                :src="storageUrl(photo.path)"
                                class="h-24 w-24 rounded object-cover border"
                                alt="Progress photo"
                            />
                            <Button
                                v-if="!isReadOnly"
                                type="button"
                                variant="destructive"
                                size="sm"
                                class="absolute right-1 top-1 h-6 px-2 text-xs"
                                @click="destroyConstructionPhoto(photo.id)"
                            >×</Button>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">No photos uploaded yet.</p>

                    <div v-if="!isReadOnly" class="grid gap-1.5">
                        <Label>Upload Progress Photo</Label>
                        <PhotoUpload
                            :key="constructionUploadKey"
                            :model-value="null"
                            :uploading="uploadingPhoto"
                            @update:model-value="onConstructionPhotoSelected"
                        />
                    </div>
                </CardContent>
            </Card>
        </template>

        <!-- ── BAST FORM ─────────────────────────────────────────────── -->
        <template v-else-if="assignment.activity_type === 'BAST' && !isLocked">

            <!-- Cover / Plant Info -->
            <Card>
                <CardHeader>
                    <CardTitle>Plant Information <span class="ml-2 text-sm font-normal text-muted-foreground">({{ siteTypeName }} Commissioning Report)</span></CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitBast">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1.5 sm:col-span-2">
                                <Label>Plant Name</Label>
                                <Input v-model="bastForm.plant_name" :disabled="isReadOnly" placeholder="Nama Plant" />
                                <InputError :message="bastForm.errors.plant_name" />
                            </div>
                            <div class="grid gap-1.5 sm:col-span-2">
                                <Label>Plant Address</Label>
                                <textarea v-model="bastForm.plant_address" :disabled="isReadOnly" rows="2"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    placeholder="Alamat Plant" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Plant Coordinate</Label>
                                <Input v-model="bastForm.plant_coordinate" :disabled="isReadOnly" placeholder="-6.123, 106.456" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>GMaps Link</Label>
                                <Input v-model="bastForm.gmaps_link" :disabled="isReadOnly" placeholder="https://maps.google.com/..." />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>{{ isBss ? 'BSS' : 'EV Charger' }} Type</Label>
                                <Input v-model="bastForm.charger_type" :disabled="isReadOnly" placeholder="Type / Model" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Serial Number Unit</Label>
                                <Input v-model="bastForm.sn_unit" :disabled="isReadOnly" placeholder="S/N" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>ID PLN / Kode Billing</Label>
                                <Input v-model="bastForm.id_pln" :disabled="isReadOnly" placeholder="ID PLN" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Provider / No SIM</Label>
                                <Input v-model="bastForm.sim_provider" :disabled="isReadOnly" placeholder="Telkomsel / 0812-xxx" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Installation Vendor</Label>
                                <Input v-model="bastForm.installation_vendor" :disabled="isReadOnly" placeholder="Nama Vendor" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>PIC Vendor Contact</Label>
                                <Input v-model="bastForm.pic_vendor_contact" :disabled="isReadOnly" placeholder="Nama / No. HP" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Installation Date</Label>
                                <Input v-model="bastForm.installation_date" type="date" :disabled="isReadOnly" />
                                <InputError :message="bastForm.errors.installation_date" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Commissioning Date</Label>
                                <Input v-model="bastForm.commissioning_date" type="date" :disabled="isReadOnly" />
                                <InputError :message="bastForm.errors.commissioning_date" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Customer</Label>
                                <Input v-model="bastForm.customer" :disabled="isReadOnly" placeholder="Nama Customer" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Nomor SIM Card</Label>
                                <Input v-model="bastForm.nomor_simcard" :disabled="isReadOnly" placeholder="Nomor SIM Card" />
                                <InputError :message="bastForm.errors.nomor_simcard" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Go LIVE Date (PLN Bypass)</Label>
                                <Input v-model="bastForm.go_live_date_pln_pass" type="date" :disabled="isReadOnly" />
                                <InputError :message="bastForm.errors.go_live_date_pln_pass" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Go LIVE Date (PLN)</Label>
                                <Input v-model="bastForm.go_live_date_pln" type="date" :disabled="isReadOnly" />
                                <InputError :message="bastForm.errors.go_live_date_pln" />
                            </div>
                        </div>
                        <div v-if="!isReadOnly" class="flex justify-end pt-2">
                            <Button type="submit" :disabled="bastForm.processing">
                                {{ bastForm.processing ? 'Saving…' : 'Save Plant Info' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Measurements -->
            <Card>
                <CardHeader>
                    <CardTitle>Measurements</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitBast">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="grid gap-1.5">
                                <Label>Grounding Resistance (Ω)</Label>
                                <Input v-model="bastForm.measurements['grounding_resistance']" :disabled="isReadOnly" placeholder="e.g. 0.5" />
                            </div>
                            <!-- BSS: 1-phase voltage fields -->
                            <template v-if="isBss">
                                <div class="grid gap-1.5">
                                    <Label>Voltage R-N (V)</Label>
                                    <Input v-model="bastForm.measurements['voltage_rn']" :disabled="isReadOnly" placeholder="220" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Voltage R-G (V)</Label>
                                    <Input v-model="bastForm.measurements['voltage_rg']" :disabled="isReadOnly" placeholder="220" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Voltage N-G (V)</Label>
                                    <Input v-model="bastForm.measurements['voltage_ng']" :disabled="isReadOnly" placeholder="0" />
                                </div>
                            </template>
                            <!-- EVCS: 3-phase voltage fields -->
                            <template v-else>
                                <div class="grid gap-1.5">
                                    <Label>Voltage R-S (V)</Label>
                                    <Input v-model="bastForm.measurements['voltage_rs']" :disabled="isReadOnly" placeholder="380" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Voltage R-T (V)</Label>
                                    <Input v-model="bastForm.measurements['voltage_rt']" :disabled="isReadOnly" placeholder="380" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Voltage S-T (V)</Label>
                                    <Input v-model="bastForm.measurements['voltage_st']" :disabled="isReadOnly" placeholder="380" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Voltage N-G (V)</Label>
                                    <Input v-model="bastForm.measurements['voltage_ng']" :disabled="isReadOnly" placeholder="0" />
                                </div>
                            </template>
                            <div class="grid gap-1.5">
                                <Label>Frequency (Hz)</Label>
                                <Input v-model="bastForm.measurements['frequency']" :disabled="isReadOnly" placeholder="50" />
                            </div>
                        </div>
                        <div v-if="!isReadOnly" class="flex justify-end pt-2">
                            <Button type="submit" :disabled="bastForm.processing">
                                {{ bastForm.processing ? 'Saving…' : 'Save Measurements' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Photo checkpoint section helper component inlined -->
            <!-- Section 1: Device -->
            <Card>
                <CardHeader>
                    <CardTitle>1. {{ isBss ? 'Battery Swap Station' : 'EV Charger' }} — Visual Inspection</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="cp in deviceCheckpoints" :key="cp.key" class="grid gap-1.5">
                            <Label class="flex items-center gap-1">
                                {{ cp.label }}
                                <span v-if="cp.optional" class="text-xs text-muted-foreground">(optional)</span>
                            </Label>
                            <PhotoUpload
                                :model-value="null"
                                :current-url="getPhoto(cp.key) ? storageUrl(getPhoto(cp.key)!.photo_path) : null"
                                :uploading="uploadingCheckpoint === cp.key"
                                :readonly="isReadOnly"
                                :deletable="true"
                                @update:model-value="(file) => file && uploadBastPhoto('device', cp.key, file)"
                                @delete="destroyBastPhoto(getPhoto(cp.key)!.id)"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Section 2: SIM Card -->
            <Card>
                <CardHeader><CardTitle>2. SIM Card — Visual Inspection</CardTitle></CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="cp in simCheckpoints" :key="cp.key" class="grid gap-1.5">
                            <Label>{{ cp.label }}</Label>
                            <PhotoUpload
                                :model-value="null"
                                :current-url="getPhoto(cp.key) ? storageUrl(getPhoto(cp.key)!.photo_path) : null"
                                :uploading="uploadingCheckpoint === cp.key"
                                :readonly="isReadOnly"
                                :deletable="true"
                                @update:model-value="(file) => file && uploadBastPhoto('sim_card', cp.key, file)"
                                @delete="destroyBastPhoto(getPhoto(cp.key)!.id)"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Section 3: Grounding -->
            <Card>
                <CardHeader><CardTitle>3. Grounding System — Visual Inspection</CardTitle></CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="cp in groundingCheckpoints" :key="cp.key" class="grid gap-1.5">
                            <Label class="flex items-center gap-1">
                                {{ cp.label }}
                                <span v-if="cp.optional" class="text-xs text-muted-foreground">(optional)</span>
                            </Label>
                            <PhotoUpload
                                :model-value="null"
                                :current-url="getPhoto(cp.key) ? storageUrl(getPhoto(cp.key)!.photo_path) : null"
                                :uploading="uploadingCheckpoint === cp.key"
                                :readonly="isReadOnly"
                                :deletable="true"
                                @update:model-value="(file) => file && uploadBastPhoto('grounding', cp.key, file)"
                                @delete="destroyBastPhoto(getPhoto(cp.key)!.id)"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Section 4: Fire Extinguisher (EVCS only) -->
            <Card v-if="!isBss">
                <CardHeader><CardTitle>4. Fire Extinguisher — Visual Inspection</CardTitle></CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="cp in fireExtCheckpoints" :key="cp.key" class="grid gap-1.5">
                            <Label>{{ cp.label }}</Label>
                            <PhotoUpload
                                :model-value="null"
                                :current-url="getPhoto(cp.key) ? storageUrl(getPhoto(cp.key)!.photo_path) : null"
                                :uploading="uploadingCheckpoint === cp.key"
                                :readonly="isReadOnly"
                                :deletable="true"
                                @update:model-value="(file) => file && uploadBastPhoto('fire_extinguisher', cp.key, file)"
                                @delete="destroyBastPhoto(getPhoto(cp.key)!.id)"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Section 5: KWH Meter -->
            <Card>
                <CardHeader><CardTitle>5. KWH Meter — Visual Inspection</CardTitle></CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="cp in kwhCheckpoints" :key="cp.key" class="grid gap-1.5">
                            <Label>{{ cp.label }}</Label>
                            <PhotoUpload
                                :model-value="null"
                                :current-url="getPhoto(cp.key) ? storageUrl(getPhoto(cp.key)!.photo_path) : null"
                                :uploading="uploadingCheckpoint === cp.key"
                                :readonly="isReadOnly"
                                :deletable="true"
                                @update:model-value="(file) => file && uploadBastPhoto('kwh_meter', cp.key, file)"
                                @delete="destroyBastPhoto(getPhoto(cp.key)!.id)"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Section 6: AC Panel -->
            <Card>
                <CardHeader><CardTitle>6. AC Panel — Visual Inspection</CardTitle></CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="cp in acPanelCheckpoints" :key="cp.key" class="grid gap-1.5">
                            <Label>{{ cp.label }}</Label>
                            <PhotoUpload
                                :model-value="null"
                                :current-url="getPhoto(cp.key) ? storageUrl(getPhoto(cp.key)!.photo_path) : null"
                                :uploading="uploadingCheckpoint === cp.key"
                                :readonly="isReadOnly"
                                :deletable="true"
                                @update:model-value="(file) => file && uploadBastPhoto('ac_panel', cp.key, file)"
                                @delete="destroyBastPhoto(getPhoto(cp.key)!.id)"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Cables -->
            <Card>
                <CardHeader><CardTitle>Cables — Visual Inspection</CardTitle></CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="cp in cableCheckpoints" :key="cp.key" class="grid gap-1.5">
                            <Label>{{ cp.label }}</Label>
                            <PhotoUpload
                                :model-value="null"
                                :current-url="getPhoto(cp.key) ? storageUrl(getPhoto(cp.key)!.photo_path) : null"
                                :uploading="uploadingCheckpoint === cp.key"
                                :readonly="isReadOnly"
                                :deletable="true"
                                @update:model-value="(file) => file && uploadBastPhoto('cables', cp.key, file)"
                                @delete="destroyBastPhoto(getPhoto(cp.key)!.id)"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

        </template>
    </div>
</template>
