<script setup lang="ts">
import { AlertCircle, ExternalLink, FileText, RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    modelValue: File | null;
    currentUrl?: string | null;
    accept?: string;
    uploading?: boolean;
    readonly?: boolean;
    testId?: string;
    /** Client-side max allowed size in kilobytes for non-image files (default 20 MB = 20480 KB) */
    maxSizeKb?: number;
}>();

const emit = defineEmits<{
    'update:modelValue': [File | null];
}>();

const inputRef = ref<HTMLInputElement | null>(null);
const selectedName = ref<string | null>(null);
const compressedSize = ref<string | null>(null);
const compressing = ref(false);
const error = ref<string | null>(null);

async function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    error.value = null;
    compressedSize.value = null;

    if (!file) {
        return;
    }

    if (file.type.startsWith('image/')) {
        compressing.value = true;
        try {
            const compressed = await compressToUnder1MB(file);
            compressedSize.value = formatSize(compressed.size);
            selectedName.value = compressed.name;
            emit('update:modelValue', compressed);
        } catch {
            error.value = 'Could not process the image. Please try another.';
        } finally {
            compressing.value = false;
            if (inputRef.value) {
                inputRef.value.value = '';
            }
        }
        return;
    }

    // Non-image files (PDF, DWG, etc.) — validate size and pass through as-is
    const limitKb = props.maxSizeKb ?? 20480;
    if (file.size > limitKb * 1024) {
        const label =
            limitKb >= 1024
                ? `${(limitKb / 1024).toFixed(0)} MB`
                : `${limitKb} KB`;
        error.value = `File too large. Maximum allowed size is ${label}.`;
        if (inputRef.value) {
            inputRef.value.value = '';
        }
        emit('update:modelValue', null);
        return;
    }

    selectedName.value = file.name;
    emit('update:modelValue', file);
}

async function compressToUnder1MB(file: File): Promise<File> {
    const ONE_MB = 1024 * 1024;

    if (file.size <= ONE_MB) {
        return file;
    }

    const img = await loadImage(file);

    for (const maxWidth of [1920, 1280, 960]) {
        for (const quality of [0.82, 0.7, 0.6, 0.5]) {
            const blob = await drawAndEncode(img, maxWidth, quality);
            if (blob.size <= ONE_MB) {
                return new File([blob], file.name.replace(/\.[^.]+$/, '.jpg'), {
                    type: 'image/jpeg',
                });
            }
        }
    }

    const blob = await drawAndEncode(img, 640, 0.4);
    return new File([blob], file.name.replace(/\.[^.]+$/, '.jpg'), {
        type: 'image/jpeg',
    });
}

function loadImage(file: File): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = URL.createObjectURL(file);
    });
}

function drawAndEncode(
    img: HTMLImageElement,
    maxWidth: number,
    quality: number,
): Promise<Blob> {
    const scale = Math.min(1, maxWidth / img.naturalWidth);
    const w = Math.round(img.naturalWidth * scale);
    const h = Math.round(img.naturalHeight * scale);
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    canvas.getContext('2d')!.drawImage(img, 0, 0, w, h);

    return new Promise((resolve, reject) =>
        canvas.toBlob(
            (b) => (b ? resolve(b) : reject(new Error('toBlob failed'))),
            'image/jpeg',
            quality,
        ),
    );
}

function formatSize(bytes: number): string {
    return `→ ${(bytes / 1024 / 1024).toFixed(2)} MB`;
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

        <!-- Selected filename + compressed size -->
        <p
            v-if="selectedName"
            class="flex items-center gap-1 truncate text-xs text-muted-foreground"
        >
            <span class="truncate">Selected: {{ selectedName }}</span>
            <span v-if="compressedSize" class="shrink-0 font-medium">{{ compressedSize }}</span>
        </p>

        <!-- Error -->
        <p
            v-if="error"
            class="flex items-center gap-1 text-xs text-destructive"
        >
            <AlertCircle class="h-3.5 w-3.5 shrink-0" />
            {{ error }}
        </p>

        <!-- Picker button -->
        <button
            v-if="!readonly"
            type="button"
            :disabled="compressing || uploading"
            class="flex min-h-[48px] w-full items-center justify-center gap-2 rounded-lg border border-dashed border-input bg-background px-4 py-3 text-sm font-medium text-muted-foreground transition hover:bg-accent hover:text-foreground disabled:cursor-not-allowed disabled:opacity-50"
            @click="inputRef?.click()"
        >
            <RefreshCw
                v-if="compressing || uploading"
                class="h-4 w-4 animate-spin"
            />
            <FileText v-else class="h-4 w-4" />
            <span>{{
                compressing
                    ? 'Compressing…'
                    : currentUrl || selectedName
                      ? 'Replace File'
                      : 'Choose File'
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
