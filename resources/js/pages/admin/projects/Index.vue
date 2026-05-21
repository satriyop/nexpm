<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, FileSpreadsheet, FolderKanban, Plus } from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import { ref } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import * as ImportActions from '@/actions/App/Http/Controllers/Admin/ManualProjectImportController';
import * as Actions from '@/actions/App/Http/Controllers/Admin/ProjectController';
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
interface Client {
    id: number;
    name: string;
    main_contractors: MainContractor[];
}
interface Project {
    id: number;
    name: string;
    start_date: string | null;
    end_date: string | null;
    budget: string | null;
    main_contractor: MainContractor | null;
    client: Client | null;
}

const props = defineProps<{
    projects: PaginatedData<Project>;
    mainContractors: MainContractor[];
    clients: Client[];
    per_page: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Projects', href: Actions.index().url },
        ],
    },
});

const { canEdit } = usePermissions();

const open = ref(false);

const form = useForm({
    name: '',
    main_contractor_id: '',
    client_id: '',
    start_date: '',
    end_date: '',
    budget: '',
});

function onMcChange(value: AcceptableValue): void {
    if (value == null) {
        return;
    }

    form.main_contractor_id = String(value);
    form.client_id = '';
}

function submit() {
    form.post(Actions.store().url);
}
</script>

<template>
    <Head title="Projects" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Projects</h1>
            <div v-if="canEdit" class="flex items-center gap-2">
                <Button variant="outline" as-child>
                    <Link :href="ImportActions.index().url">
                        <FileSpreadsheet class="mr-1.5 h-4 w-4" />Import Manual Projects
                    </Link>
                </Button>
                <Button @click="open = true"
                    ><Plus class="mr-1.5 h-4 w-4" />New Project</Button
                >
            </div>
        </div>

        <Card>
            <CardHeader
                ><CardTitle
                    >All Projects ({{ projects.total }})</CardTitle
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
                                Contractor
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Client
                            </th>
                            <th class="px-4 py-3 text-left font-medium">
                                Start
                            </th>
                            <th class="px-4 py-3 text-left font-medium">End</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="project in projects.data"
                            :key="project.id"
                            class="hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ project.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ project.main_contractor?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ project.client?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ project.start_date ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ project.end_date ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <Link :href="Actions.show(project).url">
                                    <Button variant="ghost" size="sm"
                                        ><Eye class="h-4 w-4"
                                    /></Button>
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!projects.data.length">
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <FolderKanban
                                        class="size-10 text-muted-foreground/40"
                                    />
                                    <div>
                                        <p class="font-medium text-foreground">
                                            No projects yet
                                        </p>
                                        <p
                                            class="mt-0.5 text-sm text-muted-foreground"
                                        >
                                            Create your first project to get
                                            started.
                                        </p>
                                    </div>
                                    <Button size="sm" @click="open = true">
                                        <Plus class="mr-1.5 h-4 w-4" />New
                                        Project
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <PaginationLinks :data="projects" :per-page="props.per_page" />
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="max-w-lg">
            <DialogHeader><DialogTitle>New Project</DialogTitle></DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label
                        >Project Name
                        <span class="text-destructive">*</span></Label
                    >
                    <Input
                        v-model="form.name"
                        placeholder="EVCS Rollout Phase 1"
                        autofocus
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label
                        >Main Contractor
                        <span class="text-destructive">*</span></Label
                    >
                    <Select
                        :model-value="form.main_contractor_id"
                        @update:model-value="onMcChange"
                    >
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
                    <Label
                        >Client <span class="text-destructive">*</span></Label
                    >
                    <Select v-model="form.client_id">
                        <SelectTrigger
                            ><SelectValue placeholder="Select client"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="c in clients"
                                :key="c.id"
                                :value="String(c.id)"
                                >{{ c.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.client_id" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label>Start Date</Label>
                        <Input v-model="form.start_date" type="date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>End Date</Label>
                        <Input v-model="form.end_date" type="date" />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label>Budget (IDR)</Label>
                    <Input
                        v-model="form.budget"
                        type="number"
                        placeholder="0"
                    />
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Create Project</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
