<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BadgePercent,
    Boxes,
    Building2,
    Database,
    LayoutGrid,
    Package,
    Receipt,
    RotateCcw,
    Ruler,
    ShoppingBag,
    ShoppingCart,
    SlidersHorizontal,
    Store,
    Tags,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import backup from '@/routes/backup';
import type { NavGroup, NavItem } from '@/types';

const page = usePage();

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const backupDownloadUrl = computed(() =>
    page.props.currentTeam
        ? backup.download(page.props.currentTeam.slug).url
        : '/backup/download',
);

const teamUrl = (pathname: string) =>
    page.props.currentTeam
        ? `/${page.props.currentTeam.slug}${pathname}`
        : pathname;

const dashboardNavItem = computed<NavItem>(() => ({
    title: 'Dashboard',
    href: dashboardUrl.value,
    icon: LayoutGrid,
}));

const mainNavGroups = computed<NavGroup[]>(() => [
    {
        title: 'Dashboard',
        icon: LayoutGrid,
        items: [
            { title: 'Customers', href: teamUrl('/customers'), icon: Users },
        ],
    },
    {
        title: 'Sales & POS',
        icon: ShoppingCart,
        items: [
            { title: 'Sales History & Direct Sale', href: teamUrl('/sales'), icon: ShoppingBag },
            { title: 'Sale Returns', href: teamUrl('/sales-returns'), icon: RotateCcw },
            { title: 'Expenses', href: teamUrl('/expenses'), icon: Receipt },
        ],
    },
    {
        title: 'Products',
        icon: Boxes,
        items: [
            {
                title: 'Products & Stock',
                href: teamUrl('/products'),
                icon: Package,
            },
            { title: 'Categories', href: teamUrl('/categories'), icon: Tags },
            { title: 'Units', href: teamUrl('/units'), icon: Ruler },
            {
                title: 'Stock Adjustments',
                href: teamUrl('/stock-adjustments'),
                icon: SlidersHorizontal,
            },
            {
                title: 'Discounts',
                href: teamUrl('/discounts'),
                icon: BadgePercent,
            },
        ],
    },
    {
        title: 'Purchases',
        icon: Store,
        items: [
            {
                title: 'Suppliers',
                href: teamUrl('/suppliers'),
                icon: Building2,
            },
        ],
    },
]);

const footerNavItems = computed<NavItem[]>(() => [
    {
        title: '1-Click Local Backup',
        href: backupDownloadUrl.value,
        icon: Database,
    },
]);
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="sidebar"
        class="text-sidebar-foreground border-r border-white/12 bg-[#002654]/90 shadow-[8px_0_36px_rgba(0,18,51,0.25)] backdrop-blur-2xl"
    >
        <SidebarHeader class="relative bg-transparent px-3 py-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="bg-transparent hover:bg-transparent"
                    >
                        <Link :href="dashboardUrl" class="w-full">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarMenu class="mt-3">
                <SidebarMenuItem>
                    <TeamSwitcher />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="relative bg-transparent px-2 py-3">
            <NavMain :items="mainNavGroups" :leading="dashboardNavItem" />
        </SidebarContent>

        <SidebarFooter class="relative bg-transparent p-2">
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
