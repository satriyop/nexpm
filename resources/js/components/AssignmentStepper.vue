<script setup lang="ts">
import { CheckCircle2, Circle, ClipboardList, Construction, Plug, FileCheck } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    activityType: 'SURVEY' | 'CONSTRUCTION' | 'PLN_CONNECTION' | 'BAST';
    status: string;
}>();

const steps = [
    { key: 'SURVEY', label: 'Survey', icon: ClipboardList },
    { key: 'CONSTRUCTION', label: 'Construction', icon: Construction },
    { key: 'PLN_CONNECTION', label: 'PLN Connection', icon: Plug },
    { key: 'BAST', label: 'BAST', icon: FileCheck },
] as const;

const currentIndex = computed(() =>
    steps.findIndex((s) => s.key === props.activityType),
);

const isDone = computed(() =>
    ['VERIFIED', 'REPORTED'].includes(props.status),
);
</script>

<template>
    <div class="rounded-lg border bg-card px-4 py-3">
        <p class="mb-3 text-xs font-medium text-muted-foreground uppercase tracking-wide">Activity Progress</p>
        <ol class="flex items-start gap-0">
            <li
                v-for="(step, index) in steps"
                :key="step.key"
                class="flex flex-1 flex-col items-center"
            >
                <!-- Connector line + icon row -->
                <div class="flex w-full items-center">
                    <!-- Left connector -->
                    <div
                        class="h-px flex-1 transition-colors"
                        :class="index === 0 ? 'invisible' : index <= currentIndex ? 'bg-primary' : 'bg-border'"
                    />
                    <!-- Icon bubble -->
                    <div
                        class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 transition-colors"
                        :class="{
                            'border-primary bg-primary text-primary-foreground': index === currentIndex,
                            'border-primary bg-primary/10 text-primary': index < currentIndex || (index === currentIndex && isDone),
                            'border-border bg-background text-muted-foreground': index > currentIndex,
                        }"
                    >
                        <CheckCircle2
                            v-if="index < currentIndex || (index === currentIndex && isDone)"
                            class="h-4 w-4"
                        />
                        <component
                            v-else
                            :is="step.icon"
                            class="h-4 w-4"
                        />
                    </div>
                    <!-- Right connector -->
                    <div
                        class="h-px flex-1 transition-colors"
                        :class="index === steps.length - 1 ? 'invisible' : index < currentIndex ? 'bg-primary' : 'bg-border'"
                    />
                </div>
                <!-- Label -->
                <span
                    class="mt-1.5 text-center text-[11px] leading-tight transition-colors"
                    :class="{
                        'font-semibold text-primary': index === currentIndex,
                        'text-primary/70': index < currentIndex,
                        'text-muted-foreground': index > currentIndex,
                    }"
                >{{ step.label }}</span>
            </li>
        </ol>
    </div>
</template>
