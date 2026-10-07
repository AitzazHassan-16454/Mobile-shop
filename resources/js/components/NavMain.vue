<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useTranslation } from '@/composables/useTranslation';
import type { NavGroup, NavItem } from '@/types';

defineProps<{
    items: NavGroup[];
    leading?: NavItem;
}>();

const { isCurrentUrl } = useCurrentUrl();
const { t } = useTranslation();
</script>

<template>
    <SidebarGroup
        v-if="leading"
        class="px-2 py-1 group-data-[collapsible=icon]:px-1 group-data-[collapsible=icon]:py-0.5"
    >
        <SidebarMenu class="gap-1.5">
            <SidebarMenuItem class="flex justify-center">
                <SidebarMenuButton
                    as-child
                    :tooltip="t(leading.title)"
                    :class="[
                        'flex h-11 w-full items-center rounded-xl transition-all duration-200 group-data-[collapsible=icon]:size-10 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:p-0',
                        isCurrentUrl(leading.href)
                            ? 'bg-white font-extrabold text-[#003B7D] shadow-[0_4px_16px_rgba(0,0,0,0.18),inset_0_1px_1px_rgba(255,255,255,1)] ring-1 ring-white/60'
                            : 'font-bold text-white/90 hover:bg-white hover:font-extrabold hover:text-[#003B7D] hover:shadow-[0_4px_16px_rgba(0,0,0,0.18),inset_0_1px_1px_rgba(255,255,255,1)] hover:ring-1 hover:ring-white/60',
                    ]"
                >
                    <Link
                        :href="leading.href"
                        class="flex w-full items-center gap-3 px-3.5 group-data-[collapsible=icon]:w-auto group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:gap-0 group-data-[collapsible=icon]:px-0"
                    >
                        <component
                            :is="leading.icon"
                            class="size-5 shrink-0 transition-transform duration-150 group-hover/menu-button:scale-105"
                        />
                        <span
                            class="truncate text-xs font-bold tracking-wide group-data-[collapsible=icon]:hidden lg:text-sm"
                            >{{ t(leading.title) }}</span
                        >
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>

    <SidebarGroup
        v-for="group in items"
        :key="group.title"
        class="px-2 py-1 group-data-[collapsible=icon]:px-1 group-data-[collapsible=icon]:py-0.5"
    >
        <SidebarMenu class="gap-1.5">
            <SidebarMenuItem
                v-for="item in group.items"
                :key="item.title"
                class="flex justify-center"
            >
                <SidebarMenuButton
                    as-child
                    :tooltip="t(item.title)"
                    :class="[
                        'flex h-11 w-full items-center rounded-xl transition-all duration-200 group-data-[collapsible=icon]:size-10 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:p-0',
                        isCurrentUrl(item.href)
                            ? 'bg-white font-extrabold text-[#003B7D] shadow-[0_4px_16px_rgba(0,0,0,0.18),inset_0_1px_1px_rgba(255,255,255,1)] ring-1 ring-white/60'
                            : 'font-bold text-white/90 hover:bg-white hover:font-extrabold hover:text-[#003B7D] hover:shadow-[0_4px_16px_rgba(0,0,0,0.18),inset_0_1px_1px_rgba(255,255,255,1)] hover:ring-1 hover:ring-white/60',
                    ]"
                >
                    <component
                        :is="
                            item.external ||
                            String(item.href).includes('/backup/download')
                                ? 'a'
                                : Link
                        "
                        :href="item.href"
                        class="flex w-full items-center gap-3 px-3.5 group-data-[collapsible=icon]:w-auto group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:gap-0 group-data-[collapsible=icon]:px-0"
                    >
                        <component
                            :is="item.icon"
                            class="size-5 shrink-0 transition-transform duration-150 group-hover/menu-button:scale-105"
                        />
                        <span
                            class="truncate text-xs font-bold tracking-wide group-data-[collapsible=icon]:hidden lg:text-sm"
                            >{{ t(item.title) }}</span
                        >
                    </component>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
