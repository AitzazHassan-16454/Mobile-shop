<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Check,
    CheckCircle,
    CreditCard,
    DollarSign,
    Layers,
    Plus,
    Printer,
    QrCode,
    Receipt,
    RotateCcw,
    Search,
    ShoppingCart,
    Smartphone,
    Tag,
    Trash2,
    UserCheck,
    UserPlus,
    Wallet,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
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
import pos from '@/routes/pos';
import type { Team } from '@/types';

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
const activeReceipt = ref<CompletedSale | null>(props.latestSale || null);

// Payment Tender state
const paymentMethod = ref<string>('cash');
const paidInput = ref<number | string>('');

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
                addImeiToCart(p, matchedImei);
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
        addAccessoryToCart(matchedBarcodeProduct);
        searchScanQuery.value = '';
        return;
    }

    const matches = filteredCatalogProducts.value;
    if (matches.length === 1 && !matches[0].is_serialized) {
        addAccessoryToCart(matches[0]);
        searchScanQuery.value = '';
    }
};

const addImeiToCart = (product: ProductItem, imei: ProductImeiItem) => {
    const key = `imei-${imei.id}`;
    if (cart.value.some((item) => item.key === key)) {
        alert(`IMEI "${imei.imei_1}" is already in the cart.`);
        return;
    }

    cart.value.push({
        key,
        product_id: product.id,
        product_imei_id: imei.id,
        name: product.name,
        brand: product.brand,
        is_serialized: true,
        imei_1: imei.imei_1,
        imei_2: imei.imei_2,
        color: imei.color,
        storage: imei.storage,
        condition: imei.condition,
        pta_status: imei.pta_status,
        quantity: 1,
        unit_price: Number(product.sale_price),
    });
};

const addAccessoryToCart = (product: ProductItem) => {
    const key = `prod-${product.id}`;
    const existing = cart.value.find((item) => item.key === key);

    if (existing) {
        if (
            product.stock_quantity > 0 &&
            existing.quantity >= product.stock_quantity
        ) {
            alert(
                `Cannot add more than available stock (${product.stock_quantity}).`,
            );
            return;
        }
        existing.quantity += 1;
    } else {
        if (product.stock_quantity <= 0) {
            alert(`"${product.name}" is out of stock.`);
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
    }
};

const removeCartItem = (index: number) => {
    cart.value.splice(index, 1);
};

const clearCart = () => {
    if (
        cart.value.length === 0 ||
        confirm('Clear all items from current cart?')
    ) {
        cart.value = [];
        discountInput.value = 0;
        paidInput.value = '';
    }
};

const openPaymentModal = () => {
    if (cart.value.length === 0) return;
    paymentMethod.value = 'cash';
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
        alert('Please select a customer for Udhaar (Khata) checkout.');
        return;
    }

    const payload = {
        customer_id:
            selectedCustomerId.value === 'walk_in'
                ? null
                : Number(selectedCustomerId.value),
        payment_method: paymentMethod.value,
        discount_amount: discountAmount.value,
        paid_amount: Number(paidInput.value) || 0,
        payment_details:
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
        },
    });
};

const handleGlobalKeydown = (e: KeyboardEvent) => {
    if (e.key === 'F1') {
        e.preventDefault();
        clearCart();
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
            openPaymentModal();
        }
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
    searchInputRef.value?.focus();
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
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
    <Head title="Faizan Mobile POS" />

    <div class="flex h-[calc(100vh-4rem)] flex-col overflow-hidden bg-gray-100">
        <header
            class="bg-card flex h-14 shrink-0 items-center justify-between border-b border-gray-200 px-4 py-2 shadow-sm"
        >
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <span
                        class="inline-block h-2.5 w-2.5 animate-pulse rounded-full bg-[#003b7d]"
                    ></span>
                    <span
                        class="text-sm font-black tracking-tight text-gray-900"
                        >Faizan Mobile POS</span
                    >
                    <span
                        class="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold text-violet-600 ring-1 ring-violet-200 ring-inset"
                        >T-01</span
                    >
                </div>

                <div
                    class="hidden items-center gap-2 text-[10px] text-slate-500 lg:flex"
                >
                    <span
                        class="font-bold tracking-[0.16em] text-slate-500 uppercase"
                        >Hotkeys</span
                    >
                    <span
                        class="rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 font-mono font-bold text-slate-600"
                        >F1 New</span
                    >
                    <span
                        class="rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 font-mono font-bold text-slate-600"
                        >F2 Scan</span
                    >
                    <span
                        class="rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 font-mono font-bold text-slate-600"
                        >F3 Khata</span
                    >
                    <span
                        class="rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 font-mono font-bold text-slate-600"
                        >F4 Disc</span
                    >
                    <span
                        class="rounded bg-violet-100 px-1.5 py-0.5 font-mono font-bold text-violet-600 ring-1 ring-violet-200 ring-inset"
                        >Ctrl+Enter</span
                    >
                </div>
            </div>

            <button
                @click="clearCart"
                class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-600 transition hover:bg-rose-100"
            >
                <RotateCcw class="h-3.5 w-3.5" />
                Clear Cart
            </button>
        </header>

        <div class="grid flex-1 grid-cols-1 overflow-hidden lg:grid-cols-12">
            <div
                class="flex flex-col overflow-hidden border-r border-gray-200 bg-gray-50 lg:col-span-7"
            >
                <div class="space-y-3 border-b border-gray-200 bg-gray-50 p-3">
                    <div class="relative">
                        <Search
                            class="absolute top-2.5 left-3 h-4 w-4 text-slate-500"
                        />
                        <input
                            ref="searchInputRef"
                            v-model="searchScanQuery"
                            @keydown.enter.prevent="handleScanSubmit"
                            type="text"
                            placeholder="Scan IMEI, accessory barcode or product name..."
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pr-4 pl-9 text-xs font-medium text-gray-900 placeholder:text-slate-500 focus:border-violet-500/70 focus:ring-2 focus:ring-violet-200 focus:outline-none"
                        />
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <div class="flex flex-1 items-center gap-2">
                            <span class="eyebrow text-slate-500">Customer</span>
                            <Select v-model="selectedCustomerId" class="flex-1">
                                <SelectTrigger
                                    class="h-9 border-gray-200 bg-gray-50 text-xs text-slate-600"
                                >
                                    <SelectValue
                                        placeholder="Walk-in customer"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="walk_in"
                                        >Walk-in Customer (Cash)</SelectItem
                                    >
                                    <SelectItem
                                        v-for="c in customers"
                                        :key="c.id"
                                        :value="String(c.id)"
                                    >
                                        {{ c.name }} ({{ c.phone }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <button
                            @click="isCustomerModalOpen = true"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-[11px] font-bold text-slate-600 transition hover:bg-gray-100"
                        >
                            <UserPlus class="h-3.5 w-3.5 text-violet-600" />
                            Add
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-2">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead
                            class="sticky top-0 z-10 border-b border-gray-200 bg-gray-100 text-[10px] font-bold tracking-[0.12em] text-slate-500 uppercase"
                        >
                            <tr>
                                <th class="px-3 py-2">Item</th>
                                <th class="w-24 px-3 py-2 text-right">Price</th>
                                <th class="w-20 px-3 py-2 text-center">Qty</th>
                                <th class="w-28 px-3 py-2 text-right">Total</th>
                                <th class="w-10 px-3 py-2 text-center"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-if="cart.length === 0">
                                <td
                                    colspan="5"
                                    class="py-16 text-center text-slate-500"
                                >
                                    <ShoppingCart
                                        class="mx-auto mb-3 h-10 w-10 text-slate-600"
                                    />
                                    <div
                                        class="text-sm font-black text-slate-600"
                                    >
                                        Cart is empty
                                    </div>
                                    <div class="mt-1 text-xs">
                                        Scan an IMEI or add products to begin
                                        checkout.
                                    </div>
                                </td>
                            </tr>

                            <tr
                                v-for="(item, idx) in cart"
                                :key="item.key"
                                class="hover:bg-gray-50"
                            >
                                <td class="px-3 py-2.5">
                                    <div
                                        class="text-sm font-black text-gray-900"
                                    >
                                        {{ item.name }}
                                    </div>
                                    <div
                                        v-if="item.is_serialized"
                                        class="mt-1 space-y-0.5"
                                    >
                                        <div
                                            class="tnum text-[11px] font-bold text-violet-600"
                                        >
                                            IMEI 1: {{ item.imei_1 }}
                                        </div>
                                        <div
                                            v-if="item.imei_2"
                                            class="tnum text-[10px] text-slate-500"
                                        >
                                            IMEI 2: {{ item.imei_2 }}
                                        </div>
                                        <div
                                            class="flex items-center gap-1.5 text-[10px] text-slate-500"
                                        >
                                            <span
                                                class="font-semibold text-slate-600"
                                                >{{
                                                    item.storage || 'Standard'
                                                }}</span
                                            >
                                            <span>•</span>
                                            <span>{{
                                                item.color || 'Standard'
                                            }}</span>
                                            <span>•</span>
                                            <span
                                                class="font-bold text-violet-600 uppercase"
                                                >{{ item.pta_status }}</span
                                            >
                                        </div>
                                    </div>
                                    <div
                                        v-else
                                        class="text-[10px] text-slate-500"
                                    >
                                        Accessory item
                                    </div>
                                </td>

                                <td class="px-3 py-2.5 text-right">
                                    <input
                                        v-model.number="item.unit_price"
                                        type="number"
                                        step="0.01"
                                        class="tnum w-20 rounded border border-gray-200 bg-gray-50 px-2 py-1 text-right text-xs font-bold text-gray-900 outline-none focus:border-violet-500/70"
                                    />
                                </td>

                                <td class="px-3 py-2.5 text-center">
                                    <span
                                        v-if="item.is_serialized"
                                        class="tnum text-xs font-bold text-slate-600"
                                        >1</span
                                    >
                                    <input
                                        v-else
                                        v-model.number="item.quantity"
                                        type="number"
                                        min="1"
                                        :max="item.max_stock"
                                        class="tnum w-14 rounded border border-gray-200 bg-gray-50 px-2 py-1 text-center text-xs font-bold text-gray-900 outline-none focus:border-violet-500/70"
                                    />
                                </td>

                                <td
                                    class="tnum px-3 py-2.5 text-right text-sm font-black text-gray-900"
                                >
                                    {{
                                        formatCurrency(
                                            item.quantity * item.unit_price,
                                        )
                                    }}
                                </td>

                                <td class="px-3 py-2.5 text-center">
                                    <button
                                        @click="removeCartItem(idx)"
                                        class="text-slate-500 transition hover:text-rose-600"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                class="flex flex-col overflow-hidden bg-transparent lg:col-span-5"
            >
                <div class="pos-readout m-3 rounded-3xl p-5">
                    <div
                        class="flex items-center justify-between text-xs text-slate-600"
                    >
                        <span class="eyebrow text-slate-600"
                            >Total Net Payable</span
                        >
                        <span
                            class="rounded-full bg-gray-100 px-2 py-1 font-mono text-[10px] font-bold text-gray-900"
                            >{{ cart.length }} items</span
                        >
                    </div>

                    <div class="mt-4 flex items-baseline justify-between">
                        <span class="text-lg font-bold text-slate-600"
                            >PKR</span
                        >
                        <span
                            class="text-gradient-brand tnum text-5xl font-black tracking-tight"
                            >{{
                                formatCurrency(netPayable)
                                    .replace('PKR', '')
                                    .trim()
                            }}</span
                        >
                    </div>

                    <div
                        class="mt-5 flex items-center justify-between border-t border-gray-200 pt-3 text-xs"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-slate-600">Discount</span>
                            <input
                                id="discount-input"
                                v-model="discountInput"
                                type="number"
                                placeholder="0"
                                class="tnum w-24 rounded-lg border border-gray-200 bg-gray-100 px-2 py-1 text-right text-xs font-bold text-gray-900 placeholder:text-slate-500"
                            />
                        </div>

                        <button
                            @click="openPaymentModal"
                            :disabled="cart.length === 0"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#003b7d] px-5 py-2.5 text-sm font-black text-white shadow-sm transition hover:bg-[#0f4c81] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <CreditCard class="h-4 w-4" />
                            Checkout
                        </button>
                    </div>
                </div>

                <div class="border-b border-gray-200 bg-gray-50 px-3 pt-1 pb-2">
                    <div class="flex items-center gap-1.5 overflow-x-auto">
                        <button
                            v-for="cat in categories"
                            :key="cat"
                            @click="activeCategoryTab = cat"
                            :class="[
                                activeCategoryTab === cat
                                    ? 'bg-[#003b7d] text-white'
                                    : 'bg-gray-50 text-slate-600 hover:bg-gray-100',
                                'rounded-full px-3 py-1.5 text-[10px] font-bold whitespace-nowrap capitalize transition',
                            ]"
                        >
                            {{ cat }}
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-3">
                    <div class="grid grid-cols-2 gap-2.5">
                        <div
                            v-for="p in filteredCatalogProducts"
                            :key="p.id"
                            class="bg-card flex cursor-pointer flex-col justify-between rounded-2xl border border-gray-200 p-3 shadow-sm transition hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-[0_16px_40px_rgba(139,92,246,0.12)]"
                            @click="
                                p.is_serialized ? null : addAccessoryToCart(p)
                            "
                        >
                            <div>
                                <div
                                    class="mb-2 flex items-center justify-between text-[10px] font-bold uppercase"
                                >
                                    <span class="text-violet-600">{{
                                        p.brand
                                    }}</span>
                                    <span
                                        :class="[
                                            p.is_serialized
                                                ? 'bg-violet-100 text-violet-600'
                                                : 'bg-sky-100 text-sky-600',
                                            'rounded-full px-1.5 py-0.5',
                                        ]"
                                    >
                                        {{
                                            p.is_serialized
                                                ? 'Handset'
                                                : 'Accessory'
                                        }}
                                    </span>
                                </div>
                                <h3
                                    class="line-clamp-1 text-xs font-black text-gray-900"
                                >
                                    {{ p.name }}
                                </h3>
                                <div
                                    class="tnum mt-2 text-sm font-black text-gray-900"
                                >
                                    {{ formatCurrency(p.sale_price) }}
                                </div>
                            </div>

                            <div
                                v-if="p.is_serialized"
                                class="mt-3 space-y-1.5"
                            >
                                <div
                                    class="text-[9px] font-bold tracking-[0.12em] text-slate-500 uppercase"
                                >
                                    Select IMEI
                                </div>
                                <div
                                    v-if="
                                        !p.in_stock_imeis ||
                                        p.in_stock_imeis.length === 0
                                    "
                                    class="text-[10px] text-rose-600 italic"
                                >
                                    Out of stock
                                </div>
                                <div
                                    v-else
                                    class="max-h-24 space-y-1 overflow-y-auto"
                                >
                                    <button
                                        v-for="imei in p.in_stock_imeis"
                                        :key="imei.id"
                                        @click.stop="addImeiToCart(p, imei)"
                                        class="flex w-full items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-2 py-1 text-left transition hover:bg-violet-50"
                                    >
                                        <span
                                            class="tnum text-[10px] font-bold text-slate-600"
                                            >{{ imei.imei_1 }}</span
                                        >
                                        <span
                                            class="text-[8px] font-bold text-violet-600 uppercase"
                                            >{{ imei.pta_status }}</span
                                        >
                                    </button>
                                </div>
                            </div>
                            <div
                                v-else
                                class="mt-3 flex items-center justify-between text-[10px] text-slate-500"
                            >
                                <span
                                    >Stock:
                                    <strong class="tnum text-gray-900">{{
                                        p.stock_quantity
                                    }}</strong></span
                                >
                                <span class="font-bold text-violet-600"
                                    >+ Add</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:open="isPaymentModalOpen">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle class="text-lg font-black text-gray-900"
                        >Complete Checkout</DialogTitle
                    >
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="method in [
                                'cash',
                                'jazzcash',
                                'easypaisa',
                                'bank',
                                'udhaar',
                                'split',
                            ]"
                            :key="method"
                            type="button"
                            @click="paymentMethod = method"
                            :class="[
                                paymentMethod === method
                                    ? 'bg-[#003b7d] text-white'
                                    : 'bg-gray-50 text-slate-600 hover:bg-gray-100',
                                'rounded-xl px-3 py-2 text-[11px] font-bold capitalize transition',
                            ]"
                        >
                            {{ method }}
                        </button>
                    </div>

                    <div v-if="paymentMethod !== 'split'" class="space-y-1.5">
                        <Label
                            class="text-[11px] font-bold tracking-[0.12em] text-slate-500 uppercase"
                            >Tender Received (PKR)</Label
                        >
                        <input
                            v-model="paidInput"
                            type="number"
                            step="0.01"
                            placeholder="Enter received amount"
                            class="tnum w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-lg font-black text-gray-900 outline-none focus:border-violet-500/70"
                        />
                        <div class="flex items-center gap-1.5 pt-1">
                            <button
                                type="button"
                                @click="setExactPayment"
                                class="rounded-md bg-gray-50 px-2 py-1 text-[10px] font-bold text-slate-600 transition hover:bg-gray-100"
                            >
                                Exact
                            </button>
                            <button
                                type="button"
                                @click="setTenderPreset(1000)"
                                class="rounded-md bg-gray-50 px-2 py-1 text-[10px] font-bold text-slate-600 transition hover:bg-gray-100"
                            >
                                1000
                            </button>
                            <button
                                type="button"
                                @click="setTenderPreset(5000)"
                                class="rounded-md bg-gray-50 px-2 py-1 text-[10px] font-bold text-slate-600 transition hover:bg-gray-100"
                            >
                                5000
                            </button>
                            <button
                                type="button"
                                @click="setTenderPreset(10000)"
                                class="rounded-md bg-gray-50 px-2 py-1 text-[10px] font-bold text-slate-600 transition hover:bg-gray-100"
                            >
                                10000
                            </button>
                        </div>
                    </div>

                    <div
                        class="space-y-2 rounded-2xl border border-gray-200 bg-gray-50 p-4 text-xs"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Net Payable</span>
                            <span class="tnum font-black text-gray-900">{{
                                formatCurrency(netPayable)
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Tender Received</span>
                            <span class="tnum font-black text-violet-600">{{
                                formatCurrency(paidInput)
                            }}</span>
                        </div>
                        <div
                            v-if="paymentMethod !== 'udhaar'"
                            class="flex items-center justify-between border-t border-gray-200 pt-2 text-sm font-black"
                        >
                            <span>Change Return</span>
                            <span class="tnum text-violet-600">{{
                                formatCurrency(changeToReturn)
                            }}</span>
                        </div>
                        <div
                            v-else
                            class="flex items-center justify-between border-t border-gray-200 pt-2 text-sm font-black text-amber-600"
                        >
                            <span>Khata Balance</span>
                            <span class="tnum">{{
                                formatCurrency(remainingKhataBalance)
                            }}</span>
                        </div>
                    </div>
                </div>

                <DialogFooter class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isPaymentModalOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        @click="submitCheckout"
                        class="bg-[#003b7d] text-white shadow-sm hover:bg-[#0f4c81]"
                    >
                        <Printer class="h-4 w-4" /> Complete & Print
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="isCustomerModalOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-lg font-black text-gray-900"
                        >Register Customer</DialogTitle
                    >
                </DialogHeader>

                <form
                    @submit.prevent="submitCustomerForm"
                    class="space-y-3 py-2"
                >
                    <div class="space-y-1.5">
                        <Label
                            for="cust_name"
                            class="text-[11px] font-bold tracking-[0.12em] text-slate-500 uppercase"
                            >Customer Name</Label
                        >
                        <Input
                            id="cust_name"
                            v-model="customerForm.name"
                            placeholder="Full Name"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label
                            for="cust_phone"
                            class="text-[11px] font-bold tracking-[0.12em] text-slate-500 uppercase"
                            >Mobile Number</Label
                        >
                        <Input
                            id="cust_phone"
                            v-model="customerForm.phone"
                            placeholder="03001234567"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label
                            for="cust_address"
                            class="text-[11px] font-bold tracking-[0.12em] text-slate-500 uppercase"
                            >Address</Label
                        >
                        <Input
                            id="cust_address"
                            v-model="customerForm.address"
                            placeholder="City / Area"
                        />
                    </div>

                    <DialogFooter class="pt-3">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isCustomerModalOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="customerForm.processing"
                            class="bg-[#003b7d] text-white shadow-sm hover:bg-[#0f4c81]"
                        >
                            Save Customer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="isReceiptModalOpen">
            <DialogContent class="max-w-sm p-4">
                <DialogHeader class="no-print">
                    <DialogTitle class="text-center text-sm font-black"
                        >Thermal Invoice</DialogTitle
                    >
                </DialogHeader>

                <div
                    id="thermal-receipt"
                    class="space-y-3 bg-white p-2 font-mono text-[11px] leading-tight text-black"
                >
                    <div class="border-b pb-2 text-center">
                        <div class="text-sm font-black uppercase">
                            {{ shopInfo.name }}
                        </div>
                        <div class="text-[10px]">{{ shopInfo.address }}</div>
                        <div class="text-[10px]">Ph: {{ shopInfo.phone }}</div>
                    </div>

                    <div class="space-y-0.5 border-b pb-2 text-[10px]">
                        <div class="flex justify-between">
                            <span>Invoice #</span
                            ><span class="font-bold">{{
                                activeReceipt?.invoice_no
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Date</span
                            ><span>{{
                                activeReceipt?.created_at
                                    ? new Date(
                                          activeReceipt.created_at,
                                      ).toLocaleString('en-PK')
                                    : ''
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Cashier</span
                            ><span>{{
                                activeReceipt?.cashier?.name || 'Admin'
                            }}</span>
                        </div>
                        <div
                            v-if="activeReceipt?.customer"
                            class="flex justify-between font-bold"
                        >
                            <span>Customer</span
                            ><span>{{ activeReceipt.customer.name }}</span>
                        </div>
                    </div>

                    <div class="space-y-1.5 border-b pb-2">
                        <div class="flex justify-between text-[10px] font-bold">
                            <span>ITEM</span><span>AMOUNT</span>
                        </div>
                        <div
                            v-for="item in activeReceipt?.items"
                            :key="item.id"
                            class="space-y-0.5 border-t pt-1"
                        >
                            <div class="text-[11px] font-bold">
                                {{ item.product.brand }} {{ item.product.name }}
                            </div>
                            <div
                                v-if="item.product_imei"
                                class="text-[11px] font-extrabold"
                            >
                                IMEI 1: {{ item.product_imei.imei_1 }}
                                <div v-if="item.product_imei.imei_2">
                                    IMEI 2: {{ item.product_imei.imei_2 }}
                                </div>
                            </div>
                            <div class="flex justify-between text-[10px]">
                                <span
                                    >{{ item.quantity }} x
                                    {{ formatCurrency(item.unit_price) }}</span
                                ><span class="font-bold">{{
                                    formatCurrency(item.line_total)
                                }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1 border-b pb-2 text-[11px]">
                        <div class="flex justify-between">
                            <span>Subtotal</span
                            ><span>{{
                                formatCurrency(activeReceipt?.total_amount || 0)
                            }}</span>
                        </div>
                        <div
                            v-if="Number(activeReceipt?.discount_amount) > 0"
                            class="flex justify-between"
                        >
                            <span>Discount</span
                            ><span
                                >-{{
                                    formatCurrency(
                                        activeReceipt?.discount_amount || 0,
                                    )
                                }}</span
                            >
                        </div>
                        <div
                            class="flex justify-between text-xs font-extrabold"
                        >
                            <span>Net</span
                            ><span>{{
                                formatCurrency(activeReceipt?.net_amount || 0)
                            }}</span>
                        </div>
                    </div>

                    <div class="space-y-1 pt-1 text-center text-[9px]">
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
                        >Close</Button
                    >
                    <Button
                        type="button"
                        @click="printReceipt"
                        class="bg-[#003b7d] text-white shadow-sm hover:bg-[#0f4c81]"
                    >
                        <Printer class="h-4 w-4" /> Print
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
