<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login } from '@/routes';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { 
    ClipboardCheck, 
    Construction, 
    Zap, 
    FileText, 
    ChevronRight,
    LayoutDashboard
} from 'lucide-vue-next';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const workflow = [
    {
        title: 'Site Survey',
        description: 'Detailed site evaluation and technical requirements gathering for EVCS/BSS locations.',
        icon: ClipboardCheck,
        color: 'text-blue-500',
        bg: 'bg-blue-50 dark:bg-blue-900/20'
    },
    {
        title: 'Construction',
        description: 'Real-time tracking of civil and electrical works with progress photo documentation.',
        icon: Construction,
        color: 'text-amber-500',
        bg: 'bg-amber-50 dark:bg-amber-900/20'
    },
    {
        title: 'PLN Connection',
        description: 'Streamlined coordination and documentation for power utility connection and certification.',
        icon: Zap,
        color: 'text-emerald-500',
        bg: 'bg-emerald-50 dark:bg-emerald-900/20'
    },
    {
        title: 'BAST & Reporting',
        description: 'Final handover documentation and automated project status reporting for stakeholders.',
        icon: FileText,
        color: 'text-purple-500',
        bg: 'bg-purple-50 dark:bg-purple-900/20'
    }
];
</script>

<template>
    <Head title="Welcome to NexPM" />
    
    <div class="flex min-h-screen flex-col bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
        <!-- Navigation -->
        <header class="flex items-center justify-between px-6 py-4 lg:px-12">
            <div class="flex items-center gap-2">
                <AppLogoIcon class="size-8" />
                <span class="text-xl font-bold tracking-tight">NexPM</span>
            </div>
            
            <nav class="flex items-center gap-4">
                <template v-if="$page.props.auth.user">
                    <Link
                        :href="dashboard()"
                        class="inline-flex items-center gap-2 rounded-full bg-[#1b1b18] px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-black dark:bg-[#eeeeec] dark:text-[#1c1c1a] dark:hover:bg-white"
                    >
                        <LayoutDashboard class="size-4" />
                        Dashboard
                    </Link>
                </template>
                <template v-else>
                    <Link
                        :href="login()"
                        class="text-sm font-medium hover:text-primary transition-colors"
                    >
                        Log in
                    </Link>
                    <Link
                        v-if="canRegister"
                        href="/register"
                        class="rounded-full bg-[#1b1b18] px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-black dark:bg-[#eeeeec] dark:text-[#1c1c1a] dark:hover:bg-white"
                    >
                        Get Started
                    </Link>
                </template>
            </nav>
        </header>

        <main class="flex-1">
            <!-- Hero Section -->
            <section class="mx-auto max-w-7xl px-6 py-16 text-center lg:py-24">
                <h1 class="mx-auto max-w-4xl text-4xl font-extrabold tracking-tight sm:text-6xl">
                    End-to-End Project Management for 
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#A2D149] to-[#558B2F]">vGreen Infrastructure</span>
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg text-muted-foreground">
                    Streamline your EVCS and BSS installation workflow from initial site survey to final handover reporting. One platform for complete project visibility.
                </p>
                <div class="mt-10 flex items-center justify-center gap-4">
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="login()"
                        class="inline-flex items-center gap-2 rounded-full bg-[#1b1b18] px-8 py-3 text-base font-semibold text-white shadow-lg transition-all hover:-translate-y-0.5 dark:bg-[#eeeeec] dark:text-[#1c1c1a]"
                    >
                        Access Portal
                        <ChevronRight class="size-4" />
                    </Link>
                    <Link
                        v-else
                        :href="dashboard()"
                        class="inline-flex items-center gap-2 rounded-full bg-[#1b1b18] px-8 py-3 text-base font-semibold text-white shadow-lg transition-all hover:-translate-y-0.5 dark:bg-[#eeeeec] dark:text-[#1c1c1a]"
                    >
                        Return to Dashboard
                        <ChevronRight class="size-4" />
                    </Link>
                </div>
            </section>

            <!-- Workflow Section -->
            <section class="mx-auto max-w-7xl px-6 py-12 lg:py-20">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <div 
                        v-for="step in workflow" 
                        :key="step.title"
                        class="group flex flex-col gap-4 rounded-2xl border border-sidebar-border/70 bg-card p-8 transition-all hover:shadow-xl dark:border-sidebar-border"
                    >
                        <div :class="['flex size-12 items-center justify-center rounded-xl transition-transform group-hover:scale-110', step.bg, step.color]">
                            <component :is="step.icon" class="size-6" />
                        </div>
                        <h3 class="text-lg font-bold">{{ step.title }}</h3>
                        <p class="text-sm leading-relaxed text-muted-foreground italic">
                            {{ step.description }}
                        </p>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="mt-auto border-t border-sidebar-border/50 px-6 py-8 dark:border-sidebar-border/20">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 md:flex-row">
                <div class="flex items-center gap-2 opacity-60">
                    <AppLogoIcon class="size-5" />
                    <span class="text-sm font-semibold">NexPM</span>
                </div>
                <p class="text-xs text-muted-foreground">
                    &copy; {{ new Date().getFullYear() }} NexPM. Managing the future of green energy infrastructure.
                </p>
            </div>
        </footer>
    </div>
</template>
