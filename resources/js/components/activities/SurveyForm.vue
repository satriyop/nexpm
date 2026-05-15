<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import * as SubActions from '@/actions/App/Http/Controllers/Subcontractor/AssignmentController';
import FileUpload from '@/components/FileUpload.vue';
import InputError from '@/components/InputError.vue';
import PhotoUpload from '@/components/PhotoUpload.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import type { Assignment, AssignmentSurveyData } from '@/types';

const props = defineProps<{
    assignment: Assignment;
    isReadOnly: boolean;
}>();

const surveyForm = useForm({
    _method: 'patch',
    surveyor_name: props.assignment.survey_data?.surveyor_name ?? '',
    pic_location_name: props.assignment.survey_data?.pic_location_name ?? '',
    pic_location_phone: props.assignment.survey_data?.pic_location_phone ?? '',
    charger_type: props.assignment.survey_data?.charger_type ?? '',
    ss_schedule_date: props.assignment.survey_data?.ss_schedule_date ?? '',
    cable_pulling_type: props.assignment.survey_data?.cable_pulling_type ?? '',
    pln_network_type: props.assignment.survey_data?.pln_network_type ?? '',
    parking_slot: props.assignment.survey_data?.parking_slot ?? '',
    additional_info: props.assignment.survey_data?.additional_info ?? '',
    photo_overall_site: null as File | null,
    photo_parking_evcs: null as File | null,
    photo_access_route: null as File | null,
    photo_pln_network: null as File | null,
    photo_satellite_gmaps: null as File | null,
    file_mockup_3d: null as File | null,
    file_site_plan: null as File | null,
    file_ba_survey: null as File | null,
});

function submitSurvey() {
    surveyForm
        .transform((data) => ({
            ...data,
            _method: 'patch',
        }))
        .post(SubActions.updateSurveyData(props.assignment).url, {
            forceFormData: true,
            onSuccess: () =>
                surveyForm.reset(
                    'photo_overall_site',
                    'photo_parking_evcs',
                    'photo_access_route',
                    'photo_pln_network',
                    'photo_satellite_gmaps',
                    'file_mockup_3d',
                    'file_site_plan',
                    'file_ba_survey',
                ),
        });
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

type SurveyUploadKey = Extract<
    keyof AssignmentSurveyData,
    | 'photo_overall_site'
    | 'photo_parking_evcs'
    | 'photo_access_route'
    | 'photo_pln_network'
    | 'photo_satellite_gmaps'
    | 'file_mockup_3d'
    | 'file_site_plan'
    | 'file_ba_survey'
>;

interface SurveyUploadField {
    key: SurveyUploadKey;
    label: string;
    isImage: boolean;
}

const photoFields = computed<SurveyUploadField[]>(() => [
    {
        key: 'photo_overall_site',
        label: 'Foto Tampak Keseluruhan Site',
        isImage: true,
    },
    {
        key: 'photo_parking_evcs',
        label: 'Foto Lahan Parkir EVCS / Lokasi BSS',
        isImage: true,
    },
    {
        key: 'photo_access_route',
        label: 'Foto Jalur Akses Menuju Lokasi',
        isImage: true,
    },
    {
        key: 'photo_pln_network',
        label: 'Foto Jaringan PLN Terdekat',
        isImage: true,
    },
    {
        key: 'photo_satellite_gmaps',
        label: 'Foto Satelit GMaps',
        isImage: true,
    },
    { key: 'file_mockup_3d', label: 'Mock Up 3D', isImage: true },
    { key: 'file_site_plan', label: 'Site Plan', isImage: true },
    { key: 'file_ba_survey', label: 'BA Survey', isImage: true },
]);

function currentUploadUrl(key: SurveyUploadKey): string | null {
    const path = props.assignment.survey_data?.[key];

    return path ? storageUrl(path) : null;
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Survey Data</CardTitle>
        </CardHeader>
        <CardContent>
            <form class="space-y-4" @submit.prevent="submitSurvey">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label>Surveyor Name</Label>
                        <Input
                            v-model="surveyForm.surveyor_name"
                            :disabled="isReadOnly"
                            placeholder="Nama Surveyor"
                        />
                        <InputError
                            :message="surveyForm.errors.surveyor_name"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>PIC Location Name</Label>
                        <Input
                            v-model="surveyForm.pic_location_name"
                            :disabled="isReadOnly"
                            placeholder="Nama PIC Lokasi"
                        />
                        <InputError
                            :message="surveyForm.errors.pic_location_name"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>PIC Location Phone</Label>
                        <Input
                            v-model="surveyForm.pic_location_phone"
                            :disabled="isReadOnly"
                            placeholder="No. HP PIC"
                        />
                        <InputError
                            :message="surveyForm.errors.pic_location_phone"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Charger Type</Label>
                        <Select
                            v-model="surveyForm.charger_type"
                            :disabled="isReadOnly"
                        >
                            <SelectTrigger
                                ><SelectValue placeholder="Select type"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="EVCS">EVCS</SelectItem>
                                <SelectItem value="BSS">BSS</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="surveyForm.errors.charger_type" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>SS Schedule Date (w/ Landlord)</Label>
                        <Input
                            v-model="surveyForm.ss_schedule_date"
                            type="date"
                            :disabled="isReadOnly"
                        />
                        <InputError
                            :message="surveyForm.errors.ss_schedule_date"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Cable Pulling Type</Label>
                        <Select
                            v-model="surveyForm.cable_pulling_type"
                            :disabled="isReadOnly"
                        >
                            <SelectTrigger
                                ><SelectValue placeholder="Select type"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="New Power"
                                    >New Power</SelectItem
                                >
                                <SelectItem value="Existing Power"
                                    >Existing Power</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError
                            :message="surveyForm.errors.cable_pulling_type"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Power / Daya (kVA)</Label>
                        <div
                            class="rounded-md border bg-muted/50 px-3 py-2 text-sm font-medium"
                        >
                            {{
                                assignment.site.power_kva ??
                                assignment.survey_data?.power_kva ??
                                '—'
                            }}
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Available PLN Network Type</Label>
                        <Select
                            v-model="surveyForm.pln_network_type"
                            :disabled="isReadOnly"
                        >
                            <SelectTrigger
                                ><SelectValue placeholder="Select phase"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1 Phase">1 Phase</SelectItem>
                                <SelectItem value="3 Phase">3 Phase</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError
                            :message="surveyForm.errors.pln_network_type"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Parking Slot</Label>
                        <Input
                            v-model="surveyForm.parking_slot"
                            :disabled="isReadOnly"
                            placeholder="e.g. B1-12"
                        />
                        <InputError :message="surveyForm.errors.parking_slot" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label>Additional Information</Label>
                    <textarea
                        v-model="surveyForm.additional_info"
                        :disabled="isReadOnly"
                        rows="3"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Tambahan Informasi Lain-Lain"
                    />
                </div>

                <Separator />
                <p class="text-sm font-medium text-muted-foreground">
                    Photos & Files
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <template v-for="field in photoFields" :key="field.key">
                        <div class="grid gap-1.5">
                            <Label>{{ field.label }}</Label>
                            <PhotoUpload
                                v-if="field.isImage"
                                :model-value="(surveyForm as any)[field.key]"
                                :current-url="currentUploadUrl(field.key)"
                                :readonly="isReadOnly"
                                :test-id="`survey-${field.key}`"
                                @update:model-value="
                                    (surveyForm as any)[field.key] = $event
                                "
                            />
                            <FileUpload
                                v-else
                                :model-value="(surveyForm as any)[field.key]"
                                :current-url="currentUploadUrl(field.key)"
                                accept=".pdf,.doc,.docx,.dwg,image/*"
                                :max-size-kb="20480"
                                :readonly="isReadOnly"
                                :test-id="`survey-${field.key}`"
                                @update:model-value="
                                    (surveyForm as any)[field.key] = $event
                                "
                            />
                            <InputError
                                :message="(surveyForm.errors as any)[field.key]"
                            />
                        </div>
                    </template>
                </div>

                <div v-if="!isReadOnly" class="flex justify-end pt-2">
                    <Button type="submit" :disabled="surveyForm.processing">
                        {{
                            surveyForm.processing
                                ? 'Saving…'
                                : 'Save Survey Data'
                        }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
