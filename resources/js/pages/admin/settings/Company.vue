<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import * as Actions from '@/actions/App/Http/Controllers/Admin/CompanySettingController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

const props = defineProps<{
    companyName: string;
    logoUrl: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Company Settings', href: Actions.index().url },
        ],
    },
});

const form = useForm<{ company_name: string; logo: File | null }>({
    company_name: props.companyName,
    logo: null,
});

const logoPreview = ref<string | null>(props.logoUrl);

function onLogoChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.logo = file;
    logoPreview.value = file ? URL.createObjectURL(file) : props.logoUrl;
}

function submit() {
    form.post(Actions.update().url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Company Settings" />

    <div class="space-y-6 p-6">
        <h1 class="text-2xl font-semibold">Company Settings</h1>
        <p class="text-sm text-muted-foreground">
            Set your company name and logo. These appear in the SSR PDF report header.
        </p>

        <Card class="max-w-lg">
            <CardHeader><CardTitle>Branding</CardTitle></CardHeader>
            <CardContent>
                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid gap-1.5">
                        <Label>Company Name</Label>
                        <Input v-model="form.company_name" placeholder="PT Vahana Gasti Teknika…" />
                        <InputError :message="form.errors.company_name" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label>Company Logo</Label>
                        <div v-if="logoPreview" class="mb-2">
                            <img
                                :src="logoPreview"
                                alt="Company logo"
                                class="h-16 max-w-[200px] rounded border object-contain p-1"
                            />
                        </div>
                        <Input type="file" accept="image/*" @change="onLogoChange" />
                        <p class="text-xs text-muted-foreground">JPG, PNG, or SVG — max 2 MB</p>
                        <InputError :message="form.errors.logo" />
                    </div>

                    <Button type="submit" :disabled="form.processing">Save Settings</Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
