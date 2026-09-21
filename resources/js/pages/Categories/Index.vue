<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Tags } from '@lucide/vue';
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
                title: 'Categories',
                href: layoutProps.currentTeam
                    ? `/${layoutProps.currentTeam.slug}/categories`
                    : '/categories',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Categories" />
    <SectionPlaceholder
        :icon="Tags"
        title="Categories"
        eyebrow="Product catalog"
        description="Organize products into categories for easier browsing, filtering and reporting."
    />
</template>
