<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
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
interface Client { id: number; name: string; pic: string | null; phone: string | null; email: string | null; main_contractor: MainContractor | null }

defineProps<{ clients: PaginatedData<Client>; mainContractors: MainContractor[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Clients', href: Actions.index().url },
        ],
    },
});

const open = ref(false);
const form = useForm({ name: '', main_contractor_id: '', pic: '', phone: '', email: '' });

function submit() {
    form.post(Actions.store().url, { onSuccess: () => { open.value = false; form.reset(); } });
}
</script>

<template>
    <Head title="Clients" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Clients</h1>
            <Button @click="open = true"><Plus class="mr-1.5 h-4 w-4" />Add Client</Button>
        </div>

        <Card>
            <CardHeader><CardTitle>All Clients ({{ clients.total }})</CardTitle></CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Name</th>
                            <th class="px-4 py-3 text-left font-medium">Main Contractor</th>
                            <th class="px-4 py-3 text-left font-medium">PIC</th>
                            <th class="px-4 py-3 text-left font-medium">Phone</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="client in clients.data" :key="client.id" class="hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium">{{ client.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ client.main_contractor?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ client.pic ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ client.phone ?? '—' }}</td>
                        </tr>
                        <tr v-if="!clients.data.length">
                            <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">No clients yet.</td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <PaginationLinks :data="clients" />
    </div>

    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader><DialogTitle>Add Client</DialogTitle></DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label>Name <span class="text-destructive">*</span></Label>
                    <Input v-model="form.name" placeholder="vGreen Indonesia" autofocus />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Main Contractor <span class="text-destructive">*</span></Label>
                    <Select v-model="form.main_contractor_id">
                        <SelectTrigger><SelectValue placeholder="Select contractor" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="mc in mainContractors" :key="mc.id" :value="String(mc.id)">{{ mc.name }}</SelectItem>
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
                    <Button variant="outline" type="button" @click="open = false">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">Save</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
