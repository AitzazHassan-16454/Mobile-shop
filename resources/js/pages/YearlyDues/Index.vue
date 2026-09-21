<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarClock } from '@lucide/vue';
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
                title: 'Yearly Dues',
                href: layoutProps.currentTeam
                    ? `/${layoutProps.currentTeam.slug}/yearly-dues`
                    : '/yearly-dues',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Yearly Dues" />
    <SectionPlaceholder
        :icon="CalendarClock"
        title="Yearly Dues"
        eyebrow="Dues tracker"
        description="Monitor yearly outstanding dues, collection targets and maturity reminders."
    />
</template>
