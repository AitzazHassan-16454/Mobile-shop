<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    FileText,
    HandCoins,
    Plus,
    Printer,
    Search,
    ShoppingCart,
    Smartphone,
    User,
    Wallet,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import { computed, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import mobileSales from '@/routes/mobile-sales';
import pos from '@/routes/pos';
import usedPhones from '@/routes/used-phones';
import type { Team } from '@/types';

interface HandsetImei {
    id: number;
    imei_1: string;
    imei_2?: string | null;
    color?: string | null;
    storage?: string | null;
    condition?: string;
    pta_status?: string;
    purchase_cost?: number | string;
    warranty_days?: number;
}

interface Handset {
    id: number;
    name: string;
    brand?: string | null;
    sale_price: number | string;
    cost_price?: number | string;
    in_stock_imeis?: HandsetImei[];
    inStockImeis?: HandsetImei[];
}

interface CustomerOption {
    id: number;
    name: string;
    phone?: string | null;
    current_balance?: number | string;
}

interface SaleInvoiceItem {
    id: number;
    unit_price: number | string;
    line_total: number | string;
    product?: { id: number; name: string; brand?: string | null } | null;
    productImei?: {
        id: number;
        imei_1: string;
        imei_2?: string | null;
        color?: string | null;
        storage?: string | null;
        condition?: string;
    } | null;
}

interface SaleInvoice {
    id: number;
    invoice_no: string;
    net_amount: number | string;
    paid_amount: number | string;
    change_amount?: number | string;
    payment_method: string;
    created_at: string;
    customer?: { id: number; name: string; phone?: string | null } | null;
    cashier?: { id: number; name: string } | null;
    items?: SaleInvoiceItem[];
}

interface PurchaseInvoice {
    id: number;
    voucher_no: string;
    seller_name: string;
    seller_cnic?: string | null;
    seller_phone?: string | null;
    device_model: string;
    imei_1: string;
    imei_2?: string | null;
    purchase_amount: number | string;
    payment_method: string;
    applied_at?: string | null;
    created_at: string;
}

interface PaymentMethodOption {
    value: string;
    label: string;
}

const props = defineProps<{
    handsets: Handset[];
    customers: CustomerOption[];
    saleInvoices: SaleInvoice[];
    purchaseInvoices: PurchaseInvoice[];
    unappliedPurchases: Array<{
        id: number;
        voucher_no: string;
        seller_name: string;
        device_model: string;
        imei_1: string;
        purchase_amount: number | string;
        created_at: string;
    }>;
    shopInfo: { name: string; phone: string; address: string };
    paymentMethods: PaymentMethodOption[];
    buyPaymentMethods: PaymentMethodOption[];
    filters: { search: string; invoice_search: string };
    summary: {
        ready_count: number;
        ready_value: number;
        today_count: number;
        today_total: number;
        purchase_total: number;
        purchase_count: number;
    };
    latestSale?: SaleInvoice | null;
    latestPurchase?: PurchaseInvoice | null;
}>();

const page = usePage();
const currentTeamSlug = computed(
    () => (page.props.currentTeam as Team | undefined)?.slug || 'default',
);

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam ? '/dashboard' : '/',
            },
            {
                title: 'Mobile Sales',
                href: layoutProps.currentTeam
                    ? mobileSales.index(layoutProps.currentTeam.slug).url
                    : '/mobile-sales',
            },
        ],
    }),
});

// Flatten handsets into individual sellable units
interface SellableUnit {
    key: string;
    product: Handset;
    imei: HandsetImei;
}

const sellableUnits = computed<SellableUnit[]>(() =>
    props.handsets.flatMap((product) => {
        const imeis = product.in_stock_imeis ?? product.inStockImeis ?? [];
        return imeis.map((imei) => ({
            key: `${product.id}-${imei.id}`,
            product,
            imei,
        }));
    }),
);

// Tabs
type TabKey = 'sell' | 'buy' | 'sale_invoices' | 'purchase_invoices';
const activeTab = ref<TabKey>('sell');

const tabs = computed(() => [
    { key: 'sell' as const, label: 'Sell Handset', icon: ShoppingCart },
    { key: 'buy' as const, label: 'Buy Phone', icon: HandCoins },
    {
        key: 'sale_invoices' as const,
        label: 'Sale Invoices',
        icon: FileText,
        count: props.saleInvoices.length,
    },
    {
        key: 'purchase_invoices' as const,
        label: 'Purchase Invoices',
        icon: FileText,
        count: props.purchaseInvoices.length,
    },
]);

// Search (handset picker)
const search = ref(props.filters.search || '');
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const applyFilters = () => {
    router.get(
        mobileSales.index(currentTeamSlug.value).url,
        {
            search: search.value || undefined,
            invoice_search: invoiceSearch.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 350);
});

// Search (invoices)
const invoiceSearch = ref(props.filters.invoice_search || '');
let invoiceSearchTimeout: ReturnType<typeof setTimeout> | null = null;

// Pagination for purchase vouchers
const purchasePage = ref(1);
const purchasePerPage = ref(10);
const paginatedPurchases = computed(() => {
    const start = (purchasePage.value - 1) * purchasePerPage.value;
    return (props.purchaseInvoices || []).slice(
        start,
        start + purchasePerPage.value,
    );
});
const totalPurchasePages = computed(() =>
    Math.ceil((props.purchaseInvoices?.length || 0) / purchasePerPage.value) || 1,
);

watch(invoiceSearch, () => {
    purchasePage.value = 1;
    if (invoiceSearchTimeout) clearTimeout(invoiceSearchTimeout);
    invoiceSearchTimeout = setTimeout(() => {
        applyFilters();
    }, 350);
});

onMounted(() => {
    if (props.latestSale) {
        activeTab.value = 'sale_invoices';
        toast.success('Mobile sold successfully', {
            description: `Invoice #${props.latestSale.invoice_no} created.`,
        });
    }
    if (props.latestPurchase) {
        activeTab.value = 'purchase_invoices';
        toast.success('Phone purchase recorded', {
            description: `Voucher #${props.latestPurchase.voucher_no} created.`,
        });
    }
});

// ---------- Sell tab ----------
const selected = ref<SellableUnit | null>(null);

const saleForm = useForm({
    customer_id: '' as string | number,
    payment_method: 'cash',
    unit_price: '' as string | number,
    paid_amount: '' as string | number,
});

const selectUnit = (unit: SellableUnit) => {
    selected.value = unit;
    saleForm.unit_price = Number(unit.product.sale_price) || 0;
    saleForm.paid_amount = Number(unit.product.sale_price) || 0;
    saleForm.clearErrors();
};

const clearSelection = () => {
    selected.value = null;
    saleForm.unit_price = '';
    saleForm.paid_amount = '';
    saleForm.clearErrors();
};

const netAmount = computed(() => Number(saleForm.unit_price) || 0);
const changeAmount = computed(() =>
    Math.max(0, (Number(saleForm.paid_amount) || 0) - netAmount.value),
);
const dueAmount = computed(() =>
    Math.max(0, netAmount.value - (Number(saleForm.paid_amount) || 0)),
);
const estimatedProfit = computed(() => {
    if (!selected.value) return 0;
    const cost = Number(selected.value.imei.purchase_cost) || 0;
    return netAmount.value - cost;
});

const saleItemsError = computed<string | undefined>(() => {
    const errors = saleForm.errors as Record<string, string | undefined>;
    return errors.items;
});

const submitSale = () => {
    if (!selected.value) return;

    saleForm.clearErrors();

    if (Number(saleForm.unit_price) > 1000000) {
        saleForm.setError('unit_price', 'قیمت 10 لاکھ (Rs 1,000,000) سے زیادہ نہیں ہو سکتی / Price cannot exceed Rs 1,000,000.');
        toast.error('قیمت کی حد سے تجاوز', {
            description: 'موبائل کی فروخت کی قیمت زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔',
        });
        return;
    }

    saleForm.transform((data) => ({
        customer_id: data.customer_id === '' ? null : data.customer_id,
        payment_method: data.payment_method,
        paid_amount:
            data.paid_amount === '' ? netAmount.value : data.paid_amount,
        items: [
            {
                product_id: selected.value!.product.id,
                product_imei_id: selected.value!.imei.id,
                quantity: 1,
                unit_price: netAmount.value,
            },
        ],
    }));

    saleForm.post(pos.sales.store(currentTeamSlug.value).url, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Mobile sold successfully');
            clearSelection();
        },
        onError: () => {
            toast.error('Could not complete the sale');
        },
        onFinish: () => {
            saleForm.transform((data) => data);
        },
    });
};

// ---------- Buy tab ----------
const buyForm = useForm({
    seller_name: '',
    seller_father_name: '',
    seller_cnic: '',
    seller_phone: '',
    seller_address: '',
    device_model: '',
    brand: '',
    color: '',
    storage: '',
    pta_status: 'approved',
    imei_1: '',
    imei_2: '',
    purchase_amount: '' as string | number,
    payment_method: 'cash',
    auto_add_stock: true,
});

const resetBuyForm = () => {
    buyForm.reset();
    buyForm.clearErrors();
    buyForm.pta_status = 'approved';
    buyForm.payment_method = 'cash';
    buyForm.auto_add_stock = true;
};

const suggestedResale = computed(() =>
    Math.round((Number(buyForm.purchase_amount) || 0) * 1.1),
);

const submitPurchase = () => {
    buyForm.clearErrors();

    if (Number(buyForm.purchase_amount) > 1000000) {
        buyForm.setError('purchase_amount', 'خریداری رقم 10 لاکھ (Rs 1,000,000) سے زیادہ نہیں ہو سکتی / Purchase amount cannot exceed Rs 1,000,000.');
        toast.error('قیمت کی حد سے تجاوز', {
            description: 'فون کی خریداری رقم زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔',
        });
        return;
    }

    buyForm.post(usedPhones.store(currentTeamSlug.value).url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            resetBuyForm();
        },
        onError: () => {
            toast.error('Could not record the purchase');
        },
    });
};

// ---------- Shared ----------
const copyImei = async (value?: string | null) => {
    if (!value) return;
    try {
        await navigator.clipboard.writeText(value);
        toast.success('IMEI copied');
    } catch (e) {
        console.error(e);
    }
};

const printElement = (selector: string) => {
    const el = document.querySelector(selector);
    if (!el) return;
    const original = el.innerHTML;
    el.innerHTML = original;
    window.print();
};

const formatCurrency = (val: number | string) => {
    const num = Number(val) || 0;
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: 0,
    }).format(num);
};
</script>

<template>
    <Head title="Mobile Sales" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-bold tracking-tight text-gray-900"
                >
                    <Smartphone class="h-7 w-7 text-[#003B7D]" />
                    Mobile Sales &amp; Buying
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Single desk for handset selling, customer buy-ins, and all
                    mobile invoices.
                </p>
            </div>
            <Link
                :href="`/${currentTeamSlug}/mobile-phones`"
                class="inline-flex items-center gap-1.5 rounded-xl border border-[#003B7D]/20 bg-white px-3 py-2 text-xs font-bold text-[#003B7D] shadow-xs transition hover:bg-[#003B7D]/5"
            >
                <Plus class="h-3.5 w-3.5" />
                Manage Handset Stock
            </Link>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">Ready to Sell</span>
                    <Smartphone class="h-5 w-5 text-[#003B7D]" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-gray-900">
                    {{ summary.ready_count }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    {{ formatCurrency(summary.ready_value) }} value
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">Sold Today</span>
                    <Wallet class="h-5 w-5 text-sky-600 dark:text-sky-400" />
                </div>
                <div
                    class="tnum mt-2 text-2xl font-bold text-sky-600 dark:text-sky-400"
                >
                    {{ formatCurrency(summary.today_total) }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    {{ summary.today_count }} handset(s)
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">Buy Payout</span>
                    <HandCoins class="h-5 w-5 text-amber-600" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-amber-600">
                    {{ formatCurrency(summary.purchase_total) }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    {{ summary.purchase_count }} voucher(s)
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">Pending Credits</span>
                    <FileText class="h-5 w-5 text-[#003B7D]" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-[#003B7D]">
                    {{ unappliedPurchases.length }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Unapplied trade-ins
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div
            class="no-scrollbar flex gap-1 overflow-x-auto rounded-2xl border border-gray-200 bg-gray-50 p-1.5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
        >
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                @click="activeTab = tab.key"
                :class="[
                    'inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold whitespace-nowrap transition',
                    activeTab === tab.key
                        ? 'bg-[#003B7D] text-white shadow-sm'
                        : 'text-slate-600 hover:bg-white/70 hover:text-[#003B7D]',
                ]"
            >
                <component :is="tab.icon" class="h-3.5 w-3.5" />
                {{ tab.label }}
                <span
                    v-if="tab.count !== undefined"
                    class="rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                    :class="
                        activeTab === tab.key
                            ? 'bg-white/20 text-white'
                            : 'bg-[#003B7D]/10 text-[#003B7D]'
                    "
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>

        <!-- ============ TAB: SELL ============ -->
        <div
            v-if="activeTab === 'sell'"
            class="grid grid-cols-1 gap-6 lg:grid-cols-5"
        >
            <!-- Handset Picker -->
            <div class="lg:col-span-3">
                <div
                    class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
                >
                    <div class="relative">
                        <Search
                            class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2"
                        />
                        <Input
                            v-model="search"
                            placeholder="Search by IMEI (15 digits), model or brand..."
                            class="pl-9"
                        />
                    </div>

                    <div
                        v-if="sellableUnits.length === 0"
                        class="flex flex-col items-center justify-center py-14 text-center"
                    >
                        <div
                            class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400"
                        >
                            <Smartphone class="h-7 w-7" />
                        </div>
                        <p class="text-sm font-semibold text-gray-700">
                            No handsets available
                        </p>
                        <p class="mt-1 max-w-xs text-xs text-slate-500">
                            {{
                                search
                                    ? 'No handsets match your search.'
                                    : 'No serialized handsets are currently in stock.'
                            }}
                        </p>
                    </div>

                    <div
                        v-else
                        class="mt-4 max-h-[520px] space-y-2.5 overflow-y-auto pr-1"
                    >
                        <button
                            v-for="unit in sellableUnits"
                            :key="unit.key"
                            type="button"
                            @click="selectUnit(unit)"
                            :class="[
                                'flex w-full items-center justify-between gap-3 rounded-xl border p-3 text-left transition-all',
                                selected?.key === unit.key
                                    ? 'border-[#003B7D] bg-[#003B7D]/5 shadow-sm'
                                    : 'border-gray-200 bg-white hover:border-[#003B7D]/40 hover:bg-gray-50',
                            ]"
                        >
                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex items-center gap-2 text-sm font-semibold text-gray-900"
                                >
                                    <span class="truncate">{{
                                        unit.product.name
                                    }}</span>
                                    <span
                                        v-if="unit.imei.condition"
                                        class="shrink-0 rounded-md border border-gray-200 bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 uppercase"
                                        >{{ unit.imei.condition }}</span
                                    >
                                </div>
                                <div
                                    class="mt-0.5 flex items-center gap-2 text-[11px] text-slate-500"
                                >
                                    <span class="font-mono">{{
                                        unit.imei.imei_1
                                    }}</span>
                                    <span v-if="unit.imei.storage">{{
                                        unit.imei.storage
                                    }}</span>
                                    <span v-if="unit.imei.color">{{
                                        unit.imei.color
                                    }}</span>
                                </div>
                            </div>

                            <div class="shrink-0 text-right">
                                <div
                                    class="tnum text-sm font-bold text-[#003B7D]"
                                >
                                    {{
                                        formatCurrency(unit.product.sale_price)
                                    }}
                                </div>
                                <Check
                                    v-if="selected?.key === unit.key"
                                    class="mt-1 ml-auto h-4 w-4 text-[#003B7D]"
                                />
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sale Form -->
            <div class="lg:col-span-2">
                <div
                    class="rounded-2xl border border-gray-200 bg-gray-50 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
                >
                    <h2
                        class="flex items-center gap-2 text-base font-bold text-gray-900"
                    >
                        <Plus class="h-4 w-4 text-[#003B7D]" />
                        Complete Sale
                    </h2>

                    <div
                        v-if="!selected"
                        class="mt-6 rounded-xl border border-dashed border-gray-300 py-10 text-center"
                    >
                        <p class="text-xs text-slate-500">
                            Select a handset from the list to start a sale.
                        </p>
                    </div>

                    <form
                        v-else
                        class="mt-4 space-y-4 text-xs"
                        @submit.prevent="submitSale"
                    >
                        <div
                            class="rounded-xl border border-[#003B7D]/15 bg-[#003B7D]/5 p-3"
                        >
                            <div class="text-sm font-bold text-gray-900">
                                {{ selected.product.name }}
                            </div>
                            <div
                                class="mt-1 flex items-center gap-2 font-mono text-[11px] text-slate-600"
                            >
                                {{ selected.imei.imei_1 }}
                                <button
                                    type="button"
                                    @click="copyImei(selected.imei.imei_1)"
                                    class="rounded p-0.5 text-slate-400 hover:bg-gray-200/60 hover:text-slate-700"
                                    title="Copy IMEI"
                                >
                                    <Copy class="h-3 w-3" />
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <Label>Customer</Label>
                            <Select v-model="saleForm.customer_id">
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder="Walk-in customer"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">
                                        Walk-in Customer
                                    </SelectItem>
                                    <SelectItem
                                        v-for="customer in customers"
                                        :key="customer.id"
                                        :value="customer.id"
                                    >
                                        {{ customer.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <span
                                v-if="saleForm.errors.customer_id"
                                class="text-xs text-rose-600"
                                >{{ saleForm.errors.customer_id }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label>Payment Method *</Label>
                            <Select v-model="saleForm.payment_method">
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder="Select method..."
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="method in paymentMethods"
                                        :key="method.value"
                                        :value="method.value"
                                    >
                                        {{ method.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <span
                                v-if="saleForm.errors.payment_method"
                                class="text-xs text-rose-600"
                                >{{ saleForm.errors.payment_method }}</span
                            >
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <Label for="unit_price"
                                    >Sale Price (PKR) * <span class="text-[10px] text-slate-400 font-normal">(Max: 10 Lakh)</span></Label
                                >
                                <Input
                                    id="unit_price"
                                    v-model="saleForm.unit_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="1000000"
                                    class="font-bold"
                                />
                                <span
                                    v-if="saleForm.errors.unit_price"
                                    class="text-xs text-rose-600 font-bold block mt-1"
                                    >{{ saleForm.errors.unit_price }}</span
                                >
                                <span
                                    v-else-if="saleItemsError"
                                    class="text-xs text-rose-600"
                                    >{{ saleItemsError }}</span
                                >
                            </div>

                            <div class="space-y-1">
                                <Label for="paid_amount">Paid Amount</Label>
                                <Input
                                    id="paid_amount"
                                    v-model="saleForm.paid_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                />
                            </div>
                        </div>

                        <div
                            class="space-y-2 rounded-xl border border-[#003B7D]/15 bg-[#003B7D]/5 px-4 py-3"
                        >
                            <div
                                class="flex items-center justify-between text-xs"
                            >
                                <span class="font-semibold text-slate-600"
                                    >Net Amount</span
                                >
                                <span
                                    class="tnum text-lg font-bold text-[#003B7D]"
                                    >{{ formatCurrency(netAmount) }}</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between text-xs"
                            >
                                <span class="font-semibold text-slate-600"
                                    >Change</span
                                >
                                <span class="tnum font-bold text-slate-700">{{
                                    formatCurrency(changeAmount)
                                }}</span>
                            </div>
                            <div
                                v-if="dueAmount > 0"
                                class="flex items-center justify-between text-xs"
                            >
                                <span class="font-semibold text-slate-600"
                                    >Due</span
                                >
                                <span class="tnum font-bold text-rose-600">{{
                                    formatCurrency(dueAmount)
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between border-t border-[#003B7D]/15 pt-2 text-xs"
                            >
                                <span class="font-semibold text-slate-600"
                                    >Est. Profit</span
                                >
                                <span
                                    class="tnum font-bold"
                                    :class="
                                        estimatedProfit >= 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-rose-600'
                                    "
                                    >{{ formatCurrency(estimatedProfit) }}</span
                                >
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="clearSelection"
                                class="flex-1"
                            >
                                Clear
                            </Button>
                            <Button
                                type="submit"
                                :disabled="saleForm.processing"
                                class="flex-1 gap-2 bg-[#003B7D] font-bold text-white shadow-sm hover:bg-[#002b5c]"
                            >
                                <User class="h-4 w-4" />
                                {{
                                    saleForm.processing
                                        ? 'Selling...'
                                        : 'Sell Now'
                                }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============ TAB: BUY ============ -->
        <div
            v-if="activeTab === 'buy'"
            class="grid grid-cols-1 gap-6 lg:grid-cols-3"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl lg:col-span-2"
            >
                <h2
                    class="flex items-center gap-2 text-base font-bold text-gray-900"
                >
                    <HandCoins class="h-4 w-4 text-amber-600" />
                    Buy Phone From Customer
                </h2>

                <form
                    class="mt-4 space-y-4 text-xs"
                    @submit.prevent="submitPurchase"
                >
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="seller_name">Seller Name *</Label>
                            <Input
                                id="seller_name"
                                v-model="buyForm.seller_name"
                                placeholder="e.g. Ali Raza"
                            />
                            <span
                                v-if="buyForm.errors.seller_name"
                                class="text-xs text-rose-600"
                                >{{ buyForm.errors.seller_name }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="seller_cnic">CNIC *</Label>
                            <Input
                                id="seller_cnic"
                                v-model="buyForm.seller_cnic"
                                placeholder="35202-1234567-8"
                                class="font-mono"
                            />
                            <span
                                v-if="buyForm.errors.seller_cnic"
                                class="text-xs text-rose-600"
                                >{{ buyForm.errors.seller_cnic }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="seller_phone">Phone *</Label>
                            <Input
                                id="seller_phone"
                                v-model="buyForm.seller_phone"
                                placeholder="03001234567"
                                class="font-mono"
                            />
                            <span
                                v-if="buyForm.errors.seller_phone"
                                class="text-xs text-rose-600"
                                >{{ buyForm.errors.seller_phone }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="device_model">Device Model *</Label>
                            <Input
                                id="device_model"
                                v-model="buyForm.device_model"
                                placeholder="e.g. Redmi Note 12"
                            />
                            <span
                                v-if="buyForm.errors.device_model"
                                class="text-xs text-rose-600"
                                >{{ buyForm.errors.device_model }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="imei_1">IMEI 1 *</Label>
                            <Input
                                id="imei_1"
                                v-model="buyForm.imei_1"
                                placeholder="15-digit IMEI"
                                class="font-mono"
                            />
                            <span
                                v-if="buyForm.errors.imei_1"
                                class="text-xs text-rose-600"
                                >{{ buyForm.errors.imei_1 }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="imei_2">IMEI 2</Label>
                            <Input
                                id="imei_2"
                                v-model="buyForm.imei_2"
                                placeholder="Optional"
                                class="font-mono"
                            />
                            <span
                                v-if="buyForm.errors.imei_2"
                                class="text-xs text-rose-600"
                                >{{ buyForm.errors.imei_2 }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="brand">Brand</Label>
                            <Input
                                id="brand"
                                v-model="buyForm.brand"
                                placeholder="e.g. Xiaomi"
                            />
                        </div>

                        <div class="space-y-1">
                            <Label for="storage">Storage</Label>
                            <Input
                                id="storage"
                                v-model="buyForm.storage"
                                placeholder="e.g. 128GB"
                            />
                        </div>

                        <div class="space-y-1">
                            <Label for="color">Color</Label>
                            <Input
                                id="color"
                                v-model="buyForm.color"
                                placeholder="e.g. Black"
                            />
                        </div>

                        <div class="space-y-1">
                            <Label>PTA Status</Label>
                            <Select v-model="buyForm.pta_status">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="approved"
                                        >Approved</SelectItem
                                    >
                                    <SelectItem value="non_pta"
                                        >Non-PTA</SelectItem
                                    >
                                    <SelectItem value="jv">JV</SelectItem>
                                    <SelectItem value="cpid">CPID</SelectItem>
                                    <SelectItem value="software"
                                        >Software</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-1">
                            <Label for="purchase_amount"
                                >Purchase Amount (PKR) * <span class="text-[10px] text-slate-400 font-normal">(Max: 10 Lakh)</span></Label
                            >
                            <Input
                                id="purchase_amount"
                                v-model="buyForm.purchase_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                max="1000000"
                                class="font-bold"
                            />
                            <span
                                v-if="buyForm.errors.purchase_amount"
                                class="text-xs text-rose-600 font-bold block mt-1"
                                >{{ buyForm.errors.purchase_amount }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label>Payout Method *</Label>
                            <Select v-model="buyForm.payment_method">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="method in buyPaymentMethods"
                                        :key="method.value"
                                        :value="method.value"
                                    >
                                        {{ method.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <span
                                v-if="buyForm.errors.payment_method"
                                class="text-xs text-rose-600"
                                >{{ buyForm.errors.payment_method }}</span
                            >
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between rounded-xl border border-amber-500/20 bg-amber-50/60 px-4 py-3 dark:bg-amber-950/30"
                    >
                        <span
                            class="text-xs font-semibold text-amber-800 dark:text-amber-300"
                            >Suggested Resale (+10%)</span
                        >
                        <span
                            class="tnum text-base font-bold text-amber-700 dark:text-amber-300"
                            >{{ formatCurrency(suggestedResale) }}</span
                        >
                    </div>

                    <div class="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="resetBuyForm"
                            class="flex-1"
                        >
                            Reset
                        </Button>
                        <Button
                            type="submit"
                            :disabled="buyForm.processing"
                            class="flex-1 gap-2 bg-amber-600 font-bold text-white shadow-sm hover:bg-amber-700"
                        >
                            <HandCoins class="h-4 w-4" />
                            {{
                                buyForm.processing
                                    ? 'Recording...'
                                    : 'Record Purchase'
                            }}
                        </Button>
                    </div>
                </form>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <h2
                    class="flex items-center gap-2 text-base font-bold text-gray-900"
                >
                    <Wallet class="h-4 w-4 text-[#003B7D]" />
                    Unapplied Trade-in Credits
                </h2>
                <p class="mt-1 text-[11px] text-slate-500">
                    Can be offset against a sale from the POS terminal.
                </p>

                <div
                    v-if="unappliedPurchases.length === 0"
                    class="mt-4 rounded-xl border border-dashed border-gray-300 py-8 text-center"
                >
                    <p class="text-xs text-slate-500">
                        No pending purchase credits.
                    </p>
                </div>

                <div v-else class="mt-3 space-y-2">
                    <div
                        v-for="purchase in unappliedPurchases"
                        :key="purchase.id"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-2"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span
                                class="truncate font-mono text-xs font-bold text-[#003B7D]"
                                >{{ purchase.voucher_no }}</span
                            >
                            <span class="tnum text-xs font-bold">
                                {{ formatCurrency(purchase.purchase_amount) }}
                            </span>
                        </div>
                        <div class="mt-0.5 truncate text-[11px] text-slate-500">
                            {{ purchase.seller_name }} &middot;
                            {{ purchase.device_model }}
                        </div>
                        <div
                            class="mt-0.5 truncate font-mono text-[10px] text-slate-400"
                        >
                            {{ purchase.imei_1 }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ TAB: SALE INVOICES ============ -->
        <div v-if="activeTab === 'sale_invoices'">
            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="relative">
                    <Search
                        class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2"
                    />
                    <Input
                        v-model="invoiceSearch"
                        placeholder="Search by invoice #, customer, or IMEI..."
                        class="pl-9"
                    />
                </div>
            </div>

            <div
                v-if="saleInvoices.length === 0"
                class="mt-4 rounded-2xl border border-gray-200 bg-gray-50 py-14 text-center shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)]"
            >
                <p class="text-sm font-semibold text-gray-700">
                    No mobile sale invoices found
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    {{
                        invoiceSearch
                            ? 'No invoices match your search.'
                            : 'Handset sales you record will appear here.'
                    }}
                </p>
            </div>

            <div v-else class="mt-4 space-y-3">
                <div
                    v-for="invoice in saleInvoices"
                    :key="invoice.id"
                    class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)]"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="tnum font-mono text-sm font-bold text-[#003B7D]"
                                    >{{ invoice.invoice_no }}</span
                                >
                                <span
                                    class="rounded-md border border-gray-200 bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-600 uppercase"
                                    >{{ invoice.payment_method }}</span
                                >
                            </div>
                            <div class="mt-0.5 text-[11px] text-slate-500">
                                {{
                                    invoice.customer?.name ?? 'Walk-in Customer'
                                }}
                                <span v-if="invoice.cashier">
                                    &middot; Cashier
                                    {{ invoice.cashier.name }}
                                </span>
                                &middot;
                                {{
                                    new Date(invoice.created_at).toLocaleString(
                                        'en-PK',
                                    )
                                }}
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <div
                                    class="tnum text-base font-bold text-[#003B7D]"
                                >
                                    {{ formatCurrency(invoice.net_amount) }}
                                </div>
                                <div class="text-[10px] text-slate-500">
                                    Paid
                                    {{ formatCurrency(invoice.paid_amount) }}
                                </div>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                class="h-8 gap-1"
                                title="Print invoice"
                                @click="printElement('#sale-invoice-print')"
                            >
                                <Printer class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>

                    <div class="mt-3 space-y-1.5">
                        <div
                            v-for="item in invoice.items"
                            :key="item.id"
                            class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2"
                        >
                            <div class="min-w-0">
                                <div
                                    class="truncate text-xs font-semibold text-gray-900"
                                >
                                    {{ item.product?.name }}
                                </div>
                                <div
                                    class="truncate font-mono text-[10px] text-slate-500"
                                >
                                    {{ item.productImei?.imei_1 ?? 'No IMEI' }}
                                    <span v-if="item.productImei?.storage">
                                        &middot; {{ item.productImei.storage }}
                                    </span>
                                    <span v-if="item.productImei?.condition">
                                        &middot;
                                        {{ item.productImei.condition }}
                                    </span>
                                </div>
                            </div>
                            <div class="tnum shrink-0 text-xs font-bold">
                                {{ formatCurrency(item.line_total) }}
                            </div>
                        </div>
                    </div>

                    <div
                        id="sale-invoice-print"
                        class="mt-3 hidden space-y-1 rounded-lg bg-white p-4 font-mono text-[11px] text-black"
                    >
                        <div class="text-center text-sm font-bold uppercase">
                            {{ shopInfo.name }}
                        </div>
                        <div class="text-center">{{ shopInfo.address }}</div>
                        <div class="text-center">Ph: {{ shopInfo.phone }}</div>
                        <div class="my-1 border-t pt-1">
                            Invoice: {{ invoice.invoice_no }}
                        </div>
                        <div>
                            Customer:
                            {{ invoice.customer?.name ?? 'Walk-in' }}
                        </div>
                        <div>
                            Date:
                            {{
                                new Date(invoice.created_at).toLocaleString(
                                    'en-PK',
                                )
                            }}
                        </div>
                        <div class="border-t pt-1">
                            <div v-for="item in invoice.items" :key="item.id">
                                {{ item.product?.name }} x1
                            </div>
                        </div>
                        <div class="border-t pt-1 font-bold">
                            Total: {{ formatCurrency(invoice.net_amount) }}
                        </div>
                        <div>
                            Paid: {{ formatCurrency(invoice.paid_amount) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ TAB: PURCHASE INVOICES ============ -->
        <div v-if="activeTab === 'purchase_invoices'">
            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="relative">
                    <Search
                        class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2"
                    />
                    <Input
                        v-model="invoiceSearch"
                        placeholder="Search by voucher #, seller, CNIC, model or IMEI..."
                        class="pl-9"
                    />
                </div>
            </div>

            <div
                v-if="purchaseInvoices.length === 0"
                class="mt-4 rounded-2xl border border-gray-200 bg-gray-50 py-14 text-center shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)]"
            >
                <p class="text-sm font-semibold text-gray-700">
                    No purchase invoices found
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    {{
                        invoiceSearch
                            ? 'No vouchers match your search.'
                            : 'Phones you buy from customers will appear here.'
                    }}
                </p>
            </div>

            <div
                v-else
                class="mt-4 overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)]"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="bg-muted/50 text-muted-foreground font-semibold uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Voucher #</th>
                                <th class="px-4 py-3">Seller</th>
                                <th class="px-4 py-3">Device</th>
                                <th class="px-4 py-3">IMEI</th>
                                <th class="px-4 py-3">Payout</th>
                                <th class="px-4 py-3">Method</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3 text-right">Print</th>
                            </tr>
                        </thead>
                        <tbody class="divide-border divide-y">
                            <tr
                                v-for="voucher in paginatedPurchases"
                                :key="voucher.id"
                                class="transition-colors hover:bg-white/60"
                            >
                                <td
                                    class="tnum px-4 py-3 font-mono font-bold text-[#003B7D]"
                                >
                                    {{ voucher.voucher_no }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900">
                                        {{ voucher.seller_name }}
                                    </div>
                                    <div
                                        class="font-mono text-[10px] text-slate-500"
                                    >
                                        {{ voucher.seller_cnic }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-medium">
                                    {{ voucher.device_model }}
                                </td>
                                <td
                                    class="px-4 py-3 font-mono text-[11px] text-slate-600"
                                >
                                    {{ voucher.imei_1 }}
                                </td>
                                <td
                                    class="tnum px-4 py-3 font-bold text-amber-700"
                                >
                                    {{
                                        formatCurrency(voucher.purchase_amount)
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-md border border-gray-200 bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-600 uppercase"
                                        >{{ voucher.payment_method }}</span
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="
                                            voucher.applied_at
                                                ? 'rounded-md border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 uppercase dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300'
                                                : 'rounded-md border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-700 uppercase dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300'
                                        "
                                    >
                                        {{
                                            voucher.applied_at
                                                ? 'Applied'
                                                : 'Pending'
                                        }}
                                    </span>
                                </td>
                                <td class="text-muted-foreground px-4 py-3">
                                    {{
                                        new Date(
                                            voucher.created_at,
                                        ).toLocaleDateString('en-PK')
                                    }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="h-8 w-8 p-0"
                                        title="Print voucher"
                                        @click="
                                            printElement(
                                                '#purchase-invoice-print',
                                            )
                                        "
                                    >
                                        <Printer class="h-3.5 w-3.5" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div
                    v-if="purchaseInvoices.length > 0"
                    class="flex flex-col items-center justify-between gap-4 border-t border-slate-200/80 bg-slate-50/50 px-6 py-3.5 sm:flex-row dark:border-slate-800 dark:bg-slate-800/20"
                >
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        Showing
                        <span class="font-medium text-slate-900 dark:text-slate-200">{{ paginatedPurchases.length }}</span>
                        of
                        <span class="font-medium text-slate-900 dark:text-slate-200">{{ purchaseInvoices.length }}</span>
                        vouchers
                    </div>

                    <div v-if="totalPurchasePages > 1" class="flex items-center gap-1.5">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 px-2.5 text-xs"
                            :disabled="purchasePage <= 1"
                            @click="purchasePage--"
                        >
                            Previous
                        </Button>
                        <span class="text-xs font-semibold px-2">
                            Page {{ purchasePage }} of {{ totalPurchasePages }}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 px-2.5 text-xs"
                            :disabled="purchasePage >= totalPurchasePages"
                            @click="purchasePage++"
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Printable voucher for the most recent entry -->
            <div
                id="purchase-invoice-print"
                v-if="purchaseInvoices.length > 0"
                class="mt-4 hidden bg-white p-4 font-mono text-[11px] leading-tight text-black"
            >
                <div class="border-b pb-2 text-center">
                    <div class="text-sm font-extrabold uppercase">
                        {{ shopInfo.name }}
                    </div>
                    <div>{{ shopInfo.address }}</div>
                    <div>Ph: {{ shopInfo.phone }}</div>
                    <div
                        class="mt-1 inline-block border px-2 py-0.5 text-xs font-bold"
                    >
                        PURCHASE VOUCHER
                    </div>
                </div>
                <div
                    v-for="voucher in purchaseInvoices"
                    :key="voucher.id"
                    class="border-b py-1 last:border-b-0"
                >
                    <div class="flex justify-between font-bold">
                        <span>VOUCHER #:</span>
                        <span>{{ voucher.voucher_no }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Date:</span>
                        <span>{{
                            new Date(voucher.created_at).toLocaleString('en-PK')
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Seller:</span>
                        <span>{{ voucher.seller_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>CNIC:</span>
                        <span>{{ voucher.seller_cnic }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Device:</span>
                        <span>{{ voucher.device_model }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>IMEI:</span>
                        <span>{{ voucher.imei_1 }}</span>
                    </div>
                    <div class="flex justify-between font-bold">
                        <span>Payout:</span>
                        <span>{{
                            formatCurrency(voucher.purchase_amount)
                        }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
