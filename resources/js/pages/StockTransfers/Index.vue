<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowLeftRight } from '@lucide/vue';
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
                title: 'Stock Transfer',
                href: layoutProps.currentTeam
                    ? `/${layoutProps.currentTeam.slug}/stock-transfers`
                    : '/stock-transfers',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Stock Transfer" />
    <SectionPlaceholder
        :icon="ArrowLeftRight"
        title="Stock Transfer"
        eyebrow="Inventory movement"
        description="Move stock between stores or locations and keep a complete transfer history."
    />
</template>
