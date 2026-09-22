<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Calendar,
    Check,
    Edit3,
    Eye,
    Filter,
    Package,
    Plus,
    RefreshCw,
    RotateCcw,
    Search,
    SlidersHorizontal,
    Tag,
    Trash2,
    TrendingDown,
    Undo2,
    UserCheck,
    Wallet,
    X,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';

interface Customer {
    id: number;
    name: string;
    phone: string;
}

interface User {
    id: number;
    name: string;
}

interface ProductImei {
    id: number;
    imei_1: string;
}

interface Product {
    id: number;
    name: string;
    brand?: string | null;
    category?: string | null;
    is_serialized: boolean;
    sale_price: string | number;
}

interface SaleReturnItem {
    id: number;
    product_id: number;
    product_imei_id?: number | null;
    quantity: number | string;
    unit_price: number | string;
    line_total: number | string;
    product?: Product | null;
    product_imei?: ProductImei | null;
}

interface SaleReturn {
    id: number;
    return_no: string;
    sale_id?: number | null;
    customer_id?: number | null;
    user_id: number;
    total_return_amount: number | string;
    refund_amount: number | string;
    refund_payment_method: string;
    notes?: string | null;
    created_at: string;
    sale?: { id: number; invoice_no: string } | null;
    customer?: Customer | null;
    user?: User | null;
    items: SaleReturnItem[];
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
    returns: PaginatedData<SaleReturn>;
    recentSales: any[];
    products: Product[];
    customers: Customer[];
    filters: {
        search: string;
        date_filter: string;
        date_from?: string;
        date_to?: string;
    };
    stats: {
        today_return_total: number;
        today_return_count: number;
        all_time_return_total: number;
        all_time_return_count: number;
    };
    currentTeam?: { slug: string; name: string } | null;
}

const props = defineProps<Props>();

defineOptions({
    layout: (layoutProps: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam ? `/${layoutProps.currentTeam.slug}/dashboard` : '/dashboard',
            },
            {
                title: 'Sale Returns',
                href: layoutProps.currentTeam ? `/${layoutProps.currentTeam.slug}/sales-returns` : '/sales-returns',
            },
        ],
    }),
});

// Interactive Table Column Customizer State
const defaultVisibleColumns = {
    return_no: true,
    invoice_no: true,
    created_at: true,
    customer: true,
    items: true,
    refund_amount: true,
    refund_method: true,
    actions: true,
};

const visibleColumns = ref({ ...defaultVisibleColumns });

const returnColumnLabels: Record<keyof typeof defaultVisibleColumns, string> = {
    return_no: 'Return #',
    invoice_no: 'Original Invoice #',
    created_at: 'Date & Time',
    customer: 'Customer',
    items: 'Items Returned',
    refund_amount: 'Refund Amount',
    refund_method: 'Refund Method',
    actions: 'Actions',
};

const STORAGE_KEY = 'faizan_mobile_sale_returns_table_columns_v1';

onMounted(() => {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            const parsed = JSON.parse(saved);
            visibleColumns.value = { ...defaultVisibleColumns, ...parsed };
        }
    } catch (e) {
        console.error('Failed to load column preferences:', e);
    }
});

const toggleReturnColumn = (key: string) => {
    const k = key as keyof typeof defaultVisibleColumns;
    visibleColumns.value[k] = !visibleColumns.value[k];
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(visibleColumns.value));
    } catch (e) {
        console.error('Failed to save column preferences:', e);
    }
};

const resetColumns = () => {
    visibleColumns.value = { ...defaultVisibleColumns };
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch (e) {
        console.error('Failed to reset column preferences:', e);
    }
};

const activeColumnCount = computed(() => {
    return Object.values(visibleColumns.value).filter(Boolean).length;
});

// Filters state
const search = ref(props.filters.search || '');
const dateFilter = ref(props.filters.date_filter || 'all');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');

const applyFilters = () => {
    const routeName = props.currentTeam ? `/${props.currentTeam.slug}/sales-returns` : '/sales-returns';
    router.get(
        routeName,
        {
            search: search.value || undefined,
            date_filter: dateFilter.value !== 'all' ? dateFilter.value : undefined,
            date_from: dateFilter.value === 'custom' ? dateFrom.value : undefined,
            date_to: dateFilter.value === 'custom' ? dateTo.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

const resetFilters = () => {
    search.value = '';
    dateFilter.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
};

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

// Modal State
const isProcessModalOpen = ref(false);
const isDetailModalOpen = ref(false);
const selectedReturn = ref<SaleReturn | null>(null);

const returnForm = useForm({
    sale_id: '' as number | string,
    customer_id: '' as number | string,
    refund_amount: 0,
    refund_payment_method: 'cash',
    notes: '',
    items: [] as Array<{
        product_id: number;
        product_imei_id?: number | null;
        quantity: number;
        unit_price: number;
    }>,
});

interface CartReturnItem {
    product_id: number;
    product_name: string;
    is_serialized: boolean;
    product_imei_id?: number | null;
    quantity: number;
    unit_price: number;
    line_total: number;
}

const returnCart = ref<CartReturnItem[]>([]);
const selectedProductToAdd = ref<string>('');

const openProcessModal = () => {
    returnCart.value = [];
    selectedProductToAdd.value = '';
    returnForm.reset();
    returnForm.clearErrors();
    isProcessModalOpen.value = true;
};

const handleSelectProductToReturn = () => {
    if (!selectedProductToAdd.value) return;
    const prodId = Number(selectedProductToAdd.value);
    const prod = props.products.find((p) => p.id === prodId);
    if (!prod) return;

    const price = Number(prod.sale_price) || 0;
    returnCart.value.push({
        product_id: prod.id,
        product_name: prod.name,
        is_serialized: prod.is_serialized,
        quantity: 1,
        unit_price: price,
        line_total: price,
    });

    selectedProductToAdd.value = '';
};

const removeReturnItem = (index: number) => {
    returnCart.value.splice(index, 1);
};

const totalCalculatedReturn = computed(() => {
    return returnCart.value.reduce((sum, item) => sum + item.line_total, 0);
});

watch(totalCalculatedReturn, (newVal) => {
    returnForm.refund_amount = newVal;
});

const submitReturn = () => {
    if (returnCart.value.length === 0) {
        alert('Please add at least one product to return.');
        return;
    }

    returnForm.items = returnCart.value.map((c) => ({
        product_id: c.product_id,
        product_imei_id: c.product_imei_id || null,
        quantity: c.quantity,
        unit_price: c.unit_price,
    }));

    const routePrefix = props.currentTeam ? `/${props.currentTeam.slug}` : '';
    returnForm.post(`${routePrefix}/sales-returns`, {
        onSuccess: () => {
            isProcessModalOpen.value = false;
            returnCart.value = [];
            returnForm.reset();
        },
    });
};

const viewReturnDetails = (ret: SaleReturn) => {
    selectedReturn.value = ret;
    isDetailModalOpen.value = true;
};

// Money Formatter
const formatMoney = (val: number | string) => {
    const num = Number(val) || 0;
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: 2,
    })
        .format(num)
        .replace('PKR', 'Rs.');
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head title="Sale Returns" />

    <div class="space-y-6 p-4 md:p-6">
        <!-- Header & Action -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <RotateCcw class="h-6 w-6 text-rose-600" />
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Sale Returns & Stock Reversals
                    </h1>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Process customer product returns, restore inventory stock, and track cash or Khata refunds.
                </p>
            </div>
            <Button
                @click="openProcessModal"
                class="bg-rose-600 text-white hover:bg-rose-700 gap-2 font-medium cursor-pointer"
            >
                <RotateCcw class="h-4 w-4" />
                <span>+ Process Sale Return</span>
            </Button>
        </div>

        <!-- Summary Statistics Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <!-- Today's Refund Total -->
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Today's Refunds</span>
                    <div class="rounded-lg bg-rose-50 p-2 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400">
                        <TrendingDown class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ formatMoney(props.stats.today_return_total) }}
                    </p>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                        {{ props.stats.today_return_count }} {{ props.stats.today_return_count === 1 ? 'return' : 'returns' }} today
                    </p>
                </div>
            </div>

            <!-- Today's Return Count -->
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Today's Return Entries</span>
                    <div class="rounded-lg bg-blue-50 p-2 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400">
                        <RotateCcw class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ props.stats.today_return_count }}
                    </p>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                        Total return orders today
                    </p>
                </div>
            </div>

            <!-- All Time Returns Total -->
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">All-Time Refunds</span>
                    <div class="rounded-lg bg-purple-50 p-2 text-purple-600 dark:bg-purple-950/40 dark:text-purple-400">
                        <Wallet class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ formatMoney(props.stats.all_time_return_total) }}
                    </p>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                        Total refund payouts
                    </p>
                </div>
            </div>

            <!-- All Time Count -->
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">All-Time Entries</span>
                    <div class="rounded-lg bg-amber-50 p-2 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400">
                        <Calendar class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ props.stats.all_time_return_count }}
                    </p>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                        Total recorded return receipts
                    </p>
                </div>
            </div>
        </div>

        <!-- Filter Toolbar & Interactive Columns Dropdown -->
        <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-2xs sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative min-w-[220px] flex-1">
                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                    <Input
                        v-model="search"
                        type="text"
                        placeholder="Search Return #, Invoice #, Customer..."
                        class="pl-9 text-xs"
                    />
                </div>

                <div class="min-w-[140px]">
                    <select
                        v-model="dateFilter"
                        @change="applyFilters"
                        class="h-9 w-full rounded-md border border-gray-200 bg-white px-3 text-xs font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-1 focus:ring-[#003B7D]"
                    >
                        <option value="all">All Time</option>
                        <option value="today">Today</option>
                        <option value="this_week">This Week</option>
                        <option value="this_month">This Month</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>

                <template v-if="dateFilter === 'custom'">
                    <Input v-model="dateFrom" type="date" class="h-9 text-xs" @change="applyFilters" />
                    <span class="text-xs text-gray-400">to</span>
                    <Input v-model="dateTo" type="date" class="h-9 text-xs" @change="applyFilters" />
                </template>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    @click="resetFilters"
                    class="h-9 gap-1.5 text-xs text-gray-600 dark:text-gray-300"
                >
                    <RefreshCw class="h-3.5 w-3.5" />
                    <span>Reset</span>
                </Button>

                <!-- Interactive Table Columns Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-9 gap-1.5 text-xs text-gray-700 dark:text-gray-300"
                        >
                            <SlidersHorizontal class="h-3.5 w-3.5 text-rose-600" />
                            <span>Columns</span>
                            <span class="ml-1 rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                {{ activeColumnCount }}/8
                            </span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-60 p-2 space-y-1">
                        <DropdownMenuLabel class="flex items-center justify-between text-xs font-bold text-gray-900 dark:text-white px-1 py-1">
                            <span>Table Columns</span>
                            <button
                                type="button"
                                @click="resetColumns"
                                class="text-[11px] font-semibold text-rose-600 hover:underline cursor-pointer"
                            >
                                Reset All
                            </button>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator class="my-1" />
                        <div
                            v-for="(label, key) in returnColumnLabels"
                            :key="key"
                            @click.stop="toggleReturnColumn(key)"
                            class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer select-none transition-colors"
                        >
                            <span>{{ label }}</span>
                            <input
                                type="checkbox"
                                :checked="visibleColumns[key as keyof typeof visibleColumns]"
                                @change="toggleReturnColumn(key)"
                                @click.stop
                                class="h-4 w-4 rounded border-gray-300 text-rose-600 focus:ring-rose-500 cursor-pointer"
                            />
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Returns Data Table -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xs dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-gray-200 bg-gray-50/70 text-gray-600 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                        <tr>
                            <th v-if="visibleColumns.return_no" class="px-4 py-3 font-semibold">Return #</th>
                            <th v-if="visibleColumns.invoice_no" class="px-4 py-3 font-semibold">Original Invoice #</th>
                            <th v-if="visibleColumns.created_at" class="px-4 py-3 font-semibold">Date & Time</th>
                            <th v-if="visibleColumns.customer" class="px-4 py-3 font-semibold">Customer</th>
                            <th v-if="visibleColumns.items" class="px-4 py-3 font-semibold">Items Returned</th>
                            <th v-if="visibleColumns.refund_amount" class="px-4 py-3 font-semibold">Refund Amount</th>
                            <th v-if="visibleColumns.refund_method" class="px-4 py-3 font-semibold">Refund Method</th>
                            <th v-if="visibleColumns.actions" class="px-4 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        <tr
                            v-for="ret in props.returns.data"
                            :key="ret.id"
                            class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40 transition-colors"
                        >
                            <td v-if="visibleColumns.return_no" class="px-4 py-3.5 font-bold font-mono text-rose-600 dark:text-rose-400">
                                {{ ret.return_no }}
                            </td>
                            <td v-if="visibleColumns.invoice_no" class="px-4 py-3.5 font-mono text-[#003B7D] dark:text-blue-400">
                                {{ ret.sale?.invoice_no || '-' }}
                            </td>
                            <td v-if="visibleColumns.created_at" class="px-4 py-3.5 text-gray-600 dark:text-gray-300 font-mono text-[11px]">
                                {{ formatDate(ret.created_at) }}
                            </td>
                            <td v-if="visibleColumns.customer" class="px-4 py-3.5 font-medium text-gray-900 dark:text-white">
                                {{ ret.customer?.name || 'Walk-in Customer' }}
                            </td>
                            <td v-if="visibleColumns.items" class="px-4 py-3.5">
                                <span class="rounded bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">
                                    {{ ret.items.length }} {{ ret.items.length === 1 ? 'item' : 'items' }}
                                </span>
                            </td>
                            <td v-if="visibleColumns.refund_amount" class="px-4 py-3.5 font-bold text-rose-600 dark:text-rose-400 text-sm">
                                {{ formatMoney(ret.refund_amount) }}
                            </td>
                            <td v-if="visibleColumns.refund_method" class="px-4 py-3.5 capitalize text-gray-700 dark:text-gray-300">
                                {{ ret.refund_payment_method.replace('_', ' ') }}
                            </td>
                            <td v-if="visibleColumns.actions" class="px-4 py-3.5 text-right">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="viewReturnDetails(ret)"
                                    class="h-7 gap-1 text-xs text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 cursor-pointer"
                                >
                                    <Eye class="h-3.5 w-3.5" />
                                    <span>Details</span>
                                </Button>
                            </td>
                        </tr>

                        <tr v-if="props.returns.data.length === 0">
                            <td colspan="8" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                <RotateCcw class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" />
                                <p class="mt-2 text-xs font-medium">No sale returns recorded</p>
                                <p class="text-[11px] text-gray-400">
                                    Process product returns using the + Process Sale Return button above.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="props.returns.links && props.returns.links.length > 3"
                class="flex items-center justify-between border-t border-gray-200 bg-gray-50/50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900/50"
            >
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Showing <span class="font-semibold text-gray-700 dark:text-gray-300">{{ props.returns.data.length }}</span> of <span class="font-semibold text-gray-700 dark:text-gray-300">{{ props.returns.total }}</span> returns
                </div>
                <div class="flex items-center gap-1">
                    <component
                        :is="link.url ? 'a' : 'span'"
                        v-for="(link, i) in props.returns.links"
                        :key="i"
                        :href="link.url || undefined"
                        v-html="link.label"
                        :class="[
                            'px-2.5 py-1 text-xs rounded-md transition-colors',
                            link.active
                                ? 'bg-rose-600 text-white font-bold'
                                : link.url
                                  ? 'text-gray-700 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-800'
                                  : 'text-gray-400 cursor-not-allowed'
                        ]"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Process Return Modal (Streamlined & Compact) -->
    <Dialog v-model:open="isProcessModalOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-base font-bold text-rose-600 dark:text-rose-400">
                    <RotateCcw class="h-5 w-5" />
                    <span>Process Product Sale Return</span>
                </DialogTitle>
                <DialogDescription class="text-xs text-gray-500">
                    Quickly record product returns, restore stock, and issue refund.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitReturn" class="space-y-3 py-1">
                <!-- Sale & Refund Method -->
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                            Invoice (Optional)
                        </label>
                        <select
                            v-model="returnForm.sale_id"
                            class="w-full mt-1 rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="">General Return</option>
                            <option v-for="s in props.recentSales" :key="s.id" :value="s.id">
                                Inv #{{ s.invoice_no }} ({{ s.customer?.name || 'Walk-in' }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                            Refund Method
                        </label>
                        <select
                            v-model="returnForm.refund_payment_method"
                            class="w-full mt-1 rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="cash">Cash Refund</option>
                            <option value="jazzcash">JazzCash</option>
                            <option value="easypaisa">EasyPaisa</option>
                            <option value="bank">Bank Payout</option>
                            <option value="khata_deduction">Khata Balance Deduction</option>
                        </select>
                    </div>
                </div>

                <!-- Product Dropdown Picker -->
                <div>
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                        Select Product to Return
                    </label>
                    <select
                        v-model="selectedProductToAdd"
                        @change="handleSelectProductToReturn"
                        class="w-full mt-1 rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="">+ Choose product from inventory...</option>
                        <option v-for="p in props.products" :key="p.id" :value="p.id">
                            {{ p.name }} - {{ formatMoney(p.sale_price) }}
                        </option>
                    </select>
                </div>

                <!-- Compact Returned Items List -->
                <div v-if="returnCart.length > 0" class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-2.5 py-1.5 font-semibold">Item</th>
                                <th class="px-2.5 py-1.5 font-semibold w-16 text-center">Qty</th>
                                <th class="px-2.5 py-1.5 font-semibold">Refund</th>
                                <th class="px-2.5 py-1.5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            <tr v-for="(item, idx) in returnCart" :key="idx">
                                <td class="px-2.5 py-1.5 font-medium text-gray-900 dark:text-white max-w-[140px] truncate">
                                    {{ item.product_name }}
                                </td>
                                <td class="px-2.5 py-1.5 text-center">
                                    <Input
                                        v-model.number="item.quantity"
                                        type="number"
                                        min="1"
                                        class="h-6 w-14 text-xs text-center p-1"
                                        @input="item.line_total = item.quantity * item.unit_price"
                                    />
                                </td>
                                <td class="px-2.5 py-1.5 font-bold font-mono text-rose-600">
                                    {{ formatMoney(item.line_total) }}
                                </td>
                                <td class="px-2.5 py-1.5 text-right">
                                    <button @click="removeReturnItem(idx)" class="text-rose-500 hover:text-rose-700">
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Total Refund & Notes -->
                <div class="space-y-2 pt-1 border-t border-gray-200 dark:border-gray-800">
                    <div class="flex items-center justify-between text-xs">
                        <label class="font-bold text-gray-900 dark:text-white">Total Refund Amount (Rs.):</label>
                        <Input
                            v-model.number="returnForm.refund_amount"
                            type="number"
                            min="0"
                            class="h-7 w-28 font-mono font-bold text-rose-600 text-right text-xs"
                        />
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Return Reason / Notes</label>
                        <Input
                            v-model="returnForm.notes"
                            type="text"
                            placeholder="E.g. Defective handset, customer returned"
                            class="h-7 text-xs mt-0.5"
                        />
                    </div>
                </div>

                <DialogFooter class="pt-2">
                    <Button type="button" variant="outline" @click="isProcessModalOpen = false" class="text-xs">
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="returnForm.processing || returnCart.length === 0"
                        class="bg-rose-600 text-white hover:bg-rose-700 text-xs font-medium cursor-pointer"
                    >
                        <span v-if="returnForm.processing">Processing...</span>
                        <span v-else>Confirm Return</span>
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Return Details Modal -->
    <Dialog v-model:open="isDetailModalOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center justify-between text-base font-bold text-rose-600">
                    <span>Sale Return Details</span>
                    <span class="font-mono text-sm">{{ selectedReturn?.return_no }}</span>
                </DialogTitle>
                <DialogDescription class="text-xs text-gray-500">
                    Date: {{ selectedReturn ? formatDate(selectedReturn.created_at) : '' }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="selectedReturn" class="space-y-4 py-2 text-xs">
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                    <p class="font-semibold text-gray-700 dark:text-gray-300">Customer Details:</p>
                    <p class="font-bold text-gray-900 dark:text-white mt-0.5">
                        {{ selectedReturn.customer?.name || 'Walk-in Customer' }}
                    </p>
                    <p v-if="selectedReturn.sale?.invoice_no" class="text-gray-500 font-mono">
                        Original Invoice: {{ selectedReturn.sale.invoice_no }}
                    </p>
                </div>

                <div class="space-y-1">
                    <p class="font-semibold text-gray-700 dark:text-gray-300">Returned Items:</p>
                    <div class="rounded-lg border border-gray-200 divide-y divide-gray-200 dark:border-gray-800 dark:divide-gray-800">
                        <div v-for="item in selectedReturn.items" :key="item.id" class="flex items-center justify-between p-2.5">
                            <div>
                                <p class="font-bold text-gray-900 dark:text-white">{{ item.product?.name }}</p>
                                <p v-if="item.product_imei" class="text-[10px] font-mono text-blue-600">
                                    IMEI Restored: {{ item.product_imei.imei_1 }}
                                </p>
                                <p class="text-[10px] text-gray-500">Qty: {{ item.quantity }} x {{ formatMoney(item.unit_price) }}</p>
                            </div>
                            <span class="font-bold font-mono text-rose-600">{{ formatMoney(item.line_total) }}</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-1 pt-2 border-t border-gray-200 font-mono">
                    <div class="flex justify-between font-bold text-sm text-rose-600">
                        <span>Refund Paid:</span>
                        <span>{{ formatMoney(selectedReturn.refund_amount) }}</span>
                    </div>
                    <div class="flex justify-between text-gray-500">
                        <span>Payment Method:</span>
                        <span class="capitalize">{{ selectedReturn.refund_payment_method.replace('_', ' ') }}</span>
                    </div>
                    <p v-if="selectedReturn.notes" class="text-gray-500 font-sans mt-2 pt-1 border-t border-gray-100">
                        Notes: {{ selectedReturn.notes }}
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="isDetailModalOpen = false" class="text-xs">
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
