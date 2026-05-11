<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Briefcase, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import * as Actions from '@/actions/App/Http/Controllers/Admin/ClientController';
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
interface Client {
    id: number;
    name: string;
    pic: string | null;
    phone: string | null;
    email: string | null;
    logo: string | null;
    logo_url: string | null;
    main_contractors: MainContractor[];
}

const props = defineProps<{
    clients: PaginatedData<Client>;
    mainContractors: MainContractor[];
    per_page: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Clients', href: Actions.index().url },
        ],
    },
});

// ── Add modal ──────────────────────────────────────────────────
const addOpen = ref(false);
const addForm = useForm({
    name: '',
    main_contractor_ids: [] as number[],
    pic: '',
    phone: '',
    email: '',
});

function submitAdd() {
    // Normalize and filter
    const selectedIds = addForm.main_contractor_ids.filter(id => id > 0);
    
    if (selectedIds.length === 0) {
        addForm.setError('main_contractor_ids', 'Select at least one main contractor');
        return;
    }
    
    addForm.post(Actions.store().url, {
        onSuccess: () => {
            addOpen.value = false;
            addForm.reset();
        },
    });
}

function toggleMainContractor(id: number) {
    const current = [...addForm.main_contractor_ids];
    const idx = current.indexOf(id);
    if (idx === -1) {
        current.push(id);
    } else {
        current.splice(idx, 1);
    }
    addForm.main_contractor_ids = current;
    addForm.clearErrors('main_contractor_ids');
}

// ── Edit modal ─────────────────────────────────────────────────
const editOpen = ref(false);
const editingClient = ref<Client | null>(null);
const editForm = useForm<{
    name: string;
    main_contractor_ids: number[];
    phone: string;
    email: string;
    pic: string;
    logo: File | null;
}>({ name: '', main_contractor_ids: [], phone: '', email: '', pic: '', logo: null });
const logoPreview = ref<string | null>(null);

function openEdit(client: Client) {
    editingClient.value = client;
    editForm.clearErrors();
    editForm.name = client.name;
    editForm.main_contractor_ids = Array.isArray(client.main_contractors) 
        ? client.main_contractors.map(mc => Number(mc.id)) 
        : [];
    editForm.phone = client.phone ?? '';
    editForm.email = client.email ?? '';
    editForm.pic = client.pic ?? '';
    editForm.logo = null;
    logoPreview.value = client.logo_url ?? null;
    editOpen.value = true;
}

function onLogoChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    editForm.logo = file;
    logoPreview.value = file
        ? URL.createObjectURL(file)
        : (editingClient.value?.logo_url ?? null);
}

function toggleEditMainContractor(id: number) {
    const current = [...editForm.main_contractor_ids];
    const idx = current.indexOf(id);
    if (idx === -1) {
        current.push(id);
    } else {
        current.splice(idx, 1);
    }
    editForm.main_contractor_ids = current;
    editForm.clearErrors('main_contractor_ids');
}

function submitEdit() {
    if (!editingClient.value) {
        return;
    }
    
    // Normalize and filter
    const selectedIds = editForm.main_contractor_ids.filter(id => id > 0);
    
    if (selectedIds.length === 0) {
        editForm.setError('main_contractor_ids', 'Select at least one main contractor');
        return;
    }

    editForm.post(Actions.update(editingClient.value.id).url, {
        onSuccess: () => {
            editOpen.value = false;
        },
    });
}

// ── Delete modal ─────────────────────────────────────────────────
const deleteOpen = ref(false);
const deletingClient = ref<Client | null>(null);
const deleteForm = useForm<{ id: number }>({ id: 0 });

function openDelete(client: Client) {
    deletingClient.value = client;
    deleteForm.id = client.id;
    deleteOpen.value = true;
}

function submitDelete() {
    if (!deletingClient.value) return;

    deleteForm.delete(Actions.destroy(deletingClient.value.id).url, {
        onSuccess: () => {
            deleteOpen.value = false;
            deletingClient.value = null;
        },
    });
}
</script>

<template>
    <Head title="Clients" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Clients</h1>
            <Button @click="addOpen = true"
                ><Plus class="mr-1.5 h-4 w-4" />Add Client</Button
            >
        </div>

        <Card>
            <CardHeader
                ><CardTitle
                    >All Clients ({{ clients.total }})</CardTitle
                ></CardHeader
            >
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">
                                Logo
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Name
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
                            v-for="client in clients.data"
                            :key="client.id"
                            class="hover:bg-muted/30"
                        >
                            <td class="px-4 py-3">
                                <img
                                    v-if="client.logo_url"
                                    :src="client.logo_url"
                                    :alt="client.name"
                                    class="h-8 w-8 rounded object-contain"
                                />
                                <div
                                    v-else
                                    class="h-8 w-8 rounded bg-muted"
                                ></div>
                            </td>
                            <td class="px-4 py-3 font-medium">
                                {{ client.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="mc in client.main_contractors"
                                        :key="mc.id"
                                        class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                                    >
                                        {{ mc.name }}
                                    </span>
                                    <span v-if="!client.main_contractors.length" class="text-muted-foreground">—</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ client.pic ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ client.phone ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="openEdit(client)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                        @click="openDelete(client)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!clients.data.length">
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <Briefcase
                                        class="size-10 text-muted-foreground/40"
                                    />
                                    <div>
                                        <p class="font-medium text-foreground">
                                            No clients yet
                                        </p>
                                        <p
                                            class="mt-0.5 text-sm text-muted-foreground"
                                        >
                                            Add your first client to start
                                            managing projects.
                                        </p>
                                    </div>
                                    <Button size="sm" @click="addOpen = true">
                                        <Plus class="mr-1.5 h-4 w-4" />Add
                                        Client
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <PaginationLinks :data="clients" :per-page="props.per_page" />
    </div>

    <!-- Add modal -->
    <Dialog v-model:open="addOpen">
        <DialogContent>
            <DialogHeader><DialogTitle>Add Client</DialogTitle></DialogHeader>
            <form class="space-y-4" @submit.prevent="submitAdd">
                <div class="grid gap-1.5">
                    <Label>Name <span class="text-destructive">*</span></Label>
                    <Input
                        v-model="addForm.name"
                        placeholder="vGreen Indonesia"
                        autofocus
                    />
                    <InputError :message="addForm.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label
                        >Main Contractor(s)
                        <span class="text-destructive">*</span></Label
                    >
                    <div class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-2">
                        <div
                            v-for="mc in mainContractors"
                            :key="mc.id"
                            class="flex items-center gap-2"
                        >
                            <Checkbox
                                :id="'add-mc-' + mc.id"
                                :checked="addForm.main_contractor_ids.includes(mc.id)"
                                @update:model-value="toggleMainContractor(mc.id)"
                            />
                            <Label
                                :for="'add-mc-' + mc.id"
                                class="cursor-pointer font-normal"
                            >
                                {{ mc.name }}
                            </Label>
                        </div>
                    </div>
                    <InputError :message="addForm.errors.main_contractor_ids" />
                </div>
                <div class="grid gap-1.5">
                    <Label>PIC</Label>
                    <Input v-model="addForm.pic" placeholder="Contact person" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label>Phone</Label>
                        <Input v-model="addForm.phone" placeholder="+62 812…" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Email</Label>
                        <Input v-model="addForm.email" type="email" />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        @click="addOpen = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="addForm.processing"
                        >Save</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Edit modal -->
    <Dialog v-model:open="editOpen">
        <DialogContent>
            <DialogHeader><DialogTitle>Edit Client</DialogTitle></DialogHeader>
            <form class="space-y-4" @submit.prevent="submitEdit">
                <div class="grid gap-1.5">
                    <Label>Name <span class="text-destructive">*</span></Label>
                    <Input v-model="editForm.name" autofocus />
                    <InputError :message="editForm.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label
                        >Main Contractor(s)
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
                            <Label
                                :for="'edit-mc-' + mc.id"
                                class="cursor-pointer font-normal"
                            >
                                {{ mc.name }}
                            </Label>
                        </div>
                    </div>
                    <InputError :message="editForm.errors.main_contractor_ids" />
                </div>
                <div class="grid gap-1.5">
                    <Label>PIC</Label>
                    <Input
                        v-model="editForm.pic"
                        placeholder="Contact person"
                    />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label>Phone</Label>
                        <Input
                            v-model="editForm.phone"
                            placeholder="+62 812…"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Email</Label>
                        <Input v-model="editForm.email" type="email" />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label>Logo</Label>
                    <div v-if="logoPreview" class="mb-2">
                        <img
                            :src="logoPreview"
                            alt="Logo preview"
                            class="h-16 max-w-[160px] rounded border object-contain p-1"
                        />
                    </div>
                    <Input
                        type="file"
                        accept="image/*"
                        @change="onLogoChange"
                    />
                    <InputError :message="editForm.errors.logo" />
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        @click="editOpen = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="editForm.processing"
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
                <DialogTitle>Delete Client</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                Are you sure you want to delete
                <span class="font-medium text-foreground">{{ deletingClient?.name }}</span
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
