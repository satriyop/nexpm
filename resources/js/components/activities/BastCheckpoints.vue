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
import type { Assignment, AssignmentConstructionData } from '@/types';

const props = defineProps<{
    assignment: Assignment;
    isReadOnly: boolean;
    submitUrl?: string;
    storePhotoUrl?: string;
    deletePhotoUrlFn?: (photoId: number) => string;
    siblingConstruction?: AssignmentConstructionData | null;
}>();

const siteTypeName = computed(
    () => props.assignment.site.site_type?.name ?? 'EVCS',
);
const isBss = computed(() => siteTypeName.value === 'BSS');

const derivedFields = computed(() => ({
    plant_name: props.assignment.site.location_name,
    plant_address: props.assignment.site.address,
    plant_coordinate:
        props.assignment.site.latitude && props.assignment.site.longitude
            ? `${props.assignment.site.latitude}, ${props.assignment.site.longitude}`
            : null,
    gmaps_link: props.assignment.site.google_map_url,
    charger_type: props.assignment.site.machine_type?.name ?? null,
    installation_vendor:
        props.assignment.site.project?.main_contractor?.name ?? null,
    pic_vendor_contact:
        props.assignment.site.project?.main_contractor?.pic ?? null,
    customer: props.assignment.site.project?.client?.name ?? null,
    sn_unit: props.siblingConstruction?.machine_serial_number ?? null,
    installation_date: props.siblingConstruction?.cons_actual_done_date ?? null,
    go_live_date_pln: props.siblingConstruction?.go_live_date_pln ?? null,
    go_live_date_pln_pass:
        props.siblingConstruction?.go_live_date_pln_pass ?? null,
}));

const bastForm = useForm({
    sim_provider: props.assignment.bast_data?.sim_provider ?? '',
    nomor_simcard: props.assignment.bast_data?.nomor_simcard ?? '',
    commissioning_date: props.assignment.bast_data?.commissioning_date ?? '',
});

function submitBast() {
    bastForm.patch(
        props.submitUrl ?? SubActions.updateBastData(props.assignment).url,
    );
}

const bastPhotos = computed(
    () => props.assignment.bast_data?.bast_photos ?? [],
);

function getPhoto(checkpointKey: string) {
    return (
        bastPhotos.value.find((p) => p.checkpoint_key === checkpointKey) ?? null
    );
}

const uploadingCheckpoint = ref<string | null>(null);

function uploadBastPhoto(section: string, checkpointKey: string, file: File) {
    uploadingCheckpoint.value = checkpointKey;
    const form = useForm({
        section,
        checkpoint_key: checkpointKey,
        photo: file,
    });
    const url =
        props.storePhotoUrl ??
        SubActions.storeBastPhoto(props.assignment).url;
    form.post(url, {
        forceFormData: true,
        onFinish: () => {
            uploadingCheckpoint.value = null;
        },
    });
}

function destroyBastPhoto(photoId: number) {
    const form = useForm({});
    const url =
        props.deletePhotoUrlFn?.(photoId) ??
        SubActions.destroyBastPhoto({
            assignment: props.assignment.id,
            photo: photoId,
        }).url;
    form.delete(url);
}

function storageUrl(path: string) {
    if (!path) {
        return '#';
    }

    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }

    return `/storage/${path}`;
}

interface Checkpoint {
    key: string;
    label: string;
    optional?: boolean;
}

const deviceCheckpoints = computed<Checkpoint[]>(() =>
    isBss.value
        ? [
              { key: 'device_front_view_open', label: 'Front View (Open)' },
              { key: 'device_side_view_right', label: 'Side View (Right)' },
              { key: 'device_front_view_close', label: 'Front View (Close)' },
              { key: 'device_side_view_left', label: 'Side View (Left)' },
              {
                  key: 'device_foundation_depth',
                  label: 'Foundation at 40cm Depth',
                  optional: true,
              },
              {
                  key: 'device_foundation_concrete',
                  label: 'Concrete Foundation (8cm Depth from Ground)',
                  optional: true,
              },
              { key: 'device_sticker', label: 'Sticker', optional: true },
              { key: 'device_name_plate', label: 'Name Plate' },
              {
                  key: 'device_ac_cable_termination',
                  label: 'AC Cable Termination',
              },
              {
                  key: 'device_grounding_termination',
                  label: 'Grounding Termination (Cover)',
              },
              {
                  key: 'device_cable_entry_panel',
                  label: 'AC/DC Cable Entry Termination Panel',
              },
              {
                  key: 'device_visible_safety_sign',
                  label: 'Visible Safety Sign',
              },
          ]
        : [
              { key: 'device_front_view_open', label: 'Front View (Open)' },
              { key: 'device_side_view_right', label: 'Side View (Right)' },
              { key: 'device_front_view_close', label: 'Front View (Close)' },
              { key: 'device_side_view_left', label: 'Side View (Left)' },
              {
                  key: 'device_foundation_depth',
                  label: 'Foundation at 40cm Depth',
              },
              {
                  key: 'device_foundation_concrete',
                  label: 'Concrete Foundation (8cm Depth from Ground)',
              },
              { key: 'device_parking_space', label: 'Parking Space' },
              { key: 'device_sticker', label: 'Sticker' },
              { key: 'device_name_plate', label: 'Name Plate' },
              {
                  key: 'device_ac_cable_termination',
                  label: 'AC Cable Termination',
              },
              {
                  key: 'device_emergency_button_cover',
                  label: 'Emergency Button Cover',
              },
              {
                  key: 'device_grounding_termination',
                  label: 'Grounding Termination (Cover)',
              },
              {
                  key: 'device_cable_entry_panel',
                  label: 'AC/DC Cable Entry Termination Panel',
              },
              {
                  key: 'device_visible_safety_sign',
                  label: 'Visible Safety Sign',
              },
          ],
);

const simCheckpoints = computed<Checkpoint[]>(() => [
    { key: 'sim_kartu_perdana', label: 'Kartu Perdana' },
    { key: 'sim_installed_sim_card', label: 'Installed SIM Card' },
    { key: 'sim_signal_bar', label: 'Signal Bar' },
    { key: 'sim_url', label: 'URL' },
    ...(isBss.value
        ? []
        : [
              {
                  key: 'sim_start_charge_immediately',
                  label: 'Start Charge Immediately (CE-LCD)',
              },
          ]),
    { key: 'sim_user_interface_lcd', label: 'User Interface (CE-LCD)' },
]);

const groundingCheckpoints: Checkpoint[] = [
    { key: 'grounding_rod_connection', label: 'Grounding Rod Connection' },
    {
        key: 'grounding_rod_to_earth_1',
        label: 'Grounding Rod to Earth — Spot 1 (Insert in Specific Spots)',
    },
    {
        key: 'grounding_rod_to_earth_2',
        label: 'Grounding Rod to Earth — Spot 2 (Insert in Specific Spots)',
        optional: true,
    },
    {
        key: 'grounding_busbar_panel',
        label: 'Grounding Rod Busbar (in Cable Route)',
    },
    {
        key: 'grounding_cable_route',
        label: 'Grounding Test — Cable Route (OHM)',
    },
    { key: 'grounding_test_ac_panel', label: 'Grounding Test AC Panel' },
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
    { key: 'ac_safety_sign', label: 'Safety Sign' },
    { key: 'ac_front_view_close', label: 'Front View (Close)' },
    { key: 'ac_pilot_lamps', label: 'Pilot Lamps' },
    { key: 'ac_side_view_right', label: 'Side View (Right)' },
    { key: 'ac_locking_system', label: 'Locking System' },
    { key: 'ac_side_view_left', label: 'Side View (Left)' },
    { key: 'ac_mcb', label: 'MCB' },
    { key: 'ac_panel_wiring', label: 'Panel Wiring' },
    { key: 'ac_phase_marking', label: 'Phase Marking' },
];

const measurementCheckpoints = computed<Checkpoint[]>(() =>
    isBss.value
        ? [
              { key: 'measurement_voltage_rn', label: 'Voltage R-N (V)' },
              { key: 'measurement_voltage_rg', label: 'Voltage R-G (V)' },
              { key: 'measurement_voltage_ng', label: 'Voltage N-G (V)' },
              { key: 'measurement_frequency', label: 'Frequency (Hz)' },
              {
                  key: 'measurement_grounding_ac',
                  label: 'Grounding to AC Panel',
              },
          ]
        : [
              { key: 'measurement_voltage_rs', label: 'Voltage R-S (V)' },
              { key: 'measurement_voltage_rt', label: 'Voltage R-T (V)' },
              { key: 'measurement_voltage_st', label: 'Voltage S-T (V)' },
              { key: 'measurement_frequency', label: 'Frequency (Hz)' },
              { key: 'measurement_voltage_ng', label: 'Voltage N-G (V)' },
              {
                  key: 'measurement_grounding_ac',
                  label: 'Grounding to AC Panel',
              },
          ],
);

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
                    <span class="ml-2 text-sm font-normal text-muted-foreground"
                        >({{ siteTypeName }} Commissioning Report)</span
                    >
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Read-only fields derived from masterdata / sibling construction -->
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <dt class="font-medium text-muted-foreground">
                            Plant Name
                        </dt>
                        <dd>{{ derivedFields.plant_name || '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="font-medium text-muted-foreground">
                            Plant Address
                        </dt>
                        <dd class="whitespace-pre-line">
                            {{ derivedFields.plant_address || '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            Plant Coordinate
                        </dt>
                        <dd>{{ derivedFields.plant_coordinate || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            GMaps Link
                        </dt>
                        <dd>
                            <a
                                v-if="derivedFields.gmaps_link"
                                :href="derivedFields.gmaps_link"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-blue-600 underline hover:text-blue-800"
                                >Open Map</a
                            >
                            <span v-else>—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            {{ isBss ? 'BSS' : 'EV Charger' }} Type
                        </dt>
                        <dd>{{ derivedFields.charger_type || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            Serial Number Unit
                        </dt>
                        <dd>{{ derivedFields.sn_unit || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            Installation Vendor
                        </dt>
                        <dd>{{ derivedFields.installation_vendor || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            PIC Vendor Contact
                        </dt>
                        <dd>{{ derivedFields.pic_vendor_contact || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            Installation Date
                        </dt>
                        <dd>{{ derivedFields.installation_date || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            Customer
                        </dt>
                        <dd>{{ derivedFields.customer || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            Go LIVE Date (PLN Bypass)
                        </dt>
                        <dd>{{ derivedFields.go_live_date_pln_pass || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            Go LIVE Date (PLN)
                        </dt>
                        <dd>{{ derivedFields.go_live_date_pln || '—' }}</dd>
                    </div>
                </dl>

                <!-- Editable fields -->
                <form class="space-y-4" @submit.prevent="submitBast">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Provider Sim Card</Label>
                            <Input
                                v-model="bastForm.sim_provider"
                                :disabled="isReadOnly"
                                placeholder="Telkomsel / XL / dll"
                            />
                            <InputError
                                :message="bastForm.errors.sim_provider"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Nomor SIM Card</Label>
                            <Input
                                v-model="bastForm.nomor_simcard"
                                :disabled="isReadOnly"
                                placeholder="Nomor SIM Card"
                            />
                            <InputError
                                :message="bastForm.errors.nomor_simcard"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Commissioning Date</Label>
                            <Input
                                v-model="bastForm.commissioning_date"
                                type="date"
                                :disabled="isReadOnly"
                            />
                            <InputError
                                :message="bastForm.errors.commissioning_date"
                            />
                        </div>
                    </div>
                    <div v-if="!isReadOnly" class="flex justify-end pt-2">
                        <Button type="submit" :disabled="bastForm.processing">
                            {{
                                bastForm.processing
                                    ? 'Saving…'
                                    : 'Save Plant Info'
                            }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Section 1: Device -->
        <Card>
            <CardHeader>
                <CardTitle
                    >1. {{ isBss ? 'Battery Swap Station' : 'EV Charger' }} —
                    Visual Inspection</CardTitle
                >
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="cp in deviceCheckpoints"
                        :key="cp.key"
                        class="grid gap-1.5"
                    >
                        <Label class="flex items-center gap-1">
                            {{ cp.label }}
                            <span
                                v-if="cp.optional"
                                class="text-xs text-muted-foreground"
                                >(optional)</span
                            >
                        </Label>
                        <PhotoUpload
                            :model-value="null"
                            :current-url="
                                getPhoto(cp.key)
                                    ? storageUrl(getPhoto(cp.key)!.photo_path)
                                    : null
                            "
                            :uploading="uploadingCheckpoint === cp.key"
                            :readonly="isReadOnly"
                            :deletable="true"
                            @update:model-value="
                                (file) =>
                                    file &&
                                    uploadBastPhoto('device', cp.key, file)
                            "
                            @delete="destroyBastPhoto(getPhoto(cp.key)!.id)"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Section 2: SIM Card -->
        <Card>
            <CardHeader
                ><CardTitle
                    >2. SIM Card — Visual Inspection</CardTitle
                ></CardHeader
            >
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="cp in simCheckpoints"
                        :key="cp.key"
                        class="grid gap-1.5"
                    >
                        <Label>{{ cp.label }}</Label>
                        <PhotoUpload
                            :model-value="null"
                            :current-url="
                                getPhoto(cp.key)
                                    ? storageUrl(getPhoto(cp.key)!.photo_path)
                                    : null
                            "
                            :uploading="uploadingCheckpoint === cp.key"
                            :readonly="isReadOnly"
                            :deletable="true"
                            @update:model-value="
                                (file) =>
                                    file &&
                                    uploadBastPhoto('sim_card', cp.key, file)
                            "
                            @delete="destroyBastPhoto(getPhoto(cp.key)!.id)"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Section 3: Grounding -->
        <Card>
            <CardHeader
                ><CardTitle
                    >3. Grounding System — Visual Inspection</CardTitle
                ></CardHeader
            >
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="cp in groundingCheckpoints"
                        :key="cp.key"
                        class="grid gap-1.5"
                    >
                        <Label class="flex items-center gap-1">
                            {{ cp.label }}
                            <span
                                v-if="cp.optional"
                                class="text-xs text-muted-foreground"
                                >(optional)</span
                            >
                        </Label>
                        <PhotoUpload
                            :model-value="null"
                            :current-url="
                                getPhoto(cp.key)
                                    ? storageUrl(getPhoto(cp.key)!.photo_path)
                                    : null
                            "
                            :uploading="uploadingCheckpoint === cp.key"
                            :readonly="isReadOnly"
                            :deletable="true"
                            @update:model-value="
                                (file) =>
                                    file &&
                                    uploadBastPhoto('grounding', cp.key, file)
                            "
                            @delete="destroyBastPhoto(getPhoto(cp.key)!.id)"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Section 4: Fire Extinguisher (EVCS only) -->
        <Card v-if="!isBss">
            <CardHeader
                ><CardTitle
                    >4. Fire Extinguisher — Visual Inspection</CardTitle
                ></CardHeader
            >
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="cp in fireExtCheckpoints"
                        :key="cp.key"
                        class="grid gap-1.5"
                    >
                        <Label>{{ cp.label }}</Label>
                        <PhotoUpload
                            :model-value="null"
                            :current-url="
                                getPhoto(cp.key)
                                    ? storageUrl(getPhoto(cp.key)!.photo_path)
                                    : null
                            "
                            :uploading="uploadingCheckpoint === cp.key"
                            :readonly="isReadOnly"
                            :deletable="true"
                            @update:model-value="
                                (file) =>
                                    file &&
                                    uploadBastPhoto(
                                        'fire_extinguisher',
                                        cp.key,
                                        file,
                                    )
                            "
                            @delete="destroyBastPhoto(getPhoto(cp.key)!.id)"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Section 5: KWH Meter -->
        <Card>
            <CardHeader
                ><CardTitle
                    >5. KWH Meter — Visual Inspection</CardTitle
                ></CardHeader
            >
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div
                        v-for="cp in kwhCheckpoints"
                        :key="cp.key"
                        class="grid gap-1.5"
                    >
                        <Label>{{ cp.label }}</Label>
                        <PhotoUpload
                            :model-value="null"
                            :current-url="
                                getPhoto(cp.key)
                                    ? storageUrl(getPhoto(cp.key)!.photo_path)
                                    : null
                            "
                            :uploading="uploadingCheckpoint === cp.key"
                            :readonly="isReadOnly"
                            :deletable="true"
                            @update:model-value="
                                (file) =>
                                    file &&
                                    uploadBastPhoto('kwh_meter', cp.key, file)
                            "
                            @delete="destroyBastPhoto(getPhoto(cp.key)!.id)"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Section 6: AC Panel -->
        <Card>
            <CardHeader
                ><CardTitle
                    >6. AC Panel — Visual Inspection</CardTitle
                ></CardHeader
            >
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="cp in acPanelCheckpoints"
                        :key="cp.key"
                        class="grid gap-1.5"
                    >
                        <Label>{{ cp.label }}</Label>
                        <PhotoUpload
                            :model-value="null"
                            :current-url="
                                getPhoto(cp.key)
                                    ? storageUrl(getPhoto(cp.key)!.photo_path)
                                    : null
                            "
                            :uploading="uploadingCheckpoint === cp.key"
                            :readonly="isReadOnly"
                            :deletable="true"
                            @update:model-value="
                                (file) =>
                                    file &&
                                    uploadBastPhoto('ac_panel', cp.key, file)
                            "
                            @delete="destroyBastPhoto(getPhoto(cp.key)!.id)"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Section 7: Measurements (photo of meter reading) -->
        <Card>
            <CardHeader
                ><CardTitle
                    >7. Measurements — Photo of Meter Reading</CardTitle
                ></CardHeader
            >
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="cp in measurementCheckpoints"
                        :key="cp.key"
                        class="grid gap-1.5"
                    >
                        <Label>{{ cp.label }}</Label>
                        <PhotoUpload
                            :model-value="null"
                            :current-url="
                                getPhoto(cp.key)
                                    ? storageUrl(getPhoto(cp.key)!.photo_path)
                                    : null
                            "
                            :uploading="uploadingCheckpoint === cp.key"
                            :readonly="isReadOnly"
                            :deletable="true"
                            @update:model-value="
                                (file) =>
                                    file &&
                                    uploadBastPhoto(
                                        'measurements',
                                        cp.key,
                                        file,
                                    )
                            "
                            @delete="destroyBastPhoto(getPhoto(cp.key)!.id)"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Section 8: Cables -->
        <Card>
            <CardHeader
                ><CardTitle
                    >8. Cables — Visual Inspection</CardTitle
                ></CardHeader
            >
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="cp in cableCheckpoints"
                        :key="cp.key"
                        class="grid gap-1.5"
                    >
                        <Label>{{ cp.label }}</Label>
                        <PhotoUpload
                            :model-value="null"
                            :current-url="
                                getPhoto(cp.key)
                                    ? storageUrl(getPhoto(cp.key)!.photo_path)
                                    : null
                            "
                            :uploading="uploadingCheckpoint === cp.key"
                            :readonly="isReadOnly"
                            :deletable="true"
                            @update:model-value="
                                (file) =>
                                    file &&
                                    uploadBastPhoto('cables', cp.key, file)
                            "
                            @delete="destroyBastPhoto(getPhoto(cp.key)!.id)"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
