<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu class="gap-1.5">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :tooltip="item.title"
                    :class="[
                        'flex h-12 w-full items-center rounded-md px-3',
                        isCurrentUrl(item.href)
                            ? 'bg-white text-[#003b7d] hover:bg-white hover:text-[#003b7d] focus-visible:bg-white focus-visible:text-[#003b7d]'
                            : 'text-white hover:bg-white hover:text-[#003b7d] focus-visible:bg-white focus-visible:text-[#003b7d]',
                    ]"
                >
                    <Link
                        :href="item.href"
                        class="flex w-full items-center gap-2.5"
                    >
                        <component :is="item.icon" class="size-5! shrink-0" />
                        <span
                            class="truncate text-xs font-bold tracking-wide lg:text-sm"
                            >{{ item.title }}</span
                        >
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
