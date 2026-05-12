<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import * as SubActions from '@/actions/App/Http/Controllers/Subcontractor/AssignmentController';
import InputError from '@/components/InputError.vue';
import PhotoUpload from '@/components/PhotoUpload.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Assignment } from '@/types';

const props = defineProps<{
    assignment: Assignment;
    isReadOnly: boolean;
}>();

const constructionForm = useForm({
    cons_actual_start_date:
        props.assignment.construction_data?.cons_actual_start_date ?? '',
    cons_actual_done_date:
        props.assignment.construction_data?.cons_actual_done_date ?? '',
    machine_serial_number:
        props.assignment.construction_data?.machine_serial_number ?? '',
    catatan_progres: props.assignment.construction_data?.catatan_progres ?? '',
});

function submitConstruction() {
    constructionForm.patch(
        SubActions.updateConstructionData(props.assignment).url,
    );
}

const uploadingPhoto = ref(false);
const constructionUploadKey = ref(0);

function onConstructionPhotoSelected(file: File | null) {
    if (!file) {
        return;
    }

    const form = useForm({ photo: file });
    uploadingPhoto.value = true;
    form.post(SubActions.storeConstructionPhoto(props.assignment).url, {
        forceFormData: true,
        onFinish: () => {
            uploadingPhoto.value = false;
            constructionUploadKey.value++;
        },
    });
}

function destroyConstructionPhoto(photoId: number) {
    const form = useForm({});
    form.delete(
        SubActions.destroyConstructionPhoto({
            assignment: props.assignment.id,
            photo: photoId,
        }).url,
    );
}

function storageUrl(path: string) {
    if (!path) {
        return '#';
    }

    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }

    return `/storage/${path}`;
}
</script>

<template>
    <div class="space-y-6">
        <!-- Admin fields (read-only) -->
        <Card class="border-muted bg-muted/30">
            <CardHeader>
                <CardTitle class="text-base text-muted-foreground"
                    >Work Order (filled by Admin)</CardTitle
                >
            </CardHeader>
            <CardContent>
                <dl class="grid gap-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            WO Number
                        </dt>
                        <dd>
                            {{
                                assignment.construction_data?.cons_wo_number ??
                                '—'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            Project Status
                        </dt>
                        <dd>
                            {{
                                assignment.construction_data?.project_status ??
                                '—'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            Setup Approval Date
                        </dt>
                        <dd>
                            {{
                                assignment.construction_data
                                    ?.setup_approval_date ?? '—'
                            }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <!-- Subcon fields -->
        <Card>
            <CardHeader>
                <CardTitle>Construction Data</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="space-y-4" @submit.prevent="submitConstruction">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Actual Start Date</Label>
                            <Input
                                v-model="
                                    constructionForm.cons_actual_start_date
                                "
                                type="date"
                                :disabled="isReadOnly"
                            />
                            <InputError
                                :message="
                                    constructionForm.errors
                                        .cons_actual_start_date
                                "
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Actual Done Date</Label>
                            <Input
                                v-model="constructionForm.cons_actual_done_date"
                                type="date"
                                :disabled="isReadOnly"
                            />
                            <InputError
                                :message="
                                    constructionForm.errors
                                        .cons_actual_done_date
                                "
                            />
                        </div>
                        <div class="grid gap-1.5 sm:col-span-2">
                            <Label>Machine Serial Number</Label>
                            <Input
                                v-model="constructionForm.machine_serial_number"
                                :disabled="isReadOnly"
                                placeholder="Machine SN"
                            />
                            <InputError
                                :message="
                                    constructionForm.errors
                                        .machine_serial_number
                                "
                            />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Catatan Progres</Label>
                        <textarea
                            v-model="constructionForm.catatan_progres"
                            :disabled="isReadOnly"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Progress notes…"
                        />
                    </div>
                    <div v-if="!isReadOnly" class="flex justify-end">
                        <Button
                            type="submit"
                            :disabled="constructionForm.processing"
                        >
                            {{
                                constructionForm.processing
                                    ? 'Saving…'
                                    : 'Save Construction Data'
                            }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Progress photos -->
        <Card>
            <CardHeader>
                <CardTitle>Progress Photos</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div
                    v-if="
                        assignment.construction_data?.construction_photos
                            ?.length
                    "
                    class="flex flex-wrap gap-3"
                >
                    <div
                        v-for="photo in assignment.construction_data
                            .construction_photos"
                        :key="photo.id"
                        class="relative"
                    >
                        <img
                            :src="storageUrl(photo.path)"
                            class="h-24 w-24 rounded border object-cover"
                            alt="Progress photo"
                        />
                        <Button
                            v-if="!isReadOnly"
                            type="button"
                            variant="destructive"
                            size="sm"
                            class="absolute top-1 right-1 h-6 px-2 text-xs"
                            @click="destroyConstructionPhoto(photo.id)"
                            >×</Button
                        >
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No photos uploaded yet.
                </p>

                <div
                    v-if="
                        !isReadOnly &&
                        (assignment.construction_data?.construction_photos
                            ?.length ?? 0) < 2
                    "
                    class="grid gap-1.5"
                >
                    <Label>Upload Progress Photo</Label>
                    <PhotoUpload
                        :key="constructionUploadKey"
                        :model-value="null"
                        :uploading="uploadingPhoto"
                        @update:model-value="onConstructionPhotoSelected"
                    />
                </div>
                <p
                    v-else-if="
                        !isReadOnly &&
                        (assignment.construction_data?.construction_photos
                            ?.length ?? 0) >= 2
                    "
                    class="text-xs text-muted-foreground"
                >
                    Maximum of 2 progress photos reached.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
