<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { AssignmentStatus } from '@/types';

const props = defineProps<{
    status: AssignmentStatus | string;
}>();

const styles: Record<string, string> = {
    PENDING:
        'border-transparent bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
    COMPLETED:
        'border-transparent bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200',
    REVISION:
        'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    VERIFIED:
        'border-transparent bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
    REPORTED:
        'border-transparent bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-200',
};

const labelMap: Record<string, string> = {
    PENDING: 'Pending',
    COMPLETED: 'Completed',
    REVISION: 'Revision',
    VERIFIED: 'Verified',
    REPORTED: 'Reported',
};

const className = computed(
    () => styles[props.status] ?? styles.PENDING,
);

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
