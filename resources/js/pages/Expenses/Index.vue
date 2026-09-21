<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Receipt } from '@lucide/vue';
import SectionPlaceholder from '@/components/SectionPlaceholder.vue';
import type { Team } from '@/types';

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam ? '/dashboard' : '/',
            },
            {
                title: 'Expenses',
                href: layoutProps.currentTeam
                    ? `/${layoutProps.currentTeam.slug}/expenses`
                    : '/expenses',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Expenses" />
    <SectionPlaceholder
        :icon="Receipt"
        title="Expenses"
        eyebrow="Cash expenses"
        description="Record and review all shop operating expenses, categorized for easy tracking."
    />
</template>
