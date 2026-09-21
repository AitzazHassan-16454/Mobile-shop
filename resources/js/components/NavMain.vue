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
                        'flex h-11 w-full items-center rounded-xl px-3 transition-all duration-200',
                        isCurrentUrl(item.href)
                            ? 'bg-white/95 font-black text-[#003B7D] shadow-[0_4px_16px_rgba(0,0,0,0.18),inset_0_1px_1px_rgba(255,255,255,1)] ring-1 ring-white/50 hover:bg-white hover:text-[#003B7D]'
                            : 'font-semibold text-white/80 hover:bg-white/12 hover:text-white hover:backdrop-blur-md',
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
