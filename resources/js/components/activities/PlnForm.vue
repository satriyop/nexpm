<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import * as SubActions from '@/actions/App/Http/Controllers/Subcontractor/AssignmentController';
import FileUpload from '@/components/FileUpload.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import type { Assignment } from '@/types';

const props = defineProps<{
    assignment: Assignment;
    isReadOnly: boolean;
}>();

const plnForm = useForm({
    pln_status: props.assignment.pln_data?.pln_status ?? '',
    nidi_slo_date_acquired: props.assignment.pln_data?.nidi_slo_date_acquired ?? '',
    type_rate: props.assignment.pln_data?.type_rate ?? '',
    file_slo: null as File | null,
    file_nidi: null as File | null,
    file_reg: null as File | null,
    file_pk: null as File | null,
    kwh_meter_installation_date: props.assignment.pln_data?.kwh_meter_installation_date ?? '',
    id_pelanggan: props.assignment.pln_data?.id_pelanggan ?? '',
    catatan_progres: props.assignment.pln_data?.catatan_progres ?? '',
});

const plnStatusOptions = [
    'NOT YET REGISTER',
    'WAITING BILLING',
    'WAITING PAYMENT',
    'REBILLING',
    'WAITING KWH',
    'DONE KWH',
];

function submitPln() {
    plnForm.post(SubActions.updatePlnData(props.assignment).url, {
        forceFormData: true,
        onSuccess: () => plnForm.reset('file_slo', 'file_nidi', 'file_reg', 'file_pk'),
    });
}

function storageUrl(path: string) {
    if (!path) return '#';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    return `/storage/${path}`;
}

const docFields = [
    { key: 'file_slo', label: 'File SLO' },
    { key: 'file_nidi', label: 'File NIDI' },
    { key: 'file_reg', label: 'File Reg' },
    { key: 'file_pk', label: 'File PK' },
];
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>PLN Connection Data</CardTitle>
        </CardHeader>
        <CardContent>
            <form class="space-y-4" @submit.prevent="submitPln">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label>PLN Status</Label>
                        <Select v-model="plnForm.pln_status" :disabled="isReadOnly">
                            <SelectTrigger><SelectValue placeholder="Select status" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in plnStatusOptions" :key="opt" :value="opt">
                                    {{ opt }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="plnForm.errors.pln_status" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>NIDI SLO Date Acquired</Label>
                        <Input v-model="plnForm.nidi_slo_date_acquired" type="date" :disabled="isReadOnly" />
                        <InputError :message="plnForm.errors.nidi_slo_date_acquired" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Type Rate</Label>
                        <Input v-model="plnForm.type_rate" :disabled="isReadOnly" placeholder="Type Rate" />
                        <InputError :message="plnForm.errors.type_rate" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>kWh Meter Installation Date</Label>
                        <Input v-model="plnForm.kwh_meter_installation_date" type="date" :disabled="isReadOnly" />
                        <InputError :message="plnForm.errors.kwh_meter_installation_date" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>ID Pelanggan (ID PLN)</Label>
                        <Input v-model="plnForm.id_pelanggan" :disabled="isReadOnly" placeholder="ID Pelanggan" />
                        <InputError :message="plnForm.errors.id_pelanggan" />
                    </div>
                </div>

                <Separator />
                <p class="text-sm font-medium text-muted-foreground">Document Uploads</p>

                <div class="grid gap-4 sm:grid-cols-3">
                    <template v-for="field in docFields" :key="field.key">
                        <div class="grid gap-1.5">
                            <Label>{{ field.label }}</Label>
                            <FileUpload
                                :model-value="(plnForm as any)[field.key]"
                                :current-url="assignment.pln_data?.[field.key as keyof typeof assignment.pln_data] ? storageUrl(String(assignment.pln_data[field.key as keyof typeof assignment.pln_data])) : null"
                                accept=".pdf,.doc,.docx,image/*"
                                :readonly="isReadOnly"
                                @update:model-value="(plnForm as any)[field.key] = $event"
                            />
                            <InputError :message="(plnForm.errors as any)[field.key]" />
                        </div>
                    </template>
                </div>

                <div class="grid gap-1.5">
                    <Label>Catatan Progres</Label>
                    <textarea
                        v-model="plnForm.catatan_progres"
                        :disabled="isReadOnly"
                        rows="3"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Progress notes…"
                    />
                </div>

                <div v-if="!isReadOnly" class="flex justify-end pt-2">
                    <Button type="submit" :disabled="plnForm.processing">
                        {{ plnForm.processing ? 'Saving…' : 'Save PLN Data' }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
