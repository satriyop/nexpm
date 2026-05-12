<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle, Lock } from 'lucide-vue-next';
import { computed } from 'vue';
import * as SubAssignmentIndex from '@/actions/App/Http/Controllers/Subcontractor/AssignmentController';
import BastCheckpoints from '@/components/activities/BastCheckpoints.vue';
import ConstructionForm from '@/components/activities/ConstructionForm.vue';
import PlnForm from '@/components/activities/PlnForm.vue';
import SurveyForm from '@/components/activities/SurveyForm.vue';
import ActivityTypeBadge from '@/components/ActivityTypeBadge.vue';
import AssignmentStepper from '@/components/AssignmentStepper.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { Assignment, AssignmentConstructionData } from '@/types';

const props = defineProps<{
    assignment: Assignment;
    isLocked: boolean;
    siblingConstruction: AssignmentConstructionData | null;
}>();

defineOptions({
    layout: (page: { assignment: Assignment }) => ({
        breadcrumbs: [
            { title: 'My Assignments', href: SubAssignmentIndex.index().url },
            { title: page.assignment.site.site_code, href: '#' },
        ],
    }),
});

const isReadOnly = computed(() =>
    ['VERIFIED', 'REPORTED', 'DROP'].includes(props.assignment.status),
);
</script>

<template>
    <Head :title="`Assignment — ${assignment.site.site_code}`" />

    <div class="space-y-6 p-4">
        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">
                    {{ assignment.site.location_name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ assignment.site.site_code }} ·
                    {{ assignment.site.city }}, {{ assignment.site.province }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ assignment.site.project?.name ?? '—' }} ·
                    {{ assignment.site.project?.main_contractor?.name ?? '—' }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <ActivityTypeBadge :activity-type="assignment.activity_type" />
                <StatusBadge :status="assignment.status" />
            </div>
        </div>

        <!-- Activity stepper -->
        <AssignmentStepper
            :activity-type="assignment.activity_type"
            :status="assignment.status"
        />

        <!-- Revision alert -->
        <Alert
            v-if="assignment.status === 'REVISION'"
            variant="destructive"
            class="border-amber-300 bg-amber-50 text-amber-800"
        >
            <AlertTriangle class="h-4 w-4 !text-amber-600" />
            <AlertDescription class="space-y-2">
                <p class="font-semibold">Revision Requested</p>
                <p class="text-sm leading-relaxed whitespace-pre-wrap">
                    {{ assignment.revision_comment }}
                </p>
            </AlertDescription>
        </Alert>

        <!-- Locked state -->
        <Alert
            v-if="isLocked"
            class="border-yellow-300 bg-yellow-50 text-yellow-800"
        >
            <Lock class="h-4 w-4 !text-yellow-600" />
            <AlertDescription>
                This assignment is locked pending Work Order assignment from
                Admin. Please wait until the WO Number is assigned.
            </AlertDescription>
        </Alert>

        <!-- Verified / Reported / Dropped state -->
        <Alert
            v-else-if="isReadOnly"
            class="border-green-300 bg-green-50 text-green-800"
        >
            <CheckCircle class="h-4 w-4 !text-green-600" />
            <AlertDescription>
                This assignment has been
                <strong>{{
                    assignment.status === 'VERIFIED'
                        ? 'verified'
                        : assignment.status === 'REPORTED'
                          ? 'reported'
                          : 'dropped'
                }}</strong
                >. No further changes can be made.
            </AlertDescription>
        </Alert>

        <!-- Activity-specific forms -->
        <template v-if="!isLocked">
            <SurveyForm
                v-if="assignment.activity_type === 'SURVEY'"
                :assignment="assignment"
                :is-read-only="isReadOnly"
            />
            <PlnForm
                v-else-if="assignment.activity_type === 'PLN_CONNECTION'"
                :assignment="assignment"
                :is-read-only="isReadOnly"
            />
            <ConstructionForm
                v-else-if="assignment.activity_type === 'CONSTRUCTION'"
                :assignment="assignment"
                :is-read-only="isReadOnly"
            />
            <BastCheckpoints
                v-else-if="assignment.activity_type === 'BAST'"
                :assignment="assignment"
                :is-read-only="isReadOnly"
                :sibling-construction="siblingConstruction"
            />
        </template>
    </div>
</template>
