<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { ActivityType } from '@/types';

const props = defineProps<{
    activityType: ActivityType | string;
}>();

const labelMap: Record<string, string> = {
    SURVEY: 'Survey',
    PLN_CONNECTION: 'PLN Connection',
    CONSTRUCTION: 'Construction',
    BAST: 'BAST',
};

const styleMap: Record<string, string> = {
    SURVEY: 'border-transparent bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-200',
    PLN_CONNECTION:
        'border-transparent bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200',
    CONSTRUCTION:
        'border-transparent bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-200',
    BAST: 'border-transparent bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-200',
};

const label = computed(
    () => labelMap[props.activityType] ?? props.activityType,
);
const className = computed(
    () => styleMap[props.activityType] ?? styleMap.SURVEY,
);
</script>

<template>
    <Badge :class="className" variant="outline">
        {{ label }}
    </Badge>
</template>
