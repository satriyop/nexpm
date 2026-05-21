<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, Wrench } from 'lucide-vue-next';
import { ref } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import * as Actions from '@/actions/App/Http/Controllers/Admin/SubcontractorController';
import InputError from '@/components/InputError.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
    main_contractors: MainContractor[];
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

const { canEdit } = usePermissions();

const open = ref(false);
const form = useForm({
    name: '',
    code: '',
    main_contractor_ids:
        props.mainContractors.length === 1 ? [props.mainContractors[0].id] : [],
    pic: '',
    phone: '',
    email: '',
});

function toggleMainContractor(id: number) {
    const current = [...form.main_contractor_ids];
    const index = current.indexOf(id);

    if (index === -1) {
        current.push(id);
    } else {
        current.splice(index, 1);
    }

    form.main_contractor_ids = current;
    form.clearErrors('main_contractor_ids');
}

function submit() {
    form.post(Actions.store().url, {
        onSuccess: () => {
            open.value = false;
            form.reset('name', 'code', 'pic', 'phone', 'email');
            form.main_contractor_ids =
                props.mainContractors.length === 1
                    ? [props.mainContractors[0].id]
                    : [];
        },
    });
}

// ── Edit modal ───────────────────────────────────────────────────
const editOpen = ref(false);
const editingSubcontractor = ref<Subcontractor | null>(null);
const editForm = useForm({
    name: '',
    code: '',
    main_contractor_ids: [] as number[],
    pic: '',
    phone: '',
    email: '',
});

function openEdit(sc: Subcontractor) {
    editingSubcontractor.value = sc;
    editForm.name = sc.name;
    editForm.code = sc.code;
    editForm.main_contractor_ids = sc.main_contractors.map((mc) => mc.id);
    editForm.pic = sc.pic ?? '';
    editForm.phone = sc.phone ?? '';
    editForm.email = sc.email ?? '';
    editOpen.value = true;
}

function toggleEditMainContractor(id: number) {
    const current = [...editForm.main_contractor_ids];
    const index = current.indexOf(id);

    if (index === -1) {
        current.push(id);
    } else {
        current.splice(index, 1);
    }

    editForm.main_contractor_ids = current;
    editForm.clearErrors('main_contractor_ids');
}

function submitEdit() {
    if (!editingSubcontractor.value) {
        return;
    }

    editForm.post(Actions.update(editingSubcontractor.value.id).url, {
        onSuccess: () => {
            editOpen.value = false;
            editingSubcontractor.value = null;
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
    if (!deletingSubcontractor.value) {
        return;
    }

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
            <Button v-if="canEdit" @click="open = true"
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
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="mc in sc.main_contractors"
                                        :key="mc.id"
                                        class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                                    >
                                        {{ mc.name }}
                                    </span>
                                    <span
                                        v-if="!sc.main_contractors.length"
                                        class="text-muted-foreground"
                                        >—</span
                                    >
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ sc.pic ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ sc.phone ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="canEdit" class="flex items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="openEdit(sc)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                        @click="openDelete(sc)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
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
                    <div
                        class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-2"
                    >
                        <div
                            v-for="mc in mainContractors"
                            :key="mc.id"
                            class="flex items-center gap-2"
                        >
                            <Checkbox
                                :id="'subcon-mc-' + mc.id"
                                :checked="
                                    form.main_contractor_ids.includes(mc.id)
                                "
                                @update:model-value="
                                    toggleMainContractor(mc.id)
                                "
                            />
                            <Label
                                :for="'subcon-mc-' + mc.id"
                                class="cursor-pointer font-normal"
                            >
                                {{ mc.name }}
                            </Label>
                        </div>
                    </div>
                    <InputError :message="form.errors.main_contractor_ids" />
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

    <!-- Edit dialog -->
    <Dialog v-model:open="editOpen">
        <DialogContent class="max-w-lg">
            <DialogHeader
                ><DialogTitle>Edit Subcontractor</DialogTitle></DialogHeader
            >
            <form class="space-y-4" @submit.prevent="submitEdit">
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label
                            >Name <span class="text-destructive">*</span></Label
                        >
                        <Input v-model="editForm.name" placeholder="PT Mitra Jaya…" autofocus />
                        <InputError :message="editForm.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label
                            >Code <span class="text-destructive">*</span></Label
                        >
                        <Input v-model="editForm.code" placeholder="MJ-01" />
                        <InputError :message="editForm.errors.code" />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label
                        >Main Contractor
                        <span class="text-destructive">*</span></Label
                    >
                    <div class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-2">
                        <div
                            v-for="mc in mainContractors"
                            :key="mc.id"
                            class="flex items-center gap-2"
                        >
                            <Checkbox
                                :id="'edit-mc-' + mc.id"
                                :checked="editForm.main_contractor_ids.includes(mc.id)"
                                @update:model-value="toggleEditMainContractor(mc.id)"
                            />
                            <Label :for="'edit-mc-' + mc.id" class="cursor-pointer font-normal">
                                {{ mc.name }}
                            </Label>
                        </div>
                    </div>
                    <InputError :message="editForm.errors.main_contractor_ids" />
                </div>
                <div class="grid gap-1.5">
                    <Label>PIC</Label>
                    <Input v-model="editForm.pic" placeholder="Contact person" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label>Phone</Label>
                        <Input v-model="editForm.phone" placeholder="+62 812…" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Email</Label>
                        <Input v-model="editForm.email" type="email" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" type="button" @click="editOpen = false">Cancel</Button>
                    <Button type="submit" :disabled="editForm.processing">Save Changes</Button>
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
                <span class="font-medium text-foreground">{{
                    deletingSubcontractor?.name
                }}</span
                >? This action cannot be undone.
            </p>
            <DialogFooter>
                <Button variant="outline" @click="deleteOpen = false"
                    >Cancel</Button
                >
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
