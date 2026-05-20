<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ExternalLink, FileText } from 'lucide-vue-next';
import * as DrafterAssignmentActions from '@/actions/App/Http/Controllers/Drafter/AssignmentController';
import ActivityTypeBadge from '@/components/ActivityTypeBadge.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { Assignment } from '@/types';

const props = defineProps<{
    assignment: Assignment;
}>();

defineOptions({
    layout: (page: { assignment: Assignment }) => ({
        breadcrumbs: [
            { title: 'Assignments', href: DrafterAssignmentActions.index().url },
            { title: page.assignment.site.site_code, href: '#' },
        ],
    }),
});

function formatDate(value: string | null | undefined): string {
    if (!value) return '—';
    const d = new Date(value);
    if (isNaN(d.getTime())) return value;
    return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function storageUrl(path: string | null | undefined): string | null {
    if (!path) {
        return null;
    }
    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }
    return `/storage/${path}`;
}

const photoFields: { key: keyof typeof props.assignment.survey_data & string; label: string }[] = [
    { key: 'photo_overall_site', label: 'Foto Tampak Keseluruhan Site' },
    { key: 'photo_parking_evcs', label: 'Foto Lahan Parkir EVCS / Lokasi BSS' },
    { key: 'photo_access_route', label: 'Foto Jalur Akses Menuju Lokasi' },
    { key: 'photo_pln_network', label: 'Foto Jaringan PLN Terdekat' },
    { key: 'photo_satellite_gmaps', label: 'Foto Satelit GMaps' },
    { key: 'file_mockup_3d', label: 'Mock Up 3D' },
    { key: 'file_site_plan', label: 'Site Plan' },
    { key: 'file_ba_survey', label: 'BA Survey' },
];
</script>

<template>
    <Head :title="`Survey — ${assignment.site.site_code}`" />

    <div class="space-y-6 p-4">
        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">
                    {{ assignment.site.location_name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ assignment.site.site_code }}
                    <template v-if="assignment.site.city || assignment.site.province">
                        · {{ [assignment.site.city, assignment.site.province].filter(Boolean).join(', ') }}
                    </template>
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ assignment.site.project?.name ?? '—' }} ·
                    {{ assignment.site.project?.main_contractor?.name ?? '—' }} ·
                    {{ assignment.site.project?.client?.name ?? '—' }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Subcontractor: <span class="font-medium text-foreground">{{ assignment.subcontractor?.name ?? '—' }}</span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <ActivityTypeBadge :activity-type="assignment.activity_type" />
                <StatusBadge :status="assignment.status" />
            </div>
        </div>

        <!-- No survey data -->
        <Card v-if="!assignment.survey_data">
            <CardContent class="py-10 text-center text-sm text-muted-foreground">
                No survey data available for this assignment.
            </CardContent>
        </Card>

        <!-- Survey Data -->
        <Card v-else>
            <CardHeader>
                <CardTitle>Survey Data</CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Text fields -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1">
                        <p class="text-xs font-medium text-muted-foreground">SS WO Number</p>
                        <p class="text-sm">{{ assignment.survey_data.ss_wo_number || '—' }}</p>
                    </div>
                    <div class="grid gap-1">
                        <p class="text-xs font-medium text-muted-foreground">Surveyor Name</p>
                        <p class="text-sm">{{ assignment.survey_data.surveyor_name || '—' }}</p>
                    </div>
                    <div class="grid gap-1">
                        <p class="text-xs font-medium text-muted-foreground">PIC Location Name</p>
                        <p class="text-sm">{{ assignment.survey_data.pic_location_name || '—' }}</p>
                    </div>
                    <div class="grid gap-1">
                        <p class="text-xs font-medium text-muted-foreground">PIC Location Phone</p>
                        <p class="text-sm">{{ assignment.survey_data.pic_location_phone || '—' }}</p>
                    </div>
                    <div class="grid gap-1">
                        <p class="text-xs font-medium text-muted-foreground">Charger Type</p>
                        <p class="text-sm">{{ assignment.survey_data.charger_type || '—' }}</p>
                    </div>
                    <div class="grid gap-1">
                        <p class="text-xs font-medium text-muted-foreground">SS Schedule Date</p>
                        <p class="text-sm">{{ formatDate(assignment.survey_data.ss_schedule_date) }}</p>
                    </div>
                    <div class="grid gap-1">
                        <p class="text-xs font-medium text-muted-foreground">Cable Pulling Type</p>
                        <p class="text-sm">{{ assignment.survey_data.cable_pulling_type || '—' }}</p>
                    </div>
                    <div class="grid gap-1">
                        <p class="text-xs font-medium text-muted-foreground">Power / Daya (kVA)</p>
                        <p class="text-sm">
                            {{ assignment.site.power_kva ?? assignment.survey_data.power_kva ?? '—' }}
                        </p>
                    </div>
                    <div class="grid gap-1">
                        <p class="text-xs font-medium text-muted-foreground">PLN Network Type</p>
                        <p class="text-sm">{{ assignment.survey_data.pln_network_type || '—' }}</p>
                    </div>
                    <div class="grid gap-1">
                        <p class="text-xs font-medium text-muted-foreground">Parking Slot</p>
                        <p class="text-sm">{{ assignment.survey_data.parking_slot || '—' }}</p>
                    </div>
                    <div class="grid gap-1">
                        <p class="text-xs font-medium text-muted-foreground">SS Report Submission Date</p>
                        <p class="text-sm">{{ formatDate(assignment.survey_data.ss_report_submission_date) }}</p>
                    </div>
                </div>

                <div v-if="assignment.survey_data.additional_info" class="grid gap-1">
                    <p class="text-xs font-medium text-muted-foreground">Additional Information</p>
                    <p class="text-sm whitespace-pre-wrap">{{ assignment.survey_data.additional_info }}</p>
                </div>

                <Separator />

                <p class="text-sm font-medium text-muted-foreground">Photos &amp; Files</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div v-for="field in photoFields" :key="field.key" class="grid gap-1.5">
                        <p class="text-xs font-medium text-muted-foreground">{{ field.label }}</p>
                        <template v-if="storageUrl((assignment.survey_data as any)[field.key])">
                            <a
                                :href="storageUrl((assignment.survey_data as any)[field.key])!"
                                target="_blank"
                                rel="noopener"
                                class="group block"
                            >
                                <img
                                    :src="storageUrl((assignment.survey_data as any)[field.key])!"
                                    :alt="field.label"
                                    class="h-40 w-full rounded-md border object-cover transition-opacity group-hover:opacity-80"
                                    @error="($event.target as HTMLImageElement).style.display = 'none'; ($event.target as HTMLImageElement).nextElementSibling?.classList.remove('hidden')"
                                />
                                <!-- Fallback for non-image files (e.g. PDFs) -->
                                <div class="hidden flex-col items-center justify-center gap-1 rounded-md border bg-muted/30 px-3 py-6 text-center text-sm text-muted-foreground">
                                    <FileText class="size-6" />
                                    <span>View file</span>
                                    <ExternalLink class="size-3" />
                                </div>
                            </a>
                        </template>
                        <div v-else class="flex h-10 items-center rounded-md border bg-muted/30 px-3 text-sm text-muted-foreground">
                            Not uploaded
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
