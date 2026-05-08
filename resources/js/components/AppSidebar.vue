<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, ClipboardList, FileBarChart2, FolderKanban, LayoutGrid, Settings2, Users, Wrench, Briefcase } from 'lucide-vue-next';
import { computed } from 'vue';
import * as AdminAssignmentActions from '@/actions/App/Http/Controllers/Admin/AssignmentController';
import * as AdminReportActions from '@/actions/App/Http/Controllers/Admin/ReportController';
import * as ClientActions from '@/actions/App/Http/Controllers/Admin/ClientController';
import * as CompanySettingActions from '@/actions/App/Http/Controllers/Admin/CompanySettingController';
import * as MainContractorActions from '@/actions/App/Http/Controllers/Admin/MainContractorController';
import * as ProjectActions from '@/actions/App/Http/Controllers/Admin/ProjectController';
import * as SubcontractorActions from '@/actions/App/Http/Controllers/Admin/SubcontractorController';
import * as UserActions from '@/actions/App/Http/Controllers/Admin/UserController';
import * as SubAssignmentActions from '@/actions/App/Http/Controllers/Subcontractor/AssignmentController';
import AppLogo from '@/components/AppLogo.vue';
import NavMain, { type NavGroup } from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';

const page = usePage();
const role = computed(() => (page.props.auth?.user as any)?.role as string | undefined);

const navGroups = computed<NavGroup[]>(() => {
    if (role.value === 'admin' || role.value === 'super_admin') {
        return [
            {
                label: 'Operations',
                items: [
                    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
                    { title: 'Projects', href: ProjectActions.index().url, icon: FolderKanban },
                    { title: 'Assignments', href: AdminAssignmentActions.index().url, icon: ClipboardList },
                    { title: 'Reports', href: AdminReportActions.index().url, icon: FileBarChart2 },
                ],
            },
            {
                label: 'Configuration',
                items: [
                    { title: 'Clients', href: ClientActions.index().url, icon: Briefcase },
                    { title: 'Main Contractors', href: MainContractorActions.index().url, icon: Building2 },
                    { title: 'Subcontractors', href: SubcontractorActions.index().url, icon: Wrench },
                    { title: 'Users', href: UserActions.index().url, icon: Users },
                    { title: 'App Settings', href: CompanySettingActions.index().url, icon: Settings2 },
                ],
            },
        ];
    }

    if (role.value === 'subcontractor') {
        return [
            {
                label: 'Menu',
                items: [
                    { title: 'My Assignments', href: SubAssignmentActions.index().url, icon: ClipboardList },
                ],
            },
        ];
    }

    return [
        {
            label: 'Menu',
            items: [
                { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            ],
        },
    ];
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="navGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
