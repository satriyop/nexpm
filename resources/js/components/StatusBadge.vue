<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { AssignmentStatus } from '@/types';

const props = defineProps<{
    status: AssignmentStatus | string;
}>();

const styles: Record<string, string> = {
    // Shared
    PENDING:
        'border-transparent bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
    DROP: 'border-transparent bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    VERIFIED:
        'border-transparent bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
    REPORTED:
        'border-transparent bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-200',
    // Survey
    SURVEY: 'border-transparent bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-200',
    DOCUMENT:
        'border-transparent bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200',
    // Construction
    CONSTRUCTION:
        'border-transparent bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-200',
    MACHINE_ONSITE:
        'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    DONE: 'border-transparent bg-lime-100 text-lime-700 dark:bg-lime-900/40 dark:text-lime-200',
    LIVE: 'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-200',
    // PLN
    REGISTRATION:
        'border-transparent bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-200',
    BILLING:
        'border-transparent bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-200',
    CONNECTION:
        'border-transparent bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200',
    KWH_DONE:
        'border-transparent bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-200',
    // BAST
    SUBMITTED:
        'border-transparent bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200',
    REVISION:
        'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
};

const labelMap: Record<string, string> = {
    PENDING: 'Pending',
    DROP: 'Drop',
    VERIFIED: 'Verified',
    REPORTED: 'Reported',
    SURVEY: 'Survey',
    DOCUMENT: 'Document',
    CONSTRUCTION: 'Construction',
    MACHINE_ONSITE: 'Machine Onsite',
    DONE: 'Done',
    LIVE: 'Live',
    REGISTRATION: 'Registration',
    BILLING: 'Billing',
    CONNECTION: 'Connection',
    KWH_DONE: 'KWH Done',
    SUBMITTED: 'Submitted',
    REVISION: 'Revision',
};

const className = computed(() => styles[props.status] ?? styles.PENDING);

const label = computed(
    () =>
        labelMap[props.status] ??
        (props.status
            ? props.status.charAt(0) + props.status.slice(1).toLowerCase()
            : ''),
);
</script>

<template>
    <Badge :class="className" variant="outline">
        {{ label }}
    </Badge>
</template>
