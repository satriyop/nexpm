<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil, Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import * as Actions from '@/actions/App/Http/Controllers/Admin/MainContractorController';
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
import { dashboard } from '@/routes';
import type { PaginatedData } from '@/types';

interface MainContractor {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    pic: string | null;
    logo: string | null;
    logo_url: string | null;
}

defineProps<{ mainContractors: PaginatedData<MainContractor> }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Main Contractors', href: Actions.index().url },
        ],
    },
});

// ── Add modal ──────────────────────────────────────────────────
const addOpen = ref(false);
const addForm = useForm({ name: '', phone: '', email: '', pic: '' });

function submitAdd() {
    addForm.post(Actions.store().url, {
        onSuccess: () => {
            addOpen.value = false;
            addForm.reset();
        },
    });
}

// ── Edit modal ─────────────────────────────────────────────────
const editOpen = ref(false);
const editingContractor = ref<MainContractor | null>(null);
const editForm = useForm<{
    name: string;
    phone: string;
    email: string;
    pic: string;
    logo: File | null;
}>({ name: '', phone: '', email: '', pic: '', logo: null });
const logoPreview = ref<string | null>(null);

function openEdit(mc: MainContractor) {
    editingContractor.value = mc;
    editForm.name = mc.name;
    editForm.phone = mc.phone ?? '';
    editForm.email = mc.email ?? '';
    editForm.pic = mc.pic ?? '';
    editForm.logo = null;
    logoPreview.value = mc.logo_url ?? null;
    editOpen.value = true;
}

function onLogoChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    editForm.logo = file;
    logoPreview.value = file
        ? URL.createObjectURL(file)
        : (editingContractor.value?.logo_url ?? null);
}

function submitEdit() {
    if (!editingContractor.value) {
        return;
    }

    editForm.post(Actions.update(editingContractor.value.id).url, {
        onSuccess: () => {
            editOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="Main Contractors" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Main Contractors</h1>
            <Button @click="addOpen = true"
                ><Plus class="mr-1.5 h-4 w-4" />Add Contractor</Button
            >
        </div>

        <Card>
            <CardHeader
                ><CardTitle
                    >All Contractors ({{ mainContractors.total }})</CardTitle
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
                            <th class="px-4 py-3 text-left font-medium">PIC</th>
                            <th class="px-4 py-3 text-left font-medium">
                                Phone
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Email
                            </th>
                            <th class="px-4 py-3 text-left font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="mc in mainContractors.data"
                            :key="mc.id"
                            class="hover:bg-muted/30"
                        >
                            <td class="px-4 py-3">
                                <img
                                    v-if="mc.logo_url"
                                    :src="mc.logo_url"
                                    :alt="mc.name"
                                    class="h-8 w-8 rounded object-contain"
                                />
                                <div
                                    v-else
                                    class="h-8 w-8 rounded bg-muted"
                                ></div>
                            </td>
                            <td class="px-4 py-3 font-medium">{{ mc.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ mc.pic ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ mc.phone ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ mc.email ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="openEdit(mc)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!mainContractors.data.length">
                            <td
                                colspan="6"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No contractors yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <PaginationLinks :data="mainContractors" />
    </div>

    <!-- Add modal -->
    <Dialog v-model:open="addOpen">
        <DialogContent>
            <DialogHeader
                ><DialogTitle>Add Main Contractor</DialogTitle></DialogHeader
            >
            <form class="space-y-4" @submit.prevent="submitAdd">
                <div class="grid gap-1.5">
                    <Label>Name <span class="text-destructive">*</span></Label>
                    <Input
                        v-model="addForm.name"
                        placeholder="PT Nusantara Energi…"
                        autofocus
                    />
                    <InputError :message="addForm.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label>PIC</Label>
                    <Input
                        v-model="addForm.pic"
                        placeholder="Contact person name"
                    />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label>Phone</Label>
                        <Input v-model="addForm.phone" placeholder="+62 812…" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Email</Label>
                        <Input
                            v-model="addForm.email"
                            type="email"
                            placeholder="admin@company.com"
                        />
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
            <DialogHeader
                ><DialogTitle>Edit Contractor</DialogTitle></DialogHeader
            >
            <form class="space-y-4" @submit.prevent="submitEdit">
                <div class="grid gap-1.5">
                    <Label>Name <span class="text-destructive">*</span></Label>
                    <Input
                        v-model="editForm.name"
                        placeholder="PT Nusantara Energi…"
                        autofocus
                    />
                    <InputError :message="editForm.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label>PIC</Label>
                    <Input
                        v-model="editForm.pic"
                        placeholder="Contact person name"
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
                        <Input
                            v-model="editForm.email"
                            type="email"
                            placeholder="admin@company.com"
                        />
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
</template>
