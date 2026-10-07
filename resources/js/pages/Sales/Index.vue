<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Calendar,
    Check,
    CreditCard,
    DollarSign,
    Eye,
    Filter,
    Package,
    Plus,
    Printer,
    RefreshCw,
    Search,
    ShoppingBag,
    ShoppingCart,
    Tag,
    Trash2,
    TrendingUp,
    UserCheck,
    Users,
    Wallet,
    X,
    SlidersHorizontal,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import ThermalReceipt from '@/components/ThermalReceipt.vue';

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
    current_balance?: string | number;
}

interface User {
    id: number;
    name: string;
}

interface ProductImei {
    id: number;
    imei_1: string;
    status: string;
}

interface Product {
    id: number;
    name: string;
    brand?: string | null;
    category?: string | null;
    is_serialized: boolean;
    sale_price: string | number;
    cost_price: string | number;
    stock_quantity: number;
    available_imeis?: ProductImei[];
}

interface SaleItem {
    id: number;
    product_id: number;
    product_imei_id?: number | null;
    quantity: number | string;
    unit_cost: number | string;
    unit_price: number | string;
    line_total: number | string;
    product?: Product | null;
    product_imei?: ProductImei | null;
}

interface Sale {
    id: number;
    invoice_no: string;
    customer_id?: number | null;
    total_amount: number | string;
    discount_amount: number | string;
    net_amount: number | string;
    paid_amount: number | string;
    change_amount: number | string;
    payment_method: string;
    payment_details?: any;
    cashier_id: number;
    created_at: string;
    customer?: Customer | null;
    cashier?: User | null;
    items: SaleItem[];
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
    sales: PaginatedData<Sale>;
    products: Product[];
    customers: Customer[];
    filters: {
        search: string;
        payment_method: string;
        date_filter: string;
        date_from?: string;
        date_to?: string;
    };
    stats: {
        today_revenue: number;
        today_count: number;
        month_revenue: number;
        all_time_revenue: number;
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
                title: 'Sales & Invoices',
                href: layoutProps.currentTeam
                    ? `/${layoutProps.currentTeam.slug}/sales`
                    : '/sales',
            },
        ],
    }),
});

// Table Column Customizer State
const defaultVisibleColumns = {
    invoice_no: true,
    created_at: true,
    customer: true,
    items: true,
    net_amount: true,
    payment_method: true,
    cashier: true,
    actions: true,
};

const visibleColumns = ref({ ...defaultVisibleColumns });

const saleColumnLabels: Record<keyof typeof defaultVisibleColumns, string> = {
    invoice_no: 'Invoice #',
    created_at: 'Date & Time',
    customer: 'Customer',
    items: 'Items',
    net_amount: 'Net Amount',
    payment_method: 'Payment Method',
    cashier: 'Cashier',
    actions: 'Action',
};

const STORAGE_KEY = 'faizan_mobile_sales_table_columns_v1';

onMounted(() => {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            visibleColumns.value = {
                ...defaultVisibleColumns,
                ...JSON.parse(saved),
            };
        }
    } catch (e) {
        console.error(e);
    }
});

const toggleSaleColumn = (key: string) => {
    const k = key as keyof typeof defaultVisibleColumns;
    visibleColumns.value[k] = !visibleColumns.value[k];
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(visibleColumns.value));
    } catch (e) {
        console.error(e);
    }
};

const resetColumns = () => {
    visibleColumns.value = { ...defaultVisibleColumns };
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch (e) {
        console.error(e);
    }
};

const activeColumnCount = computed(() => {
    return Object.values(visibleColumns.value).filter(Boolean).length;
});

// Filter state
const search = ref(props.filters.search || '');
const paymentMethod = ref(props.filters.payment_method || 'all');
const dateFilter = ref(props.filters.date_filter || 'all');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');

const applyFilters = () => {
    const routeName = props.currentTeam
        ? `/${props.currentTeam.slug}/sales`
        : '/sales';
    router.get(
        routeName,
        {
            search: search.value || undefined,
            payment_method:
                paymentMethod.value !== 'all' ? paymentMethod.value : undefined,
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
    paymentMethod.value = 'all';
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

// View Invoice Modal
const selectedSale = ref<Sale | null>(null);
const isInvoiceModalOpen = ref(false);

const viewInvoice = (sale: Sale) => {
    selectedSale.value = sale;
    isInvoiceModalOpen.value = true;
};

const formattedReceiptData = computed(() => {
    if (!selectedSale.value) return null;
    return {
        invoice_no: selectedSale.value.invoice_no,
        created_at: selectedSale.value.created_at,
        customer: selectedSale.value.customer,
        payment_method: selectedSale.value.payment_method,
        items: (selectedSale.value.items || []).map((item: any) => ({
            id: item.id,
            product_name: item.product?.name,
            product_brand: item.product?.brand,
            quantity: Number(item.quantity),
            unit_price: Number(item.unit_price),
            line_total: Number(item.line_total),
            imei: item.product_imei?.imei_1 || null,
        })),
        total_amount: Number(selectedSale.value.total_amount),
        discount_amount: Number(selectedSale.value.discount_amount),
        net_amount: Number(selectedSale.value.net_amount),
        paid_amount: Number(selectedSale.value.paid_amount),
        change_amount: Number(selectedSale.value.change_amount || 0),
    };
});

const printInvoice = () => {
    window.print();
};

// Direct Sale Modal & Form State
const isDirectSaleModalOpen = ref(false);
const productSearch = ref('');
const selectedProductId = ref<number | null>(null);
const selectedImeiId = ref<number | null>(null);
const itemQuantity = ref(1);

interface CartLineItem {
    product_id: number;
    product_name: string;
    is_serialized: boolean;
    product_imei_id?: number | null;
    imei_code?: string;
    quantity: number;
    unit_price: number;
    line_total: number;
}

const cart = ref<CartLineItem[]>([]);

const directSaleForm = useForm({
    customer_id: '' as number | string,
    payment_method: 'cash',
    discount_amount: 0,
    paid_amount: 0,
    items: [] as Array<{
        product_id: number;
        product_imei_id?: number | null;
        quantity: number;
        unit_price: number;
    }>,
});

const filteredProducts = computed(() => {
    if (!productSearch.value) return props.products.slice(0, 10);
    const q = productSearch.value.toLowerCase();
    return props.products.filter(
        (p) =>
            p.name.toLowerCase().includes(q) ||
            (p.brand && p.brand.toLowerCase().includes(q)) ||
            (p.category && p.category.toLowerCase().includes(q)),
    );
});

const activeProduct = computed(() => {
    if (!selectedProductId.value) return null;
    return props.products.find((p) => p.id === selectedProductId.value) || null;
});

const openDirectSaleModal = () => {
    cart.value = [];
    productSearch.value = '';
    selectedProductId.value = null;
    selectedImeiId.value = null;
    itemQuantity.value = 1;
    directSaleForm.reset();
    directSaleForm.clearErrors();
    isDirectSaleModalOpen.value = true;
};

const addProductToCart = (product: Product) => {
    if (product.is_serialized) {
        if (!product.available_imeis || product.available_imeis.length === 0) {
            alert('No available IMEIs for this handset.');
            return;
        }
        selectedProductId.value = product.id;
        selectedImeiId.value = product.available_imeis[0].id;
        return;
    }

    const existingIndex = cart.value.findIndex(
        (c) => c.product_id === product.id && !c.is_serialized,
    );
    if (existingIndex > -1) {
        cart.value[existingIndex].quantity += 1;
        cart.value[existingIndex].line_total =
            cart.value[existingIndex].quantity *
            cart.value[existingIndex].unit_price;
    } else {
        cart.value.push({
            product_id: product.id,
            product_name: product.name,
            is_serialized: false,
            quantity: 1,
            unit_price: Number(product.sale_price),
            line_total: Number(product.sale_price),
        });
    }
};

const addSerializedHandset = () => {
    if (!activeProduct.value || !selectedImeiId.value) return;
    const imeiObj = activeProduct.value.available_imeis?.find(
        (i) => i.id === selectedImeiId.value,
    );
    if (!imeiObj) return;

    if (cart.value.some((c) => c.product_imei_id === selectedImeiId.value)) {
        alert('This IMEI is already added to cart.');
        return;
    }

    cart.value.push({
        product_id: activeProduct.value.id,
        product_name: `${activeProduct.value.name} (IMEI: ${imeiObj.imei_1})`,
        is_serialized: true,
        product_imei_id: selectedImeiId.value,
        imei_code: imeiObj.imei_1,
        quantity: 1,
        unit_price: Number(activeProduct.value.sale_price),
        line_total: Number(activeProduct.value.sale_price),
    });

    selectedProductId.value = null;
    selectedImeiId.value = null;
};

const removeFromCart = (index: number) => {
    cart.value.splice(index, 1);
};

const cartSubtotal = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.line_total, 0);
});

const cartNetTotal = computed(() => {
    const disc = Number(directSaleForm.discount_amount) || 0;
    return Math.max(0, cartSubtotal.value - disc);
});

const cartChangeAmount = computed(() => {
    if (directSaleForm.payment_method === 'udhaar') return 0;
    const paid = Number(directSaleForm.paid_amount) || 0;
    return Math.max(0, paid - cartNetTotal.value);
});

const submitDirectSale = () => {
    if (cart.value.length === 0) {
        alert('Please add at least one product to the sale.');
        return;
    }

    if (
        directSaleForm.payment_method === 'udhaar' &&
        !directSaleForm.customer_id
    ) {
        directSaleForm.setError(
            'customer_id',
            'Customer is required for Udhaar (Khata) sales.',
        );
        return;
    }

    directSaleForm.items = cart.value.map((c) => ({
        product_id: c.product_id,
        product_imei_id: c.product_imei_id || null,
        quantity: c.quantity,
        unit_price: c.unit_price,
    }));

    if (
        !directSaleForm.paid_amount &&
        directSaleForm.payment_method !== 'udhaar'
    ) {
        directSaleForm.paid_amount = cartNetTotal.value;
    }

    const routePrefix = props.currentTeam ? `/${props.currentTeam.slug}` : '';
    directSaleForm.post(`${routePrefix}/sales`, {
        onSuccess: () => {
            isDirectSaleModalOpen.value = false;
            cart.value = [];
            directSaleForm.reset();
        },
    });
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

const getPaymentBadge = (method: string) => {
    switch (method.toLowerCase()) {
        case 'cash':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300';
        case 'jazzcash':
            return 'bg-red-100 text-red-800 border-red-200 dark:bg-red-950/40 dark:text-red-300';
        case 'easypaisa':
            return 'bg-green-100 text-green-800 border-green-200 dark:bg-green-950/40 dark:text-green-300';
        case 'bank':
        case 'card':
            return 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300';
        case 'udhaar':
            return 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-800 dark:text-gray-300';
    }
};
</script>

<template>
    <Head title="Sales & Direct Sales" />

    <div class="space-y-6 p-4 md:p-6">
        <!-- Page Header & Action -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div class="flex items-center gap-2">
                    <ShoppingBag class="h-6 w-6 text-[#003B7D]" />
                    <h1
                        class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white"
                    >
                        Sales History & Direct Sales
                    </h1>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Review past invoices, customer transactions, and create
                    direct sales without opening the full POS interface.
                </p>
            </div>
            <Button
                @click="openDirectSaleModal"
                class="cursor-pointer gap-2 bg-[#003B7D] font-medium text-white hover:bg-[#002a59]"
            >
                <Plus class="h-4 w-4" />
                <span>+ Create Direct Sale</span>
            </Button>
        </div>

        <!-- Summary Statistics Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <!-- Today's Revenue -->
            <div
                class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400"
                        >Today's Sales Revenue</span
                    >
                    <div
                        class="rounded-lg bg-emerald-50 p-2 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400"
                    >
                        <TrendingUp class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ formatMoney(props.stats.today_revenue) }}
                    </p>
                    <p
                        class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"
                    >
                        {{ props.stats.today_count }}
                        {{ props.stats.today_count === 1 ? 'sale' : 'sales' }}
                        today
                    </p>
                </div>
            </div>

            <!-- Today's Sales Count -->
            <div
                class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400"
                        >Today's Invoices</span
                    >
                    <div
                        class="rounded-lg bg-blue-50 p-2 text-[#003B7D] dark:bg-blue-950/40 dark:text-blue-400"
                    >
                        <ShoppingCart class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ props.stats.today_count }}
                    </p>
                    <p
                        class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"
                    >
                        Total completed orders today
                    </p>
                </div>
            </div>

            <!-- This Month's Revenue -->
            <div
                class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400"
                        >This Month's Revenue</span
                    >
                    <div
                        class="rounded-lg bg-purple-50 p-2 text-purple-600 dark:bg-purple-950/40 dark:text-purple-400"
                    >
                        <Calendar class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ formatMoney(props.stats.month_revenue) }}
                    </p>
                    <p
                        class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"
                    >
                        Monthly accumulated revenue
                    </p>
                </div>
            </div>

            <!-- All Time Total Revenue -->
            <div
                class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400"
                        >All-Time Revenue</span
                    >
                    <div
                        class="rounded-lg bg-amber-50 p-2 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400"
                    >
                        <Wallet class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ formatMoney(props.stats.all_time_revenue) }}
                    </p>
                    <p
                        class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"
                    >
                        Total sales system volume
                    </p>
                </div>
            </div>
        </div>

        <!-- Filters & Search Toolbar -->
        <div
            class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-2xs sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <!-- Search Input -->
                <div class="relative min-w-[220px] flex-1">
                    <Search
                        class="absolute top-2.5 left-3 h-4 w-4 text-gray-400"
                    />
                    <Input
                        v-model="search"
                        type="text"
                        placeholder="Search Invoice #, Customer Name, Phone..."
                        class="pl-9 text-xs"
                    />
                </div>

                <!-- Payment Method Filter -->
                <div class="min-w-[150px]">
                    <select
                        v-model="paymentMethod"
                        @change="applyFilters"
                        class="h-9 w-full rounded-md border border-gray-200 bg-white px-3 text-xs font-medium text-gray-700 focus:ring-1 focus:ring-[#003B7D] dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    >
                        <option value="all">All Payment Methods</option>
                        <option value="cash">Cash</option>
                        <option value="jazzcash">JazzCash</option>
                        <option value="easypaisa">EasyPaisa</option>
                        <option value="bank">Bank</option>
                        <option value="udhaar">Udhaar (Khata)</option>
                    </select>
                </div>

                <!-- Date Range Filter -->
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

                <!-- Custom Dates -->
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

                <!-- Table Columns Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-9 gap-1.5 text-xs text-gray-700 dark:text-gray-300"
                        >
                            <SlidersHorizontal
                                class="h-3.5 w-3.5 text-[#003B7D]"
                            />
                            <span>Columns</span>
                            <span
                                class="ml-1 rounded-full bg-[#003B7D]/10 px-1.5 py-0.5 text-[10px] font-bold text-[#003B7D]"
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
                                class="cursor-pointer text-[11px] font-semibold text-[#003B7D] hover:underline"
                            >
                                Reset All
                            </button>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator class="my-1" />
                        <div
                            v-for="(label, key) in saleColumnLabels"
                            :key="key"
                            @click.stop="toggleSaleColumn(key)"
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
                                @change="toggleSaleColumn(key)"
                                @click.stop
                                class="h-4 w-4 cursor-pointer rounded border-gray-300 text-[#003B7D] focus:ring-[#003B7D]"
                            />
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Sales Data Table -->
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
                                v-if="visibleColumns.invoice_no"
                                class="px-4 py-3 font-semibold"
                            >
                                Invoice #
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
                                Items
                            </th>
                            <th
                                v-if="visibleColumns.net_amount"
                                class="px-4 py-3 font-semibold"
                            >
                                Net Amount
                            </th>
                            <th
                                v-if="visibleColumns.payment_method"
                                class="px-4 py-3 font-semibold"
                            >
                                Payment Method
                            </th>
                            <th
                                v-if="visibleColumns.cashier"
                                class="px-4 py-3 font-semibold"
                            >
                                Cashier
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
                            v-for="sale in props.sales.data"
                            :key="sale.id"
                            class="transition-colors hover:bg-gray-50/50 dark:hover:bg-gray-800/40"
                        >
                            <!-- Invoice # -->
                            <td
                                v-if="visibleColumns.invoice_no"
                                class="px-4 py-3.5 font-mono font-bold text-[#003B7D] dark:text-blue-400"
                            >
                                {{ sale.invoice_no }}
                            </td>

                            <!-- Date & Time -->
                            <td
                                v-if="visibleColumns.created_at"
                                class="px-4 py-3.5 font-mono text-[11px] text-gray-600 dark:text-gray-300"
                            >
                                {{ formatDate(sale.created_at) }}
                            </td>

                            <!-- Customer -->
                            <td
                                v-if="visibleColumns.customer"
                                class="px-4 py-3.5"
                            >
                                <div
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        sale.customer?.name ||
                                        'Walk-in Customer'
                                    }}
                                </div>
                                <div
                                    v-if="sale.customer?.phone"
                                    class="font-mono text-[11px] text-gray-400"
                                >
                                    {{ sale.customer.phone }}
                                </div>
                            </td>

                            <!-- Items count -->
                            <td v-if="visibleColumns.items" class="px-4 py-3.5">
                                <span
                                    class="rounded bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    {{ sale.items.length }}
                                    {{
                                        sale.items.length === 1
                                            ? 'item'
                                            : 'items'
                                    }}
                                </span>
                            </td>

                            <!-- Net Amount -->
                            <td
                                v-if="visibleColumns.net_amount"
                                class="px-4 py-3.5 text-sm font-bold text-emerald-600 dark:text-emerald-400"
                            >
                                {{ formatMoney(sale.net_amount) }}
                            </td>

                            <!-- Payment Method -->
                            <td
                                v-if="visibleColumns.payment_method"
                                class="px-4 py-3.5"
                            >
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold capitalize',
                                        getPaymentBadge(sale.payment_method),
                                    ]"
                                >
                                    <span>{{ sale.payment_method }}</span>
                                </span>
                            </td>

                            <!-- Cashier -->
                            <td
                                v-if="visibleColumns.cashier"
                                class="px-4 py-3.5 text-gray-600 dark:text-gray-300"
                            >
                                {{ sale.cashier?.name || 'Staff' }}
                            </td>

                            <!-- Action -->
                            <td
                                v-if="visibleColumns.actions"
                                class="px-4 py-3.5 text-right"
                            >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="viewInvoice(sale)"
                                    class="h-7 cursor-pointer gap-1 text-xs text-[#003B7D] hover:bg-blue-50 dark:hover:bg-blue-950/40"
                                >
                                    <Eye class="h-3.5 w-3.5" />
                                    <span>View Invoice</span>
                                </Button>
                            </td>
                        </tr>

                        <!-- Empty state -->
                        <tr v-if="props.sales.data.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-12 text-center text-gray-500 dark:text-gray-400"
                            >
                                <ShoppingBag
                                    class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600"
                                />
                                <p class="mt-2 text-xs font-medium">
                                    No sales transactions found
                                </p>
                                <p class="text-[11px] text-gray-400">
                                    Try adjusting your search filters or record
                                    a new direct sale.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="props.sales.links && props.sales.links.length > 3"
                class="flex items-center justify-between border-t border-gray-200 bg-gray-50/50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900/50"
            >
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Showing
                    <span
                        class="font-semibold text-gray-700 dark:text-gray-300"
                        >{{ props.sales.data.length }}</span
                    >
                    of
                    <span
                        class="font-semibold text-gray-700 dark:text-gray-300"
                        >{{ props.sales.total }}</span
                    >
                    sales
                </div>
                <div class="flex items-center gap-1">
                    <component
                        :is="link.url ? 'a' : 'span'"
                        v-for="(link, i) in props.sales.links"
                        :key="i"
                        :href="link.url || undefined"
                        v-html="link.label"
                        :class="[
                            'rounded-md px-2.5 py-1 text-xs transition-colors',
                            link.active
                                ? 'bg-[#003B7D] font-bold text-white'
                                : link.url
                                  ? 'text-gray-700 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-800'
                                  : 'cursor-not-allowed text-gray-400',
                        ]"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Direct Sale Modal -->
    <Dialog v-model:open="isDirectSaleModalOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle
                    class="flex items-center gap-2 text-base font-bold text-gray-900 dark:text-white"
                >
                    <ShoppingBag class="h-5 w-5 text-[#003B7D]" />
                    <span>Create Direct Sale (Invoice)</span>
                </DialogTitle>
                <DialogDescription
                    class="text-xs text-gray-500 dark:text-gray-400"
                >
                    Select customer, add products (handsets with IMEIs or
                    accessories), set discounts, and complete payment.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-2">
                <!-- Customer Selection -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label
                            class="text-xs font-semibold text-gray-700 dark:text-gray-300"
                        >
                            Select Customer
                        </label>
                        <select
                            v-model="directSaleForm.customer_id"
                            class="mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-900 focus:ring-1 focus:ring-[#003B7D] dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="">Walk-in Customer (Cash)</option>
                            <option
                                v-for="c in props.customers"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }} ({{ c.phone }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="text-xs font-semibold text-gray-700 dark:text-gray-300"
                        >
                            Payment Method
                            <span class="text-rose-500 dark:text-rose-400"
                                >*</span
                            >
                        </label>
                        <select
                            v-model="directSaleForm.payment_method"
                            class="mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-900 focus:ring-1 focus:ring-[#003B7D] dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="cash">Cash</option>
                            <option value="jazzcash">JazzCash</option>
                            <option value="easypaisa">EasyPaisa</option>
                            <option value="bank">Bank / Card</option>
                            <option value="udhaar">
                                Udhaar (Khata Credit)
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Add Products Section -->
                <div
                    class="space-y-3 rounded-xl border border-gray-200 bg-gray-50/50 p-3.5 dark:border-gray-800 dark:bg-gray-800/30"
                >
                    <div
                        class="flex items-center justify-between text-xs font-bold text-gray-900 dark:text-white"
                    >
                        <span>Select Products to Add</span>
                        <span class="text-[11px] font-normal text-gray-500"
                            >Search handset or accessory</span
                        >
                    </div>

                    <!-- Search Product Bar -->
                    <div class="relative">
                        <Search
                            class="absolute top-2.5 left-3 h-4 w-4 text-gray-400"
                        />
                        <Input
                            v-model="productSearch"
                            type="text"
                            placeholder="Type product name or brand..."
                            class="bg-white pl-9 text-xs dark:bg-gray-900"
                        />
                    </div>

                    <!-- Products Grid / Selection List -->
                    <div
                        class="grid max-h-40 grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2"
                    >
                        <div
                            v-for="p in filteredProducts"
                            :key="p.id"
                            @click="addProductToCart(p)"
                            class="flex cursor-pointer items-center justify-between rounded-lg border border-gray-200 bg-white p-2.5 text-xs transition-colors hover:border-[#003B7D] dark:border-gray-700 dark:bg-gray-900"
                        >
                            <div>
                                <p
                                    class="font-bold text-gray-900 dark:text-white"
                                >
                                    {{ p.name }}
                                </p>
                                <p class="text-[10px] text-gray-400">
                                    {{
                                        p.is_serialized
                                            ? 'Handset (Serialized)'
                                            : `Stock: ${p.stock_quantity} Pcs`
                                    }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p
                                    class="font-bold text-[#003B7D] dark:text-blue-400"
                                >
                                    {{ formatMoney(p.sale_price) }}
                                </p>
                                <span
                                    class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"
                                    >+ Add</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Serialized Handset IMEI Selector (if handset selected) -->
                    <div
                        v-if="activeProduct?.is_serialized"
                        class="space-y-2 rounded-lg border border-blue-200 bg-blue-50/70 p-3 dark:border-blue-800 dark:bg-blue-950/40"
                    >
                        <div
                            class="text-xs font-bold text-blue-900 dark:text-blue-200"
                        >
                            Select IMEI for {{ activeProduct.name }}
                        </div>
                        <div class="flex items-center gap-2">
                            <select
                                v-model="selectedImeiId"
                                class="flex-1 rounded-md border border-blue-200 bg-white px-2.5 py-1.5 font-mono text-xs text-gray-900 dark:bg-gray-800 dark:text-white"
                            >
                                <option
                                    v-for="imei in activeProduct.available_imeis"
                                    :key="imei.id"
                                    :value="imei.id"
                                >
                                    IMEI: {{ imei.imei_1 }}
                                </option>
                            </select>
                            <Button
                                size="sm"
                                @click="addSerializedHandset"
                                class="h-8 bg-[#003B7D] text-xs text-white"
                            >
                                Add Handset
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Cart Table -->
                <div class="space-y-2">
                    <div
                        class="text-xs font-bold text-gray-900 dark:text-white"
                    >
                        Sale Items List
                    </div>
                    <div
                        class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800"
                    >
                        <table class="w-full text-left text-xs">
                            <thead
                                class="bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                            >
                                <tr>
                                    <th class="px-3 py-2 font-semibold">
                                        Item
                                    </th>
                                    <th class="px-3 py-2 font-semibold">Qty</th>
                                    <th class="px-3 py-2 font-semibold">
                                        Price
                                    </th>
                                    <th class="px-3 py-2 font-semibold">
                                        Total
                                    </th>
                                    <th class="px-3 py-2 text-right">Remove</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-200 dark:divide-gray-800"
                            >
                                <tr v-for="(item, idx) in cart" :key="idx">
                                    <td
                                        class="px-3 py-2 font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ item.product_name }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <Input
                                            v-if="!item.is_serialized"
                                            v-model.number="item.quantity"
                                            type="number"
                                            min="1"
                                            class="h-7 w-16 text-center text-xs"
                                            @input="
                                                item.line_total =
                                                    item.quantity *
                                                    item.unit_price
                                            "
                                        />
                                        <span v-else class="font-mono">1</span>
                                    </td>
                                    <td class="px-3 py-2 font-mono">
                                        {{ formatMoney(item.unit_price) }}
                                    </td>
                                    <td
                                        class="px-3 py-2 font-mono font-bold text-emerald-600 dark:text-emerald-400"
                                    >
                                        {{ formatMoney(item.line_total) }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button
                                            @click="removeFromCart(idx)"
                                            class="text-rose-500 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="cart.length === 0">
                                    <td
                                        colspan="5"
                                        class="px-3 py-6 text-center text-xs text-gray-400"
                                    >
                                        No items in cart yet. Select products
                                        above.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payment Calculation Summary -->
                <div
                    class="space-y-3 rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40"
                >
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-500">Subtotal:</span>
                        <span
                            class="font-mono font-bold text-gray-900 dark:text-white"
                            >{{ formatMoney(cartSubtotal) }}</span
                        >
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <label
                            class="font-semibold text-gray-700 dark:text-gray-300"
                            >Discount (Rs.):</label
                        >
                        <Input
                            v-model.number="directSaleForm.discount_amount"
                            type="number"
                            min="0"
                            class="h-7 w-28 text-right font-mono text-xs font-bold"
                        />
                    </div>

                    <div
                        class="flex items-center justify-between border-t border-gray-200 pt-1 text-sm dark:border-gray-700"
                    >
                        <span class="font-bold text-gray-900 dark:text-white"
                            >Net Total Amount:</span
                        >
                        <span
                            class="font-mono text-base font-bold text-emerald-600 dark:text-emerald-400"
                            >{{ formatMoney(cartNetTotal) }}</span
                        >
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <label
                            class="font-semibold text-gray-700 dark:text-gray-300"
                            >Paid Amount (Rs.):</label
                        >
                        <Input
                            v-model.number="directSaleForm.paid_amount"
                            type="number"
                            min="0"
                            class="h-7 w-28 text-right font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400"
                        />
                    </div>

                    <div
                        v-if="directSaleForm.payment_method !== 'udhaar'"
                        class="flex items-center justify-between pt-1 text-xs"
                    >
                        <span class="text-gray-500">Change Return:</span>
                        <span
                            class="font-mono font-bold text-amber-600 dark:text-amber-400"
                            >{{ formatMoney(cartChangeAmount) }}</span
                        >
                    </div>
                </div>

                <DialogFooter class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isDirectSaleModalOpen = false"
                        class="text-xs"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        :disabled="
                            directSaleForm.processing || cart.length === 0
                        "
                        @click="submitDirectSale"
                        class="cursor-pointer bg-[#003B7D] text-xs font-medium text-white hover:bg-[#002a59]"
                    >
                        <span v-if="directSaleForm.processing"
                            >Processing...</span
                        >
                        <span v-else>Complete & Save Direct Sale</span>
                    </Button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Invoice Details Modal -->
    <Dialog v-model:open="isInvoiceModalOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle
                    class="flex items-center justify-between text-base font-bold text-gray-900 dark:text-white"
                >
                    <span>Invoice Details</span>
                    <span class="font-mono text-sm text-[#003B7D]">{{
                        selectedSale?.invoice_no
                    }}</span>
                </DialogTitle>
                <DialogDescription class="text-xs text-gray-500">
                    Date:
                    {{
                        selectedSale ? formatDate(selectedSale.created_at) : ''
                    }}
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="formattedReceiptData"
                class="max-h-[70vh] overflow-y-auto py-2"
            >
                <ThermalReceipt :receipt="formattedReceiptData" />
            </div>

            <DialogFooter class="flex justify-between gap-2 pt-2">
                <Button
                    variant="outline"
                    @click="isInvoiceModalOpen = false"
                    class="text-xs"
                >
                    Close
                </Button>
                <Button
                    type="button"
                    @click="printInvoice"
                    class="bg-[#003B7D] text-xs font-semibold text-white hover:bg-[#002b5c]"
                >
                    <Printer class="mr-1.5 h-3.5 w-3.5" /> Print Thermal Receipt
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
