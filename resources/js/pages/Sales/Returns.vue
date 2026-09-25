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
import { toast } from 'vue-sonner';

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
                href: layoutProps.currentTeam
                    ? `/${layoutProps.currentTeam.slug}/dashboard`
                    : '/dashboard',
            },
            {
                title: 'Sale Returns',
                href: layoutProps.currentTeam
                    ? `/${layoutProps.currentTeam.slug}/sales-returns`
                    : '/sales-returns',
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
    const routeName = props.currentTeam
        ? `/${props.currentTeam.slug}/sales-returns`
        : '/sales-returns';
    router.get(
        routeName,
        {
            search: search.value || undefined,
            date_filter:
                dateFilter.value !== 'all' ? dateFilter.value : undefined,
            date_from:
                dateFilter.value === 'custom' ? dateFrom.value : undefined,
            date_to: dateFilter.value === 'custom' ? dateTo.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
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
    sale_item_id?: number | null;
    product_id: number;
    product_name: string;
    is_serialized: boolean;
    product_imei_id?: number | null;
    imei_1?: string | null;
    purchased_quantity?: number;
    returned_quantity?: number;
    remaining_quantity?: number;
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

const selectedSaleForReturn = computed(() => {
    if (!returnForm.sale_id) return null;
    return props.recentSales.find((s) => s.id === Number(returnForm.sale_id)) || null;
});

watch(() => returnForm.sale_id, (saleId) => {
    if (!saleId) {
        returnCart.value = [];
        returnForm.customer_id = '';
        return;
    }
    const sale = props.recentSales.find((s) => s.id === Number(saleId));
    if (sale) {
        returnForm.customer_id = sale.customer?.id || '';
        returnCart.value = (sale.items || [])
            .filter((item: any) => Number(item.remaining_quantity) > 0)
            .map((item: any) => {
                const maxQty = Number(item.remaining_quantity) || 1;
                const unitPrice = Number(item.unit_price) || 0;
                return {
                    sale_item_id: item.id,
                    product_id: item.product_id,
                    product_name: item.product_name || item.product?.name || 'Item',
                    is_serialized: Boolean(item.is_serialized || item.product?.is_serialized),
                    product_imei_id: item.product_imei_id || null,
                    imei_1: item.imei_1 || null,
                    purchased_quantity: Number(item.quantity) || 1,
                    returned_quantity: Number(item.returned_quantity) || 0,
                    remaining_quantity: maxQty,
                    quantity: maxQty,
                    unit_price: unitPrice,
                    line_total: maxQty * unitPrice,
                };
            });
    }
});

const handleSelectProductToReturn = () => {
    if (!selectedProductToAdd.value) return;
    const prodId = Number(selectedProductToAdd.value);
    const prod = props.products.find((p) => p.id === prodId);
    if (!prod) return;

    const price = Number(prod.sale_price) || 0;
    returnCart.value.push({
        sale_item_id: null,
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

const calculatedDueOffset = computed(() => {
    if (!selectedSaleForReturn.value) return 0;
    const saleDue = Number(selectedSaleForReturn.value.due_amount) || 0;
    const custDebt = selectedSaleForReturn.value.customer
        ? Math.max(0, Number(selectedSaleForReturn.value.customer.current_balance) || 0)
        : 0;
    return Math.min(totalCalculatedReturn.value, saleDue, custDebt);
});

const calculatedNetRefund = computed(() => {
    return Math.max(0, totalCalculatedReturn.value - calculatedDueOffset.value);
});

watch(calculatedNetRefund, (newVal) => {
    returnForm.refund_amount = newVal;
});

const submitReturn = () => {
    if (returnCart.value.length === 0) {
        toast.error('No Products Selected', { description: 'Please add at least one product to return.' });
        return;
    }

    for (const item of returnCart.value) {
        if (item.remaining_quantity !== undefined && item.quantity > item.remaining_quantity) {
            toast.error('Invalid Return Quantity', {
                description: `Return quantity for ${item.product_name} exceeds remaining returnable quantity (${item.remaining_quantity}).`,
            });
            return;
        }
    }

    if (returnForm.refund_amount > calculatedNetRefund.value + 0.01) {
        toast.error('Invalid Refund Amount', {
            description: `Refund amount (Rs. ${returnForm.refund_amount.toLocaleString()}) exceeds the maximum payable refund of Rs. ${calculatedNetRefund.value.toLocaleString()}.`,
        });
        return;
    }

    returnForm.items = returnCart.value.map((c) => ({
        sale_item_id: c.sale_item_id || null,
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
            toast.success('Sale Return Processed Successfully', {
                description: 'Inventory restored and financial transaction recorded.',
            });
        },
        onError: (errors) => {
            const msg = Object.values(errors).flat().join(' ') || 'Could not process sale return.';
            toast.error('Return Failed', { description: msg });
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
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div class="flex items-center gap-2">
                    <RotateCcw class="h-6 w-6 text-rose-600 dark:text-rose-400" />
                    <h1
                        class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white"
                    >
                        Sale Returns & Stock Reversals
                    </h1>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Process customer product returns, restore inventory stock,
                    and track cash or Khata refunds.
                </p>
            </div>
            <Button
                @click="openProcessModal"
                class="cursor-pointer gap-2 bg-rose-600 font-medium text-white hover:bg-rose-700"
            >
                <RotateCcw class="h-4 w-4" />
                <span>+ Process Sale Return</span>
            </Button>
        </div>

        <!-- Summary Statistics Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <!-- Today's Refund Total -->
            <div
                class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400"
                        >Today's Refunds</span
                    >
                    <div
                        class="rounded-lg bg-rose-50 p-2 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400"
                    >
                        <TrendingDown class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ formatMoney(props.stats.today_return_total) }}
                    </p>
                    <p
                        class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"
                    >
                        {{ props.stats.today_return_count }}
                        {{
                            props.stats.today_return_count === 1
                                ? 'return'
                                : 'returns'
                        }}
                        today
                    </p>
                </div>
            </div>

            <!-- Today's Return Count -->
            <div
                class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400"
                        >Today's Return Entries</span
                    >
                    <div
                        class="rounded-lg bg-blue-50 p-2 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400"
                    >
                        <RotateCcw class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ props.stats.today_return_count }}
                    </p>
                    <p
                        class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"
                    >
                        Total return orders today
                    </p>
                </div>
            </div>

            <!-- All Time Returns Total -->
            <div
                class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400"
                        >All-Time Refunds</span
                    >
                    <div
                        class="rounded-lg bg-purple-50 p-2 text-purple-600 dark:bg-purple-950/40 dark:text-purple-400"
                    >
                        <Wallet class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ formatMoney(props.stats.all_time_return_total) }}
                    </p>
                    <p
                        class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"
                    >
                        Total refund payouts
                    </p>
                </div>
            </div>

            <!-- All Time Count -->
            <div
                class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400"
                        >All-Time Entries</span
                    >
                    <div
                        class="rounded-lg bg-amber-50 p-2 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400"
                    >
                        <Calendar class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ props.stats.all_time_return_count }}
                    </p>
                    <p
                        class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"
                    >
                        Total recorded return receipts
                    </p>
                </div>
            </div>
        </div>

        <!-- Filter Toolbar & Interactive Columns Dropdown -->
        <div
            class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-2xs sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative min-w-[220px] flex-1">
                    <Search
                        class="absolute top-2.5 left-3 h-4 w-4 text-gray-400"
                    />
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
                        class="h-9 w-full rounded-md border border-gray-200 bg-white px-3 text-xs font-medium text-gray-700 focus:ring-1 focus:ring-[#003B7D] dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    >
                        <option value="all">All Time</option>
                        <option value="today">Today</option>
                        <option value="this_week">This Week</option>
                        <option value="this_month">This Month</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>

                <template v-if="dateFilter === 'custom'">
                    <Input
                        v-model="dateFrom"
                        type="date"
                        class="h-9 text-xs"
                        @change="applyFilters"
                    />
                    <span class="text-xs text-gray-400">to</span>
                    <Input
                        v-model="dateTo"
                        type="date"
                        class="h-9 text-xs"
                        @change="applyFilters"
                    />
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
                            <SlidersHorizontal
                                class="h-3.5 w-3.5 text-rose-600 dark:text-rose-400"
                            />
                            <span>Columns</span>
                            <span
                                class="ml-1 rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-950 dark:text-rose-300"
                            >
                                {{ activeColumnCount }}/8
                            </span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-60 space-y-1 p-2">
                        <DropdownMenuLabel
                            class="flex items-center justify-between px-1 py-1 text-xs font-bold text-gray-900 dark:text-white"
                        >
                            <span>Table Columns</span>
                            <button
                                type="button"
                                @click="resetColumns"
                                class="cursor-pointer text-[11px] font-semibold text-rose-600 hover:underline dark:text-rose-400"
                            >
                                Reset All
                            </button>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator class="my-1" />
                        <div
                            v-for="(label, key) in returnColumnLabels"
                            :key="key"
                            @click.stop="toggleReturnColumn(key)"
                            class="flex cursor-pointer items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-800 transition-colors select-none hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                        >
                            <span>{{ label }}</span>
                            <input
                                type="checkbox"
                                :checked="
                                    visibleColumns[
                                        key as keyof typeof visibleColumns
                                    ]
                                "
                                @change="toggleReturnColumn(key)"
                                @click.stop
                                class="h-4 w-4 cursor-pointer rounded border-gray-300 text-rose-600 focus:ring-rose-500 dark:text-rose-400 dark:focus:ring-rose-400"
                            />
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Returns Data Table -->
        <div
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xs dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead
                        class="border-b border-gray-200 bg-gray-50/70 text-gray-600 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400"
                    >
                        <tr>
                            <th
                                v-if="visibleColumns.return_no"
                                class="px-4 py-3 font-semibold"
                            >
                                Return #
                            </th>
                            <th
                                v-if="visibleColumns.invoice_no"
                                class="px-4 py-3 font-semibold"
                            >
                                Original Invoice #
                            </th>
                            <th
                                v-if="visibleColumns.created_at"
                                class="px-4 py-3 font-semibold"
                            >
                                Date & Time
                            </th>
                            <th
                                v-if="visibleColumns.customer"
                                class="px-4 py-3 font-semibold"
                            >
                                Customer
                            </th>
                            <th
                                v-if="visibleColumns.items"
                                class="px-4 py-3 font-semibold"
                            >
                                Items Returned
                            </th>
                            <th
                                v-if="visibleColumns.refund_amount"
                                class="px-4 py-3 font-semibold"
                            >
                                Refund Amount
                            </th>
                            <th
                                v-if="visibleColumns.refund_method"
                                class="px-4 py-3 font-semibold"
                            >
                                Refund Method
                            </th>
                            <th
                                v-if="visibleColumns.actions"
                                class="px-4 py-3 text-right font-semibold"
                            >
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-200 dark:divide-gray-800"
                    >
                        <tr
                            v-for="ret in props.returns.data"
                            :key="ret.id"
                            class="transition-colors hover:bg-gray-50/50 dark:hover:bg-gray-800/40"
                        >
                            <td
                                v-if="visibleColumns.return_no"
                                class="px-4 py-3.5 font-mono font-bold text-rose-600 dark:text-rose-400"
                            >
                                {{ ret.return_no }}
                            </td>
                            <td
                                v-if="visibleColumns.invoice_no"
                                class="px-4 py-3.5 font-mono text-[#003B7D] dark:text-blue-400"
                            >
                                {{ ret.sale?.invoice_no || '-' }}
                            </td>
                            <td
                                v-if="visibleColumns.created_at"
                                class="px-4 py-3.5 font-mono text-[11px] text-gray-600 dark:text-gray-300"
                            >
                                {{ formatDate(ret.created_at) }}
                            </td>
                            <td
                                v-if="visibleColumns.customer"
                                class="px-4 py-3.5 font-medium text-gray-900 dark:text-white"
                            >
                                {{ ret.customer?.name || 'Walk-in Customer' }}
                            </td>
                            <td v-if="visibleColumns.items" class="px-4 py-3.5">
                                <span
                                    class="rounded bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-950/40 dark:text-rose-300"
                                >
                                    {{ ret.items.length }}
                                    {{
                                        ret.items.length === 1
                                            ? 'item'
                                            : 'items'
                                    }}
                                </span>
                            </td>
                            <td
                                v-if="visibleColumns.refund_amount"
                                class="px-4 py-3.5 text-sm font-bold text-rose-600 dark:text-rose-400"
                            >
                                {{ formatMoney(ret.refund_amount) }}
                            </td>
                            <td
                                v-if="visibleColumns.refund_method"
                                class="px-4 py-3.5 text-gray-700 capitalize dark:text-gray-300"
                            >
                                {{
                                    ret.refund_payment_method.replace('_', ' ')
                                }}
                            </td>
                            <td
                                v-if="visibleColumns.actions"
                                class="px-4 py-3.5 text-right"
                            >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="viewReturnDetails(ret)"
                                    class="h-7 cursor-pointer gap-1 text-xs text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                >
                                    <Eye class="h-3.5 w-3.5" />
                                    <span>Details</span>
                                </Button>
                            </td>
                        </tr>

                        <tr v-if="props.returns.data.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-12 text-center text-gray-500 dark:text-gray-400"
                            >
                                <RotateCcw
                                    class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600"
                                />
                                <p class="mt-2 text-xs font-medium">
                                    No sale returns recorded
                                </p>
                                <p class="text-[11px] text-gray-400">
                                    Process product returns using the + Process
                                    Sale Return button above.
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
                    Showing
                    <span
                        class="font-semibold text-gray-700 dark:text-gray-300"
                        >{{ props.returns.data.length }}</span
                    >
                    of
                    <span
                        class="font-semibold text-gray-700 dark:text-gray-300"
                        >{{ props.returns.total }}</span
                    >
                    returns
                </div>
                <div class="flex items-center gap-1">
                    <component
                        :is="link.url ? 'a' : 'span'"
                        v-for="(link, i) in props.returns.links"
                        :key="i"
                        :href="link.url || undefined"
                        v-html="link.label"
                        :class="[
                            'rounded-md px-2.5 py-1 text-xs transition-colors',
                            link.active
                                ? 'bg-rose-600 font-bold text-white'
                                : link.url
                                  ? 'text-gray-700 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-800'
                                  : 'cursor-not-allowed text-gray-400',
                        ]"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Process Return Modal (Spacious & Refined) -->
    <Dialog v-model:open="isProcessModalOpen">
        <DialogContent class="max-w-4xl w-[95vw] max-h-[90vh] flex flex-col p-6 rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900 overflow-hidden">
            <DialogHeader class="border-b border-slate-100 pb-3 shrink-0 dark:border-slate-800">
                <DialogTitle class="flex items-center gap-2.5 text-base font-black text-slate-900 dark:text-white">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400">
                        <RotateCcw class="h-5 w-5" />
                    </div>
                    <span>Process Product Sale Return & Stock Reversal</span>
                </DialogTitle>
                <DialogDescription class="text-xs text-slate-500 mt-0.5">
                    Select a completed sale invoice or return manual items. Returned items are restored to stock and customer ledger is updated.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitReturn" class="flex-1 flex flex-col min-h-0 space-y-3.5 py-1 text-xs">
                <!-- Sale & Refund Method Selectors -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 shrink-0">
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300">
                            Select Sale Invoice (Recommended)
                        </label>
                        <select
                            v-model="returnForm.sale_id"
                            class="h-9 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-900 shadow-2xs focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        >
                            <option value="">General Return (No Invoice)</option>
                            <option
                                v-for="s in props.recentSales"
                                :key="s.id"
                                :value="s.id"
                            >
                                Inv #{{ s.invoice_no }} &bull; {{ s.customer?.name || 'Walk-in' }} (Rs {{ Number(s.net_amount).toLocaleString() }})
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300">
                            Refund Payout Mode
                        </label>
                        <select
                            v-model="returnForm.refund_payment_method"
                            class="h-9 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-900 shadow-2xs focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        >
                            <option value="cash">Cash Refund</option>
                            <option value="jazzcash">JazzCash Payout</option>
                            <option value="easypaisa">EasyPaisa Payout</option>
                            <option value="bank">Bank Payout</option>
                            <option value="card">Card Refund</option>
                            <option value="khata_credit">Khata Credit (Keep as Advance)</option>
                        </select>
                    </div>
                </div>

                <!-- Invoice Details Overview Banner (If Invoice Selected) -->
                <div v-if="selectedSaleForReturn" class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3 text-xs dark:border-slate-800 dark:bg-slate-800/40 grid grid-cols-2 sm:grid-cols-4 gap-2.5 shrink-0 shadow-2xs">
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">Invoice #</span>
                        <span class="font-mono font-black text-[#003B7D] dark:text-blue-400 text-sm">{{ selectedSaleForReturn.invoice_no }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">Customer</span>
                        <span class="font-bold text-slate-900 dark:text-white block truncate">{{ selectedSaleForReturn.customer?.name || 'Walk-In Customer' }}</span>
                        <span v-if="selectedSaleForReturn.customer?.phone" class="font-mono text-[10px] text-slate-400">{{ selectedSaleForReturn.customer.phone }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">Invoice Bill</span>
                        <span class="font-black text-slate-900 dark:text-white">Rs {{ Number(selectedSaleForReturn.net_amount).toLocaleString() }}</span>
                        <span class="text-[10px] text-emerald-600 block font-semibold">(Paid: Rs {{ Number(selectedSaleForReturn.paid_amount).toLocaleString() }})</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">Unpaid Due</span>
                        <span class="font-black text-sm" :class="Number(selectedSaleForReturn.due_amount) > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-emerald-700 dark:text-emerald-400'">
                            {{ Number(selectedSaleForReturn.due_amount) > 0 ? `Rs ${Number(selectedSaleForReturn.due_amount).toLocaleString()}` : 'Rs 0 (Paid)' }}
                        </span>
                    </div>
                </div>

                <!-- Manual Product Picker if no invoice -->
                <div v-if="!returnForm.sale_id" class="space-y-1 shrink-0">
                    <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300">
                        Add Product to Return
                    </label>
                    <select
                        v-model="selectedProductToAdd"
                        @change="handleSelectProductToReturn"
                        class="h-9 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-900 shadow-2xs focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    >
                        <option value="">+ Choose product from inventory...</option>
                        <option
                            v-for="p in props.products"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.name }} &bull; Rs {{ Number(p.sale_price).toLocaleString() }}
                        </option>
                    </select>
                </div>

                <!-- Return Items Table -->
                <div class="flex-1 min-h-0 overflow-y-auto rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs [scrollbar-width:thin]">
                    <div v-if="returnCart.length === 0" class="py-12 text-center text-xs text-slate-400">
                        <Package class="mx-auto h-8 w-8 text-slate-300 mb-2" />
                        <div class="font-bold text-slate-600 dark:text-slate-300">No Products Selected</div>
                        <div class="text-[11px] mt-0.5">Select a completed invoice or pick items to process return.</div>
                    </div>
                    <table v-else class="w-full text-left text-xs">
                        <thead class="sticky top-0 bg-slate-100 text-[11px] uppercase tracking-wider text-slate-600 dark:bg-slate-800 dark:text-slate-300 z-10 shadow-xs">
                            <tr>
                                <th class="px-3.5 py-2.5">Product & Details</th>
                                <th class="px-3.5 py-2.5 text-center">Purchased</th>
                                <th class="px-3.5 py-2.5 text-center">Already Ret.</th>
                                <th class="px-3.5 py-2.5 text-center">Available Ret.</th>
                                <th class="px-3.5 py-2.5 text-center w-28">Return Qty</th>
                                <th class="px-3.5 py-2.5 text-right">Unit Price</th>
                                <th class="px-3.5 py-2.5 text-right">Refund Total</th>
                                <th class="px-3.5 py-2.5 text-center w-12">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                            <tr v-for="(item, idx) in returnCart" :key="idx" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-3.5 py-2.5 font-bold text-slate-800 dark:text-slate-200">
                                    <div>{{ item.product_name }}</div>
                                    <div v-if="item.is_serialized && item.imei_1" class="font-mono text-[10px] font-bold text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-200 dark:bg-indigo-950 dark:text-indigo-300 dark:border-indigo-800 inline-block mt-0.5">
                                        IMEI: {{ item.imei_1 }}
                                    </div>
                                </td>
                                <td class="px-3.5 py-2.5 text-center font-bold text-slate-700 dark:text-slate-300">
                                    {{ item.purchased_quantity || '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 text-center font-semibold text-slate-500">
                                    {{ item.returned_quantity || 0 }}
                                </td>
                                <td class="px-3.5 py-2.5 text-center font-black" :class="(item.remaining_quantity || 0) > 0 ? 'text-emerald-600' : 'text-slate-400'">
                                    {{ item.remaining_quantity ?? item.quantity }}
                                </td>
                                <td class="px-3.5 py-2.5 text-center">
                                    <Input
                                        v-model.number="item.quantity"
                                        type="number"
                                        min="1"
                                        :max="item.remaining_quantity || undefined"
                                        class="h-8 w-20 text-center text-xs font-black rounded-xl border-slate-300 focus:border-[#003B7D] mx-auto"
                                        @input="
                                            if (item.remaining_quantity !== undefined && item.quantity > item.remaining_quantity) {
                                                item.quantity = item.remaining_quantity;
                                            }
                                            if (item.quantity < 1) item.quantity = 1;
                                            item.line_total = item.quantity * item.unit_price;
                                        "
                                    />
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-medium text-slate-700 dark:text-slate-300">
                                    Rs {{ Number(item.unit_price).toLocaleString() }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-black text-rose-600 dark:text-rose-400">
                                    Rs {{ Number(item.line_total).toLocaleString() }}
                                </td>
                                <td class="px-3.5 py-2.5 text-center">
                                    <button
                                        type="button"
                                        @click="removeReturnItem(idx)"
                                        class="text-rose-500 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300 transition"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Financial Settlement Card: 3 Unified Columns -->
                <div class="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm dark:border-slate-800 dark:bg-slate-900 shrink-0">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5">
                        <!-- Col 1: Financial breakdown -->
                        <div class="space-y-1.5 text-xs rounded-xl bg-slate-50/70 p-3 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                            <div class="flex justify-between text-slate-600 dark:text-slate-400">
                                <span class="font-medium">Total Return Value:</span>
                                <span class="font-black text-slate-900 dark:text-white">Rs {{ totalCalculatedReturn.toLocaleString() }}</span>
                            </div>
                            <div v-if="calculatedDueOffset > 0" class="flex justify-between text-amber-600 dark:text-amber-400 font-bold">
                                <span>Offset Unpaid Due:</span>
                                <span>-Rs {{ calculatedDueOffset.toLocaleString() }}</span>
                            </div>
                            <div class="flex justify-between text-emerald-600 dark:text-emerald-400 font-black text-sm border-t pt-1.5 border-slate-200 dark:border-slate-700">
                                <span>Net Refund Payable:</span>
                                <span>Rs {{ calculatedNetRefund.toLocaleString() }}</span>
                            </div>
                        </div>

                        <!-- Col 2: Custom Refund Override -->
                        <div class="space-y-1.5 text-xs rounded-xl bg-slate-50/70 p-3 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                            <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300 block">Actual Refund Paid (PKR):</label>
                            <Input
                                v-model.number="returnForm.refund_amount"
                                type="number"
                                min="0"
                                :max="calculatedNetRefund"
                                step="0.01"
                                class="h-9 w-full rounded-xl border-slate-300 font-black text-sm text-slate-900 focus:border-[#003B7D]"
                            />
                            <span v-if="returnForm.errors.refund_amount" class="text-[10px] font-bold text-rose-500 block">
                                {{ returnForm.errors.refund_amount }}
                            </span>
                            <span v-else class="text-[10px] text-slate-500 block">Defaults to net payable after due debt deduction.</span>
                        </div>

                        <!-- Col 3: Notes & Safety -->
                        <div class="space-y-1.5 text-xs rounded-xl bg-slate-50/70 p-3 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                            <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300 block">Return Reason / Notes:</label>
                            <Input
                                v-model="returnForm.notes"
                                type="text"
                                placeholder="e.g. Defective unit, model replacement"
                                class="h-9 text-xs rounded-xl"
                            />
                        </div>
                    </div>
                </div>

                <DialogFooter class="pt-3 border-t border-slate-100 dark:border-slate-800 shrink-0 gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isProcessModalOpen = false"
                        class="rounded-xl font-bold text-xs"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="returnForm.processing || returnCart.length === 0"
                        class="rounded-xl bg-rose-600 text-xs font-bold text-white shadow-sm hover:bg-rose-700 transition active:scale-95 disabled:opacity-40"
                    >
                        <span v-if="returnForm.processing" class="flex items-center gap-1.5">
                            <div class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent"></div>
                            <span>Processing Return...</span>
                        </span>
                        <span v-else>Confirm & Process Return (Rs {{ Number(returnForm.refund_amount).toLocaleString() }})</span>
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Return Details Modal (Polished Voucher Layout) -->
    <Dialog v-model:open="isDetailModalOpen">
        <DialogContent class="max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
            <DialogHeader class="border-b border-slate-100 pb-3 dark:border-slate-800">
                <DialogTitle class="flex items-center justify-between text-base font-black text-slate-900 dark:text-white">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400">
                            <RotateCcw class="h-4 w-4" />
                        </div>
                        <span>Return Voucher</span>
                    </div>
                    <span class="font-mono text-sm font-black text-[#003B7D] dark:text-blue-400">{{ selectedReturn?.return_no }}</span>
                </DialogTitle>
                <DialogDescription class="text-xs text-slate-500 mt-0.5">
                    Processed on {{ selectedReturn ? formatDate(selectedReturn.created_at) : '' }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="selectedReturn" class="space-y-3.5 py-2 text-xs">
                <!-- Customer info card -->
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-800/40">
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Customer Details</span>
                    <div class="font-black text-slate-900 dark:text-white text-sm mt-0.5">
                        {{ selectedReturn.customer?.name || 'Walk-in Customer' }}
                    </div>
                    <div v-if="selectedReturn.sale?.invoice_no" class="font-mono text-slate-500 text-[11px] mt-0.5">
                        Original Invoice: #{{ selectedReturn.sale.invoice_no }}
                    </div>
                </div>

                <!-- Returned Items List -->
                <div class="space-y-1.5">
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Restored Items:</span>
                    <div class="divide-y divide-slate-100 rounded-2xl border border-slate-200 dark:divide-slate-800 dark:border-slate-800 overflow-hidden">
                        <div
                            v-for="item in selectedReturn.items"
                            :key="item.id"
                            class="flex items-center justify-between p-3"
                        >
                            <div>
                                <div class="font-bold text-slate-900 dark:text-white">
                                    {{ item.product?.name }}
                                </div>
                                <div
                                    v-if="item.product_imei"
                                    class="font-mono text-[10px] font-bold text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-200 dark:bg-indigo-950 dark:text-indigo-300 dark:border-indigo-800 inline-block mt-0.5"
                                >
                                    IMEI Restored: {{ item.product_imei.imei_1 }}
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">
                                    Qty: {{ item.quantity }} &times; {{ formatMoney(item.unit_price) }}
                                </div>
                            </div>
                            <span class="font-mono font-black text-rose-600 text-sm">
                                {{ formatMoney(item.line_total) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Financial totals -->
                <div class="space-y-1.5 rounded-2xl border border-slate-200 bg-slate-50/70 p-3 font-mono text-xs dark:border-slate-800 dark:bg-slate-800/40">
                    <div class="flex justify-between text-slate-600 dark:text-slate-400">
                        <span>Total Return Value:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ formatMoney(selectedReturn.total_return_amount) }}</span>
                    </div>
                    <div class="flex justify-between font-black text-sm text-rose-600 border-t border-slate-200 dark:border-slate-700 pt-1.5">
                        <span>Refund Paid:</span>
                        <span>{{ formatMoney(selectedReturn.refund_amount) }}</span>
                    </div>
                    <div class="flex justify-between text-slate-500 font-sans text-[11px]">
                        <span>Payment Method:</span>
                        <span class="capitalize font-bold">{{ selectedReturn.refund_payment_method.replace('_', ' ') }}</span>
                    </div>
                    <div v-if="selectedReturn.notes" class="text-slate-500 font-sans text-[11px] border-t border-slate-200 dark:border-slate-700 pt-1">
                        Reason: {{ selectedReturn.notes }}
                    </div>
                </div>
            </div>

            <DialogFooter class="gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                <Button
                    variant="outline"
                    @click="isDetailModalOpen = false"
                    class="rounded-xl font-bold text-xs"
                >
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
