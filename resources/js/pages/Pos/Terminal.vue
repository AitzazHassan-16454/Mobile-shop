<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    Barcode,
    Check,
    CheckCircle,
    CreditCard,
    DollarSign,
    History,
    Layers,
    Maximize2,
    Minimize2,
    Minus,
    Pause,
    Play,
    Plus,
    Printer,
    QrCode,
    Receipt,
    RotateCcw,
    Search,
    ShoppingCart,
    Smartphone,
    Store,
    Tag,
    Trash2,
    User,
    UserCheck,
    UserPlus,
    Wallet,
    X,
    Zap,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Toaster } from '@/components/ui/sonner';
import { useConfirm } from '@/composables/useConfirm';
import { dashboard } from '@/routes';
import pos from '@/routes/pos';
import type { Team } from '@/types';

const { confirm, alert } = useConfirm();

interface ProductImeiItem {
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
}

interface ProductItem {
    id: number;
    name: string;
    brand: string;
    category: string;
    barcode?: string | null;
    is_serialized: boolean;
    sale_price: number | string;
    cost_price: number | string;
    stock_quantity: number;
    alert_quantity: number;
    in_stock_imeis?: ProductImeiItem[];
}

interface CustomerItem {
    id: number;
    name: string;
    phone: string;
    address?: string | null;
    current_balance: number | string;
}

interface ShopInfo {
    name: string;
    phone: string;
    address: string;
    return_policy: string;
}

interface CartItem {
    key: string;
    product_id: number;
    product_imei_id?: number | null;
    name: string;
    brand: string;
    is_serialized: boolean;
    imei_1?: string;
    imei_2?: string | null;
    color?: string | null;
    storage?: string | null;
    condition?: string;
    pta_status?: string;
    quantity: number;
    unit_price: number;
    max_stock?: number;
}

interface CompletedSaleItem {
    id: number;
    product_id: number;
    product_imei_id?: number | null;
    quantity: number | string;
    unit_price: number | string;
    line_total: number | string;
    product: ProductItem;
    product_imei?: ProductImeiItem | null;
}

interface CompletedSale {
    id: number;
    invoice_no: string;
    customer_id?: number | null;
    total_amount: number | string;
    discount_amount: number | string;
    net_amount: number | string;
    paid_amount: number | string;
    change_amount: number | string;
    payment_method: string;
    payment_details?: Record<string, number> | null;
    created_at: string;
    customer?: CustomerItem | null;
    cashier?: { id: number; name: string };
    items: CompletedSaleItem[];
}

interface HeldSale {
    id: string;
    customer_id: string;
    customer_name: string;
    cart: CartItem[];
    discount: number | string;
    total: number;
    held_at: string;
}

const props = defineProps<{
    products: ProductItem[];
    customers: CustomerItem[];
    shopInfo: ShopInfo;
    latestSale?: CompletedSale | null;
}>();

const page = usePage();
const currentTeamSlug = computed(
    () => (page.props.currentTeam as Team | undefined)?.slug || 'default',
);
const dashboardUrl = computed(() => dashboard(currentTeamSlug.value).url);
const currentShopName = computed(
    () =>
        props.shopInfo?.name ||
        (page.props.currentTeam as Team | undefined)?.name ||
        'Faizan Mobiles & Reparing Mobile',
);

const isFullscreen = ref(false);

const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement
            .requestFullscreen()
            .then(() => {
                isFullscreen.value = true;
            })
            .catch(() => {});
    } else {
        if (document.exitFullscreen) {
            document
                .exitFullscreen()
                .then(() => {
                    isFullscreen.value = false;
                })
                .catch(() => {});
        }
    }
};

const handleFullscreenChange = () => {
    isFullscreen.value = !!document.fullscreenElement;
};

// POS Cart State
const cart = ref<CartItem[]>([]);
const selectedCustomerId = ref<string>('walk_in');
const discountInput = ref<number | string>(0);
const searchScanQuery = ref('');
const searchInputRef = ref<HTMLInputElement | null>(null);

const activeCategoryTab = ref('all');

// Modals
const isPaymentModalOpen = ref(false);
const isCustomerModalOpen = ref(false);
const isReceiptModalOpen = ref(false);
const isHeldSalesModalOpen = ref(false);
const isMethodSelectionPromptOpen = ref(false);
const activeReceipt = ref<CompletedSale | null>(props.latestSale || null);

// Payment Tender state
const paymentMethod = ref<string>('');
const paymentMethodError = ref(false);
const paidInput = ref<number | string>('');

// Held Sales list (Boson Studio Hold Cart Feature)
const heldSales = ref<HeldSale[]>([]);

// Split Payment Details
const splitAmounts = ref({
    cash: 0,
    jazzcash: 0,
    easypaisa: 0,
    bank: 0,
    card: 0,
});

// Form for quick customer add
const customerForm = useForm({
    name: '',
    phone: '',
    address: '',
});

const paymentMethodsList = [
    {
        id: 'cash',
        label: 'Cash',
        icon: DollarSign,
        color: 'text-emerald-600',
        activeBg:
            'border-emerald-500 bg-emerald-50/80 text-emerald-950 ring-1 ring-emerald-500/30 shadow-xs',
    },
    {
        id: 'jazzcash',
        label: 'JazzCash',
        icon: Smartphone,
        color: 'text-red-600',
        activeBg:
            'border-red-500 bg-red-50/80 text-red-950 ring-1 ring-red-500/30 shadow-xs',
    },
    {
        id: 'easypaisa',
        label: 'Easypaisa',
        icon: Wallet,
        color: 'text-emerald-600',
        activeBg:
            'border-emerald-500 bg-emerald-50/80 text-emerald-950 ring-1 ring-emerald-500/30 shadow-xs',
    },
    {
        id: 'bank',
        label: 'Bank Transfer',
        icon: CreditCard,
        color: 'text-blue-600',
        activeBg:
            'border-blue-500 bg-blue-50/80 text-blue-950 ring-1 ring-blue-500/30 shadow-xs',
    },
    {
        id: 'udhaar',
        label: 'Udhaar (Khata)',
        icon: UserCheck,
        color: 'text-amber-600',
        activeBg:
            'border-amber-500 bg-amber-50/80 text-amber-950 ring-1 ring-amber-500/30 shadow-xs',
    },
    {
        id: 'split',
        label: 'Split Pay',
        icon: Layers,
        color: 'text-indigo-600',
        activeBg:
            'border-indigo-500 bg-indigo-50/80 text-indigo-950 ring-1 ring-indigo-500/30 shadow-xs',
    },
];

const categories = computed(() => {
    const set = new Set<string>();
    props.products.forEach((p) => set.add(p.category));
    return ['all', ...Array.from(set)];
});

const filteredCatalogProducts = computed(() => {
    let list = props.products;
    if (activeCategoryTab.value !== 'all') {
        list = list.filter((p) => p.category === activeCategoryTab.value);
    }
    const q = searchScanQuery.value.trim().toLowerCase();
    if (!q) return list;

    return list.filter((p) => {
        const matchName = p.name.toLowerCase().includes(q);
        const matchBrand = p.brand.toLowerCase().includes(q);
        const matchBarcode = p.barcode
            ? p.barcode.toLowerCase().includes(q)
            : false;
        const matchImei = p.in_stock_imeis
            ? p.in_stock_imeis.some(
                  (i) =>
                      i.imei_1.toLowerCase().includes(q) ||
                      (i.imei_2 && i.imei_2.toLowerCase().includes(q)),
              )
            : false;

        return matchName || matchBrand || matchBarcode || matchImei;
    });
});

const selectedCustomer = computed(() => {
    if (selectedCustomerId.value === 'walk_in') return null;
    return (
        props.customers.find(
            (c) => c.id === Number(selectedCustomerId.value),
        ) || null
    );
});

const totalItemsCount = computed(() => {
    return cart.value.reduce((acc, item) => acc + item.quantity, 0);
});

const subtotal = computed(() => {
    return cart.value.reduce(
        (acc, item) => acc + item.quantity * item.unit_price,
        0,
    );
});

const discountAmount = computed(() => {
    const val = Number(discountInput.value) || 0;
    return Math.min(val, subtotal.value);
});

const netPayable = computed(() => {
    return Math.max(0, subtotal.value - discountAmount.value);
});

const changeToReturn = computed(() => {
    const paid = Number(paidInput.value) || 0;
    return Math.max(0, paid - netPayable.value);
});

const remainingKhataBalance = computed(() => {
    const paid = Number(paidInput.value) || 0;
    return Math.max(0, netPayable.value - paid);
});

watch(
    () => props.latestSale,
    (newSale) => {
        if (newSale) {
            activeReceipt.value = newSale;
            isReceiptModalOpen.value = true;
            cart.value = [];
            discountInput.value = 0;
            paidInput.value = '';
            paymentMethod.value = '';
            paymentMethodError.value = false;
            selectedCustomerId.value = 'walk_in';
        }
    },
    { immediate: true },
);

const handleScanSubmit = () => {
    const q = searchScanQuery.value.trim();
    if (!q) return;

    for (const p of props.products) {
        if (p.is_serialized && p.in_stock_imeis) {
            const matchedImei = p.in_stock_imeis.find(
                (i) =>
                    i.imei_1.toLowerCase() === q.toLowerCase() ||
                    (i.imei_2 && i.imei_2.toLowerCase() === q.toLowerCase()),
            );
            if (matchedImei) {
                addProductToCart(p, matchedImei);
                searchScanQuery.value = '';
                return;
            }
        }
    }

    const matchedBarcodeProduct = props.products.find(
        (p) =>
            !p.is_serialized &&
            p.barcode &&
            p.barcode.toLowerCase() === q.toLowerCase(),
    );
    if (matchedBarcodeProduct) {
        addProductToCart(matchedBarcodeProduct);
        searchScanQuery.value = '';
        return;
    }

    const matches = filteredCatalogProducts.value;
    if (matches.length === 1) {
        addProductToCart(matches[0]);
        searchScanQuery.value = '';
    }
};

const addProductToCart = (
    product: ProductItem,
    specificImei?: ProductImeiItem,
) => {
    if (product.is_serialized) {
        const availableImei =
            specificImei ||
            product.in_stock_imeis?.find(
                (imei) =>
                    !cart.value.some(
                        (item) => item.product_imei_id === imei.id,
                    ),
            );

        if (!availableImei) {
            toast.error(`"${product.name}" is out of stock.`);
            return;
        }

        const key = `imei-${availableImei.id}`;
        if (cart.value.some((item) => item.key === key)) {
            toast.error(`This item is already in the cart.`);
            return;
        }

        cart.value.push({
            key,
            product_id: product.id,
            product_imei_id: availableImei.id,
            name: product.name,
            brand: product.brand,
            is_serialized: true,
            quantity: 1,
            unit_price: Number(product.sale_price),
        });
        toast.success(`Added: ${product.name}`);
    } else {
        const key = `prod-${product.id}`;
        const existing = cart.value.find((item) => item.key === key);

        if (existing) {
            if (
                product.stock_quantity > 0 &&
                existing.quantity >= product.stock_quantity
            ) {
                toast.error(
                    `Cannot add more than available stock (${product.stock_quantity}).`,
                );
                return;
            }
            existing.quantity += 1;
            toast.success(`Added: ${product.name}`);
        } else {
            if (product.stock_quantity <= 0) {
                toast.error(`"${product.name}" is out of stock.`);
                return;
            }
            cart.value.push({
                key,
                product_id: product.id,
                name: product.name,
                brand: product.brand,
                is_serialized: false,
                quantity: 1,
                unit_price: Number(product.sale_price),
                max_stock: product.stock_quantity,
            });
            toast.success(`Added: ${product.name}`);
        }
    }
};

const incrementCartItem = (item: CartItem) => {
    if (item.is_serialized) {
        const prod = props.products.find((p) => p.id === item.product_id);
        if (prod) {
            addProductToCart(prod);
        }
        return;
    }
    if (item.max_stock && item.quantity >= item.max_stock) {
        toast.error(`Cannot exceed available stock of ${item.max_stock}.`);
        return;
    }
    item.quantity += 1;
};

const decrementCartItem = (item: CartItem, index: number) => {
    if (item.quantity > 1) {
        item.quantity -= 1;
    } else {
        removeCartItem(index);
    }
};

const setDiscountPreset = (amount: number) => {
    discountInput.value = amount;
};

const removeCartItem = (index: number) => {
    cart.value.splice(index, 1);
};

const clearCart = async () => {
    if (cart.value.length === 0) return;
    const ok = await confirm({
        title: 'Clear Cart',
        message: 'Are you sure you want to clear all items from the current cart?',
        confirmText: 'Clear Cart',
        cancelText: 'Cancel',
        variant: 'destructive',
    });
    if (ok) {
        cart.value = [];
        discountInput.value = 0;
        paidInput.value = '';
        paymentMethod.value = '';
        paymentMethodError.value = false;
    }
};

// Boson Studio POS Feature: Hold & Recall Sales
const holdCurrentSale = () => {
    if (cart.value.length === 0) {
        toast.error('Cart is empty, nothing to hold.');
        return;
    }
    const customerName = selectedCustomer.value?.name || 'Walk-in Customer';
    heldSales.value.push({
        id: 'HOLD-' + (heldSales.value.length + 1),
        customer_id: selectedCustomerId.value,
        customer_name: customerName,
        cart: JSON.parse(JSON.stringify(cart.value)),
        discount: discountInput.value,
        total: netPayable.value,
        held_at: new Date().toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        }),
    });
    cart.value = [];
    discountInput.value = 0;
    paymentMethod.value = '';
    selectedCustomerId.value = 'walk_in';
    toast.success(`Sale for "${customerName}" placed on hold.`);
};

const recallHeldSale = async (index: number) => {
    const item = heldSales.value[index];
    if (!item) return;

    if (cart.value.length > 0) {
        const ok = await confirm({
            title: 'Replace Cart',
            message: 'Replace current cart with this held sale?',
            confirmText: 'Replace Cart',
            cancelText: 'Cancel',
            variant: 'warning',
        });
        if (!ok) return;
    }

    cart.value = JSON.parse(JSON.stringify(item.cart));
    discountInput.value = item.discount;
    selectedCustomerId.value = item.customer_id;
    paymentMethod.value = '';
    paymentMethodError.value = false;
    heldSales.value.splice(index, 1);
    isHeldSalesModalOpen.value = false;
    toast.success(`Recalled held sale for "${item.customer_name}".`);
};

const removeHeldSale = (index: number) => {
    heldSales.value.splice(index, 1);
    toast.info('Held sale removed.');
};

// Fast Cash 1-tap checkout (Common in retail mobile shops)
const quickCashCheckout = () => {
    if (cart.value.length === 0) return;
    paymentMethod.value = 'cash';
    paidInput.value = netPayable.value;
    submitCheckout();
};

const handleProceedToPayment = () => {
    if (cart.value.length === 0) return;

    if (!paymentMethod.value) {
        paymentMethodError.value = true;
        isMethodSelectionPromptOpen.value = true;
        toast.error('Bara-e-meherbani pehle Payment Method select karein!');
        return;
    }

    if (
        paymentMethod.value === 'udhaar' &&
        selectedCustomerId.value === 'walk_in'
    ) {
        alert({
            title: 'Customer Required',
            message: 'Please select a customer for Udhaar (Khata) checkout.',
            variant: 'warning',
        });
        return;
    }

    paymentMethodError.value = false;
    openPaymentModal();
};

const selectMethodAndProceed = (methodId: string) => {
    paymentMethod.value = methodId;
    paymentMethodError.value = false;
    isMethodSelectionPromptOpen.value = false;
    if (methodId === 'udhaar' && selectedCustomerId.value === 'walk_in') {
        alert({
            title: 'Customer Required',
            message: 'Please select a customer for Udhaar (Khata) checkout.',
            variant: 'warning',
        });
        return;
    }
    openPaymentModal();
};

const openPaymentModal = (method?: string) => {
    if (cart.value.length === 0) return;
    if (method) {
        paymentMethod.value = method;
    } else if (!paymentMethod.value) {
        handleProceedToPayment();
        return;
    }
    paidInput.value = netPayable.value;
    splitAmounts.value = {
        cash: netPayable.value,
        jazzcash: 0,
        easypaisa: 0,
        bank: 0,
        card: 0,
    };
    isPaymentModalOpen.value = true;
};

const setExactPayment = () => {
    paidInput.value = netPayable.value;
};

const setTenderPreset = (amount: number) => {
    paidInput.value = amount;
};

const submitCheckout = () => {
    if (cart.value.length === 0) return;

    if (
        paymentMethod.value === 'udhaar' &&
        selectedCustomerId.value === 'walk_in'
    ) {
        alert({
            title: 'Customer Required',
            message: 'Please select a customer for Udhaar (Khata) checkout.',
            variant: 'warning',
        });
        return;
    }

    const payload = {
        customer_id:
            selectedCustomerId.value === 'walk_in'
                ? null
                : Number(selectedCustomerId.value),
        payment_method: paymentMethod.value,
        discount_amount: discountAmount.value,
        paid_amount:
            paymentMethod.value === 'udhaar'
                ? Number(paidInput.value) || 0
                : Number(paidInput.value) || netPayable.value,
        split_details:
            paymentMethod.value === 'split' ? splitAmounts.value : null,
        items: cart.value.map((item) => ({
            product_id: item.product_id,
            product_imei_id: item.product_imei_id || null,
            quantity: item.quantity,
            unit_price: item.unit_price,
        })),
    };

    router.post(pos.sales.store(currentTeamSlug.value).url, payload, {
        preserveScroll: true,
        onSuccess: () => {
            isPaymentModalOpen.value = false;
        },
    });
};

const submitCustomerForm = () => {
    customerForm.post(pos.customers.store(currentTeamSlug.value).url, {
        onSuccess: () => {
            customerForm.reset();
            isCustomerModalOpen.value = false;
            toast.success('Customer registered successfully.');
        },
    });
};

const handleGlobalKeydown = (e: KeyboardEvent) => {
    if (e.key === 'F1') {
        e.preventDefault();
        if (cart.value.length > 0) {
            holdCurrentSale();
        } else if (heldSales.value.length > 0) {
            isHeldSalesModalOpen.value = true;
        }
    } else if (e.key === 'F2') {
        e.preventDefault();
        searchInputRef.value?.focus();
    } else if (e.key === 'F3') {
        e.preventDefault();
        isCustomerModalOpen.value = true;
    } else if (e.key === 'F4') {
        e.preventDefault();
        document.getElementById('discount-input')?.focus();
    } else if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        if (isPaymentModalOpen.value) {
            submitCheckout();
        } else if (cart.value.length > 0) {
            handleProceedToPayment();
        }
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    searchInputRef.value?.focus();
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
});

const formatCurrency = (val: number | string) => {
    const num = Number(val) || 0;
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: 0,
    }).format(num);
};

const printReceipt = () => {
    window.print();
};
</script>

<template>
    <Head :title="`${currentShopName} - POS Terminal`" />

    <div
        class="relative flex h-screen min-h-screen w-screen flex-col overflow-hidden bg-[#edf2f8] text-slate-800 antialiased select-none"
    >
        <!-- Atmospheric Radial Ambient Lights -->
        <div
            class="pointer-events-none absolute -top-32 left-1/4 h-80 w-[600px] rounded-full bg-blue-400/10 blur-3xl"
        ></div>
        <div
            class="pointer-events-none absolute right-1/4 -bottom-32 h-80 w-[600px] rounded-full bg-sky-300/10 blur-3xl"
        ></div>

        <!-- POS Top Navigation Bar -->
        <header
            class="z-30 flex h-14 shrink-0 items-center justify-between border-b border-white/15 bg-gradient-to-r from-[#002654] via-[#003B7D] to-[#004e9c] px-4 shadow-[0_4px_20px_rgba(0,35,80,0.2)] backdrop-blur-2xl"
        >
            <div class="flex items-center gap-3">
                <Link
                    :href="dashboardUrl"
                    class="group inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-bold text-white shadow-2xs backdrop-blur-md transition hover:border-white/35 hover:bg-white/20 active:scale-95"
                    title="Return to Main Dashboard"
                >
                    <ArrowLeft
                        class="h-3.5 w-3.5 text-blue-200 transition-transform group-hover:-translate-x-0.5 group-hover:text-white"
                    />
                    <span>Dashboard</span>
                </Link>
            </div>

            <!-- Header Right: Fullscreen & Clear Cart -->
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="toggleFullscreen"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-2.5 py-1.5 text-xs font-semibold text-white shadow-2xs backdrop-blur-md transition hover:border-white/35 hover:bg-white/20 active:scale-95"
                    :title="
                        isFullscreen
                            ? 'Exit Fullscreen'
                            : 'Enter Fullscreen mode'
                    "
                >
                    <Minimize2
                        v-if="isFullscreen"
                        class="h-3.5 w-3.5 text-blue-200"
                    />
                    <Maximize2 v-else class="h-3.5 w-3.5 text-blue-200" />
                    <span>{{ isFullscreen ? 'Windowed' : 'Fullscreen' }}</span>
                </button>

                <button
                    type="button"
                    @click="clearCart"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-rose-400/30 bg-rose-500/20 px-3 py-1.5 text-xs font-bold text-rose-100 shadow-2xs backdrop-blur-md transition hover:border-rose-400/50 hover:bg-rose-500/30 hover:text-white active:scale-95"
                    title="Reset / Clear Cart"
                >
                    <RotateCcw class="h-3.5 w-3.5 text-rose-300" />
                    <span>Clear Cart</span>
                </button>
            </div>
        </header>

        <!-- Main Workspace Body: Two Frosted Glass Decks -->
        <div
            class="relative z-10 grid flex-1 grid-cols-1 gap-3 overflow-hidden p-3 lg:grid-cols-12"
        >
            <!-- LEFT DECK: Cart & Scanner Bar (col-span-7) -->
            <div
                class="flex flex-col overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_32px_rgba(0,25,60,0.06)] backdrop-blur-2xl lg:col-span-7"
            >
                <!-- Scanner & Customer Khata Header -->
                <div
                    class="space-y-2.5 border-b border-slate-200/70 bg-white/60 p-3 backdrop-blur-md"
                >
                    <!-- Barcode/IMEI Scanner Input -->
                    <div class="relative">
                        <div
                            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                        >
                            <Barcode class="h-4 w-4 text-[#003B7D]" />
                        </div>
                        <input
                            ref="searchInputRef"
                            v-model="searchScanQuery"
                            @keydown.enter.prevent="handleScanSubmit"
                            type="text"
                            placeholder="Scan IMEI, accessory barcode or search product name..."
                            class="w-full rounded-2xl border border-slate-200/80 bg-white/90 py-2.5 pr-20 pl-9 text-xs font-semibold text-slate-900 shadow-2xs transition-all placeholder:text-slate-400 focus:border-[#003B7D] focus:ring-3 focus:ring-[#003B7D]/15 focus:outline-none"
                        />
                        <div
                            class="absolute inset-y-0 right-0 flex items-center gap-1.5 pr-2.5"
                        >
                            <button
                                v-if="searchScanQuery"
                                type="button"
                                @click="searchScanQuery = ''"
                                class="rounded-lg p-0.5 text-slate-400 hover:text-slate-600"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                            <span
                                class="rounded-md border border-slate-200 bg-slate-100 px-1.5 py-0.5 font-mono text-[9px] font-bold text-slate-500"
                            >
                                F2
                            </span>
                        </div>
                    </div>

                    <!-- Customer Selection & Add Customer Button -->
                    <div class="flex items-center gap-2">
                        <div class="flex flex-1 items-center gap-2">
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-slate-200/60 bg-slate-100 text-slate-600"
                            >
                                <User class="h-3.5 w-3.5" />
                            </div>
                            <Select v-model="selectedCustomerId" class="flex-1">
                                <SelectTrigger
                                    class="h-9 rounded-xl border-slate-200/80 bg-white/90 text-xs font-medium text-slate-700 shadow-2xs"
                                >
                                    <SelectValue
                                        placeholder="Walk-in Customer (Cash)"
                                    />
                                </SelectTrigger>
                                <SelectContent
                                    class="rounded-2xl border-white/80 bg-white/95 shadow-xl backdrop-blur-xl"
                                >
                                    <SelectItem value="walk_in">
                                        <span class="font-bold"
                                            >Walk-in Customer</span
                                        >
                                        (Standard Cash Sale)
                                    </SelectItem>
                                    <SelectItem
                                        v-for="c in customers"
                                        :key="c.id"
                                        :value="String(c.id)"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold">{{
                                                c.name
                                            }}</span>
                                            <span
                                                class="text-[10px] text-slate-400"
                                                >({{ c.phone }})</span
                                            >
                                            <span
                                                v-if="
                                                    Number(c.current_balance) >
                                                    0
                                                "
                                                class="rounded-full bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold text-amber-700 ring-1 ring-amber-600/20"
                                            >
                                                Khata:
                                                {{
                                                    formatCurrency(
                                                        c.current_balance,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Add Customer Modal Trigger (F3) -->
                        <button
                            type="button"
                            @click="isCustomerModalOpen = true"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200/80 bg-white/90 px-3 py-2 text-xs font-bold text-slate-700 shadow-2xs transition hover:bg-slate-100 hover:text-slate-900 active:scale-95"
                            title="Register New Customer (F3)"
                        >
                            <UserPlus class="h-3.5 w-3.5 text-[#003B7D]" />
                            <span>+ Khata</span>
                            <span
                                class="rounded bg-slate-100 px-1 font-mono text-[9px] text-slate-500"
                                >F3</span
                            >
                        </button>
                    </div>
                </div>

                <!-- Cart Items Table Area -->
                <div class="flex-1 overflow-y-auto p-3">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead
                            class="sticky top-0 z-10 border-b border-slate-200/80 bg-slate-100/90 text-[10px] font-black tracking-[0.14em] text-slate-500 uppercase backdrop-blur-md"
                        >
                            <tr>
                                <th
                                    class="w-8 rounded-l-xl px-2 py-2.5 text-center"
                                >
                                    #
                                </th>
                                <th class="px-3 py-2.5">
                                    Item & Specifications
                                </th>
                                <th class="w-24 px-3 py-2.5 text-right">
                                    Price
                                </th>
                                <th class="w-24 px-3 py-2.5 text-center">
                                    Qty
                                </th>
                                <th class="w-28 px-3 py-2.5 text-right">
                                    Subtotal
                                </th>
                                <th
                                    class="w-10 rounded-r-xl px-2 py-2.5 text-center"
                                ></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="cart.length === 0">
                                <td colspan="6" class="py-20 text-center">
                                    <div
                                        class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-tr from-slate-100 to-slate-200/80 text-slate-400 shadow-inner"
                                    >
                                        <ShoppingCart class="h-7 w-7" />
                                    </div>
                                    <div
                                        class="text-sm font-black text-slate-700"
                                    >
                                        Cart is empty
                                    </div>
                                    <div class="mt-1 text-xs text-slate-400">
                                        Scan an IMEI / barcode or click an item
                                        from catalog to start.
                                    </div>
                                    <div
                                        class="mt-4 flex items-center justify-center gap-2"
                                    >
                                        <button
                                            type="button"
                                            @click="searchInputRef?.focus()"
                                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200/80 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50"
                                        >
                                            <Barcode
                                                class="h-3.5 w-3.5 text-[#003B7D]"
                                            />
                                            <span>Focus Scanner (F2)</span>
                                        </button>
                                        <button
                                            v-if="heldSales.length > 0"
                                            type="button"
                                            @click="isHeldSalesModalOpen = true"
                                            class="inline-flex items-center gap-1.5 rounded-xl border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-800 shadow-2xs hover:bg-amber-100"
                                        >
                                            <History
                                                class="h-3.5 w-3.5 text-amber-700"
                                            />
                                            <span
                                                >Recall Held Sale ({{
                                                    heldSales.length
                                                }})</span
                                            >
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr
                                v-for="(item, idx) in cart"
                                :key="item.key"
                                class="group transition-colors hover:bg-blue-50/40"
                            >
                                <td
                                    class="px-2 py-3 text-center font-mono text-[10px] text-slate-400"
                                >
                                    {{ idx + 1 }}
                                </td>

                                <td class="px-3 py-3">
                                    <div class="flex items-baseline gap-2">
                                        <span
                                            class="text-xs font-extrabold text-slate-900"
                                            >{{ item.name }}</span
                                        >
                                        <span
                                            class="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-500 uppercase"
                                            >{{ item.brand }}</span
                                        >
                                    </div>
                                </td>

                                <!-- Unit Price (Inline Editable for Bargaining) -->
                                <td class="px-3 py-3 text-right">
                                    <div class="relative inline-block">
                                        <input
                                            v-model.number="item.unit_price"
                                            type="number"
                                            step="1"
                                            class="tnum w-24 rounded-xl border border-slate-200/90 bg-white/90 px-2 py-1 text-right text-xs font-black text-slate-900 shadow-2xs focus:border-[#003B7D] focus:ring-2 focus:ring-[#003B7D]/20 focus:outline-none"
                                        />
                                    </div>
                                </td>

                                <!-- Quantity Stepper Column -->
                                <td class="px-3 py-3 text-center">
                                    <div
                                        class="inline-flex items-center gap-1 rounded-xl border border-slate-200/80 bg-white p-0.5 shadow-2xs"
                                    >
                                        <button
                                            type="button"
                                            @click="
                                                decrementCartItem(item, idx)
                                            "
                                            class="flex h-5 w-5 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 active:scale-95"
                                        >
                                            <Minus class="h-3 w-3" />
                                        </button>
                                        <input
                                            v-model.number="item.quantity"
                                            type="number"
                                            min="1"
                                            :max="item.max_stock"
                                            class="tnum w-8 text-center text-xs font-black text-slate-900 focus:outline-none"
                                        />
                                        <button
                                            type="button"
                                            @click="incrementCartItem(item)"
                                            class="flex h-5 w-5 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 active:scale-95"
                                        >
                                            <Plus class="h-3 w-3" />
                                        </button>
                                    </div>
                                </td>

                                <!-- Line Total -->
                                <td
                                    class="tnum px-3 py-3 text-right text-xs font-black text-slate-900"
                                >
                                    {{
                                        formatCurrency(
                                            item.quantity * item.unit_price,
                                        )
                                    }}
                                </td>

                                <!-- Trash Action -->
                                <td class="px-2 py-3 text-center">
                                    <button
                                        type="button"
                                        @click="removeCartItem(idx)"
                                        class="flex h-7 w-7 items-center justify-center rounded-xl text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 active:scale-90"
                                        title="Remove item"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- RIGHT DECK: Checkout Summary & Product Catalog (col-span-5) -->
            <div
                class="flex h-full flex-col gap-3 overflow-hidden lg:col-span-5"
            >
                <!-- Redesigned Apple iOS Glassmorphic Checkout Summary Card -->
                <div
                    class="pos-readout relative flex shrink-0 flex-col justify-between overflow-hidden rounded-3xl border border-white/85 bg-white/85 p-4 shadow-[0_12px_36px_rgba(0,35,80,0.06)] backdrop-blur-2xl transition-all"
                >
                    <!-- Top Row: Section Header & Items Count -->
                    <div
                        class="flex items-center justify-between border-b border-slate-100 pb-2.5"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="flex h-7 w-7 items-center justify-center rounded-xl bg-[#003B7D]/10 text-[#003B7D]"
                            >
                                <Receipt class="h-3.5 w-3.5" />
                            </div>
                            <div>
                                <h3
                                    class="text-xs font-black tracking-tight text-slate-900 uppercase"
                                >
                                    Checkout Summary
                                </h3>
                                <span class="text-[10px] text-slate-400"
                                    >Order calculation & billing</span
                                >
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span
                                class="rounded-full bg-slate-100 px-2 py-0.5 font-mono text-[10px] font-bold text-slate-700"
                            >
                                {{ cart.length }} items (Qty:
                                {{ totalItemsCount }})
                            </span>
                        </div>
                    </div>

                    <!-- Middle: Breakdown & Liquid Net Payable Card -->
                    <div class="space-y-2 py-2">
                        <!-- Subtotal row -->
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-slate-500"
                                >Subtotal Amount</span
                            >
                            <span class="tnum font-bold text-slate-800">{{
                                formatCurrency(subtotal)
                            }}</span>
                        </div>

                        <!-- Discount row with quick chips and input -->
                        <div
                            class="flex items-center justify-between gap-2 text-xs"
                        >
                            <div
                                class="flex items-center gap-1.5 font-medium text-slate-500"
                            >
                                <Tag class="h-3.5 w-3.5 text-[#003B7D]" />
                                <span>Discount</span>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <div class="flex items-center gap-1">
                                    <button
                                        v-for="amt in [0, 500, 1000]"
                                        :key="amt"
                                        type="button"
                                        @click="setDiscountPreset(amt)"
                                        :class="[
                                            Number(discountInput) === amt
                                                ? 'bg-[#003B7D] font-bold text-white shadow-2xs'
                                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80',
                                            'rounded-lg px-2 py-0.5 text-[10px] transition active:scale-95',
                                        ]"
                                    >
                                        {{ amt === 0 ? '0' : `${amt / 1000}k` }}
                                    </button>
                                </div>
                                <input
                                    id="discount-input"
                                    v-model="discountInput"
                                    type="number"
                                    placeholder="0"
                                    class="tnum w-20 rounded-xl border border-slate-200/80 bg-slate-50 px-2 py-1 text-right text-xs font-bold text-slate-900 shadow-inner focus:border-[#003B7D] focus:bg-white focus:outline-none"
                                />
                            </div>
                        </div>

                        <!-- Total Net Payable Display Card -->
                        <div
                            class="relative overflow-hidden rounded-2xl border border-white/20 bg-gradient-to-br from-[#001f4d] via-[#003B7D] to-[#0055b3] p-3.5 text-white shadow-[0_12px_28px_-6px_rgba(0,59,125,0.35)]"
                        >
                            <!-- Specular glow bubbles -->
                            <div
                                class="pointer-events-none absolute -top-10 -right-10 h-32 w-32 rounded-full bg-sky-400/20 blur-xl"
                            ></div>
                            <div
                                class="pointer-events-none absolute -bottom-10 -left-10 h-32 w-32 rounded-full bg-blue-600/30 blur-xl"
                            ></div>

                            <div
                                class="relative z-10 flex items-baseline justify-between"
                            >
                                <div>
                                    <div
                                        class="text-[9px] font-black tracking-[0.16em] text-blue-200 uppercase"
                                    >
                                        Total Net Payable
                                    </div>
                                    <div class="text-[10px] text-blue-200/80">
                                        Final Amount Due
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span
                                        class="mr-1 text-xs font-bold text-blue-200"
                                        >PKR</span
                                    >
                                    <span
                                        class="tnum text-3xl font-black tracking-tight text-white drop-shadow-xs md:text-4xl"
                                    >
                                        {{
                                            formatCurrency(netPayable)
                                                .replace('PKR', '')
                                                .trim()
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom: Payment Method Selection & Proceed CTA -->
                    <div class="space-y-2 border-t border-slate-100 pt-2.5">
                        <!-- Heading with selection indicator -->
                        <div class="flex items-center justify-between">
                            <span
                                class="text-[10px] font-black tracking-wider text-slate-700 uppercase"
                            >
                                Payment Method:
                            </span>
                            <span
                                v-if="paymentMethod"
                                class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 ring-1 ring-emerald-600/20"
                            >
                                <Check class="h-3 w-3" />
                                Selected:
                                {{
                                    paymentMethodsList.find(
                                        (m) => m.id === paymentMethod,
                                    )?.label
                                }}
                            </span>
                            <span
                                v-else
                                :class="[
                                    paymentMethodError
                                        ? 'animate-pulse bg-rose-50 font-bold text-rose-600 ring-1 ring-rose-500/30'
                                        : 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20',
                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium',
                                ]"
                            >
                                * Select Method First
                            </span>
                        </div>

                        <!-- Payment Method Interactive Buttons Grid -->
                        <div
                            :class="[
                                paymentMethodError && !paymentMethod
                                    ? 'rounded-2xl bg-rose-50/50 p-1 ring-2 ring-rose-500/40'
                                    : '',
                                'grid grid-cols-3 gap-1.5',
                            ]"
                        >
                            <button
                                v-for="pm in paymentMethodsList"
                                :key="pm.id"
                                type="button"
                                @click="
                                    paymentMethod = pm.id;
                                    paymentMethodError = false;
                                "
                                :class="[
                                    paymentMethod === pm.id
                                        ? 'border-[#003B7D] bg-[#003B7D] font-bold text-white shadow-xs'
                                        : 'border-slate-200/80 bg-slate-50 text-slate-700 hover:bg-slate-100 hover:text-slate-900',
                                    'flex items-center justify-center gap-1.5 rounded-xl border px-2 py-1.5 text-[11px] font-semibold transition active:scale-95',
                                ]"
                            >
                                <component
                                    :is="pm.icon"
                                    :class="[
                                        'h-3.5 w-3.5',
                                        paymentMethod === pm.id
                                            ? 'text-white'
                                            : pm.color,
                                    ]"
                                />
                                <span class="truncate">{{ pm.label }}</span>
                            </button>
                        </div>

                        <!-- Action Buttons Row: Proceed, Quick Cash, and Hold -->
                        <div class="space-y-1.5 pt-1">
                            <!-- Proceed to Payment Button -->
                            <button
                                type="button"
                                @click="handleProceedToPayment"
                                :disabled="cart.length === 0"
                                :class="[
                                    paymentMethod
                                        ? 'bg-gradient-to-r from-emerald-500 to-teal-600 shadow-[0_10px_25px_-5px_rgba(16,185,129,0.35)] hover:from-emerald-400 hover:to-teal-500'
                                        : 'bg-gradient-to-r from-[#003B7D] to-[#0055b3] shadow-[0_10px_25px_-5px_rgba(0,59,125,0.3)] hover:from-[#002f66] hover:to-[#00448f]',
                                    'group flex w-full items-center justify-center gap-2 rounded-2xl px-5 py-2.5 text-sm font-black text-white transition active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none',
                                ]"
                            >
                                <CreditCard
                                    class="h-4 w-4 transition-transform group-hover:scale-110"
                                />
                                <span>{{
                                    paymentMethod
                                        ? `Proceed with ${paymentMethodsList.find((m) => m.id === paymentMethod)?.label} (Ctrl+Enter)`
                                        : 'Proceed to Payment (Ctrl+Enter)'
                                }}</span>
                            </button>

                            <!-- Secondary Row: Quick Cash & Hold Sale -->
                            <div class="grid grid-cols-2 gap-1.5">
                                <button
                                    type="button"
                                    @click="quickCashCheckout"
                                    :disabled="cart.length === 0"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-emerald-300/80 bg-emerald-50/80 px-3 py-2 text-xs font-bold text-emerald-800 shadow-2xs hover:bg-emerald-100 active:scale-95 disabled:opacity-40"
                                    title="Instant exact cash transaction"
                                >
                                    <Zap class="h-3.5 w-3.5 text-emerald-600" />
                                    <span>Quick Cash Pay</span>
                                </button>

                                <button
                                    type="button"
                                    @click="holdCurrentSale"
                                    :disabled="cart.length === 0"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-amber-300/80 bg-amber-50/80 px-3 py-2 text-xs font-bold text-amber-800 shadow-2xs hover:bg-amber-100 active:scale-95 disabled:opacity-40"
                                    title="Suspend/Hold this cart for later"
                                >
                                    <Pause class="h-3.5 w-3.5 text-amber-600" />
                                    <span>Hold Sale (F1)</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Catalog Deck -->
                <div
                    class="flex flex-1 flex-col overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_32px_rgba(0,25,60,0.06)] backdrop-blur-2xl"
                >
                    <!-- Category Segmented Filter Tabs -->
                    <div
                        class="border-b border-slate-200/70 bg-white/60 p-2.5 backdrop-blur-md"
                    >
                        <div
                            class="no-scrollbar flex items-center gap-1 overflow-x-auto"
                        >
                            <button
                                v-for="cat in categories"
                                :key="cat"
                                @click="activeCategoryTab = cat"
                                :class="[
                                    activeCategoryTab === cat
                                        ? 'bg-[#003B7D] text-white shadow-2xs'
                                        : 'bg-slate-100/80 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900',
                                    'rounded-xl px-3 py-1.5 text-[11px] font-bold whitespace-nowrap capitalize transition-all duration-150',
                                ]"
                            >
                                {{ cat }}
                            </button>
                        </div>
                    </div>

                    <!-- Catalog Grid -->
                    <div class="flex-1 overflow-y-auto p-3">
                        <div class="grid grid-cols-2 gap-2.5">
                            <div
                                v-for="p in filteredCatalogProducts"
                                :key="p.id"
                                class="group relative flex cursor-pointer flex-col justify-between rounded-2xl border border-slate-200/80 bg-white/90 p-3 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:border-[#003B7D]/40 hover:shadow-md active:scale-[0.99]"
                                @click="addProductToCart(p)"
                            >
                                <div>
                                    <div
                                        class="mb-1.5 flex items-center justify-between text-[10px] font-bold uppercase"
                                    >
                                        <span
                                            class="font-extrabold text-[#003B7D]"
                                            >{{ p.brand }}</span
                                        >
                                        <span
                                            class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-600"
                                        >
                                            Stock:
                                            <strong
                                                class="tnum text-slate-900"
                                                >{{
                                                    p.is_serialized
                                                        ? p.in_stock_imeis
                                                              ?.length || 0
                                                        : p.stock_quantity
                                                }}</strong
                                            >
                                        </span>
                                    </div>

                                    <h3
                                        class="line-clamp-1 text-xs font-black text-slate-900 group-hover:text-[#003B7D]"
                                    >
                                        {{ p.name }}
                                    </h3>

                                    <div
                                        class="tnum mt-1.5 text-xs font-black text-[#003B7D]"
                                    >
                                        {{ formatCurrency(p.sale_price) }}
                                    </div>
                                </div>

                                <div
                                    class="mt-2.5 flex items-center justify-between border-t border-slate-100 pt-2 text-[10px]"
                                >
                                    <span class="text-slate-400"
                                        >Tap to add</span
                                    >
                                    <span
                                        class="inline-flex items-center gap-0.5 font-black text-[#003B7D] group-hover:underline"
                                    >
                                        + Add to Cart
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Checkout / Payment Modal -->
        <Dialog v-model:open="isPaymentModalOpen">
            <DialogContent
                class="max-w-lg rounded-3xl border border-white/80 bg-white/95 p-6 shadow-2xl backdrop-blur-2xl"
            >
                <DialogHeader>
                    <DialogTitle
                        class="flex items-center gap-2 text-lg font-black text-slate-900"
                    >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#003B7D] text-white"
                        >
                            <CreditCard class="h-4 w-4" />
                        </div>
                        <span>Complete Sale Transaction</span>
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Confirm payment method and tender received from
                        customer.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <!-- Payment Methods Grid -->
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="pm in paymentMethodsList"
                            :key="pm.id"
                            type="button"
                            @click="paymentMethod = pm.id"
                            :class="[
                                paymentMethod === pm.id
                                    ? pm.activeBg
                                    : 'border-slate-200/80 bg-slate-50 text-slate-600 hover:bg-slate-100',
                                'flex flex-col items-center justify-center gap-1.5 rounded-2xl border p-3 text-xs font-bold transition active:scale-95',
                            ]"
                        >
                            <component
                                :is="pm.icon"
                                :class="['h-4 w-4', pm.color]"
                            />
                            <span>{{ pm.label }}</span>
                        </button>
                    </div>

                    <!-- Tender Received (if not split) -->
                    <div v-if="paymentMethod !== 'split'" class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label
                                class="text-[11px] font-black tracking-[0.14em] text-slate-500 uppercase"
                            >
                                Tender Received (PKR)
                            </Label>
                            <span class="text-xs font-bold text-slate-400">
                                Due: {{ formatCurrency(netPayable) }}
                            </span>
                        </div>

                        <div class="relative">
                            <input
                                v-model="paidInput"
                                type="number"
                                step="1"
                                placeholder="Enter cash received"
                                class="tnum w-full rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-2xl font-black text-slate-900 shadow-inner focus:border-[#003B7D] focus:bg-white focus:ring-3 focus:ring-[#003B7D]/20 focus:outline-none"
                            />
                        </div>

                        <!-- Quick Cash Note Chips -->
                        <div class="flex flex-wrap items-center gap-1.5 pt-1">
                            <button
                                type="button"
                                @click="setExactPayment"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-1 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-100 active:scale-95"
                            >
                                Exact: {{ formatCurrency(netPayable) }}
                            </button>
                            <button
                                v-for="preset in [500, 1000, 5000, 10000]"
                                :key="preset"
                                type="button"
                                @click="setTenderPreset(preset)"
                                class="rounded-xl border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-100 active:scale-95"
                            >
                                Rs. {{ preset }}
                            </button>
                        </div>
                    </div>

                    <!-- Split Payment Form -->
                    <div
                        v-else
                        class="space-y-2 rounded-2xl border border-slate-200 bg-slate-50 p-3"
                    >
                        <div
                            class="text-xs font-black tracking-wide text-slate-700 uppercase"
                        >
                            Split Payment Amounts:
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="font-bold text-slate-600"
                                    >Cash:</span
                                >
                                <input
                                    v-model.number="splitAmounts.cash"
                                    type="number"
                                    class="tnum mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 font-bold"
                                />
                            </div>
                            <div>
                                <span class="font-bold text-slate-600"
                                    >JazzCash:</span
                                >
                                <input
                                    v-model.number="splitAmounts.jazzcash"
                                    type="number"
                                    class="tnum mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 font-bold"
                                />
                            </div>
                            <div>
                                <span class="font-bold text-slate-600"
                                    >Easypaisa:</span
                                >
                                <input
                                    v-model.number="splitAmounts.easypaisa"
                                    type="number"
                                    class="tnum mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 font-bold"
                                />
                            </div>
                            <div>
                                <span class="font-bold text-slate-600"
                                    >Bank:</span
                                >
                                <input
                                    v-model.number="splitAmounts.bank"
                                    type="number"
                                    class="tnum mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 font-bold"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Change to Return / Khata Balance Summary Card -->
                    <div
                        v-if="paymentMethod !== 'udhaar'"
                        class="flex items-center justify-between rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-950"
                    >
                        <div>
                            <div
                                class="text-[10px] font-bold tracking-wider text-emerald-700 uppercase"
                            >
                                Change to Return
                            </div>
                            <div class="text-xs text-emerald-800">
                                Give customer back from cash drawer
                            </div>
                        </div>
                        <span class="tnum text-2xl font-black text-emerald-700">
                            {{ formatCurrency(changeToReturn) }}
                        </span>
                    </div>

                    <div
                        v-else
                        class="flex items-center justify-between rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-amber-950"
                    >
                        <div>
                            <div
                                class="text-[10px] font-bold tracking-wider text-amber-700 uppercase"
                            >
                                Added to Customer Khata
                            </div>
                            <div class="text-xs text-amber-800">
                                Customer:
                                {{ selectedCustomer?.name || 'Walk-in' }}
                            </div>
                        </div>
                        <span class="tnum text-2xl font-black text-amber-700">
                            {{ formatCurrency(remainingKhataBalance) }}
                        </span>
                    </div>
                </div>

                <DialogFooter class="flex items-center justify-between pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isPaymentModalOpen = false"
                        class="rounded-xl"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        @click="submitCheckout"
                        class="rounded-xl bg-[#003B7D] px-5 text-white shadow-md hover:bg-[#002b5c]"
                    >
                        <Printer class="mr-1.5 h-4 w-4" />
                        Complete Sale & Print
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Boson Studio Feature: Held Sales Dialog -->
        <Dialog v-model:open="isHeldSalesModalOpen">
            <DialogContent
                class="max-w-md rounded-3xl border border-white/80 bg-white/95 p-6 shadow-2xl backdrop-blur-2xl"
            >
                <DialogHeader>
                    <DialogTitle
                        class="flex items-center gap-2 text-lg font-black text-slate-900"
                    >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-500/15 text-amber-700"
                        >
                            <History class="h-4 w-4" />
                        </div>
                        <span>Held / Suspended Sales</span>
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Recall previously suspended carts to continue customer
                        billing:
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-80 space-y-2 overflow-y-auto py-2">
                    <div
                        v-if="heldSales.length === 0"
                        class="py-8 text-center text-xs text-slate-400"
                    >
                        No sales currently on hold.
                    </div>
                    <div
                        v-for="(held, idx) in heldSales"
                        :key="held.id"
                        class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50/80 p-3 transition hover:bg-slate-100"
                    >
                        <div>
                            <div class="text-xs font-bold text-slate-900">
                                {{ held.customer_name }}
                            </div>
                            <div class="text-[10px] text-slate-400">
                                {{ held.cart.length }} items • Held at
                                {{ held.held_at }}
                            </div>
                            <div
                                class="tnum mt-0.5 text-xs font-black text-[#003B7D]"
                            >
                                {{ formatCurrency(held.total) }}
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                @click="recallHeldSale(idx)"
                                class="inline-flex items-center gap-1 rounded-xl bg-[#003B7D] px-2.5 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-[#002b5c]"
                            >
                                <Play class="h-3 w-3" />
                                <span>Recall</span>
                            </button>
                            <button
                                type="button"
                                @click="removeHeldSale(idx)"
                                class="rounded-xl p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                title="Delete held cart"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                <DialogFooter class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isHeldSalesModalOpen = false"
                        class="w-full rounded-xl"
                    >
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Method Selection Prompt Modal (pops up if user proceeds to payment without selecting method) -->
        <Dialog v-model:open="isMethodSelectionPromptOpen">
            <DialogContent
                class="max-w-md rounded-3xl border border-white/80 bg-white/95 p-6 shadow-2xl backdrop-blur-2xl"
            >
                <DialogHeader>
                    <DialogTitle
                        class="flex items-center gap-2.5 text-lg font-black text-slate-900"
                    >
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600"
                        >
                            <CreditCard class="h-5 w-5" />
                        </div>
                        <span>Payment Method Select Karein</span>
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Proceed karne se pehle payment method select karein:
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-2 gap-2.5 py-4">
                    <button
                        v-for="pm in paymentMethodsList"
                        :key="pm.id"
                        type="button"
                        @click="selectMethodAndProceed(pm.id)"
                        class="group flex flex-col items-center justify-center gap-2 rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 transition-all hover:-translate-y-0.5 hover:border-[#003B7D] hover:bg-blue-50/50 hover:shadow-md active:scale-95"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white shadow-2xs transition-transform group-hover:scale-110"
                        >
                            <component
                                :is="pm.icon"
                                :class="['h-5 w-5', pm.color]"
                            />
                        </div>
                        <div class="text-center">
                            <div
                                class="text-xs font-black text-slate-800 group-hover:text-[#003B7D]"
                            >
                                {{ pm.label }}
                            </div>
                            <div class="text-[10px] text-slate-400">
                                {{
                                    pm.id === 'cash'
                                        ? 'Cash payment'
                                        : pm.id === 'udhaar'
                                          ? 'Customer ledger'
                                          : 'Digital transfer'
                                }}
                            </div>
                        </div>
                    </button>
                </div>

                <DialogFooter class="pt-1">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isMethodSelectionPromptOpen = false"
                        class="w-full rounded-xl"
                    >
                        Cancel
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Register Customer Modal -->
        <Dialog v-model:open="isCustomerModalOpen">
            <DialogContent
                class="max-w-md rounded-3xl border border-white/80 bg-white/95 p-6 shadow-2xl backdrop-blur-2xl"
            >
                <DialogHeader>
                    <DialogTitle class="text-lg font-black text-slate-900">
                        Register Customer
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Create a quick customer profile to link sales and
                        maintain Khata credit ledger.
                    </DialogDescription>
                </DialogHeader>

                <form
                    @submit.prevent="submitCustomerForm"
                    class="space-y-3 py-2"
                >
                    <div class="space-y-1.5">
                        <Label
                            for="cust_name"
                            class="text-[11px] font-bold tracking-[0.12em] text-slate-500 uppercase"
                        >
                            Customer Name
                        </Label>
                        <Input
                            id="cust_name"
                            v-model="customerForm.name"
                            placeholder="Full Name"
                            class="rounded-xl"
                            required
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label
                            for="cust_phone"
                            class="text-[11px] font-bold tracking-[0.12em] text-slate-500 uppercase"
                        >
                            Mobile Number
                        </Label>
                        <Input
                            id="cust_phone"
                            v-model="customerForm.phone"
                            placeholder="03001234567"
                            class="rounded-xl"
                            required
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label
                            for="cust_address"
                            class="text-[11px] font-bold tracking-[0.12em] text-slate-500 uppercase"
                        >
                            Address
                        </Label>
                        <Input
                            id="cust_address"
                            v-model="customerForm.address"
                            placeholder="City / Area"
                            class="rounded-xl"
                        />
                    </div>

                    <DialogFooter class="pt-3">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isCustomerModalOpen = false"
                            class="rounded-xl"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="customerForm.processing"
                            class="rounded-xl bg-[#003B7D] text-white shadow-sm hover:bg-[#002b5c]"
                        >
                            Save Customer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Thermal Receipt Print Modal -->
        <Dialog v-model:open="isReceiptModalOpen">
            <DialogContent
                class="max-w-sm rounded-3xl border border-slate-200/80 bg-white p-5 shadow-2xl"
            >
                <DialogHeader class="no-print">
                    <DialogTitle
                        class="text-center text-sm font-black text-slate-900"
                    >
                        Thermal Invoice Receipt
                    </DialogTitle>
                </DialogHeader>

                <div
                    id="thermal-receipt"
                    class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-3.5 font-mono text-[11px] leading-tight text-black"
                >
                    <div
                        class="border-b border-dashed border-slate-300 pb-2 text-center"
                    >
                        <div class="text-sm font-black uppercase">
                            {{ shopInfo.name }}
                        </div>
                        <div class="text-[10px] text-slate-600">
                            {{ shopInfo.address }}
                        </div>
                        <div class="text-[10px] text-slate-600">
                            Ph: {{ shopInfo.phone }}
                        </div>
                    </div>

                    <div
                        class="space-y-0.5 border-b border-dashed border-slate-300 pb-2 text-[10px]"
                    >
                        <div class="flex justify-between">
                            <span>Invoice #:</span>
                            <span class="font-bold">{{
                                activeReceipt?.invoice_no
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Date:</span>
                            <span>{{
                                activeReceipt?.created_at
                                    ? new Date(
                                          activeReceipt.created_at,
                                      ).toLocaleString('en-PK')
                                    : ''
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Cashier:</span>
                            <span>{{
                                activeReceipt?.cashier?.name || 'Admin'
                            }}</span>
                        </div>
                        <div
                            v-if="activeReceipt?.customer"
                            class="flex justify-between font-bold"
                        >
                            <span>Customer:</span>
                            <span>{{ activeReceipt.customer.name }}</span>
                        </div>
                    </div>

                    <div
                        class="space-y-1.5 border-b border-dashed border-slate-300 pb-2"
                    >
                        <div
                            class="flex justify-between text-[10px] font-black uppercase"
                        >
                            <span>ITEM</span>
                            <span>AMOUNT</span>
                        </div>
                        <div
                            v-for="item in activeReceipt?.items"
                            :key="item.id"
                            class="space-y-0.5 border-t border-slate-200 pt-1"
                        >
                            <div class="text-[11px] font-bold">
                                {{ item.product.brand }} {{ item.product.name }}
                            </div>
                            <div class="flex justify-between text-[10px]">
                                <span>
                                    {{ item.quantity }} x
                                    {{ formatCurrency(item.unit_price) }}
                                </span>
                                <span class="font-bold">
                                    {{ formatCurrency(item.line_total) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="space-y-1 border-b border-dashed border-slate-300 pb-2 text-[11px]"
                    >
                        <div class="flex justify-between">
                            <span>Subtotal:</span>
                            <span>{{
                                formatCurrency(activeReceipt?.total_amount || 0)
                            }}</span>
                        </div>
                        <div
                            v-if="Number(activeReceipt?.discount_amount) > 0"
                            class="flex justify-between"
                        >
                            <span>Discount:</span>
                            <span
                                >-{{
                                    formatCurrency(
                                        activeReceipt?.discount_amount || 0,
                                    )
                                }}</span
                            >
                        </div>
                        <div class="flex justify-between text-xs font-black">
                            <span>Net Total:</span>
                            <span>{{
                                formatCurrency(activeReceipt?.net_amount || 0)
                            }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span>Paid:</span>
                            <span>{{
                                formatCurrency(activeReceipt?.paid_amount || 0)
                            }}</span>
                        </div>
                        <div
                            v-if="Number(activeReceipt?.change_amount) > 0"
                            class="flex justify-between text-[10px] font-bold text-emerald-700"
                        >
                            <span>Change Return:</span>
                            <span>{{
                                formatCurrency(
                                    activeReceipt?.change_amount || 0,
                                )
                            }}</span>
                        </div>
                    </div>

                    <div
                        class="space-y-1 pt-1 text-center text-[9px] text-slate-600"
                    >
                        <div class="font-bold uppercase">
                            {{ shopInfo.return_policy }}
                        </div>
                        <div>*** Thank You For Shopping ***</div>
                    </div>
                </div>

                <DialogFooter class="no-print flex justify-between pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isReceiptModalOpen = false"
                        class="rounded-xl"
                    >
                        Close
                    </Button>
                    <Button
                        type="button"
                        @click="printReceipt"
                        class="rounded-xl bg-[#003B7D] text-white shadow-sm hover:bg-[#002b5c]"
                    >
                        <Printer class="mr-1.5 h-4 w-4" /> Print Receipt
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Toaster />
        <ConfirmDialog />
    </div>
</template>
