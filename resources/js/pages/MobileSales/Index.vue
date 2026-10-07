<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    Eye,
    FileText,
    HandCoins,
    Plus,
    Printer,
    Search,
    ShoppingCart,
    Smartphone,
    User,
    UserPlus,
    Wallet,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import { computed, onMounted, ref, watch } from 'vue';
import HandsetStockTab from '@/components/handsets/HandsetStockTab.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    quantity?: number | string;
    product?: { id: number; name: string; brand?: string | null; is_serialized?: boolean } | null;
    productImei?: {
        id: number;
        imei_1: string;
        imei_2?: string | null;
        color?: string | null;
        storage?: string | null;
        condition?: string;
        pta_status?: string;
        warranty_days?: number;
    } | null;
}

interface SaleInvoice {
    id: number;
    invoice_no: string;
    total_amount?: number | string;
    discount_amount?: number | string;
    trade_in_amount?: number | string;
    net_amount: number | string;
    paid_amount: number | string;
    change_amount?: number | string;
    payment_method: string;
    created_at: string;
    customer?: { id: number; name: string; phone?: string | null } | null;
    cashier?: { id: number; name: string } | null;
    usedPhonePurchase?: { id: number; voucher_no: string; purchase_amount: number | string } | null;
    items?: SaleInvoiceItem[];
}

type CreditStatus = 'pending' | 'approved' | 'rejected';

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
    status?: CreditStatus;
    applied_at?: string | null;
    created_at: string;
}

interface PaymentMethodOption {
    value: string;
    label: string;
}

interface StockProduct {
    id: number;
    name: string;
    brand: string;
    sale_price: number | string;
}

interface StockImeiItem {
    id: number;
    product_id: number;
    imei_1: string;
    imei_2?: string | null;
    color?: string | null;
    storage?: string | null;
    condition: 'new' | 'used';
    pta_status: 'approved' | 'non_pta' | 'jv' | 'cpid' | 'software';
    purchase_cost: number | string;
    warranty_days: number;
    status: 'in_stock' | 'sold' | 'repairing' | 'returned';
    sold_at?: string | null;
    created_at: string;
    product?: StockProduct;
}

const props = defineProps<{
    handsets: Handset[];
    customers: CustomerOption[];
    saleInvoices: SaleInvoice[];
    purchaseInvoices: PurchaseInvoice[];
    stockImeis: {
        data: StockImeiItem[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    stockBrands: string[];
    stockSummary: {
        in_stock_count: number;
        new_stock_count: number;
        used_stock_count: number;
        total_cost_value: number;
    };
    stockFilters: {
        search: string;
        condition: string;
        status: string;
        pta_status: string;
        brand: string;
        per_page: number;
    };
    unappliedPurchases: Array<{
        id: number;
        voucher_no: string;
        seller_name: string;
        device_model: string;
        imei_1: string;
        purchase_amount: number | string;
        status: CreditStatus;
        rejection_reason?: string | null;
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
        pending_credit_count: number;
        pending_credit_amount: number;
        approved_credit_count: number;
        approved_credit_amount: number;
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
                title: 'Mobile Handsets',
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
type TabKey = 'stock' | 'sell' | 'buy' | 'sale_invoices' | 'purchase_invoices';
const activeTab = ref<TabKey>('stock');

const tabs = computed(() => [
    {
        key: 'stock' as const,
        label: 'Stock / Add Phone',
        icon: Smartphone,
        count: props.stockSummary.in_stock_count,
    },
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
const totalPurchasePages = computed(
    () =>
        Math.ceil(
            (props.purchaseInvoices?.length || 0) / purchasePerPage.value,
        ) || 1,
);

watch(invoiceSearch, () => {
    purchasePage.value = 1;
    if (invoiceSearchTimeout) clearTimeout(invoiceSearchTimeout);
    invoiceSearchTimeout = setTimeout(() => {
        applyFilters();
    }, 350);
});

// Modal States & Print Handlers
const selectedSaleInvoiceModal = ref<SaleInvoice | null>(null);
const isSaleInvoiceModalOpen = ref(false);

const openSaleInvoiceModal = (invoice: SaleInvoice) => {
    selectedSaleInvoiceModal.value = invoice;
    isSaleInvoiceModalOpen.value = true;
};

const selectedPurchaseVoucherModal = ref<PurchaseInvoice | null>(null);
const isPurchaseVoucherModalOpen = ref(false);

const openPurchaseVoucherModal = (voucher: PurchaseInvoice) => {
    selectedPurchaseVoucherModal.value = voucher;
    isPurchaseVoucherModalOpen.value = true;
};

// Add Customer Modal
const isAddCustomerModalOpen = ref(false);
const addCustomerForm = useForm({
    name: '',
    phone: '',
    address: '',
});

const submitAddCustomer = () => {
    addCustomerForm.clearErrors();
    addCustomerForm.post(pos.customers.store(currentTeamSlug.value).url, {
        preserveScroll: true,
        onSuccess: (pageProps) => {
            isAddCustomerModalOpen.value = false;
            toast.success('Customer added successfully!');
            const customersList = pageProps.props.customers as CustomerOption[] | undefined;
            if (customersList && customersList.length > 0) {
                const newCust = customersList[customersList.length - 1];
                if (newCust) {
                    saleForm.customer_id = String(newCust.id);
                }
            }
            addCustomerForm.reset();
        },
        onError: () => {
            toast.error('Could not create customer');
        },
    });
};

const printHtmlContent = (elementId: string, title = 'Invoice Print') => {
    const printEl = document.getElementById(elementId);
    if (!printEl) {
        toast.error('Print content element not found');
        return;
    }
    const printWindow = window.open('', '_blank', 'width=800,height=900');
    if (!printWindow) {
        toast.error(
            'Pop-up window blocked. Please enable pop-ups in your browser settings to print.',
        );
        return;
    }
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${title}</title>
            <style>
                body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; color: #0f172a; background: #fff; }
                .ticket { max-width: 440px; margin: 0 auto; background: #fff; padding: 16px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; line-height: 1.5; }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .font-bold { font-weight: 700; }
                .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
                .border-b { border-bottom: 1px dashed #cbd5e1; }
                .border-t { border-top: 1px dashed #cbd5e1; }
                .py-1 { padding-top: 4px; padding-bottom: 4px; }
                .py-2 { padding-top: 8px; padding-bottom: 8px; }
                .my-2 { margin-top: 8px; margin-bottom: 8px; }
                .flex { display: flex; justify-content: space-between; align-items: center; }
                .badge { font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: bold; text-transform: uppercase; background: #f1f5f9; border: 1px solid #e2e8f0; }
                @media print {
                    body { padding: 0; background: none; }
                    .ticket { max-width: 100%; width: 100%; border: none; padding: 0; }
                }
            </style>
        </head>
        <body>
            <div class="ticket">
                ${printEl.innerHTML}
            </div>
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() { window.close(); }, 500);
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
};

watch(
    () => props.latestSale,
    (newSale) => {
        if (newSale) {
            activeTab.value = 'sale_invoices';
            openSaleInvoiceModal(newSale);
        }
    },
    { immediate: true },
);

watch(
    () => props.latestPurchase,
    (newPurchase) => {
        if (newPurchase) {
            activeTab.value = 'purchase_invoices';
            openPurchaseVoucherModal(newPurchase);
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (props.latestSale) {
        activeTab.value = 'sale_invoices';
        openSaleInvoiceModal(props.latestSale);
    }
    if (props.latestPurchase) {
        activeTab.value = 'purchase_invoices';
        openPurchaseVoucherModal(props.latestPurchase);
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
        saleForm.setError(
            'unit_price',
            'قیمت 10 لاکھ (Rs 1,000,000) سے زیادہ نہیں ہو سکتی / Price cannot exceed Rs 1,000,000.',
        );
        toast.error('قیمت کی حد سے تجاوز', {
            description:
                'موبائل کی فروخت کی قیمت زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔',
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
        onSuccess: (pageProps) => {
            toast.success('Mobile sold successfully');
            clearSelection();
            activeTab.value = 'sale_invoices';
            const sale = pageProps.props.latestSale as SaleInvoice | undefined;
            if (sale) {
                openSaleInvoiceModal(sale);
            }
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
        buyForm.setError(
            'purchase_amount',
            'خریداری رقم 10 لاکھ (Rs 1,000,000) سے زیادہ نہیں ہو سکتی / Purchase amount cannot exceed Rs 1,000,000.',
        );
        toast.error('قیمت کی حد سے تجاوز', {
            description:
                'فون کی خریداری رقم زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔',
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

// ---------- Trade-in Credit Approval ----------
const isReviewingCreditId = ref<number | null>(null);

const canApproveCredits = computed(() => page.props.auth?.isAdmin !== false);

const creditStatusBadgeClass = (status: CreditStatus) =>
    ({
        pending:
            'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-400',
        approved:
            'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-400',
        rejected:
            'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-400',
    })[status];

const approveCredit = (purchase: {
    id: number;
    voucher_no: string;
    purchase_amount: number | string;
}) => {
    isReviewingCreditId.value = purchase.id;

    router.put(
        usedPhones.status.update([currentTeamSlug.value, purchase.id]).url,
        { status: 'approved' },
        {
            preserveScroll: true,
            onSuccess: () => {
                isReviewingCreditId.value = null;
                toast.success('Trade-in credit approved', {
                    description: `${purchase.voucher_no} can now be used at the POS terminal.`,
                });
            },
            onError: (errors: Record<string, string>) => {
                isReviewingCreditId.value = null;
                toast.error('Could not approve credit', {
                    description: Object.values(errors)[0],
                });
            },
        },
    );
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
    <Head title="Mobile Handsets" />

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
                    Mobile Handsets — Stock, Sales &amp; Buying
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    One desk to add handsets, sell them, buy used phones, and
                    review every mobile invoice.
                </p>
            </div>
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
                class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">Pending Approval</span>
                    <FileText class="h-5 w-5 text-amber-600" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-amber-600">
                    {{ summary.pending_credit_count }}
                </div>
                <div class="tnum mt-1 text-xs text-amber-700/80">
                    {{ formatCurrency(summary.pending_credit_amount) }} awaiting
                    review
                </div>
            </div>

            <div
                class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">Approved Credits</span>
                    <Check class="h-5 w-5 text-emerald-600" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-emerald-600">
                    {{ summary.approved_credit_count }}
                </div>
                <div class="tnum mt-1 text-xs text-emerald-700/80">
                    {{ formatCurrency(summary.approved_credit_amount) }} usable
                    at POS
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

        <!-- ============ TAB: STOCK / ADD PHONE ============ -->
        <HandsetStockTab
            v-if="activeTab === 'stock'"
            :current-team-slug="currentTeamSlug"
            :stock-imeis="stockImeis"
            :stock-brands="stockBrands"
            :stock-summary="stockSummary"
            :stock-filters="stockFilters"
        />

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
                            <div class="flex items-center justify-between">
                                <Label>Customer</Label>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="h-6 px-2 text-[11px] font-bold text-[#003B7D] hover:bg-[#003B7D]/10 hover:text-[#003B7D]"
                                    @click="isAddCustomerModalOpen = true"
                                >
                                    <UserPlus class="mr-1 h-3.5 w-3.5" />
                                    <span>Add Customer</span>
                                </Button>
                            </div>
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
                                        :value="String(customer.id)"
                                    >
                                        {{ customer.name }} ({{ customer.phone }})
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
                                    >Sale Price (PKR) *
                                    <span
                                        class="text-[10px] font-normal text-slate-400"
                                        >(Max: 10 Lakh)</span
                                    ></Label
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
                                    class="mt-1 block text-xs font-bold text-rose-600"
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
                                >Purchase Amount (PKR) *
                                <span
                                    class="text-[10px] font-normal text-slate-400"
                                    >(Max: 10 Lakh)</span
                                ></Label
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
                                class="mt-1 block text-xs font-bold text-rose-600"
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
                    Trade-in Credits
                </h2>
                <p class="mt-1 text-[11px] text-slate-500">
                    Approved credits can be offset against a sale from the POS
                    terminal. Pending ones need admin approval first.
                </p>

                <div
                    v-if="unappliedPurchases.length === 0"
                    class="mt-4 rounded-xl border border-dashed border-gray-300 py-8 text-center"
                >
                    <p class="text-xs text-slate-500">
                        No unapplied purchase credits.
                    </p>
                </div>

                <div v-else class="mt-3 space-y-2">
                    <div
                        v-for="purchase in unappliedPurchases"
                        :key="purchase.id"
                        class="rounded-lg border px-3 py-2"
                        :class="
                            purchase.status === 'approved'
                                ? 'border-emerald-200 bg-emerald-50/40'
                                : purchase.status === 'rejected'
                                  ? 'border-rose-200 bg-rose-50/40'
                                  : 'border-gray-200 bg-white'
                        "
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

                        <div
                            class="mt-1.5 flex items-center justify-between gap-2"
                        >
                            <span
                                class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase"
                                :class="creditStatusBadgeClass(purchase.status)"
                            >
                                {{
                                    purchase.status === 'pending'
                                        ? 'Pending Approval'
                                        : purchase.status === 'approved'
                                          ? 'Approved'
                                          : 'Rejected'
                                }}
                            </span>

                            <Button
                                v-if="
                                    canApproveCredits &&
                                    purchase.status !== 'approved'
                                "
                                size="sm"
                                :disabled="isReviewingCreditId === purchase.id"
                                @click="approveCredit(purchase)"
                                class="h-6 gap-1 bg-emerald-600 px-2 text-[10px] font-semibold text-white hover:bg-emerald-700"
                            >
                                <Check class="h-3 w-3" />
                                Approve
                            </Button>
                        </div>

                        <div
                            v-if="purchase.rejection_reason"
                            class="mt-1 text-[10px] leading-tight text-rose-600 dark:text-rose-400"
                        >
                            {{ purchase.rejection_reason }}
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
                        placeholder="Search by invoice #, customer, model or IMEI..."
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
                    class="cursor-pointer rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] transition-all hover:border-[#003B7D]/40 hover:bg-white"
                    @click="openSaleInvoiceModal(invoice)"
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
                                class="h-8 gap-1.5 font-bold"
                                title="View & Print Invoice"
                                @click.stop="openSaleInvoiceModal(invoice)"
                            >
                                <Eye class="h-3.5 w-3.5 text-[#003B7D]" />
                                <Printer class="h-3.5 w-3.5 text-slate-600" />
                                <span>Invoice</span>
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
                                    <span v-if="item.productImei?.imei_2">
                                        &middot; {{ item.productImei.imei_2 }}
                                    </span>
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
                                <th class="px-4 py-3 text-right">View / Print</th>
                            </tr>
                        </thead>
                        <tbody class="divide-border divide-y">
                            <tr
                                v-for="voucher in paginatedPurchases"
                                :key="voucher.id"
                                class="cursor-pointer transition-colors hover:bg-white/60"
                                @click="openPurchaseVoucherModal(voucher)"
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
                                        class="h-8 gap-1"
                                        title="View Voucher"
                                        @click.stop="openPurchaseVoucherModal(voucher)"
                                    >
                                        <Eye class="h-3.5 w-3.5 text-amber-600" />
                                        <Printer class="h-3.5 w-3.5 text-slate-600" />
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
                        <span
                            class="font-medium text-slate-900 dark:text-slate-200"
                            >{{ paginatedPurchases.length }}</span
                        >
                        of
                        <span
                            class="font-medium text-slate-900 dark:text-slate-200"
                            >{{ purchaseInvoices.length }}</span
                        >
                        vouchers
                    </div>

                    <div
                        v-if="totalPurchasePages > 1"
                        class="flex items-center gap-1.5"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 px-2.5 text-xs"
                            :disabled="purchasePage <= 1"
                            @click="purchasePage--"
                        >
                            Previous
                        </Button>
                        <span class="px-2 text-xs font-semibold">
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
        </div>

        <!-- MODAL: SALE INVOICE RECEIPT & DETAILS -->
        <Dialog
            :open="isSaleInvoiceModalOpen"
            @update:open="isSaleInvoiceModalOpen = $event"
        >
            <DialogContent
                class="max-w-xl rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
            >
                <DialogHeader
                    class="border-b border-slate-100 pb-3 dark:border-slate-800"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#003B7D]/10 text-[#003B7D] dark:bg-blue-500/20 dark:text-blue-400"
                        >
                            <FileText class="h-6 w-6" />
                        </div>
                        <div>
                            <DialogTitle
                                class="text-base font-black text-slate-900 dark:text-white"
                            >
                                Mobile Sale Invoice
                            </DialogTitle>
                            <DialogDescription class="text-xs text-slate-500">
                                Invoice #{{
                                    selectedSaleInvoiceModal?.invoice_no
                                }}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div
                    v-if="selectedSaleInvoiceModal"
                    class="space-y-4 py-2 text-xs"
                >
                    <div
                        id="sale-invoice-printable-area"
                        class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-800/40"
                    >
                        <div
                            class="border-b border-dashed border-slate-300 pb-3 text-center dark:border-slate-700"
                        >
                            <div
                                class="text-base font-black tracking-tight text-[#003B7D] uppercase dark:text-blue-400"
                            >
                                {{ shopInfo.name }}
                            </div>
                            <div
                                class="text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                {{ shopInfo.address }}
                            </div>
                            <div
                                class="font-mono text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                Ph: {{ shopInfo.phone }}
                            </div>
                        </div>

                        <div
                            class="grid grid-cols-2 gap-2 border-b border-dashed border-slate-300 py-3 text-[11px] dark:border-slate-700"
                        >
                            <div>
                                <span class="text-slate-400">Invoice No:</span>
                                <span
                                    class="ml-1 font-mono font-bold text-slate-900 dark:text-white"
                                    >{{
                                        selectedSaleInvoiceModal.invoice_no
                                    }}</span
                                >
                            </div>
                            <div class="text-right">
                                <span class="text-slate-400">Date:</span>
                                <span
                                    class="ml-1 font-mono text-slate-700 dark:text-slate-300"
                                    >{{
                                        new Date(
                                            selectedSaleInvoiceModal.created_at,
                                        ).toLocaleString('en-PK')
                                    }}</span
                                >
                            </div>
                            <div>
                                <span class="text-slate-400">Customer:</span>
                                <span
                                    class="ml-1 font-semibold text-slate-800 dark:text-slate-200"
                                    >{{
                                        selectedSaleInvoiceModal.customer
                                            ?.name ?? 'Walk-in Customer'
                                    }}</span
                                >
                            </div>
                            <div class="text-right">
                                <span class="text-slate-400">Payment:</span>
                                <span
                                    class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold text-slate-700 uppercase dark:bg-slate-700 dark:text-slate-200"
                                    >{{
                                        selectedSaleInvoiceModal.payment_method
                                    }}</span
                                >
                            </div>
                        </div>

                        <div class="py-3">
                            <div
                                class="mb-2 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Handset &amp; Items Purchased
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="item in selectedSaleInvoiceModal.items"
                                    :key="item.id"
                                    class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-800"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div
                                            class="font-bold text-slate-900 dark:text-white"
                                        >
                                            {{ item.product?.name }}
                                        </div>
                                        <div
                                            class="tnum font-bold text-[#003B7D] dark:text-blue-400"
                                        >
                                            {{
                                                formatCurrency(item.line_total)
                                            }}
                                        </div>
                                    </div>
                                    <div
                                        v-if="item.productImei"
                                        class="mt-1 space-y-1 text-[11px] text-slate-600 dark:text-slate-300"
                                    >
                                        <div
                                            class="flex flex-wrap items-center gap-2 font-mono"
                                        >
                                            <span
                                                >IMEI 1:
                                                <strong
                                                    class="text-slate-900 dark:text-white"
                                                    >{{
                                                        item.productImei.imei_1
                                                    }}</strong
                                                ></span
                                            >
                                            <span v-if="item.productImei.imei_2"
                                                >| IMEI 2:
                                                <strong
                                                    class="text-slate-900 dark:text-white"
                                                    >{{
                                                        item.productImei.imei_2
                                                    }}</strong
                                                ></span
                                            >
                                        </div>
                                        <div
                                            class="flex flex-wrap items-center gap-1.5 text-[10px]"
                                        >
                                            <span
                                                v-if="item.productImei.storage"
                                                class="rounded bg-slate-100 px-1.5 py-0.5 font-medium dark:bg-slate-700"
                                                >{{
                                                    item.productImei.storage
                                                }}</span
                                            >
                                            <span
                                                v-if="item.productImei.color"
                                                class="rounded bg-slate-100 px-1.5 py-0.5 font-medium dark:bg-slate-700"
                                                >{{
                                                    item.productImei.color
                                                }}</span
                                            >
                                            <span
                                                v-if="
                                                    item.productImei.condition
                                                "
                                                class="rounded bg-amber-50 px-1.5 py-0.5 font-bold text-amber-700 uppercase dark:bg-amber-950/50 dark:text-amber-300"
                                                >{{
                                                    item.productImei.condition
                                                }}</span
                                            >
                                            <span
                                                v-if="
                                                    item.productImei.pta_status
                                                "
                                                class="rounded bg-blue-50 px-1.5 py-0.5 font-bold text-blue-700 uppercase dark:bg-blue-950/50 dark:text-blue-300"
                                                >{{
                                                    item.productImei.pta_status
                                                }}</span
                                            >
                                            <span
                                                v-if="
                                                    item.productImei
                                                        .warranty_days
                                                "
                                                class="rounded bg-emerald-50 px-1.5 py-0.5 font-bold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"
                                                >{{
                                                    item.productImei
                                                        .warranty_days
                                                }}d Warranty</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="space-y-1.5 border-t border-dashed border-slate-300 pt-3 text-[11px] dark:border-slate-700"
                        >
                            <div
                                v-if="
                                    selectedSaleInvoiceModal.trade_in_amount &&
                                    Number(
                                        selectedSaleInvoiceModal.trade_in_amount,
                                    ) > 0
                                "
                                class="flex justify-between text-slate-600"
                            >
                                <span>Trade-in Credit Offset</span>
                                <span class="font-bold text-amber-600"
                                    >-
                                    {{
                                        formatCurrency(
                                            selectedSaleInvoiceModal.trade_in_amount,
                                        )
                                    }}</span
                                >
                            </div>
                            <div
                                class="flex justify-between text-sm font-bold text-slate-900 dark:text-white"
                            >
                                <span>Net Total</span>
                                <span class="text-[#003B7D] dark:text-blue-400">{{
                                    formatCurrency(
                                        selectedSaleInvoiceModal.net_amount,
                                    )
                                }}</span>
                            </div>
                            <div
                                class="flex justify-between text-slate-600 dark:text-slate-400"
                            >
                                <span>Paid Amount</span>
                                <span
                                    class="font-semibold text-slate-800 dark:text-slate-200"
                                    >{{
                                        formatCurrency(
                                            selectedSaleInvoiceModal.paid_amount,
                                        )
                                    }}</span
                                >
                            </div>
                            <div
                                v-if="
                                    Number(
                                        selectedSaleInvoiceModal.change_amount,
                                    ) > 0
                                "
                                class="flex justify-between text-slate-600 dark:text-slate-400"
                            >
                                <span>Change Returned</span>
                                <span
                                    class="font-semibold text-emerald-600 dark:text-emerald-400"
                                    >{{
                                        formatCurrency(
                                            selectedSaleInvoiceModal.change_amount,
                                        )
                                    }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter
                    class="flex items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-800"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        @click="isSaleInvoiceModalOpen = false"
                    >
                        Close
                    </Button>
                    <Button
                        size="sm"
                        class="gap-1.5 bg-[#003B7D] font-bold text-white hover:bg-[#002b5c]"
                        @click="
                            printHtmlContent(
                                'sale-invoice-printable-area',
                                'Sale Invoice ' +
                                    selectedSaleInvoiceModal?.invoice_no,
                            )
                        "
                    >
                        <Printer class="h-4 w-4" /> Print Invoice
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- MODAL: PURCHASE VOUCHER RECEIPT & DETAILS -->
        <Dialog
            :open="isPurchaseVoucherModalOpen"
            @update:open="isPurchaseVoucherModalOpen = $event"
        >
            <DialogContent
                class="max-w-xl rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
            >
                <DialogHeader
                    class="border-b border-slate-100 pb-3 dark:border-slate-800"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400"
                        >
                            <HandCoins class="h-6 w-6" />
                        </div>
                        <div>
                            <DialogTitle
                                class="text-base font-black text-slate-900 dark:text-white"
                            >
                                Phone Purchase Voucher
                            </DialogTitle>
                            <DialogDescription class="text-xs text-slate-500">
                                Voucher #{{
                                    selectedPurchaseVoucherModal?.voucher_no
                                }}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div
                    v-if="selectedPurchaseVoucherModal"
                    class="space-y-4 py-2 text-xs"
                >
                    <div
                        id="purchase-voucher-printable-area"
                        class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-800/40"
                    >
                        <div
                            class="border-b border-dashed border-slate-300 pb-3 text-center dark:border-slate-700"
                        >
                            <div
                                class="text-base font-black tracking-tight text-[#003B7D] uppercase dark:text-blue-400"
                            >
                                {{ shopInfo.name }}
                            </div>
                            <div
                                class="text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                {{ shopInfo.address }}
                            </div>
                            <div
                                class="font-mono text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                Ph: {{ shopInfo.phone }}
                            </div>
                            <div
                                class="mt-2 inline-block rounded border border-amber-300 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-800 uppercase"
                            >
                                Used Phone Purchase Voucher
                            </div>
                        </div>

                        <div
                            class="grid grid-cols-2 gap-2 border-b border-dashed border-slate-300 py-3 text-[11px] dark:border-slate-700"
                        >
                            <div>
                                <span class="text-slate-400">Voucher No:</span>
                                <span
                                    class="ml-1 font-mono font-bold text-slate-900 dark:text-white"
                                    >{{
                                        selectedPurchaseVoucherModal.voucher_no
                                    }}</span
                                >
                            </div>
                            <div class="text-right">
                                <span class="text-slate-400">Date:</span>
                                <span
                                    class="ml-1 font-mono text-slate-700 dark:text-slate-300"
                                    >{{
                                        new Date(
                                            selectedPurchaseVoucherModal.created_at,
                                        ).toLocaleString('en-PK')
                                    }}</span
                                >
                            </div>
                            <div>
                                <span class="text-slate-400">Seller Name:</span>
                                <span
                                    class="ml-1 font-bold text-slate-900 dark:text-white"
                                    >{{
                                        selectedPurchaseVoucherModal.seller_name
                                    }}</span
                                >
                            </div>
                            <div class="text-right">
                                <span class="text-slate-400">CNIC:</span>
                                <span
                                    class="ml-1 font-mono font-bold text-slate-900 dark:text-white"
                                    >{{
                                        selectedPurchaseVoucherModal.seller_cnic
                                    }}</span
                                >
                            </div>
                            <div
                                v-if="selectedPurchaseVoucherModal.seller_phone"
                            >
                                <span class="text-slate-400"
                                    >Seller Phone:</span
                                >
                                <span
                                    class="ml-1 font-mono text-slate-700 dark:text-slate-300"
                                    >{{
                                        selectedPurchaseVoucherModal.seller_phone
                                    }}</span
                                >
                            </div>
                            <div class="text-right">
                                <span class="text-slate-400">Payout:</span>
                                <span
                                    class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold text-slate-700 uppercase dark:bg-slate-700 dark:text-slate-200"
                                    >{{
                                        selectedPurchaseVoucherModal.payment_method
                                    }}</span
                                >
                            </div>
                        </div>

                        <div class="py-3">
                            <div
                                class="mb-1 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Device Acquired
                            </div>
                            <div
                                class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-800"
                            >
                                <div
                                    class="text-sm font-bold text-slate-900 dark:text-white"
                                >
                                    {{
                                        selectedPurchaseVoucherModal.device_model
                                    }}
                                </div>
                                <div
                                    class="mt-1 font-mono text-xs text-slate-700 dark:text-slate-300"
                                >
                                    IMEI 1:
                                    <strong>{{
                                        selectedPurchaseVoucherModal.imei_1
                                    }}</strong>
                                    <span
                                        v-if="
                                            selectedPurchaseVoucherModal.imei_2
                                        "
                                    >
                                        | IMEI 2:
                                        <strong>{{
                                            selectedPurchaseVoucherModal.imei_2
                                        }}</strong></span
                                    >
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between border-t border-dashed border-slate-300 pt-3 text-sm font-bold text-slate-900 dark:border-slate-700 dark:text-white"
                        >
                            <span>Payout Amount</span>
                            <span class="text-amber-600 dark:text-amber-400">{{
                                formatCurrency(
                                    selectedPurchaseVoucherModal.purchase_amount,
                                )
                            }}</span>
                        </div>
                    </div>
                </div>

                <DialogFooter
                    class="flex items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-800"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        @click="isPurchaseVoucherModalOpen = false"
                    >
                        Close
                    </Button>
                    <Button
                        size="sm"
                        class="gap-1.5 bg-amber-600 font-bold text-white hover:bg-amber-700"
                        @click="
                            printHtmlContent(
                                'purchase-voucher-printable-area',
                                'Purchase Voucher ' +
                                    selectedPurchaseVoucherModal?.voucher_no,
                            )
                        "
                    >
                        <Printer class="h-4 w-4" /> Print Voucher
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- MODAL: ADD NEW CUSTOMER -->
        <Dialog
            :open="isAddCustomerModalOpen"
            @update:open="isAddCustomerModalOpen = $event"
        >
            <DialogContent
                class="max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
            >
                <DialogHeader
                    class="border-b border-slate-100 pb-3 dark:border-slate-800"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#003B7D]/10 text-[#003B7D] dark:bg-blue-500/20 dark:text-blue-400"
                        >
                            <UserPlus class="h-6 w-6" />
                        </div>
                        <div>
                            <DialogTitle
                                class="text-base font-black text-slate-900 dark:text-white"
                            >
                                Add New Customer
                            </DialogTitle>
                            <DialogDescription class="text-xs text-slate-500">
                                Register a new customer for handset sales and
                                record ledger.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form
                    class="space-y-3.5 py-1 text-xs"
                    @submit.prevent="submitAddCustomer"
                >
                    <div class="space-y-1">
                        <Label class="font-bold"
                            >Customer Name
                            <span class="text-rose-500">*</span></Label
                        >
                        <Input
                            v-model="addCustomerForm.name"
                            required
                            placeholder="e.g. Mohammad Usman"
                            class="h-9 text-xs"
                        />
                        <span
                            v-if="addCustomerForm.errors.name"
                            class="text-xs font-bold text-rose-500"
                            >{{ addCustomerForm.errors.name }}</span
                        >
                    </div>

                    <div class="space-y-1">
                        <Label class="font-bold"
                            >Phone Number
                            <span class="text-rose-500">*</span></Label
                        >
                        <Input
                            v-model="addCustomerForm.phone"
                            required
                            placeholder="03001234567"
                            class="h-9 font-mono text-xs"
                        />
                        <span
                            v-if="addCustomerForm.errors.phone"
                            class="text-xs font-bold text-rose-500"
                            >{{ addCustomerForm.errors.phone }}</span
                        >
                    </div>

                    <div class="space-y-1">
                        <Label class="font-bold">Address (Optional)</Label>
                        <Input
                            v-model="addCustomerForm.address"
                            placeholder="e.g. Main Market, Shop #4"
                            class="h-9 text-xs"
                        />
                    </div>

                    <DialogFooter class="pt-3">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="isAddCustomerModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="addCustomerForm.processing"
                            class="bg-[#003B7D] font-bold text-white hover:bg-[#002b5c]"
                        >
                            {{
                                addCustomerForm.processing
                                    ? 'Saving...'
                                    : 'Save Customer'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
