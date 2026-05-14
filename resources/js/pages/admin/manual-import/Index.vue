<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle, ChevronDown, ChevronRight, Download, FileSpreadsheet, SkipForward, Upload } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import * as Actions from '@/actions/App/Http/Controllers/Admin/ManualProjectImportController';
import * as ProjectActions from '@/actions/App/Http/Controllers/Admin/ProjectController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

interface PreviewRow {
    row_number: number | null;
    project_name: string;
    site_code: string;
    activity_type: string;
    status: 'ok' | 'error' | 'skipped';
    message: string;
    data: Record<string, string>;
}

interface PreviewSummary {
    ok: number;
    errors: number;
    skipped: number;
}

const props = defineProps<{
    previewRows?: PreviewRow[];
    previewSummary?: PreviewSummary;
    tempPath?: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Projects', href: ProjectActions.index().url },
            { title: 'Import Manual Projects', href: Actions.index().url },
        ],
    },
});

const isPreview = computed(() => props.previewRows !== undefined);

// ── Upload step ────────────────────────────────────────────────────────────────

const uploadForm = useForm({ file: null as File | null });
const fileRef = ref<HTMLInputElement | null>(null);

function submit() {
    if (!fileRef.value?.files?.[0]) {
        return;
    }
    uploadForm.file = fileRef.value.files[0];
    uploadForm.post(Actions.preview().url, { forceFormData: true });
}

// ── Preview step ───────────────────────────────────────────────────────────────

const confirmForm = useForm({ temp_path: props.tempPath ?? '' });
const showAll = ref(false);
const expandedSites = ref<Set<string>>(new Set());

const hasErrors = computed(() => (props.previewSummary?.errors ?? 0) > 0);

const filteredRows = computed(() => {
    if (!props.previewRows) {
        return [];
    }
    if (showAll.value) {
        return props.previewRows;
    }
    return props.previewRows.filter((r) => r.status === 'error');
});

// Group filtered rows by "project / site" key for collapsible display.
const groupedRows = computed(() => {
    const groups: Record<string, { label: string; rows: PreviewRow[] }> = {};
    for (const row of filteredRows.value) {
        const key = `${row.project_name}__${row.site_code}`;
        if (!groups[key]) {
            groups[key] = {
                label: row.site_code ? `${row.project_name} › ${row.site_code}` : row.project_name,
                rows: [],
            };
            expandedSites.value.add(key);
        }
        groups[key].rows.push(row);
    }
    return groups;
});

function toggleSite(key: string) {
    if (expandedSites.value.has(key)) {
        expandedSites.value.delete(key);
    } else {
        expandedSites.value.add(key);
    }
}

function confirmImport() {
    confirmForm.temp_path = props.tempPath ?? '';
    confirmForm.post(Actions.store().url);
}

// ── beforeunload guard ─────────────────────────────────────────────────────────

function warnBeforeLeave(e: BeforeUnloadEvent) {
    e.preventDefault();
}

onMounted(() => {
    if (isPreview.value) {
        window.addEventListener('beforeunload', warnBeforeLeave);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', warnBeforeLeave);
});

// ── Helpers ────────────────────────────────────────────────────────────────────

function statusColor(status: string) {
    return status === 'ok' ? 'text-green-600' : status === 'error' ? 'text-red-600' : 'text-amber-600';
}

function activityLabel(type: string) {
    const map: Record<string, string> = {
        SURVEY: 'Survey',
        CONSTRUCTION: 'Construction',
        PLN_CONNECTION: 'PLN',
        BAST: 'BAST',
    };
    return map[type] ?? type;
}
</script>

<template>
    <Head title="Import Manual Projects" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Import Manual Projects</h1>
            <Link :href="ProjectActions.index().url" class="text-sm text-muted-foreground hover:text-foreground">
                ← Back to Projects
            </Link>
        </div>

        <!-- ── Step 1: Upload ─────────────────────────────────────────────── -->
        <template v-if="!isPreview">
            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileSpreadsheet class="h-5 w-5" />
                        Upload CSV
                    </CardTitle>
                    <CardDescription>
                        Use the template to prepare your data, then upload the filled CSV.
                        One row per assignment — site fields repeat on each row.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <a
                        :href="Actions.template().url"
                        class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <Download class="h-4 w-4" />
                        Download CSV Template
                    </a>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-1.5">
                            <label class="text-sm font-medium">CSV File</label>
                            <input
                                ref="fileRef"
                                type="file"
                                accept=".csv,.txt"
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm file:border-0 file:bg-transparent file:text-sm file:font-medium"
                            />
                        </div>
                        <Button type="submit" :disabled="uploadForm.processing">
                            <Upload class="mr-1.5 h-4 w-4" />
                            {{ uploadForm.processing ? 'Parsing...' : 'Preview Import' }}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle class="text-base">CSV Column Guide</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <p class="mb-1 font-medium text-muted-foreground">Project</p>
                            <ul class="space-y-0.5 text-muted-foreground">
                                <li>project_name <span class="text-red-500">*</span></li>
                                <li>client_name</li>
                                <li>main_contractor_name</li>
                            </ul>
                        </div>
                        <div>
                            <p class="mb-1 font-medium text-muted-foreground">Site</p>
                            <ul class="space-y-0.5 text-muted-foreground">
                                <li>site_code <span class="text-red-500">*</span></li>
                                <li>location_name <span class="text-red-500">*</span></li>
                                <li>address, province, city</li>
                                <li>google_map_url, site_type, machine_type</li>
                                <li>power_kva, bd_pic, ss_wo_number</li>
                                <li>cable_length_to_panel, charging_station_count</li>
                            </ul>
                        </div>
                        <div>
                            <p class="mb-1 font-medium text-muted-foreground">Assignment</p>
                            <ul class="space-y-0.5 text-muted-foreground">
                                <li>activity_type <span class="text-red-500">*</span> (SURVEY / CONSTRUCTION / PLN_CONNECTION / BAST)</li>
                                <li>subcontractor_code</li>
                                <li>status (default: REPORTED)</li>
                            </ul>
                        </div>
                        <div>
                            <p class="mb-1 font-medium text-muted-foreground">Activity Data</p>
                            <ul class="space-y-0.5 text-muted-foreground">
                                <li><span class="font-medium">Survey:</span> ss_schedule_date, ss_report_submission_date, cable_pulling_type, parking_slot</li>
                                <li><span class="font-medium">Construction:</span> cons_wo_number, setup_approval_date, cons_actual_start_date, cons_actual_done_date, machine_serial_number, go_live_date_pln, go_live_date_pln_pass</li>
                                <li><span class="font-medium">PLN:</span> kwh_meter_installation_date, type_rate, id_pelanggan, nidi_slo_date_acquired, bpujl_acquired_date</li>
                                <li><span class="font-medium">BAST:</span> sim_provider, nomor_simcard</li>
                            </ul>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </template>

        <!-- ── Step 2: Preview ────────────────────────────────────────────── -->
        <template v-if="isPreview && previewSummary">
            <!-- Summary bar -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-1.5 text-sm">
                    <CheckCircle class="h-4 w-4 text-green-600" />
                    <span class="font-medium text-green-700">{{ previewSummary.ok }} OK</span>
                </div>
                <div class="flex items-center gap-1.5 text-sm">
                    <AlertCircle class="h-4 w-4 text-red-600" />
                    <span class="font-medium text-red-700">{{ previewSummary.errors }} Error{{ previewSummary.errors !== 1 ? 's' : '' }}</span>
                </div>
                <div class="flex items-center gap-1.5 text-sm">
                    <SkipForward class="h-4 w-4 text-amber-500" />
                    <span class="font-medium text-amber-700">{{ previewSummary.skipped }} Skipped</span>
                </div>

                <div class="ml-auto flex items-center gap-2">
                    <button
                        class="text-sm text-muted-foreground hover:text-foreground"
                        @click="showAll = !showAll"
                    >
                        {{ showAll ? 'Show errors only' : 'Show all rows' }}
                    </button>
                </div>
            </div>

            <Alert v-if="hasErrors" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertTitle>Rows with errors will be skipped</AlertTitle>
                <AlertDescription>
                    Fix the errors in your CSV and re-upload, or proceed and skip the invalid rows.
                </AlertDescription>
            </Alert>

            <!-- Preview table -->
            <Card>
                <CardContent class="p-0">
                    <div v-if="filteredRows.length === 0" class="py-12 text-center text-muted-foreground">
                        <CheckCircle class="mx-auto mb-2 h-8 w-8 text-green-500" />
                        No errors found. All rows are ready to import.
                    </div>

                    <template v-else>
                        <div
                            v-for="(group, key) in groupedRows"
                            :key="key"
                            class="border-b last:border-b-0"
                        >
                            <!-- Group header -->
                            <button
                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium hover:bg-muted/50"
                                @click="toggleSite(key)"
                            >
                                <component
                                    :is="expandedSites.has(key) ? ChevronDown : ChevronRight"
                                    class="h-4 w-4 shrink-0 text-muted-foreground"
                                />
                                {{ group.label }}
                                <Badge variant="secondary" class="ml-auto">
                                    {{ group.rows.length }}
                                </Badge>
                            </button>

                            <!-- Group rows -->
                            <template v-if="expandedSites.has(key)">
                                <div
                                    v-for="row in group.rows"
                                    :key="row.row_number ?? row.activity_type"
                                    class="flex items-start gap-3 border-t px-4 py-2.5 text-sm"
                                    :class="{
                                        'bg-red-50': row.status === 'error',
                                        'bg-amber-50': row.status === 'skipped',
                                    }"
                                >
                                    <component
                                        :is="row.status === 'ok' ? CheckCircle : row.status === 'error' ? AlertCircle : SkipForward"
                                        class="mt-0.5 h-4 w-4 shrink-0"
                                        :class="statusColor(row.status)"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span v-if="row.row_number" class="text-xs text-muted-foreground">Row {{ row.row_number }}</span>
                                            <Badge v-if="row.activity_type" variant="outline" class="text-xs">
                                                {{ activityLabel(row.activity_type) }}
                                            </Badge>
                                        </div>
                                        <p v-if="row.message" class="mt-0.5 text-xs" :class="statusColor(row.status)">
                                            {{ row.message }}
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </CardContent>
            </Card>

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <Button
                    :disabled="confirmForm.processing"
                    @click="confirmImport"
                >
                    {{ confirmForm.processing ? 'Importing...' : 'Confirm Import' }}
                </Button>
                <Link
                    :href="Actions.index().url"
                    class="text-sm text-muted-foreground hover:text-foreground"
                >
                    Cancel — re-upload
                </Link>
            </div>
        </template>
    </div>
</template>
