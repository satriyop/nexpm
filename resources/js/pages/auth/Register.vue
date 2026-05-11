<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { login } from '@/routes';

// Registration is disabled — this page is not reachable.

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Enter your details below to create your account',
    },
});
</script>

<template>
    <Head title="Register" />
    <div class="flex flex-col gap-6 text-center text-sm text-muted-foreground">
        <p>Registration is not available. Please contact your administrator.</p>
        <TextLink :href="login()" class="underline underline-offset-4"
            >Back to login</TextLink
        >
    </div>
</template>
