<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import * as Actions from '@/actions/App/Http/Controllers/Admin/CompanySettingController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

const props = defineProps<{
    pdfFooterNote: string;
    reportEmail: string;
    surveySlaDays: number;
    constructionSlaDays: number;
    slowDays: number;
    stalledDays: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'App Settings', href: Actions.index().url },
        ],
    },
});

const form = useForm({
    pdf_footer_note: props.pdfFooterNote,
    report_email: props.reportEmail,
    survey_sla_days: props.surveySlaDays,
    construction_sla_days: props.constructionSlaDays,
    slow_days: props.slowDays,
    stalled_days: props.stalledDays,
});

function submit() {
    form.post(Actions.update().url, { preserveScroll: true });
}
</script>

<template>
    <Head title="App Settings" />

    <div class="space-y-6 p-6">
        <div>
            <h1 class="text-2xl font-semibold">App Settings</h1>
            <p class="mt-1 text-sm text-muted-foreground">Global configuration that applies across all projects and reports.</p>
        </div>

        <form class="space-y-5 max-w-lg" @submit.prevent="submit">
            <!-- PDF Settings -->
            <Card>
                <CardHeader>
                    <CardTitle>PDF Reports</CardTitle>
                    <CardDescription>Settings applied to all generated PDF documents (SSR, BAST).</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-1.5">
                        <Label>Footer / Disclaimer Note</Label>
                        <textarea
                            v-model="form.pdf_footer_note"
                            placeholder="Dokumen ini bersifat rahasia dan hanya untuk keperluan internal…"
                            rows="3"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[80px] w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <p class="text-xs text-muted-foreground">Printed at the bottom of every SSR and BAST PDF. Leave blank to omit.</p>
                        <InputError :message="form.errors.pdf_footer_note" />
                    </div>
                </CardContent>
            </Card>

            <!-- Notifications -->
            <Card>
                <CardHeader>
                    <CardTitle>Notifications</CardTitle>
                    <CardDescription>Where to send system notifications.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-1.5">
                        <Label>Report Notification Email</Label>
                        <Input v-model="form.report_email" type="email" placeholder="management@company.com" />
                        <p class="text-xs text-muted-foreground">CC'd whenever a report is generated. Leave blank to disable.</p>
                        <InputError :message="form.errors.report_email" />
                    </div>
                </CardContent>
            </Card>

            <!-- SLA Thresholds -->
            <Card>
                <CardHeader>
                    <CardTitle>SLA Thresholds</CardTitle>
                    <CardDescription>Days before an activity is flagged as overdue on the dashboard.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-1.5">
                            <Label>Survey SLA (days)</Label>
                            <Input v-model.number="form.survey_sla_days" type="number" min="1" max="365" />
                            <InputError :message="form.errors.survey_sla_days" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Construction SLA (days)</Label>
                            <Input v-model.number="form.construction_sla_days" type="number" min="1" max="365" />
                            <InputError :message="form.errors.construction_sla_days" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-1.5">
                            <Label>Slow badge threshold (days)</Label>
                            <Input v-model.number="form.slow_days" type="number" min="1" max="365" />
                            <p class="text-xs text-muted-foreground">Amber "Slow" badge appears after this many days without progress.</p>
                            <InputError :message="form.errors.slow_days" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Stalled badge threshold (days)</Label>
                            <Input v-model.number="form.stalled_days" type="number" min="1" max="365" />
                            <p class="text-xs text-muted-foreground">Red "Stalled" badge appears after this many days without progress.</p>
                            <InputError :message="form.errors.stalled_days" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Button type="submit" :disabled="form.processing">Save Settings</Button>
        </form>
    </div>
</template>
