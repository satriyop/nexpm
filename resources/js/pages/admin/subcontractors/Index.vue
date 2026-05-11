<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Trash2, Wrench } from 'lucide-vue-next';
import { ref } from 'vue';
import * as Actions from '@/actions/App/Http/Controllers/Admin/SubcontractorController';
import InputError from '@/components/InputError.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

import { dashboard } from '@/routes';
import type { PaginatedData } from '@/types';

interface MainContractor {
    id: number;
    name: string;
}
interface Subcontractor {
    id: number;
    name: string;
    code: string;
    pic: string | null;
    phone: string | null;
    email: string | null;
    main_contractor: MainContractor | null;
}

const props = defineProps<{
    subcontractors: PaginatedData<Subcontractor>;
    mainContractors: MainContractor[];
    per_page: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Subcontractors', href: Actions.index().url },
        ],
    },
});

const open = ref(false);
const form = useForm({
    name: '',
    code: '',
    main_contractor_id: '',
    pic: '',
    phone: '',
    email: '',
});

function submit() {
    form.post(Actions.store().url, {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}

// ── Delete modal ─────────────────────────────────────────────────
const deleteOpen = ref(false);
const deletingSubcontractor = ref<Subcontractor | null>(null);
const deleteForm = useForm<{ id: number }>({ id: 0 });

function openDelete(sc: Subcontractor) {
    deletingSubcontractor.value = sc;
    deleteForm.id = sc.id;
    deleteOpen.value = true;
}

function submitDelete() {
    if (!deletingSubcontractor.value) return;

    deleteForm.delete(Actions.destroy(deletingSubcontractor.value.id).url, {
        onSuccess: () => {
            deleteOpen.value = false;
            deletingSubcontractor.value = null;
        },
    });
}
</script>

<template>
    <Head title="Subcontractors" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Subcontractors</h1>
            <Button @click="open = true"
                ><Plus class="mr-1.5 h-4 w-4" />Add Subcontractor</Button
            >
        </div>

        <Card>
            <CardHeader
                ><CardTitle
                    >All Subcontractors ({{ subcontractors.total }})</CardTitle
                ></CardHeader
            >
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">
                                Name
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Code
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Main Contractor
                            </th>
                            <th class="px-4 py-3 text-left font-medium">PIC</th>
                            <th class="px-4 py-3 text-left font-medium">
                                Phone
                            </th>
                            <th class="px-4 py-3 text-left font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="sc in subcontractors.data"
                            :key="sc.id"
                            class="hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-medium">{{ sc.name }}</td>
                            <td
                                class="px-4 py-3 font-mono text-xs text-muted-foreground"
                            >
                                {{ sc.code }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ sc.main_contractor?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ sc.pic ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ sc.phone ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    @click="openDelete(sc)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!subcontractors.data.length">
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <Wrench
                                        class="size-10 text-muted-foreground/40"
                                    />
                                    <div>
                                        <p class="font-medium text-foreground">
                                            No subcontractors yet
                                        </p>
                                        <p
                                            class="mt-0.5 text-sm text-muted-foreground"
                                        >
                                            Add subcontractors to assign them to
                                            sites.
                                        </p>
                                    </div>
                                    <Button size="sm" @click="open = true">
                                        <Plus class="mr-1.5 h-4 w-4" />Add
                                        Subcontractor
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <PaginationLinks :data="subcontractors" :per-page="props.per_page" />
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="max-w-lg">
            <DialogHeader
                ><DialogTitle>Add Subcontractor</DialogTitle></DialogHeader
            >
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label
                            >Name <span class="text-destructive">*</span></Label
                        >
                        <Input
                            v-model="form.name"
                            placeholder="PT Mitra Jaya…"
                            autofocus
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label
                            >Code <span class="text-destructive">*</span></Label
                        >
                        <Input v-model="form.code" placeholder="MJ-01" />
                        <InputError :message="form.errors.code" />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label
                        >Main Contractor
                        <span class="text-destructive">*</span></Label
                    >
                    <Select v-model="form.main_contractor_id">
                        <SelectTrigger
                            ><SelectValue placeholder="Select contractor"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="mc in mainContractors"
                                :key="mc.id"
                                :value="String(mc.id)"
                                >{{ mc.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.main_contractor_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label>PIC</Label>
                    <Input v-model="form.pic" placeholder="Contact person" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label>Phone</Label>
                        <Input v-model="form.phone" placeholder="+62 812…" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Email</Label>
                        <Input v-model="form.email" type="email" />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Save</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Delete confirmation dialog -->
    <Dialog v-model:open="deleteOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete Subcontractor</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                Are you sure you want to delete
                <span class="font-medium text-foreground">{{ deletingSubcontractor?.name }}</span
                >? This action cannot be undone.
            </p>
            <DialogFooter>
                <Button variant="outline" @click="deleteOpen = false">Cancel</Button>
                <Button
                    variant="destructive"
                    :disabled="deleteForm.processing"
                    @click="submitDelete"
                >
                    Delete
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
