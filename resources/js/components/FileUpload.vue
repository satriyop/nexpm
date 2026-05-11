<script setup lang="ts">
import { ExternalLink, FileText, RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    modelValue: File | null;
    currentUrl?: string | null;
    accept?: string;
    uploading?: boolean;
    readonly?: boolean;
    testId?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [File | null];
}>();

const inputRef = ref<HTMLInputElement | null>(null);
const selectedName = ref<string | null>(null);

function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    selectedName.value = file?.name ?? null;
    emit('update:modelValue', file);
}
</script>

<template>
    <div class="grid gap-2">
        <!-- Current file link -->
        <a
            v-if="currentUrl"
            :href="currentUrl"
            target="_blank"
            class="flex items-center gap-1.5 text-sm text-blue-600 underline"
        >
            <ExternalLink class="h-3.5 w-3.5 shrink-0" />
            <span class="truncate">View current file</span>
        </a>

        <!-- Selected filename -->
        <p v-if="selectedName" class="truncate text-xs text-muted-foreground">
            Selected: {{ selectedName }}
        </p>

        <!-- Picker button -->
        <button
            v-if="!readonly"
            type="button"
            :disabled="uploading"
            class="flex min-h-[48px] w-full items-center justify-center gap-2 rounded-lg border border-dashed border-input bg-background px-4 py-3 text-sm font-medium text-muted-foreground transition hover:bg-accent hover:text-foreground disabled:cursor-not-allowed disabled:opacity-50"
            @click="inputRef?.click()"
        >
            <RefreshCw v-if="uploading" class="h-4 w-4 animate-spin" />
            <FileText v-else class="h-4 w-4" />
            <span>{{
                currentUrl || selectedName ? 'Replace File' : 'Choose File'
            }}</span>
        </button>

        <!-- Hidden native file input -->
        <input
            ref="inputRef"
            type="file"
            :accept="accept"
            :data-test="testId"
            class="sr-only"
            @change="onFileChange"
        />
    </div>
</template>
