<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BadgePercent } from '@lucide/vue';
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
                title: 'Discounts',
                href: layoutProps.currentTeam
                    ? `/${layoutProps.currentTeam.slug}/discounts`
                    : '/discounts',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Discounts" />
    <SectionPlaceholder
        :icon="BadgePercent"
        title="Discounts"
        eyebrow="Pricing rules"
        description="Create and manage discount rules for products, customers and seasonal offers."
    />
</template>
