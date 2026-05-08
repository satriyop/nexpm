<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil, Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import * as Actions from '@/actions/App/Http/Controllers/Admin/ClientController';
import InputError from '@/components/InputError.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';
import type { PaginatedData } from '@/types';

interface MainContractor { id: number; name: string }
interface Client {
    id: number;
    name: string;
    pic: string | null;
    phone: string | null;
    email: string | null;
    logo: string | null;
    logo_url: string | null;
    main_contractor: MainContractor | null;
}

defineProps<{ clients: PaginatedData<Client>; mainContractors: MainContractor[] }>();

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
const addForm = useForm({ name: '', main_contractor_id: '', pic: '', phone: '', email: '' });

function submitAdd() {
    addForm.post(Actions.store().url, {
        onSuccess: () => { addOpen.value = false; addForm.reset(); },
    });
}

// ── Edit modal ─────────────────────────────────────────────────
const editOpen = ref(false);
const editingClient = ref<Client | null>(null);
const editForm = useForm<{
    name: string;
    phone: string;
    email: string;
    pic: string;
    logo: File | null;
}>({ name: '', phone: '', email: '', pic: '', logo: null });
const logoPreview = ref<string | null>(null);

function openEdit(client: Client) {
    editingClient.value = client;
    editForm.name = client.name;
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
    logoPreview.value = file ? URL.createObjectURL(file) : (editingClient.value?.logo_url ?? null);
}

function submitEdit() {
    if (!editingClient.value) return;
    editForm.post(Actions.update(editingClient.value.id).url, {
        onSuccess: () => { editOpen.value = false; },
    });
}
</script>

<template>
    <Head title="Clients" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Clients</h1>
            <Button @click="addOpen = true"><Plus class="mr-1.5 h-4 w-4" />Add Client</Button>
        </div>

        <Card>
            <CardHeader><CardTitle>All Clients ({{ clients.total }})</CardTitle></CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Logo</th>
                            <th class="px-4 py-3 text-left font-medium">Name</th>
                            <th class="px-4 py-3 text-left font-medium">Main Contractor</th>
                            <th class="px-4 py-3 text-left font-medium">PIC</th>
                            <th class="px-4 py-3 text-left font-medium">Phone</th>
                            <th class="px-4 py-3 text-left font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="client in clients.data" :key="client.id" class="hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <img v-if="client.logo_url" :src="client.logo_url" :alt="client.name" class="h-8 w-8 rounded object-contain" />
                                <div v-else class="h-8 w-8 rounded bg-muted"></div>
                            </td>
                            <td class="px-4 py-3 font-medium">{{ client.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ client.main_contractor?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ client.pic ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ client.phone ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <Button variant="ghost" size="sm" @click="openEdit(client)">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!clients.data.length">
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">No clients yet.</td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <PaginationLinks :data="clients" />
    </div>

    <!-- Add modal -->
    <Dialog v-model:open="addOpen">
        <DialogContent>
            <DialogHeader><DialogTitle>Add Client</DialogTitle></DialogHeader>
            <form class="space-y-4" @submit.prevent="submitAdd">
                <div class="grid gap-1.5">
                    <Label>Name <span class="text-destructive">*</span></Label>
                    <Input v-model="addForm.name" placeholder="vGreen Indonesia" autofocus />
                    <InputError :message="addForm.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Main Contractor <span class="text-destructive">*</span></Label>
                    <Select v-model="addForm.main_contractor_id">
                        <SelectTrigger><SelectValue placeholder="Select contractor" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="mc in mainContractors" :key="mc.id" :value="String(mc.id)">{{ mc.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="addForm.errors.main_contractor_id" />
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
                    <Button variant="outline" type="button" @click="addOpen = false">Cancel</Button>
                    <Button type="submit" :disabled="addForm.processing">Save</Button>
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
                <div class="grid gap-1.5">
                    <Label>Logo</Label>
                    <div v-if="logoPreview" class="mb-2">
                        <img :src="logoPreview" alt="Logo preview" class="h-16 max-w-[160px] rounded border object-contain p-1" />
                    </div>
                    <Input type="file" accept="image/*" @change="onLogoChange" />
                    <InputError :message="editForm.errors.logo" />
                </div>
                <DialogFooter>
                    <Button variant="outline" type="button" @click="editOpen = false">Cancel</Button>
                    <Button type="submit" :disabled="editForm.processing">Save</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
