<script setup lang="ts">
import { computed } from 'vue';
import { SidebarInset } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    variant?: AppVariant;
    class?: string;
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});
const className = computed(() => props.class);
</script>

<template>
    <SidebarInset
        v-if="props.variant === 'sidebar'"
        :class="['text-foreground min-w-0 flex-1 bg-transparent', className]"
    >
        <slot />
    </SidebarInset>
    <main
        v-else
        class="mx-auto flex h-full w-full max-w-[1700px] min-w-0 flex-1 flex-col"
        :class="className"
    >
        <slot />
    </main>
</template>
