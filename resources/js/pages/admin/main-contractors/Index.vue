<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import * as Actions from '@/actions/App/Http/Controllers/Admin/MainContractorController';
import InputError from '@/components/InputError.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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

const open = ref(false);
const form = useForm({ name: '', phone: '', email: '', pic: '' });

function submit() {
    form.post(Actions.store().url, { onSuccess: () => { open.value = false; form.reset(); } });
}
</script>

<template>
    <Head title="Main Contractors" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Main Contractors</h1>
            <Button @click="open = true"><Plus class="mr-1.5 h-4 w-4" />Add Contractor</Button>
        </div>

        <Card>
            <CardHeader><CardTitle>All Contractors ({{ mainContractors.total }})</CardTitle></CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Name</th>
                            <th class="px-4 py-3 text-left font-medium">PIC</th>
                            <th class="px-4 py-3 text-left font-medium">Phone</th>
                            <th class="px-4 py-3 text-left font-medium">Email</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="mc in mainContractors.data" :key="mc.id" class="hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium">{{ mc.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ mc.pic ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ mc.phone ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ mc.email ?? '—' }}</td>
                        </tr>
                        <tr v-if="!mainContractors.data.length">
                            <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">No contractors yet.</td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <PaginationLinks :data="mainContractors" />
    </div>

    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader><DialogTitle>Add Main Contractor</DialogTitle></DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label>Name <span class="text-destructive">*</span></Label>
                    <Input v-model="form.name" placeholder="PT Nusantara Energi…" autofocus />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label>PIC</Label>
                    <Input v-model="form.pic" placeholder="Contact person name" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label>Phone</Label>
                        <Input v-model="form.phone" placeholder="+62 812…" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Email</Label>
                        <Input v-model="form.email" type="email" placeholder="admin@company.com" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" type="button" @click="open = false">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">Save</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
