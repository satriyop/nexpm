<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    ExternalLink,
    LockKeyhole,
    MapPin,
    Trash2,
} from 'lucide-vue-next';
import { computed, watch } from 'vue';

function relativeTime(dateStr: string | null | undefined): string {
    if (!dateStr) return 'No activity yet';
    const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
    const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });
    if (diff < 60) return rtf.format(-Math.round(diff), 'second');
    if (diff < 3600) return rtf.format(-Math.round(diff / 60), 'minute');
    if (diff < 86400) return rtf.format(-Math.round(diff / 3600), 'hour');
    if (diff < 2592000) return rtf.format(-Math.round(diff / 86400), 'day');
    if (diff < 31536000) return rtf.format(-Math.round(diff / 2592000), 'month');
    return rtf.format(-Math.round(diff / 31536000), 'year');
}
import * as AdminAssignmentActions from '@/actions/App/Http/Controllers/Admin/AssignmentController';
import ActivityTypeBadge from '@/components/ActivityTypeBadge.vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
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

interface SiteDetail {
    id: number;
    site_code: string;
    location_name: string;
    address: string | null;
    city: string | null;
    province: string | null;
    google_map_url: string | null;
    site_type: { id: number; name: string } | null;
    project: {
        id: number;
        name: string;
        client: { name: string } | null;
        main_contractor: { name: string } | null;
    } | null;
}

interface Subcontractor {
    id: number;
    name: string;
}

const props = defineProps<{
    site: SiteDetail;
    assignments: Assignment[];
    subcontractors: Subcontractor[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Assignments', href: AdminAssignmentActions.index().url },
        ],
    },
});

function getAssignment(activityType: ActivityType): Assignment | null {
    return (
        props.assignments.find((a) => a.activity_type === activityType) ?? null
    );
}

const surveyAssignment = computed(() => getAssignment('SURVEY'));
const constructionAssignment = computed(() => getAssignment('CONSTRUCTION'));
const plnAssignment = computed(() => getAssignment('PLN_CONNECTION'));
const bastAssignment = computed(() => getAssignment('BAST'));

// --- Assign forms (for unassigned cards) ---
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

// --- Reassign forms (for assigned cards) ---
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

// --- Remove ---
function removeAssignment(assignment: Assignment): void {
    router.delete(AdminAssignmentActions.destroy(assignment.id).url, {
        preserveScroll: true,
    });
}

// --- Construction WO form ---
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

const locationParts = computed(() =>
    [props.site.city, props.site.province].filter(Boolean).join(', '),
);
</script>

<template>
    <Head :title="`${site.site_code} — Assignments`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Back + Header -->
        <div class="flex flex-col gap-2">
            <Button
                as-child
                variant="ghost"
                size="sm"
                class="-ml-2 w-fit text-muted-foreground"
            >
                <Link :href="AdminAssignmentActions.index().url">
                    <ArrowLeft class="size-4" />
                    Back to Assignments
                </Link>
            </Button>

            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-2 text-2xl font-semibold">
                    <span>{{ site.site_code }}</span>
                    <span class="text-muted-foreground">·</span>
                    <span class="font-normal text-muted-foreground">{{
                        site.location_name
                    }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-if="locationParts"
                        class="flex items-center gap-1 text-sm text-muted-foreground"
                    >
                        <MapPin class="size-3.5" />
                        {{ locationParts }}
                    </span>
                    <Badge
                        v-if="site.site_type"
                        variant="outline"
                        class="text-xs"
                    >
                        {{ site.site_type.name }}
                    </Badge>
                </div>
            </div>
        </div>

        <!-- Site info card -->
        <Card>
            <CardContent class="grid gap-4 pt-6 sm:grid-cols-2">
                <div>
                    <p class="text-xs text-muted-foreground">Address</p>
                    <p class="font-medium">{{ site.address ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Project</p>
                    <p class="font-medium">{{ site.project?.name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Client</p>
                    <p class="font-medium">
                        {{ site.project?.client?.name ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Main Contractor</p>
                    <p class="font-medium">
                        {{ site.project?.main_contractor?.name ?? '—' }}
                    </p>
                </div>
                <div v-if="site.google_map_url" class="sm:col-span-2">
                    <a
                        :href="site.google_map_url"
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

        <!-- 4 Activity cards in 2×2 grid -->
        <div class="grid gap-4 md:grid-cols-2">
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
                            <Trash2 class="size-3.5" />
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
                            <AlertDescription
                                class="text-xs text-amber-900/90 dark:text-amber-100/90"
                            >
                                {{ surveyAssignment.revision_comment }}
                            </AlertDescription>
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
                                    <SelectTrigger class="h-8 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="sc in subcontractors"
                                            :key="sc.id"
                                            :value="sc.id.toString()"
                                        >
                                            {{ sc.name }}
                                        </SelectItem>
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
                            >
                                Change
                            </Button>
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
                                <SelectTrigger class="h-8 text-xs">
                                    <SelectValue
                                        placeholder="Select subcontractor"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="sc in subcontractors"
                                        :key="sc.id"
                                        :value="sc.id.toString()"
                                    >
                                        {{ sc.name }}
                                    </SelectItem>
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
                            >
                                Assign
                            </Button>
                        </div>
                        <InputError
                            :message="surveyAssignForm.errors.subcontractor_id"
                        />
                    </div>
                </CardContent>
                <Link
                    v-if="surveyAssignment"
                    :href="AdminAssignmentActions.show(surveyAssignment.id).url"
                    class="-mb-6 -mt-6 flex items-center justify-between rounded-b-xl border-t border-primary/20 bg-primary/10 px-5 py-3 text-primary transition-colors hover:bg-primary/15 active:bg-primary/20"
                >
                    <span class="text-xs text-primary/70">
                        Updated {{ relativeTime(surveyAssignment.survey_data?.updated_at ?? surveyAssignment.updated_at) }}
                    </span>
                    <span class="flex items-center gap-1.5 text-xs font-semibold">
                        Details
                        <ArrowRight class="size-3.5" />
                    </span>
                </Link>
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
                            <Trash2 class="size-3.5" />
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
                            <AlertDescription
                                class="text-xs text-amber-900/90 dark:text-amber-100/90"
                            >
                                {{ constructionAssignment.revision_comment }}
                            </AlertDescription>
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
                                    <SelectTrigger class="h-8 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="sc in subcontractors"
                                            :key="sc.id"
                                            :value="sc.id.toString()"
                                        >
                                            {{ sc.name }}
                                        </SelectItem>
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
                            >
                                Change
                            </Button>
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
                                <SelectTrigger class="h-8 text-xs">
                                    <SelectValue
                                        placeholder="Select subcontractor"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="sc in subcontractors"
                                        :key="sc.id"
                                        :value="sc.id.toString()"
                                    >
                                        {{ sc.name }}
                                    </SelectItem>
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
                            >
                                Assign
                            </Button>
                        </div>
                        <InputError
                            :message="
                                constructionAssignForm.errors.subcontractor_id
                            "
                        />
                    </div>
                </CardContent>
                <Link
                    v-if="constructionAssignment"
                    :href="AdminAssignmentActions.show(constructionAssignment.id).url"
                    class="-mb-6 -mt-6 flex items-center justify-between rounded-b-xl border-t border-primary/20 bg-primary/10 px-5 py-3 text-primary transition-colors hover:bg-primary/15 active:bg-primary/20"
                >
                    <span class="text-xs text-primary/70">
                        Updated {{ relativeTime(constructionAssignment.construction_data?.updated_at ?? constructionAssignment.updated_at) }}
                    </span>
                    <span class="flex items-center gap-1.5 text-xs font-semibold">
                        Details
                        <ArrowRight class="size-3.5" />
                    </span>
                </Link>
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
                            <Trash2 class="size-3.5" />
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
                            <AlertDescription
                                class="text-xs text-amber-900/90 dark:text-amber-100/90"
                            >
                                {{ plnAssignment.revision_comment }}
                            </AlertDescription>
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
                                    <SelectTrigger class="h-8 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="sc in subcontractors"
                                            :key="sc.id"
                                            :value="sc.id.toString()"
                                        >
                                            {{ sc.name }}
                                        </SelectItem>
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
                            >
                                Change
                            </Button>
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
                                <SelectTrigger class="h-8 text-xs">
                                    <SelectValue
                                        placeholder="Select subcontractor"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="sc in subcontractors"
                                        :key="sc.id"
                                        :value="sc.id.toString()"
                                    >
                                        {{ sc.name }}
                                    </SelectItem>
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
                            >
                                Assign
                            </Button>
                        </div>
                        <InputError
                            :message="plnAssignForm.errors.subcontractor_id"
                        />
                    </div>
                </CardContent>
                <Link
                    v-if="plnAssignment"
                    :href="AdminAssignmentActions.show(plnAssignment.id).url"
                    class="-mb-6 -mt-6 flex items-center justify-between rounded-b-xl border-t border-primary/20 bg-primary/10 px-5 py-3 text-primary transition-colors hover:bg-primary/15 active:bg-primary/20"
                >
                    <span class="text-xs text-primary/70">
                        Updated {{ relativeTime(plnAssignment.pln_data?.updated_at ?? plnAssignment.updated_at) }}
                    </span>
                    <span class="flex items-center gap-1.5 text-xs font-semibold">
                        Details
                        <ArrowRight class="size-3.5" />
                    </span>
                </Link>
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
                            <Trash2 class="size-3.5" />
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
                            <AlertDescription
                                class="text-xs text-amber-900/90 dark:text-amber-100/90"
                            >
                                {{ bastAssignment.revision_comment }}
                            </AlertDescription>
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
                                    <SelectTrigger class="h-8 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="sc in subcontractors"
                                            :key="sc.id"
                                            :value="sc.id.toString()"
                                        >
                                            {{ sc.name }}
                                        </SelectItem>
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
                            >
                                Change
                            </Button>
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
                                <SelectTrigger class="h-8 text-xs">
                                    <SelectValue
                                        placeholder="Select subcontractor"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="sc in subcontractors"
                                        :key="sc.id"
                                        :value="sc.id.toString()"
                                    >
                                        {{ sc.name }}
                                    </SelectItem>
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
                            >
                                Assign
                            </Button>
                        </div>
                        <InputError
                            :message="bastAssignForm.errors.subcontractor_id"
                        />
                    </div>
                </CardContent>
                <Link
                    v-if="bastAssignment"
                    :href="AdminAssignmentActions.show(bastAssignment.id).url"
                    class="-mb-6 -mt-6 flex items-center justify-between rounded-b-xl border-t border-primary/20 bg-primary/10 px-5 py-3 text-primary transition-colors hover:bg-primary/15 active:bg-primary/20"
                >
                    <span class="text-xs text-primary/70">
                        Updated {{ relativeTime(bastAssignment.bast_data?.updated_at ?? bastAssignment.updated_at) }}
                    </span>
                    <span class="flex items-center gap-1.5 text-xs font-semibold">
                        Details
                        <ArrowRight class="size-3.5" />
                    </span>
                </Link>
            </Card>
        </div>
    </div>
</template>
