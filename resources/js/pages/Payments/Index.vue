<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Wallet } from '@lucide/vue';
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
                title: 'All Payments',
                href: layoutProps.currentTeam
                    ? `/${layoutProps.currentTeam.slug}/all-payments`
                    : '/all-payments',
            },
        ],
    }),
});
</script>

<template>
    <Head title="All Payments" />
    <SectionPlaceholder
        :icon="Wallet"
        title="All Payments"
        eyebrow="Payments desk"
        description="Track every payment received or paid across customers, suppliers and sales."
    />
</template>
