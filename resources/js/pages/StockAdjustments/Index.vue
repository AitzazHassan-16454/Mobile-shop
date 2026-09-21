<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { SlidersHorizontal } from '@lucide/vue';
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
                title: 'Stock Adjustments',
                href: layoutProps.currentTeam
                    ? `/${layoutProps.currentTeam.slug}/stock-adjustments`
                    : '/stock-adjustments',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Stock Adjustments" />
    <SectionPlaceholder
        :icon="SlidersHorizontal"
        title="Stock Adjustments"
        eyebrow="Inventory control"
        description="Adjust stock levels for damage, loss, returns or periodic cycle counts."
    />
</template>
