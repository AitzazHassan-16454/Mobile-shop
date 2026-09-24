<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Boxes,
    CalendarClock,
    ChevronDown,
    Database,
    LayoutGrid,
    Menu,
    Receipt,
    ShieldCheck,
    ShoppingCart,
    Smartphone,
    Store,
    Users,
    Wrench,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { dashboard } from '@/routes';
import backup from '@/routes/backup';
import customers from '@/routes/customers';
import installments from '@/routes/installments';
import inventory from '@/routes/inventory';
import pos from '@/routes/pos';
import repairs from '@/routes/repairs';
import reports from '@/routes/reports';
import shifts from '@/routes/shifts';
import suppliers from '@/routes/suppliers';
import usedPhones from '@/routes/used-phones';
import type { BreadcrumbItem, Team, User } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth as { user: User });
const currentTeam = computed(() => page.props.currentTeam as Team | undefined);
const teamSlug = computed(() => currentTeam.value?.slug ?? 'default');
const shopName = computed(() => currentTeam.value?.name || 'Horizon Studio');

const { isCurrentOrParentUrl } = useCurrentUrl();

const dashboardUrl = computed(() => dashboard(teamSlug.value).url);
const posUrl = computed(() => pos.index(teamSlug.value).url);
const customersUrl = computed(() => customers.index(teamSlug.value).url);
const installmentsUrl = computed(() => installments.index(teamSlug.value).url);
const inventoryUrl = computed(() => inventory.index(teamSlug.value).url);
const repairsUrl = computed(() => repairs.index(teamSlug.value).url);
const shiftsUrl = computed(() => shifts.index(teamSlug.value).url);
const usedPhonesUrl = computed(() => usedPhones.index(teamSlug.value).url);
const suppliersUrl = computed(() => suppliers.index(teamSlug.value).url);
const reportsUrl = computed(() => reports.index(teamSlug.value).url);
const backupUrl = computed(() => backup.download(teamSlug.value).url);

const primaryNavItems = computed(() => [
    { title: 'Dashboard', href: dashboardUrl.value, icon: LayoutGrid },
    { title: 'Khata', href: customersUrl.value, icon: Users },
    { title: 'Installments', href: installmentsUrl.value, icon: CalendarClock },
    { title: 'Inventory', href: inventoryUrl.value, icon: Boxes },
    { title: 'Repairs', href: repairsUrl.value, icon: Wrench },
    { title: 'Reports', href: reportsUrl.value, icon: BarChart3 },
]);

const secondaryNavItems = computed(() => [
    {
        title: 'Shift & Cash',
        href: shiftsUrl.value,
        icon: Receipt,
        desc: 'Cash drawer & daily shift',
    },
    {
        title: 'Used Phones',
        href: usedPhonesUrl.value,
        icon: ShieldCheck,
        desc: 'Customer phone buying',
    },
    {
        title: 'Suppliers',
        href: suppliersUrl.value,
        icon: Store,
        desc: 'Vendor accounts & payables',
    },
    {
        title: 'Local Backup',
        href: backupUrl.value,
        icon: Database,
        desc: 'Download offline backup',
        external: true,
    },
]);

const allNavItems = computed(() => [
    ...primaryNavItems.value,
    ...secondaryNavItems.value,
]);

const isMoreActive = computed(() => {
    return secondaryNavItems.value.some((item) =>
        isCurrentOrParentUrl(item.href),
    );
});

const isMobileMenuOpen = ref(false);
</script>

<template>
    <header
        class="sticky top-0 z-40 w-full border-b border-white/70 bg-white/80 shadow-[0_8px_30px_rgba(0,30,80,0.06),inset_0_1px_1px_rgba(255,255,255,0.95)] backdrop-blur-2xl"
    >
        <div
            class="mx-auto flex h-16 w-full max-w-[1700px] items-center justify-between px-4 sm:px-6"
        >
            <!-- Left Side: Shop Branding & Store Switcher -->
            <div class="flex items-center gap-3">
                <!-- Mobile Drawer Trigger -->
                <div class="xl:hidden">
                    <Sheet v-model:open="isMobileMenuOpen">
                        <SheetTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-9 w-9 rounded-xl border border-slate-200/80 bg-white/70 text-slate-700 shadow-xs hover:bg-white"
                            >
                                <Menu class="h-5 w-5" />
                                <span class="sr-only"
                                    >Toggle navigation menu</span
                                >
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="left"
                            class="w-[310px] border-r border-white/80 bg-white/95 p-0 backdrop-blur-2xl"
                        >
                            <SheetHeader
                                class="flex flex-row items-center gap-3 border-b border-slate-100 p-5 text-left"
                            >
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#003B7D] to-[#002654] text-white shadow-md"
                                >
                                    <Smartphone class="h-5 w-5" />
                                </div>
                                <div>
                                    <SheetTitle
                                        class="text-base font-black tracking-tight text-slate-900"
                                    >
                                        {{ shopName }}
                                    </SheetTitle>
                                    <p
                                        class="text-[11px] font-semibold text-slate-500"
                                    >
                                        Store Management & POS
                                    </p>
                                </div>
                            </SheetHeader>

                            <!-- Mobile Quick POS Launch -->
                            <div class="border-b border-slate-100 p-4">
                                <Link
                                    :href="posUrl"
                                    @click="isMobileMenuOpen = false"
                                    class="flex w-full items-center justify-between gap-3 rounded-2xl bg-gradient-to-r from-[#003B7D] to-[#004f9e] p-3.5 text-white shadow-lg shadow-[#003B7D]/25 transition hover:brightness-105"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20"
                                        >
                                            <ShoppingCart class="h-5 w-5" />
                                        </div>
                                        <div class="text-left">
                                            <div class="text-xs font-black">
                                                Open POS Terminal
                                            </div>
                                            <div
                                                class="text-[10px] text-white/80"
                                            >
                                                Full size checkout
                                            </div>
                                        </div>
                                    </div>
                                    <span
                                        class="rounded-lg bg-white/20 px-2 py-0.5 text-[10px] font-bold"
                                        >Full</span
                                    >
                                </Link>
                            </div>

                            <!-- Mobile Navigation Links -->
                            <div
                                class="max-h-[calc(100vh-230px)] space-y-1 overflow-y-auto p-3"
                            >
                                <Link
                                    v-for="item in allNavItems"
                                    :key="item.title"
                                    :href="item.href"
                                    @click="isMobileMenuOpen = false"
                                    :class="[
                                        'flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-xs font-bold transition-all',
                                        isCurrentOrParentUrl(item.href)
                                            ? 'bg-[#003B7D] font-black text-white shadow-md shadow-[#003B7D]/20'
                                            : 'text-slate-700 hover:bg-slate-100 hover:text-[#003B7D]',
                                    ]"
                                >
                                    <component
                                        :is="item.icon"
                                        class="h-4 w-4 shrink-0"
                                    />
                                    <span>{{ item.title }}</span>
                                </Link>
                            </div>

                            <!-- Mobile Footer: User Info -->
                            <div
                                class="absolute right-0 bottom-0 left-0 border-t border-slate-100 bg-slate-50/90 p-4"
                            >
                                <div class="flex items-center gap-3">
                                    <Avatar
                                        class="h-9 w-9 rounded-full ring-1 ring-slate-200"
                                    >
                                        <AvatarFallback
                                            class="bg-[#003B7D] text-xs font-bold text-white"
                                        >
                                            {{ getInitials(auth?.user?.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="truncate text-xs font-bold text-slate-900"
                                        >
                                            {{ auth?.user?.name }}
                                        </div>
                                        <div
                                            class="truncate text-[11px] text-slate-500"
                                        >
                                            {{ auth?.user?.email }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <!-- Shop Brand & Name -->
                <Link
                    :href="dashboardUrl"
                    class="group flex items-center gap-3"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#003B7D] to-[#002752] text-white shadow-[0_6px_20px_rgba(0,59,125,0.3),inset_0_1px_1.5px_rgba(255,255,255,0.35)] ring-1 ring-white/30 transition group-hover:scale-105"
                    >
                        <Smartphone class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span
                                class="text-base font-black tracking-tight text-slate-900 transition group-hover:text-[#003B7D]"
                            >
                                {{ shopName }}
                            </span>
                            <span
                                class="hidden rounded-md bg-[#003B7D]/10 px-1.5 py-0.5 text-[10px] font-bold text-[#003B7D] sm:inline-flex"
                            >
                                Shop
                            </span>
                        </div>
                        <span
                            class="-mt-0.5 block text-[10px] font-bold tracking-wide text-slate-400"
                        >
                            Management & POS
                        </span>
                    </div>
                </Link>

                <div class="ml-2 hidden sm:block">
                    <TeamSwitcher :in-header="true" />
                </div>
            </div>

            <!-- Right Side: Navigation Links & Actions -->
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Desktop Navigation Links -->
                <nav
                    class="hidden items-center gap-1 rounded-2xl border border-white/80 bg-slate-100/80 p-1 shadow-inner backdrop-blur-md xl:flex"
                >
                    <Link
                        v-for="item in primaryNavItems"
                        :key="item.title"
                        :href="item.href"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition-all duration-150',
                            isCurrentOrParentUrl(item.href)
                                ? 'bg-white font-black text-[#003B7D] shadow-sm'
                                : 'text-slate-600 hover:bg-white/70 hover:text-[#003B7D]',
                        ]"
                    >
                        <component
                            :is="item.icon"
                            class="h-3.5 w-3.5 shrink-0"
                        />
                        <span>{{ item.title }}</span>
                    </Link>

                    <!-- More Dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                :class="[
                                    'inline-flex items-center gap-1 rounded-xl px-3 py-1.5 text-xs font-bold transition-all duration-150 outline-none',
                                    isMoreActive
                                        ? 'bg-white font-black text-[#003B7D] shadow-sm'
                                        : 'text-slate-600 hover:bg-white/70 hover:text-[#003B7D]',
                                ]"
                            >
                                <span>More</span>
                                <ChevronDown class="h-3 w-3 opacity-60" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56 p-1.5">
                            <DropdownMenuItem
                                v-for="subItem in secondaryNavItems"
                                :key="subItem.title"
                                :as-child="true"
                            >
                                <component
                                    :is="subItem.external || String(subItem.href).includes('/backup/download') ? 'a' : Link"
                                    :href="subItem.href"
                                    :class="[
                                        'flex cursor-pointer items-center gap-2.5 rounded-xl px-2.5 py-2 text-xs font-bold transition',
                                        isCurrentOrParentUrl(subItem.href)
                                            ? 'bg-[#003B7D]/10 text-[#003B7D]'
                                            : 'text-slate-700 hover:bg-slate-100',
                                    ]"
                                >
                                    <component
                                        :is="subItem.icon"
                                        class="h-4 w-4 text-[#003B7D]"
                                    />
                                    <div>
                                        <div>{{ subItem.title }}</div>
                                        <div
                                            class="text-[10px] font-normal text-slate-400"
                                        >
                                            {{ subItem.desc }}
                                        </div>
                                    </div>
                                </component>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </nav>

                <!-- Dedicated Full Size POS Launch Button -->
                <Link
                    :href="posUrl"
                    class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-[#003B7D] to-[#004f9e] px-4 py-2 text-xs font-black text-white shadow-[0_6px_20px_rgba(0,59,125,0.3),inset_0_1px_1.5px_rgba(255,255,255,0.35)] backdrop-blur-xl transition-all duration-200 hover:-translate-y-0.5 hover:brightness-110"
                >
                    <ShoppingCart class="h-4 w-4" />
                    <span>Open POS</span>
                </Link>

                <!-- User Profile Menu -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-2xl border border-white/80 bg-white/70 p-1 pr-2.5 shadow-xs transition hover:bg-white focus:outline-none"
                        >
                            <Avatar
                                class="h-8 w-8 rounded-xl ring-1 ring-[#003B7D]/20"
                            >
                                <AvatarImage
                                    v-if="auth?.user?.avatar"
                                    :src="auth.user.avatar"
                                    :alt="auth.user.name"
                                />
                                <AvatarFallback
                                    class="rounded-xl bg-[#003B7D] text-xs font-black text-white"
                                >
                                    {{ getInitials(auth?.user?.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <span
                                class="hidden max-w-[90px] truncate text-xs font-bold text-slate-800 md:inline-block"
                            >
                                {{ auth?.user?.name?.split(' ')[0] }}
                            </span>
                            <ChevronDown
                                class="hidden h-3 w-3 text-slate-400 md:inline-block"
                            />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-60">
                        <UserMenuContent v-if="auth?.user" :user="auth.user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </header>
</template>
