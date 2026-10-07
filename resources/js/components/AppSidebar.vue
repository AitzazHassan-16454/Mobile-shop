<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    BadgePercent,
    BarChart3,
    Boxes,
    Building2,
    CalendarClock,
    CalendarDays,
    Clock,
    FolderTree,
    HardDrive,
    LayoutDashboard,
    ReceiptText,
    Scale,
    SlidersHorizontal,
    Smartphone,
    TrendingDown,
    Undo2,
    UsersRound,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
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

const teamUrl = (pathname: string) =>
    page.props.currentTeam
        ? `/${page.props.currentTeam.slug}${pathname}`
        : pathname;

const backupUrl = computed(() =>
    page.props.currentTeam
        ? backup.download(page.props.currentTeam.slug).url
        : '/backup/download',
);

const dashboardNavItem = computed<NavItem>(() => ({
    title: 'Dashboard',
    href: dashboardUrl.value,
    icon: LayoutDashboard,
}));

const mainNavGroups = computed<NavGroup[]>(() => [
    {
        title: 'Core',
        icon: LayoutDashboard,
        items: [
            {
                title: 'Customers',
                href: teamUrl('/customers'),
                icon: UsersRound,
            },
            {
                title: 'Installments',
                href: teamUrl('/installments'),
                icon: CalendarClock,
            },
            {
                title: 'Yearly Dues',
                href: teamUrl('/yearly-dues'),
                icon: CalendarDays,
            },
        ],
    },
    {
        title: 'Sales',
        icon: ReceiptText,
        items: [
            {
                title: 'Mobile Handsets (Add, Sell, Buy)',
                href: teamUrl('/mobile-sales'),
                icon: Smartphone,
            },
            {
                title: 'Sales History & Direct Sale',
                href: teamUrl('/sales'),
                icon: ReceiptText,
            },
            {
                title: 'Sale Returns',
                href: teamUrl('/sales-returns'),
                icon: Undo2,
            },
            {
                title: 'Repair Sales',
                href: teamUrl('/repair-sales'),
                icon: Wrench,
            },
            {
                title: 'Repair Lab (Tickets)',
                href: teamUrl('/repairs'),
                icon: Wrench,
            },
            {
                title: 'Expenses',
                href: teamUrl('/expenses'),
                icon: TrendingDown,
            },
        ],
    },
    {
        title: 'Inventory',
        icon: Boxes,
        items: [
            {
                title: 'Accessories',
                href: teamUrl('/products'),
                icon: Boxes,
            },
            {
                title: 'Categories',
                href: teamUrl('/categories'),
                icon: FolderTree,
            },
            { title: 'Units', href: teamUrl('/units'), icon: Scale },
            {
                title: 'Stock Adjustments',
                href: teamUrl('/stock-adjustments'),
                icon: SlidersHorizontal,
            },
            {
                title: 'Stock Transfers',
                href: teamUrl('/stock-transfers'),
                icon: ArrowLeftRight,
            },
            {
                title: 'Discounts',
                href: teamUrl('/discounts'),
                icon: BadgePercent,
            },
        ],
    },
    {
        title: 'Management',
        icon: Building2,
        items: [
            {
                title: 'Register Shifts',
                href: teamUrl('/shifts'),
                icon: Clock,
            },
            {
                title: 'Suppliers',
                href: teamUrl('/suppliers'),
                icon: Building2,
            },
            {
                title: 'Reports & Analytics',
                href: teamUrl('/reports'),
                icon: BarChart3,
            },
            {
                title: 'Local Backup',
                href: backupUrl.value,
                icon: HardDrive,
                external: true,
            },
        ],
    },
]);
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="sidebar"
        class="text-sidebar-foreground border-r border-white/12 bg-[#002654]/95 shadow-[8px_0_36px_rgba(0,18,51,0.25)] backdrop-blur-2xl dark:border-white/10 dark:bg-[#090d16] dark:shadow-[8px_0_36px_rgba(0,0,0,0.5)]"
    >
        <SidebarHeader
            class="relative bg-transparent p-2.5 group-data-[collapsible=icon]:p-2 group-data-[collapsible=icon]:py-3"
        >
            <SidebarMenu>
                <SidebarMenuItem class="flex justify-center">
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="bg-transparent group-data-[collapsible=icon]:size-10 group-data-[collapsible=icon]:p-0 hover:bg-transparent"
                    >
                        <Link
                            :href="dashboardUrl"
                            class="flex w-full items-center justify-center"
                        >
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent
            class="no-scrollbar relative bg-transparent px-2 py-3 group-data-[collapsible=icon]:px-1 group-data-[collapsible=icon]:py-2"
        >
            <NavMain :items="mainNavGroups" :leading="dashboardNavItem" />
        </SidebarContent>
    </Sidebar>
    <slot />
</template>
