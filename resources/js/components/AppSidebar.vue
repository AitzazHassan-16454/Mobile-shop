<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Boxes,
    CalendarClock,
    Database,
    LayoutGrid,
    Receipt,
    ShieldCheck,
    ShoppingCart,
    Store,
    Users,
    Wrench,
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
import customers from '@/routes/customers';
import inventory from '@/routes/inventory';
import installments from '@/routes/installments';
import pos from '@/routes/pos';
import repairs from '@/routes/repairs';
import reports from '@/routes/reports';
import shifts from '@/routes/shifts';
import suppliers from '@/routes/suppliers';
import usedPhones from '@/routes/used-phones';
import type { NavItem } from '@/types';

const page = usePage();

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const posUrl = computed(() =>
    page.props.currentTeam
        ? pos.index(page.props.currentTeam.slug).url
        : '/pos',
);

const usedPhonesUrl = computed(() =>
    page.props.currentTeam
        ? usedPhones.index(page.props.currentTeam.slug).url
        : '/used-phones',
);

const repairsUrl = computed(() =>
    page.props.currentTeam
        ? repairs.index(page.props.currentTeam.slug).url
        : '/repairs',
);

const customersUrl = computed(() =>
    page.props.currentTeam
        ? customers.index(page.props.currentTeam.slug).url
        : '/customers',
);

const inventoryUrl = computed(() =>
    page.props.currentTeam
        ? inventory.index(page.props.currentTeam.slug).url
        : '/inventory',
);

const shiftsUrl = computed(() =>
    page.props.currentTeam
        ? shifts.index(page.props.currentTeam.slug).url
        : '/shifts',
);

const reportsUrl = computed(() =>
    page.props.currentTeam
        ? reports.index(page.props.currentTeam.slug).url
        : '/reports',
);

const suppliersUrl = computed(() =>
    page.props.currentTeam
        ? suppliers.index(page.props.currentTeam.slug).url
        : '/suppliers',
);

const installmentsUrl = computed(() =>
    page.props.currentTeam
        ? installments.index(page.props.currentTeam.slug).url
        : '/installments',
);

const backupDownloadUrl = computed(() =>
    page.props.currentTeam
        ? backup.download(page.props.currentTeam.slug).url
        : '/backup/download',
);

const mainNavItems = computed<NavItem[]>(() => [
    { title: 'Dashboard', href: dashboardUrl.value, icon: LayoutGrid },
    { title: 'POS Terminal', href: posUrl.value, icon: ShoppingCart },
    { title: 'Shift & Cash Drawer', href: shiftsUrl.value, icon: Receipt },
    { title: 'Customer Khata', href: customersUrl.value, icon: Users },
    {
        title: 'Installment Plans',
        href: installmentsUrl.value,
        icon: CalendarClock,
    },
    { title: 'Suppliers & Payables', href: suppliersUrl.value, icon: Store },
    {
        title: 'Used Phone Buying',
        href: usedPhonesUrl.value,
        icon: ShieldCheck,
    },
    { title: 'Repairing Lab', href: repairsUrl.value, icon: Wrench },
    { title: 'Inventory', href: inventoryUrl.value, icon: Boxes },
    { title: 'Reports & Analytics', href: reportsUrl.value, icon: BarChart3 },
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
        class="bg-sidebar text-sidebar-foreground border-r border-white/10"
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
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="relative bg-transparent p-2">
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
