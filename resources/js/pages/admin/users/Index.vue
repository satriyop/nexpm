<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Users } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import * as Actions from '@/actions/App/Http/Controllers/Admin/UserController';
import InputError from '@/components/InputError.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Badge } from '@/components/ui/badge';
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
    main_contractor_id: number;
}
interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    main_contractor: MainContractor | null;
    subcontractor: Subcontractor | null;
}

const props = defineProps<{
    users: PaginatedData<User>;
    mainContractors: MainContractor[];
    subcontractors: Subcontractor[];
    per_page: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Users', href: Actions.index().url },
        ],
    },
});

const open = ref(false);
const form = useForm({
    name: '',
    email: '',
    password: '',
    role: '',
    main_contractor_id: '',
    subcontractor_id: '',
});

watch(
    () => form.role,
    () => {
        form.main_contractor_id = '';
        form.subcontractor_id = '';
    },
);

function submit() {
    form.post(Actions.store().url, {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}

// ── Edit modal ───────────────────────────────────────────────────
const editOpen = ref(false);
const editingUser = ref<User | null>(null);
const editForm = useForm({
    name: '',
    email: '',
    password: '',
    role: '',
    main_contractor_id: '',
    subcontractor_id: '',
});

watch(
    () => editForm.role,
    () => {
        editForm.main_contractor_id = '';
        editForm.subcontractor_id = '';
    },
);

function openEdit(user: User) {
    editingUser.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.role = user.role;
    editForm.main_contractor_id = user.main_contractor ? String(user.main_contractor.id) : '';
    editForm.subcontractor_id = user.subcontractor ? String(user.subcontractor.id) : '';
    editOpen.value = true;
}

function submitEdit() {
    if (!editingUser.value) return;

    editForm.post(Actions.update(editingUser.value.id).url, {
        onSuccess: () => {
            editOpen.value = false;
            editingUser.value = null;
        },
    });
}

const roleBadgeVariant = (role: string) => {
    if (role === 'super_admin') {
        return 'destructive';
    }

    if (role === 'admin') {
        return 'default';
    }

    return 'secondary';
};
</script>

<template>
    <Head title="Users" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Users</h1>
            <Button @click="open = true"
                ><Plus class="mr-1.5 h-4 w-4" />Add User</Button
            >
        </div>

        <Card>
            <CardHeader
                ><CardTitle
                    >All Users ({{ users.total }})</CardTitle
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
                                Email
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Role
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Organization
                            </th>
                            <th class="px-4 py-3 text-left font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ user.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ user.email }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge :variant="roleBadgeVariant(user.role)">{{
                                    user.role.replace('_', ' ')
                                }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{
                                    user.main_contractor?.name ??
                                    user.subcontractor?.name ??
                                    '—'
                                }}
                            </td>
                            <td class="px-4 py-3">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="openEdit(user)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!users.data.length">
                            <td colspan="4" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <Users
                                        class="size-10 text-muted-foreground/40"
                                    />
                                    <div>
                                        <p class="font-medium text-foreground">
                                            No users yet
                                        </p>
                                        <p
                                            class="mt-0.5 text-sm text-muted-foreground"
                                        >
                                            Add team members to give them access
                                            to the platform.
                                        </p>
                                    </div>
                                    <Button size="sm" @click="open = true">
                                        <Plus class="mr-1.5 h-4 w-4" />Add User
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <PaginationLinks :data="users" :per-page="props.per_page" />
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="max-w-lg">
            <DialogHeader><DialogTitle>Add User</DialogTitle></DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label>Name <span class="text-destructive">*</span></Label>
                    <Input
                        v-model="form.name"
                        placeholder="Full name"
                        autofocus
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Email <span class="text-destructive">*</span></Label>
                    <Input
                        v-model="form.email"
                        type="email"
                        placeholder="user@example.com"
                    />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-1.5">
                    <Label
                        >Password <span class="text-destructive">*</span></Label
                    >
                    <Input
                        v-model="form.password"
                        type="password"
                        placeholder="Min 8 characters"
                    />
                    <InputError :message="form.errors.password" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Role <span class="text-destructive">*</span></Label>
                    <Select v-model="form.role">
                        <SelectTrigger
                            ><SelectValue placeholder="Select role"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="admin">Admin</SelectItem>
                            <SelectItem value="subcontractor"
                                >Subcontractor</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.role" />
                </div>

                <div v-if="form.role === 'admin'" class="grid gap-1.5">
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

                <div v-if="form.role === 'subcontractor'" class="grid gap-1.5">
                    <Label
                        >Subcontractor
                        <span class="text-destructive">*</span></Label
                    >
                    <Select v-model="form.subcontractor_id">
                        <SelectTrigger
                            ><SelectValue placeholder="Select subcontractor"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="sc in subcontractors"
                                :key="sc.id"
                                :value="String(sc.id)"
                                >{{ sc.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.subcontractor_id" />
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Create User</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Edit dialog -->
    <Dialog v-model:open="editOpen">
        <DialogContent class="max-w-lg">
            <DialogHeader><DialogTitle>Edit User</DialogTitle></DialogHeader>
            <form class="space-y-4" @submit.prevent="submitEdit">
                <div class="grid gap-1.5">
                    <Label>Name <span class="text-destructive">*</span></Label>
                    <Input v-model="editForm.name" placeholder="Full name" autofocus />
                    <InputError :message="editForm.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Email <span class="text-destructive">*</span></Label>
                    <Input v-model="editForm.email" type="email" placeholder="user@example.com" />
                    <InputError :message="editForm.errors.email" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Password <span class="text-muted-foreground text-xs">(leave blank to keep current)</span></Label>
                    <Input v-model="editForm.password" type="password" placeholder="Min 8 characters" />
                    <InputError :message="editForm.errors.password" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Role <span class="text-destructive">*</span></Label>
                    <Select v-model="editForm.role">
                        <SelectTrigger><SelectValue placeholder="Select role" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="admin">Admin</SelectItem>
                            <SelectItem value="subcontractor">Subcontractor</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="editForm.errors.role" />
                </div>

                <div v-if="editForm.role === 'admin'" class="grid gap-1.5">
                    <Label>Main Contractor <span class="text-destructive">*</span></Label>
                    <Select v-model="editForm.main_contractor_id">
                        <SelectTrigger><SelectValue placeholder="Select contractor" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="mc in mainContractors"
                                :key="mc.id"
                                :value="String(mc.id)"
                            >{{ mc.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="editForm.errors.main_contractor_id" />
                </div>

                <div v-if="editForm.role === 'subcontractor'" class="grid gap-1.5">
                    <Label>Subcontractor <span class="text-destructive">*</span></Label>
                    <Select v-model="editForm.subcontractor_id">
                        <SelectTrigger><SelectValue placeholder="Select subcontractor" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="sc in subcontractors"
                                :key="sc.id"
                                :value="String(sc.id)"
                            >{{ sc.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="editForm.errors.subcontractor_id" />
                </div>

                <DialogFooter>
                    <Button variant="outline" type="button" @click="editOpen = false">Cancel</Button>
                    <Button type="submit" :disabled="editForm.processing">Save Changes</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
