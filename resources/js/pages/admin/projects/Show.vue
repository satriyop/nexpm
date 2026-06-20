<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    ClipboardList,
    Download,
    Eye,
    Pencil,
    Search,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import * as AssignmentActions from '@/actions/App/Http/Controllers/Admin/AssignmentController';
import * as AssignmentImport from '@/actions/App/Http/Controllers/Admin/AssignmentImportController';
import * as ProjectActions from '@/actions/App/Http/Controllers/Admin/ProjectController';
import * as SiteActions from '@/actions/App/Http/Controllers/Admin/SiteController';
import * as SiteImport from '@/actions/App/Http/Controllers/Admin/SiteImportController';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
import { dashboard } from '@/routes';
import type { PaginatedData } from '@/types';

interface MainContractor {
    id: number;
    name: string;
}
interface Client {
    id: number;
    name: string;
}
interface SiteType {
    id: number;
    name: string;
}
interface Site {
    id: number;
    site_code: string;
    location_name: string;
    address: string | null;
    province: string | null;
    city: string | null;
    site_type: SiteType | null;
    charging_station_count: number | null;
}
interface Project {
    id: number;
    name: string;
    start_date: string | null;
    end_date: string | null;
    budget: string | null;
    main_contractor: MainContractor | null;
    client: Client | null;
}
interface ImportResult {
    type: 'sites' | 'assignments';
    project_id: number;
    created: number;
    updated: number;
    skipped?: number;
    warnings?: string[];
    errors: string[];
}

const props = defineProps<{
    project: Project;
    sites: PaginatedData<Site>;
    filters: { site_search?: string };
    import: ImportResult | null;
}>();
const siteSearch = ref<string>('');
const isSearchFocused = ref(false);
let searchTimeout: number | null = null;

watch(
    () => props.filters,
    (next) => {
        if (!isSearchFocused.value) {
            siteSearch.value = next?.site_search ?? '';
        }
    },
    { immediate: true },
);

function cancelScheduledSearch(): void {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
    }
}

function applyFilters(): void {
    cancelScheduledSearch();
    const query: Record<string, string> = {};
    if (siteSearch.value.trim()) {
        query.site_search = siteSearch.value.trim();
    }
    router.cancelAll();
    router.get(ProjectActions.show(props.project).url, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function onSearchInput(): void {
    cancelScheduledSearch();
    searchTimeout = window.setTimeout(() => {
        applyFilters();
    }, 400);
}

onBeforeUnmount(() => cancelScheduledSearch());

const importResult = computed(() => props.import);
const importWarnings = computed(() => importResult.value?.warnings ?? []);
const skippedImportRows = computed(() => importResult.value?.skipped ?? 0);
const importHasErrors = computed(
    () => (importResult.value?.errors.length ?? 0) > 0,
);
const importHasWarnings = computed(() => importWarnings.value.length > 0);
const importResultTitle = computed(() => {
    const importType =
        importResult.value?.type === 'sites' ? 'Sites' : 'Assignments';

    if (importHasErrors.value) {
        return `${importType} Import Completed With Errors`;
    }

    if (importHasWarnings.value) {
        return `${importType} Import Completed With Warnings`;
    }

    return `${importType} Import Complete`;
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Projects', href: ProjectActions.index().url },
            { title: 'Detail', href: '#' },
        ],
    },
});

const siteFileRef = ref<HTMLInputElement | null>(null);
const assignmentFileRef = ref<HTMLInputElement | null>(null);

const siteForm = useForm({ file: null as File | null });
const assignmentForm = useForm({ file: null as File | null });

// --- CSV preview ---
type PreviewType = 'sites' | 'assignments';
interface CsvPreview {
    type: PreviewType;
    headers: string[];
    rows: string[][];
    totalRows: number;
}
const preview = ref<CsvPreview | null>(null);
const previewOpen = ref(false);

function detectDelimiter(sample: string): string {
    const semicolons = (sample.match(/;/g) ?? []).length;
    const commas = (sample.match(/,/g) ?? []).length;

    return semicolons >= commas ? ';' : ',';
}

function parseCsvText(text: string): string[][] {
    const sep = detectDelimiter(text.slice(0, 500));

    return text
        .split(/\r?\n/)
        .filter((line) => line.trim() && !line.startsWith('#'))
        .map((line) =>
            line
                .split(sep)
                .map((cell) => cell.trim().replace(/^["']|["']$/g, '')),
        );
}

function openPreview(type: PreviewType): void {
    const fileRef =
        type === 'sites' ? siteFileRef.value : assignmentFileRef.value;
    const file = fileRef?.files?.[0];

    if (!file) {
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        const text = e.target?.result as string;
        const allRows = parseCsvText(text);

        if (allRows.length < 1) {
            return;
        }

        preview.value = {
            type,
            headers: allRows[0],
            rows: allRows.slice(1, 21),
            totalRows: allRows.length - 1,
        };
        previewOpen.value = true;
    };
    reader.readAsText(file);
}

function confirmImport(): void {
    if (!preview.value) {
        return;
    }

    previewOpen.value = false;

    if (preview.value.type === 'sites') {
        submitSiteImport();
    } else {
        submitAssignmentImport();
    }
}

function submitSiteImport() {
    if (!siteFileRef.value?.files?.[0]) {
        return;
    }

    siteForm.file = siteFileRef.value.files[0];
    siteForm.post(SiteImport.store(props.project).url, {
        forceFormData: true,
        onSuccess: () => {
            siteForm.reset();

            if (siteFileRef.value) {
                siteFileRef.value.value = '';
            }
        },
    });
}

function submitAssignmentImport() {
    if (!assignmentFileRef.value?.files?.[0]) {
        return;
    }

    assignmentForm.file = assignmentFileRef.value.files[0];
    assignmentForm.post(AssignmentImport.store(props.project).url, {
        forceFormData: true,
        onSuccess: () => {
            assignmentForm.reset();

            if (assignmentFileRef.value) {
                assignmentFileRef.value.value = '';
            }
        },
    });
}

const formatBudget = (val: string | null) =>
    val ? `IDR ${Number(val).toLocaleString('id-ID')}` : '—';

const page = usePage();
const isSuperAdmin = computed(
    () => (page.props.auth as any)?.user?.role === 'super_admin',
);

const deleteConfirmOpen = ref(false);
const deleteForm = useForm({});

function confirmDelete(): void {
    deleteForm.delete(ProjectActions.destroy(props.project).url, {
        onSuccess: () => {
            deleteConfirmOpen.value = false;
        },
    });
}
</script>

<template>
    <Head :title="project.name" />

    <div class="space-y-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a :href="ProjectActions.index().url">
                    <Button variant="ghost" size="icon"
                        ><ArrowLeft class="h-4 w-4"
                    /></Button>
                </a>
                <h1 class="text-xl font-semibold md:text-2xl">
                    {{ project.name }}
                </h1>
            </div>
            <div class="flex items-center gap-2">
                <a
                    :href="
                        AssignmentActions.index({
                            query: { project_id: project.id },
                        }).url
                    "
                >
                    <Button variant="outline" size="sm">
                        <ClipboardList class="mr-1.5 h-4 w-4" />
                        View Assignments
                    </Button>
                </a>
                <Button
                    v-if="isSuperAdmin"
                    variant="destructive"
                    size="sm"
                    @click="deleteConfirmOpen = true"
                >
                    <Trash2 class="mr-1.5 h-4 w-4" />
                    Delete Project
                </Button>
            </div>
        </div>

        <!-- Delete confirmation dialog -->
        <Dialog v-model:open="deleteConfirmOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Project</DialogTitle>
                    <DialogDescription>
                        This will permanently delete
                        <strong>{{ project.name }}</strong> along with all its
                        sites, assignments, activity data, uploaded files, and
                        reports. This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="deleteConfirmOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        @click="confirmDelete"
                    >
                        {{
                            deleteForm.processing
                                ? 'Deleting…'
                                : 'Yes, delete everything'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Import result alert -->
        <Alert
            v-if="importResult"
            :variant="importHasErrors ? 'destructive' : 'default'"
            :class="
                importHasWarnings && !importHasErrors
                    ? 'border-amber-300 bg-amber-50 text-amber-950'
                    : ''
            "
        >
            <CheckCircle2
                v-if="!importHasErrors && !importHasWarnings"
                class="h-4 w-4"
            />
            <AlertCircle v-else class="h-4 w-4" />
            <AlertTitle>{{ importResultTitle }}</AlertTitle>
            <AlertDescription>
                <p>
                    Created: {{ importResult.created }}, Updated:
                    {{ importResult.updated }}
                    <template v-if="skippedImportRows > 0">
                        , Skipped: {{ skippedImportRows }}
                    </template>
                </p>
                <ul
                    v-if="importWarnings.length"
                    class="mt-2 list-disc pl-4 text-xs text-amber-800"
                >
                    <li v-for="(warning, i) in importWarnings" :key="i">
                        {{ warning }}
                    </li>
                </ul>
                <ul v-if="importHasErrors" class="mt-1 list-disc pl-4 text-xs">
                    <li v-for="(err, i) in importResult.errors" :key="i">
                        {{ err }}
                    </li>
                </ul>
            </AlertDescription>
        </Alert>

        <!-- Project info -->
        <Card>
            <CardHeader><CardTitle>Project Info</CardTitle></CardHeader>
            <CardContent>
                <dl
                    class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm md:grid-cols-3"
                >
                    <div>
                        <dt class="text-muted-foreground">Main Contractor</dt>
                        <dd class="font-medium">
                            {{ project.main_contractor?.name ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Client</dt>
                        <dd class="font-medium">
                            {{ project.client?.name ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Budget</dt>
                        <dd class="font-medium">
                            {{ formatBudget(project.budget) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Start Date</dt>
                        <dd class="font-medium">
                            {{ project.start_date ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">End Date</dt>
                        <dd class="font-medium">
                            {{ project.end_date ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <!-- CSV Imports -->
        <div class="grid gap-4 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <CardTitle class="text-base"
                                >Import Sites</CardTitle
                            >
                            <CardDescription
                                >Upload a CSV file to create or update sites for
                                this project.</CardDescription
                            >
                        </div>
                        <a
                            :href="SiteImport.template(project).url"
                            class="inline-flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                        >
                            <Download class="h-3.5 w-3.5" />
                            Template
                        </a>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex items-end gap-3">
                        <div class="grid flex-1 gap-1.5">
                            <Label>CSV File</Label>
                            <input
                                ref="siteFileRef"
                                type="file"
                                accept=".csv,.txt"
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            />
                        </div>
                        <Button
                            type="button"
                            :disabled="siteForm.processing"
                            size="sm"
                            @click="openPreview('sites')"
                        >
                            <Eye class="mr-1.5 h-3.5 w-3.5" />Preview
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <CardTitle class="text-base"
                                >Import Assignments</CardTitle
                            >
                            <CardDescription
                                >Upload a CSV file to create or update
                                assignments for this project's
                                sites.</CardDescription
                            >
                        </div>
                        <a
                            :href="AssignmentImport.template(project).url"
                            class="inline-flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                        >
                            <Download class="h-3.5 w-3.5" />
                            Template
                        </a>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex items-end gap-3">
                        <div class="grid flex-1 gap-1.5">
                            <Label>CSV File</Label>
                            <input
                                ref="assignmentFileRef"
                                type="file"
                                accept=".csv,.txt"
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            />
                        </div>
                        <Button
                            type="button"
                            :disabled="assignmentForm.processing"
                            size="sm"
                            @click="openPreview('assignments')"
                        >
                            <Eye class="mr-1.5 h-3.5 w-3.5" />Preview
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- CSV preview dialog -->
        <Dialog v-model:open="previewOpen">
            <DialogContent class="max-w-4xl">
                <DialogHeader>
                    <DialogTitle
                        >Preview:
                        {{
                            preview?.type === 'sites' ? 'Sites' : 'Assignments'
                        }}
                        Import</DialogTitle
                    >
                    <DialogDescription>
                        Showing {{ preview?.rows.length }} of
                        {{ preview?.totalRows }} rows. Review before confirming.
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="preview"
                    class="max-h-[60vh] overflow-auto rounded-md border"
                >
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 bg-muted/90">
                            <tr>
                                <th
                                    v-for="(header, i) in preview.headers"
                                    :key="i"
                                    class="px-3 py-2 text-left font-medium text-muted-foreground"
                                >
                                    {{ header }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(row, ri) in preview.rows"
                                :key="ri"
                                class="hover:bg-muted/30"
                            >
                                <td
                                    v-for="(cell, ci) in row"
                                    :key="ci"
                                    class="px-3 py-2"
                                    :class="cell ? '' : 'text-muted-foreground'"
                                >
                                    {{ cell || '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="previewOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        :disabled="
                            (preview?.type === 'sites'
                                ? siteForm
                                : assignmentForm
                            ).processing
                        "
                        @click="confirmImport"
                    >
                        <Upload class="mr-1.5 h-4 w-4" />
                        {{
                            (preview?.type === 'sites'
                                ? siteForm
                                : assignmentForm
                            ).processing
                                ? 'Importing…'
                                : 'Confirm Import'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Sites table -->
        <Card>
            <CardHeader class="space-y-3">
                <CardTitle>Sites ({{ sites.total }})</CardTitle>
                <div class="relative max-w-xs">
                    <Search class="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                        v-model="siteSearch"
                        type="search"
                        placeholder="Site code, location, city…"
                        class="pl-9"
                        @focus="isSearchFocused = true"
                        @blur="isSearchFocused = false"
                        @input="onSearchInput"
                    />
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">
                                Site Code
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Location
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                City
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Province
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Type
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Stations
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="site in sites.data"
                            :key="site.id"
                            class="hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-mono text-xs font-medium">
                                {{ site.site_code }}
                            </td>
                            <td class="px-4 py-3 font-medium">
                                {{ site.location_name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ site.city ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ site.province ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    v-if="site.site_type"
                                    variant="secondary"
                                    >{{ site.site_type.name }}</Badge
                                >
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ site.charging_station_count ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <a
                                        :href="`${SiteActions.edit(site).url}?tab=assignments`"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            title="Manage assignments"
                                        >
                                            <ClipboardList
                                                class="h-3.5 w-3.5"
                                            />
                                        </Button>
                                    </a>
                                    <a :href="SiteActions.edit(site).url">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            title="Edit site"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                        </Button>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!sites.data.length">
                            <td
                                colspan="7"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No sites yet. Import a CSV to get started.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <PaginationLinks :data="sites" />
    </div>
</template>
