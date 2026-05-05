<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, ClipboardList, FileBarChart2, FolderKanban, LayoutGrid, Users, Wrench } from 'lucide-vue-next';
import { computed } from 'vue';
import * as AdminAssignmentActions from '@/actions/App/Http/Controllers/Admin/AssignmentController';
import * as AdminReportActions from '@/actions/App/Http/Controllers/Admin/ReportController';
import * as MainContractorActions from '@/actions/App/Http/Controllers/Admin/MainContractorController';
import * as ProjectActions from '@/actions/App/Http/Controllers/Admin/ProjectController';
import * as SubcontractorActions from '@/actions/App/Http/Controllers/Admin/SubcontractorController';
import * as UserActions from '@/actions/App/Http/Controllers/Admin/UserController';
import * as SubAssignmentActions from '@/actions/App/Http/Controllers/Subcontractor/AssignmentController';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
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
import type { NavItem } from '@/types';

const page = usePage();
const role = computed(() => (page.props.auth?.user as any)?.role as string | undefined);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    ];

    if (role.value === 'admin' || role.value === 'super_admin') {
        items.push(
            { title: 'Projects', href: ProjectActions.index().url, icon: FolderKanban },
            { title: 'Assignments', href: AdminAssignmentActions.index().url, icon: ClipboardList },
            { title: 'Reports', href: AdminReportActions.index().url, icon: FileBarChart2 },
            { title: 'Subcontractors', href: SubcontractorActions.index().url, icon: Wrench },
            { title: 'Users', href: UserActions.index().url, icon: Users },
            { title: 'Main Contractors', href: MainContractorActions.index().url, icon: Building2 },
        );
    }

    if (role.value === 'subcontractor') {
        items.push({
            title: 'My Assignments',
            href: SubAssignmentActions.index().url,
            icon: ClipboardList,
        });
    }

    return items;
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
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
