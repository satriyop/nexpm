<script setup lang="ts">
import { Camera, ImageIcon, RefreshCw, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    modelValue: File | null;
    currentUrl?: string | null;
    uploading?: boolean;
    readonly?: boolean;
    deletable?: boolean;
    testId?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [File | null];
    delete: [];
}>();

const inputRef = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);
const compressedSize = ref<string | null>(null);
const error = ref<string | null>(null);
const compressing = ref(false);

async function onFileChange(e: Event) {
    error.value = null;
    const file = (e.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    if (!file.type.startsWith('image/')) {
        error.value = 'Please select an image file (JPG, PNG, etc.).';

        return;
    }

    compressing.value = true;

    try {
        const compressed = await compressToUnder1MB(file);
        previewUrl.value = URL.createObjectURL(compressed);
        compressedSize.value = formatSize(compressed.size);
        emit('update:modelValue', compressed);
    } catch {
        error.value = 'Could not process the image. Please try another.';
    } finally {
        compressing.value = false;

        // Reset input so the same file can be re-selected after a delete
        if (inputRef.value) {
            inputRef.value.value = '';
        }
    }
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

    // Absolute last resort: 640px at 0.4 quality
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
    return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
}
</script>

<template>
    <div class="grid gap-2">
        <!-- Photo preview (local preview takes priority over server URL) -->
        <div v-if="previewUrl || currentUrl" class="relative">
            <img
                :src="previewUrl ?? currentUrl!"
                class="h-40 w-full rounded-lg border object-cover"
                alt="Photo preview"
            />
            <span
                v-if="compressedSize"
                class="absolute right-1 bottom-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-white"
            >
                → {{ compressedSize }}
            </span>
            <!-- Uploading overlay -->
            <div
                v-if="uploading"
                class="absolute inset-0 flex items-center justify-center rounded-lg bg-black/40"
            >
                <RefreshCw class="h-6 w-6 animate-spin text-white" />
            </div>
            <!-- Delete button (shown on server photo when deletable) -->
            <button
                v-if="!readonly && deletable && currentUrl && !previewUrl"
                type="button"
                class="absolute top-1 right-1 flex h-7 w-7 items-center justify-center rounded bg-destructive text-destructive-foreground hover:bg-destructive/90"
                @click="emit('delete')"
            >
                <Trash2 class="h-3.5 w-3.5" />
            </button>
        </div>

        <!-- Gallery picker button -->
        <button
            v-if="!readonly"
            type="button"
            :disabled="compressing || uploading"
            class="flex min-h-[48px] w-full items-center justify-center gap-2 rounded-lg border border-dashed border-input bg-background px-4 py-3 text-sm font-medium text-muted-foreground transition hover:bg-accent hover:text-foreground disabled:cursor-not-allowed disabled:opacity-50"
            @click="inputRef?.click()"
        >
            <RefreshCw v-if="compressing" class="h-4 w-4 animate-spin" />
            <Camera v-else-if="!previewUrl && !currentUrl" class="h-4 w-4" />
            <ImageIcon v-else class="h-4 w-4" />
            <span>{{
                compressing
                    ? 'Compressing…'
                    : previewUrl || currentUrl
                      ? 'Replace Photo'
                      : 'Choose from Gallery'
            }}</span>
        </button>

        <!-- Hidden native file input -->
        <input
            ref="inputRef"
            type="file"
            accept="image/*"
            :data-test="testId"
            class="sr-only"
            @change="onFileChange"
        />

        <p v-if="error" class="text-xs text-destructive">{{ error }}</p>
    </div>
</template>
