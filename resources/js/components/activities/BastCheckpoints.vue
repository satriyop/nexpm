<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import * as SubActions from '@/actions/App/Http/Controllers/Subcontractor/AssignmentController';
import InputError from '@/components/InputError.vue';
import PhotoUpload from '@/components/PhotoUpload.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Assignment } from '@/types';

const props = defineProps<{
    assignment: Assignment;
    isReadOnly: boolean;
}>();

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

function storageUrl(path: string) {
    if (!path) return '#';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    return `/storage/${path}`;
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
</script>

<template>
    <div class="space-y-6">
        <!-- Cover / Plant Info -->
        <Card>
            <CardHeader>
                <CardTitle>
                    Plant Information
                    <span class="ml-2 text-sm font-normal text-muted-foreground">({{ siteTypeName }} Commissioning Report)</span>
                </CardTitle>
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
                            <textarea
                                v-model="bastForm.plant_address"
                                :disabled="isReadOnly"
                                rows="2"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Alamat Plant"
                            />
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
            <CardHeader><CardTitle>Measurements</CardTitle></CardHeader>
            <CardContent>
                <form class="space-y-4" @submit.prevent="submitBast">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-1.5">
                            <Label>Grounding Resistance (Ω)</Label>
                            <Input v-model="bastForm.measurements['grounding_resistance']" :disabled="isReadOnly" placeholder="e.g. 0.5" />
                        </div>
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
    </div>
</template>
