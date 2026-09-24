<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    Barcode,
    BookOpen,
    Check,
    CheckCircle,
    ChevronDown,
    CreditCard,
    DollarSign,
    ExternalLink,
    FileText,
    Folder,
    History,
    Keyboard,
    Layers,
    LayoutDashboard,
    LogOut,
    Maximize2,
    Minimize2,
    Minus,
    Pause,
    Play,
    Plus,
    Printer,
    Receipt,
    Repeat,
    RotateCcw,
    Search,
    Settings,
    ShoppingCart,
    Smartphone,
    Sparkles,
    Tag,
    Trash2,
    User,
    UserCheck,
    UserPlus,
    Volume2,
    VolumeX,
    Wallet,
    X,
    Zap,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
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
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Toaster } from '@/components/ui/sonner';
import { useConfirm } from '@/composables/useConfirm';
import { dashboard } from '@/routes';
import pos from '@/routes/pos';
import usedPhones from '@/routes/used-phones';
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

interface TradeInItem {
    id: number;
    voucher_no: string;
    seller_name: string;
    device_model: string;
    imei_1: string;
    purchase_amount: number | string;
    created_at: string;
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
    item_discount: number;
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
    trade_in_amount?: number | string;
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
    usedPhonePurchases: TradeInItem[];
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
        'Mobile Shop POS',
);

// Search & Filter state
const searchByName = ref(true);
const searchByPhone = ref(true);
const searchBySku = ref(true);
const exactMatch = ref(false);
const selectedCategory = ref<string>('all');

// POS Settings & Sound
const isSettingsModalOpen = ref(false);
const posSettings = ref({
    soundEnabled: true,
    compactView: false,
    autoPrint: false,
    defaultPayment: 'cash',
});

const loadPosSettings = () => {
    try {
        const saved = localStorage.getItem('pos_user_settings');
        if (saved) {
            posSettings.value = { ...posSettings.value, ...JSON.parse(saved) };
        }
    } catch (e) {}
};

const savePosSettings = () => {
    try {
        localStorage.setItem('pos_user_settings', JSON.stringify(posSettings.value));
        toast.success('POS preferences saved.');
        isSettingsModalOpen.value = false;
    } catch (e) {}
};

const playAudioBeep = (type: 'scan' | 'success' | 'delete' = 'scan') => {
    if (!posSettings.value.soundEnabled) return;
    try {
        const AudioCtx = window.AudioContext || (window as any).webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);

        if (type === 'scan') {
            osc.frequency.setValueAtTime(1100, ctx.currentTime);
            gain.gain.setValueAtTime(0.12, ctx.currentTime);
            osc.start();
            osc.stop(ctx.currentTime + 0.08);
        } else if (type === 'success') {
            osc.frequency.setValueAtTime(700, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(1300, ctx.currentTime + 0.12);
            gain.gain.setValueAtTime(0.18, ctx.currentTime);
            osc.start();
            osc.stop(ctx.currentTime + 0.12);
        } else if (type === 'delete') {
            osc.frequency.setValueAtTime(350, ctx.currentTime);
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            osc.start();
            osc.stop(ctx.currentTime + 0.09);
        }
    } catch (e) {}
};

// Fullscreen
const isFullscreen = ref(false);
const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().then(() => {
            isFullscreen.value = true;
        }).catch(() => {
            toast.error('Fullscreen toggle not supported in browser.');
        });
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen().then(() => {
                isFullscreen.value = false;
            });
        }
    }
};

// POS Cart & Form State
const cart = ref<CartItem[]>([]);
const selectedCustomerId = ref<string>('walk_in');
const salesman = ref<string>('Admin');

const currentDateFormatted = ref('');
const updateCurrentTime = () => {
    const now = new Date();
    currentDateFormatted.value = now.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }) + ' • ' + now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    });
};

// Discount Mode ($ or %)
const discountMode = ref<'amount' | 'percent'>('amount');
const discountInput = ref<number | string>(0);

// Notes & Payments
const notesInput = ref<string>('');
const paymentMethod = ref<string>('cash');
const paidInput = ref<number | string>(0);
const splitAmounts = ref<Record<string, number>>({
    cash: 0,
    jazzcash: 0,
    easypaisa: 0,
    bank: 0,
    card: 0,
    udhaar: 0,
});
const splitMethods = [
    { key: 'cash', label: '💵 Cash' },
    { key: 'jazzcash', label: '📱 JazzCash' },
    { key: 'easypaisa', label: '📲 Easypaisa' },
    { key: 'bank', label: '🏛️ Bank' },
    { key: 'card', label: '💳 Card' },
    { key: 'udhaar', label: '📖 Udhaar (Khata)' },
];
const splitTotal = computed(() =>
    Object.values(splitAmounts.value).reduce(
        (sum, amount) => sum + (Number(amount) || 0),
        0,
    ),
);

// Trade-In / Exchange
const selectedTradeIn = ref<TradeInItem | null>(null);
const isTradeInModalOpen = ref(false);
const tradeInTab = ref<'apply' | 'new'>('apply');
const tradeInForm = useForm({
    seller_name: '',
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
    purchase_amount: '',
    payment_method: 'cash',
    auto_add_stock: true,
});

const appliedTradeInAmount = computed(() => Number(selectedTradeIn.value?.purchase_amount) || 0);

const selectTradeIn = (purchase: TradeInItem) => {
    selectedTradeIn.value = purchase;
    tradeInTab.value = 'apply';
    isTradeInModalOpen.value = false;
    playAudioBeep('scan');
};

const openNewTradeIn = () => {
    tradeInForm.reset();
    tradeInForm.clearErrors();
    tradeInTab.value = 'new';
};

const submitTradeIn = () => {
    tradeInForm.post(usedPhones.store(currentTeamSlug.value).url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const submittedImei = tradeInForm.imei_1;
            tradeInForm.reset();
            tradeInTab.value = 'apply';
            playAudioBeep('success');
            router.reload({
                only: ['usedPhonePurchases'],
                onSuccess: (page) => {
                    const refreshed = (page.props.usedPhonePurchases as TradeInItem[]) || props.usedPhonePurchases;
                    const created = refreshed.find((p) => p.imei_1 === submittedImei);
                    if (created) {
                        selectTradeIn(created);
                        toast.success('Trade-In Added', { description: `Rs ${Number(created.purchase_amount).toLocaleString()} credit applied to this bill.` });
                    }
                },
            });
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors).flat().join(' ') || 'Could not register the trade-in.';
            toast.error('Trade-In Failed', { description: errorMsg });
        },
    });
};

// Live API Product Search
const searchScanQuery = ref('');
const searchInputRef = ref<HTMLInputElement | null>(null);
const searchContainerRef = ref<HTMLElement | null>(null);
const searchApiResults = ref<ProductItem[]>([]);
const isSearchingApi = ref(false);
const showSearchDropdown = ref(false);
const selectedSearchIndex = ref<number>(-1);
let apiSearchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

// IMEI Selector Modal State
const isImeiSelectorOpen = ref(false);
const imeiSelectProduct = ref<ProductItem | null>(null);

// Modals
const isCustomerModalOpen = ref(false);
const isReceiptModalOpen = ref(false);
const isHeldSalesModalOpen = ref(false);
const isShortcutsModalOpen = ref(false);
const isNotesModalOpen = ref(false);
const activeReceipt = ref<CompletedSale | null>(props.latestSale || null);

// Held Sales
const heldSales = ref<HeldSale[]>([]);

const loadHeldSales = () => {
    try {
        const saved = localStorage.getItem('pos_held_sales');
        if (saved) {
            heldSales.value = JSON.parse(saved);
        }
    } catch (e) {
        heldSales.value = [];
    }
};

const saveHeldSalesToStorage = () => {
    try {
        localStorage.setItem('pos_held_sales', JSON.stringify(heldSales.value));
    } catch (e) {}
};

const holdCurrentSale = () => {
    if (cart.value.length === 0) {
        toast.error('Cart is Empty', { description: 'Search items to add before holding sale.' });
        return;
    }
    const customerName = selectedCustomer.value ? selectedCustomer.value.name : 'Walk-in Customer';
    const heldItem: HeldSale = {
        id: 'HOLD-' + Math.floor(1000 + Math.random() * 9000),
        customer_id: selectedCustomerId.value,
        customer_name: customerName,
        cart: JSON.parse(JSON.stringify(cart.value)),
        discount: discountInput.value,
        total: netPayable.value,
        held_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    };

    heldSales.value.unshift(heldItem);
    saveHeldSalesToStorage();
    playAudioBeep('success');

    cart.value = [];
    discountInput.value = 0;
    paidInput.value = 0;
    notesInput.value = '';

    toast.success('Sale Saved to Hold!', {
        description: `Ref: ${heldItem.id} (${heldItem.cart.length} items)`,
    });
};

const restoreHeldSale = (held: HeldSale, index: number) => {
    cart.value = JSON.parse(JSON.stringify(held.cart));
    discountInput.value = held.discount;
    selectedCustomerId.value = held.customer_id;
    heldSales.value.splice(index, 1);
    saveHeldSalesToStorage();
    isHeldSalesModalOpen.value = false;
    playAudioBeep('scan');
    toast.success('Held Sale Loaded into Cart!');
};

const deleteHeldSale = (index: number) => {
    heldSales.value.splice(index, 1);
    saveHeldSalesToStorage();
    playAudioBeep('delete');
    toast.info('Held sale deleted.');
};

// Form for quick customer add
const customerForm = useForm({
    name: '',
    phone: '',
    address: '',
});

const availableCategories = computed(() => {
    const set = new Set<string>();
    props.products.forEach((p) => {
        if (p.category) set.add(p.category);
    });
    return Array.from(set);
});

const formatProductName = (name: string, brand?: string) => {
    if (!name) return '';
    if (!brand) return name;
    if (name.toLowerCase().startsWith(brand.toLowerCase())) {
        return name;
    }
    return `${brand} ${name}`;
};

const performApiSearch = async (query: string) => {
    const q = query.trim();
    if (!q) {
        searchApiResults.value = [];
        showSearchDropdown.value = false;
        selectedSearchIndex.value = -1;
        return;
    }
    isSearchingApi.value = true;
    showSearchDropdown.value = true;
    selectedSearchIndex.value = -1;

    try {
        const catParam = selectedCategory.value !== 'all' ? `&category=${encodeURIComponent(selectedCategory.value)}` : '';
        const url = `/${currentTeamSlug.value}/pos/products?search=${encodeURIComponent(q)}${catParam}`;
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (res.ok) {
            const data = await res.json();
            searchApiResults.value = Array.isArray(data) ? data : [];
        }
    } catch (err) {
        console.error('POS Search Error:', err);
    } finally {
        isSearchingApi.value = false;
    }
};

watch(searchScanQuery, (newVal) => {
    if (apiSearchDebounceTimer) clearTimeout(apiSearchDebounceTimer);
    if (!newVal.trim()) {
        searchApiResults.value = [];
        showSearchDropdown.value = false;
        selectedSearchIndex.value = -1;
        return;
    }
    apiSearchDebounceTimer = setTimeout(() => {
        performApiSearch(newVal);
    }, 120);
});

const onSearchFocus = () => {
    if (searchScanQuery.value.trim() && searchApiResults.value.length > 0) {
        showSearchDropdown.value = true;
    }
};

const closeSearchDropdown = () => {
    showSearchDropdown.value = false;
    selectedSearchIndex.value = -1;
};

const clearSearchQuery = () => {
    searchScanQuery.value = '';
    searchApiResults.value = [];
    closeSearchDropdown();
    searchInputRef.value?.focus();
};

const navigateSearchResults = (direction: number) => {
    if (!showSearchDropdown.value || searchApiResults.value.length === 0) return;
    const max = searchApiResults.value.length - 1;
    let next = selectedSearchIndex.value + direction;
    if (next < 0) next = max;
    if (next > max) next = 0;
    selectedSearchIndex.value = next;

    const el = document.getElementById(`search-result-item-${next}`);
    if (el) {
        el.scrollIntoView({ block: 'nearest' });
    }
};

const handleClickOutside = (event: MouseEvent) => {
    if (
        searchContainerRef.value &&
        !searchContainerRef.value.contains(event.target as Node)
    ) {
        closeSearchDropdown();
    }
};

const getAvailableImeis = (product: ProductItem | null) => {
    if (!product) return [];
    const imeis = product.in_stock_imeis || (product as any).inStockImeis || [];
    if (!Array.isArray(imeis)) return [];
    return imeis.filter(
        (imei: ProductImeiItem) =>
            (!imei.status || imei.status === 'in_stock') &&
            !cart.value.some((item) => item.product_imei_id === imei.id),
    );
};

const confirmImeiSelection = (imei: ProductImeiItem) => {
    if (!imeiSelectProduct.value) return;
    addProductToCart(imeiSelectProduct.value, imei);
    isImeiSelectorOpen.value = false;
    imeiSelectProduct.value = null;
    clearSearchQuery();
};

const selectApiProduct = (product: ProductItem, specificImei?: ProductImeiItem) => {
    if (Boolean(product.is_serialized) && !specificImei) {
        const available = getAvailableImeis(product);
        if (available.length > 1) {
            imeiSelectProduct.value = product;
            isImeiSelectorOpen.value = true;
            closeSearchDropdown();
            return;
        } else if (available.length === 1) {
            addProductToCart(product, available[0]);
            clearSearchQuery();
            return;
        }
    }
    addProductToCart(product, specificImei);
    clearSearchQuery();
};

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
        (acc, item) => acc + (item.quantity * item.unit_price) - (item.item_discount || 0),
        0,
    );
});

const calculatedDiscountAmount = computed(() => {
    const inputVal = Number(discountInput.value) || 0;
    if (discountMode.value === 'percent') {
        return Math.min(subtotal.value, Math.round((subtotal.value * inputVal) / 100));
    }
    return Math.min(inputVal, subtotal.value);
});

const netPayable = computed(() => {
    return Math.max(0, subtotal.value - calculatedDiscountAmount.value);
});

const netPayableAfterTradeIn = computed(() => {
    return Math.max(0, netPayable.value - appliedTradeInAmount.value);
});

const effectivePaid = computed(() => {
    if (paymentMethod.value === 'split') {
        return splitTotal.value;
    }
    return Number(paidInput.value) || 0;
});

const duePayment = computed(() => {
    return Math.max(0, netPayableAfterTradeIn.value - effectivePaid.value);
});

const splitRemaining = computed(() => Math.max(0, netPayableAfterTradeIn.value - splitTotal.value));

const fillSplitRemaining = () => {
    splitAmounts.value.cash = (Number(splitAmounts.value.cash) || 0) + splitRemaining.value;
    playAudioBeep('scan');
};

watch(
    () => props.latestSale,
    (newSale) => {
        if (newSale) {
            activeReceipt.value = newSale;
            isReceiptModalOpen.value = true;
            cart.value = [];
            discountInput.value = 0;
            paidInput.value = 0;
            notesInput.value = '';
            paymentMethod.value = posSettings.value.defaultPayment || 'cash';
            splitAmounts.value = { cash: 0, jazzcash: 0, easypaisa: 0, bank: 0, card: 0, udhaar: 0 };
            selectedCustomerId.value = 'walk_in';
            selectedTradeIn.value = null;
        }
    },
    { immediate: true },
);

const handleScanSubmit = () => {
    if (
        showSearchDropdown.value &&
        selectedSearchIndex.value >= 0 &&
        searchApiResults.value[selectedSearchIndex.value]
    ) {
        selectApiProduct(searchApiResults.value[selectedSearchIndex.value]);
        return;
    }

    const q = searchScanQuery.value.trim();
    if (!q) return;

    for (const p of props.products) {
        if (p.is_serialized) {
            const imeis = p.in_stock_imeis || (p as any).inStockImeis || [];
            if (Array.isArray(imeis)) {
                const matchedImei = imeis.find(
                    (i: any) =>
                        i.imei_1.toLowerCase() === q.toLowerCase() ||
                        (i.imei_2 && i.imei_2.toLowerCase() === q.toLowerCase()),
                );
                if (matchedImei) {
                    selectApiProduct(p, matchedImei);
                    return;
                }
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
        selectApiProduct(matchedBarcodeProduct);
        return;
    }

    if (searchApiResults.value.length > 0) {
        selectApiProduct(searchApiResults.value[0]);
        return;
    }
};

const addProductToCart = (
    product: ProductItem,
    specificImei?: ProductImeiItem,
) => {
    const fullName = formatProductName(product.name, product.brand);
    const isSerialized = Boolean(product.is_serialized);

    if (isSerialized) {
        const availableImeis = getAvailableImeis(product);
        const availableImei = specificImei || availableImeis[0];

        if (!availableImei) {
            if (Number(product.stock_quantity) > 0) {
                const fallbackKey = `prod-serialized-${product.id}`;
                const existing = cart.value.find((item) => item.key === fallbackKey);
                if (existing) {
                    if (existing.quantity >= Number(product.stock_quantity)) {
                        toast.warning('Maximum Stock Limit', {
                            description: `Inventory limit reached for "${fullName}".`,
                        });
                        return;
                    }
                    existing.quantity += 1;
                } else {
                    cart.value.push({
                        key: fallbackKey,
                        product_id: product.id,
                        name: product.name,
                        brand: product.brand,
                        is_serialized: true,
                        quantity: 1,
                        unit_price: Number(product.sale_price),
                        item_discount: 0,
                        max_stock: Number(product.stock_quantity),
                    });
                }
                playAudioBeep('scan');
                toast.success('Item Added to Invoice', {
                    description: `"${fullName}" added to cart.`,
                });
                return;
            }

            toast.error('Inventory Out of Stock', {
                description: `"${fullName}" has no available units in inventory.`,
            });
            return;
        }

        const key = `imei-${availableImei.id}`;
        if (cart.value.some((item) => item.key === key)) {
            toast.warning('Serial Already Added', {
                description: `Device (IMEI: ${availableImei.imei_1}) is already in this cart.`,
            });
            return;
        }

        cart.value.push({
            key,
            product_id: product.id,
            product_imei_id: availableImei.id,
            name: product.name,
            brand: product.brand,
            is_serialized: true,
            imei_1: availableImei.imei_1,
            imei_2: availableImei.imei_2,
            color: availableImei.color,
            storage: availableImei.storage,
            condition: availableImei.condition,
            pta_status: availableImei.pta_status,
            quantity: 1,
            unit_price: Number(product.sale_price),
            item_discount: 0,
        });
        playAudioBeep('scan');
        toast.success('Device Serial Added', {
            description: `"${fullName}" (IMEI: ${availableImei.imei_1}) added to invoice.`,
        });
    } else {
        const key = `prod-${product.id}`;
        const existing = cart.value.find((item) => item.key === key);

        if (existing) {
            if (
                Number(product.stock_quantity) > 0 &&
                existing.quantity >= Number(product.stock_quantity)
            ) {
                toast.warning('Maximum Stock Limit', {
                    description: `Inventory limit reached for "${fullName}".`,
                });
                return;
            }
            existing.quantity += 1;
        } else {
            if (Number(product.stock_quantity) <= 0) {
                toast.error('Inventory Out of Stock', {
                    description: `"${fullName}" is currently out of stock.`,
                });
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
                item_discount: 0,
                max_stock: Number(product.stock_quantity),
            });
        }
        playAudioBeep('scan');
        toast.success('Item Added to Invoice', {
            description: `"${fullName}" added to cart.`,
        });
    }
};

const removeCartItem = (index: number) => {
    cart.value.splice(index, 1);
    playAudioBeep('delete');
};

const clearCart = () => {
    if (cart.value.length === 0) return;
    cart.value = [];
    discountInput.value = 0;
    paidInput.value = 0;
    notesInput.value = '';
    paymentMethod.value = posSettings.value.defaultPayment || 'cash';
    splitAmounts.value = { cash: 0, jazzcash: 0, easypaisa: 0, bank: 0, card: 0, udhaar: 0 };
    selectedCustomerId.value = 'walk_in';
    selectedTradeIn.value = null;
    playAudioBeep('delete');
    toast.info('Terminal Reset', { description: 'Cart and invoice inputs cleared.' });
};

const addQuickCashPreset = (amount: number) => {
    const current = Number(paidInput.value) || 0;
    paidInput.value = current + amount;
    playAudioBeep('scan');
};

const saveSale = () => {
    if (cart.value.length === 0) {
        toast.error('Cart is Empty', { description: 'Please add products before processing payment.' });
        return;
    }

    if (paymentMethod.value === 'udhaar' && selectedCustomerId.value === 'walk_in') {
        toast.error('Customer Account Required', {
            description: 'Please select or register a customer for credit ledger transactions.',
        });
        isCustomerModalOpen.value = true;
        return;
    }

    if (
        paymentMethod.value === 'split' &&
        selectedCustomerId.value === 'walk_in' &&
        ((Number(splitAmounts.value.udhaar) || 0) > 0 || splitTotal.value < netPayableAfterTradeIn.value)
    ) {
        toast.error('Customer Account Required', {
            description: 'Select a customer when splitting with Udhaar or paying less than the total.',
        });
        isCustomerModalOpen.value = true;
        return;
    }

    const payload: any = {
        customer_id:
            selectedCustomerId.value === 'walk_in'
                ? null
                : Number(selectedCustomerId.value),
        payment_method: paymentMethod.value,
        discount_amount: calculatedDiscountAmount.value,
        trade_in_purchase_id: selectedTradeIn.value?.id ?? null,
        items: cart.value.map((item) => ({
            product_id: item.product_id,
            product_imei_id: item.product_imei_id || null,
            quantity: item.quantity,
            unit_price: item.unit_price - (item.item_discount || 0),
        })),
    };

    if (paymentMethod.value === 'udhaar') {
        payload.paid_amount = Number(paidInput.value) || 0;
    } else if (paymentMethod.value === 'split') {
        payload.paid_amount = splitTotal.value;
        payload.payment_details = Object.fromEntries(
            Object.entries(splitAmounts.value)
                .filter(([, value]) => (Number(value) || 0) > 0)
                .map(([key, value]) => [key, Number(value) || 0]),
        );
    } else {
        payload.paid_amount = Number(paidInput.value) || netPayableAfterTradeIn.value;
    }

    router.post(pos.sales.store(currentTeamSlug.value).url, payload, {
        preserveScroll: true,
        onSuccess: (page) => {
            // Instantly decrement stock in UI props array
            cart.value.forEach((cartItem) => {
                const prod = props.products.find((p) => p.id === cartItem.product_id);
                if (prod) {
                    prod.stock_quantity = Math.max(0, prod.stock_quantity - cartItem.quantity);
                    if (prod.is_serialized && cartItem.product_imei_id) {
                        const imeis = prod.in_stock_imeis || (prod as any).inStockImeis;
                        if (Array.isArray(imeis)) {
                            const idx = imeis.findIndex((i: any) => i.id === cartItem.product_imei_id);
                            if (idx !== -1) imeis.splice(idx, 1);
                        }
                    }
                }
            });

            playAudioBeep('success');
            toast.success('Transaction Completed', { description: 'Sale processed and invoice recorded successfully.' });
            isReceiptModalOpen.value = true;
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors).flat().join(' ') || 'Could not complete transaction.';
            toast.error('Transaction Failed', { description: errorMsg });
        },
    });
};

const submitCustomerForm = () => {
    const regPhone = customerForm.phone;
    customerForm.post(pos.customers.store(currentTeamSlug.value).url, {
        preserveScroll: true,
        onSuccess: (page) => {
            customerForm.reset();
            isCustomerModalOpen.value = false;
            playAudioBeep('success');
            toast.success('Customer Registered', { description: 'New customer account created for credit ledger.' });

            const updatedCustomers = (page.props.customers as CustomerItem[]) || props.customers;
            const newCust = updatedCustomers.find((c: CustomerItem) => c.phone === regPhone);
            if (newCust) {
                selectedCustomerId.value = String(newCust.id);
            }
        },
        onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            toast.error('Registration Failed', { description: Array.isArray(firstErr) ? firstErr[0] : String(firstErr || 'Please check customer details.') });
        },
    });
};

const addNotePreset = (preset: string) => {
    if (notesInput.value) {
        notesInput.value += ` | ${preset}`;
    } else {
        notesInput.value = preset;
    }
};

const handleGlobalKeydown = (e: KeyboardEvent) => {
    if (e.key === 'F1') {
        e.preventDefault();
        clearCart();
    } else if (e.key === 'F2') {
        e.preventDefault();
        searchInputRef.value?.focus();
        showSearchDropdown.value = true;
    } else if (e.key === 'F3') {
        e.preventDefault();
        document.getElementById('customer-select-trigger')?.focus();
    } else if (e.key === 'F4') {
        e.preventDefault();
        document.getElementById('discount-input-field')?.focus();
    } else if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        saveSale();
    } else if (e.ctrlKey && (e.key === 'f' || e.key === 'F')) {
        e.preventDefault();
        searchInputRef.value?.focus();
    } else if (e.altKey && (e.key === 'p' || e.key === 'P')) {
        e.preventDefault();
        document.getElementById('total-payment-input')?.focus();
    } else if (e.altKey && (e.key === 'h' || e.key === 'H')) {
        e.preventDefault();
        holdCurrentSale();
    }
};

let timerInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    updateCurrentTime();
    loadHeldSales();
    loadPosSettings();
    timerInterval = setInterval(updateCurrentTime, 1000);
    window.addEventListener('keydown', handleGlobalKeydown);
    document.addEventListener('mousedown', handleClickOutside);
    searchInputRef.value?.focus();
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
    window.removeEventListener('keydown', handleGlobalKeydown);
    document.removeEventListener('mousedown', handleClickOutside);
});

const printReceipt = () => {
    window.print();
};
</script>

<template>
    <Head :title="`${currentShopName} - POS Billing`" />

    <div class="flex h-screen w-screen flex-col overflow-hidden bg-slate-950 text-slate-100 antialiased select-none font-sans">
        <!-- TOP BRAND NAVBAR -->
        <header class="flex h-13 shrink-0 items-center justify-between bg-slate-900 px-4 text-white shadow-md border-b border-slate-800/90 z-20">
            <!-- Left: Dashboard Navigation -->
            <div class="flex items-center">
                <Link
                    :href="dashboardUrl"
                    class="group flex items-center gap-2 rounded-lg border border-slate-700/60 bg-slate-800/80 px-3 py-1.5 text-xs font-semibold text-slate-200 shadow-xs transition-all hover:bg-indigo-600 hover:text-white hover:border-indigo-500 active:scale-95"
                    title="Return to Dashboard"
                >
                    <LayoutDashboard class="h-4 w-4 text-indigo-400 group-hover:text-white transition-colors" />
                    <span>Dashboard</span>
                </Link>
            </div>

            <!-- Center: Shop Brand & Admin Label -->
            <div class="flex items-center gap-2.5">
                <div class="flex h-7.5 w-7.5 items-center justify-center rounded-lg bg-gradient-to-tr from-indigo-600 to-blue-600 text-white font-black text-sm shadow-xs border border-indigo-400/30">
                    <Smartphone class="h-3.5 w-3.5" />
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-black tracking-tight text-white">{{ currentShopName }}</span>
                    <span class="text-slate-600 font-light select-none">|</span>
                    <span class="text-xs font-bold text-slate-300">Admin</span>
                </div>
            </div>

            <!-- Right: Navbar Interactive Tools -->
            <div class="flex items-center gap-1.5">
                <!-- Hold Sales Button -->
                <button
                    type="button"
                    @click="isHeldSalesModalOpen = true"
                    class="relative flex h-9 px-2.5 items-center gap-1.5 rounded-xl border border-amber-500/30 bg-amber-500/10 text-amber-300 hover:bg-amber-500/20 transition backdrop-blur-md active:scale-95 text-xs font-bold"
                    title="View Held Sales"
                >
                    <Pause class="h-3.5 w-3.5" />
                    <span class="hidden lg:inline">Hold Sales</span>
                    <span v-if="heldSales.length > 0" class="flex h-4 min-w-[16px] px-1 items-center justify-center rounded-full bg-amber-500 text-[10px] font-black text-slate-950">
                        {{ heldSales.length }}
                    </span>
                </button>

                <!-- Recent Invoice Button -->
                <button
                    type="button"
                    @click="activeReceipt ? (isReceiptModalOpen = true) : toast.info('No recent invoice in this session.')"
                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-700/60 bg-slate-800/80 text-slate-300 hover:text-white hover:bg-slate-700 transition backdrop-blur-md active:scale-95"
                    title="Print Last Invoice"
                >
                    <Printer class="h-4 w-4" />
                </button>

                <!-- Quick Notes Button -->
                <button
                    type="button"
                    @click="isNotesModalOpen = true"
                    :class="notesInput ? 'border-sky-500/50 bg-sky-500/20 text-sky-300' : 'border-slate-700/60 bg-slate-800/80 text-slate-300'"
                    class="flex h-9 w-9 items-center justify-center rounded-xl border hover:text-white hover:bg-slate-700 transition backdrop-blur-md active:scale-95"
                    title="Sale Notes & Warranty Tags"
                >
                    <FileText class="h-4 w-4" />
                </button>

                <!-- Customer Ledger / Khata Link -->
                <Link
                    :href="`/${currentTeamSlug}/customers`"
                    class="flex h-9 px-2.5 items-center gap-1.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-300 hover:bg-emerald-500/20 transition backdrop-blur-md active:scale-95 text-xs font-bold"
                    title="Open Customer Khata Ledger"
                >
                    <BookOpen class="h-3.5 w-3.5" />
                    <span class="hidden lg:inline">Khata Ledger</span>
                </Link>

                <!-- POS Settings -->
                <button
                    type="button"
                    @click="isSettingsModalOpen = true"
                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-700/60 bg-slate-800/80 text-slate-300 hover:text-white hover:bg-slate-700 transition backdrop-blur-md active:scale-95"
                    title="POS Preferences"
                >
                    <Settings class="h-4 w-4" />
                </button>

                <!-- Fullscreen Toggle -->
                <button
                    type="button"
                    @click="toggleFullscreen"
                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-700/60 bg-slate-800/80 text-slate-300 hover:text-white hover:bg-slate-700 transition backdrop-blur-md active:scale-95"
                    :title="isFullscreen ? 'Exit Fullscreen' : 'Enter Fullscreen (F11)'"
                >
                    <Minimize2 v-if="isFullscreen" class="h-4 w-4" />
                    <Maximize2 v-else class="h-4 w-4" />
                </button>

                <!-- Keyboard Shortcuts -->
                <button
                    type="button"
                    @click="isShortcutsModalOpen = true"
                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-700/60 bg-slate-800/80 text-slate-300 hover:text-white hover:bg-slate-700 transition backdrop-blur-md active:scale-95"
                    title="Shortcuts (Alt+?)"
                >
                    <Keyboard class="h-4 w-4" />
                </button>
            </div>
        </header>

        <!-- MAIN LAYOUT CONTENT -->
        <div class="flex flex-1 overflow-hidden p-3 gap-3 bg-slate-900/90 text-slate-800">
            <!-- LEFT COLUMN: Search, Filters & Interactive Cart (~68% width) -->
            <div class="flex flex-1 flex-col overflow-hidden rounded-2xl border border-slate-700/60 bg-white p-3.5 shadow-xl space-y-3">
                
                <!-- Category Filter Pills Bar -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none">
                    <button
                        type="button"
                        @click="selectedCategory = 'all'"
                        :class="selectedCategory === 'all' ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-semibold'"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-xl transition shrink-0 active:scale-95"
                    >
                        <Layers class="h-3.5 w-3.5" />
                        <span>All Items</span>
                    </button>
                    <button
                        v-for="cat in availableCategories"
                        :key="cat"
                        type="button"
                        @click="selectedCategory = cat"
                        :class="selectedCategory === cat ? 'bg-indigo-600 text-white font-extrabold shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-semibold'"
                        class="px-3 py-1.5 text-xs rounded-xl transition shrink-0 active:scale-95 capitalize"
                    >
                        {{ cat }}
                    </button>
                </div>

                <!-- Customer Selection & Options Card -->
                <div class="rounded-xl bg-slate-50 p-2.5 border border-slate-200/80 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <label class="font-extrabold text-slate-900 flex items-center gap-1.5">
                            <User class="h-3.5 w-3.5 text-indigo-600" />
                            <span>Customer (F3)</span>
                        </label>
                        <div class="flex items-center gap-2 text-[11px]">
                            <button
                                type="button"
                                @click="isCustomerModalOpen = true"
                                class="flex items-center gap-1 font-bold text-indigo-600 hover:text-indigo-800 hover:underline"
                            >
                                <UserPlus class="h-3 w-3" />
                                <span>+ Register Customer</span>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <select
                            id="customer-select-trigger"
                            v-model="selectedCustomerId"
                            class="flex-1 h-9 rounded-xl border border-slate-300 bg-white px-3 py-1 text-xs font-bold text-slate-900 shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/20 focus:outline-none transition"
                        >
                            <option value="walk_in">
                                🛒 Walk In Customer (Cash / Counter Sale)
                            </option>
                            <option v-for="c in customers" :key="c.id" :value="String(c.id)">
                                👤 {{ c.name }} ({{ c.phone }}) — Khata Due: Rs {{ Number(c.current_balance).toLocaleString() }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Live Product Search Bar -->
                <div>
                    <div class="flex items-center justify-between mb-1.5 text-xs">
                        <label class="font-extrabold text-slate-900 flex items-center gap-1.5">
                            <Barcode class="h-3.5 w-3.5 text-indigo-600" />
                            <span>Product / Barcode / IMEI Scan (F2)</span>
                        </label>
                        <span class="text-[10px] font-bold text-slate-400">Scan Barcode or Type Product Name</span>
                    </div>

                    <div class="flex items-center gap-2" ref="searchContainerRef">
                        <div class="relative flex-1">
                            <input
                                ref="searchInputRef"
                                v-model="searchScanQuery"
                                @keydown.enter.prevent="handleScanSubmit"
                                @keydown.down.prevent="navigateSearchResults(1)"
                                @keydown.up.prevent="navigateSearchResults(-1)"
                                @keydown.esc.prevent="closeSearchDropdown"
                                @focus="onSearchFocus"
                                type="text"
                                placeholder="Scan barcode, type Model Name, SKU or IMEI number..."
                                class="w-full h-10 rounded-xl border border-slate-300 bg-slate-50 px-3 pr-8 text-xs font-semibold text-slate-900 placeholder:text-slate-400 focus:border-indigo-600 focus:bg-white focus:ring-2 focus:ring-indigo-600/20 focus:outline-none transition"
                            />
                            <Search class="pointer-events-none absolute right-3 top-3 h-4 w-4 text-slate-400" />

                            <!-- Live Search API Dropdown Results -->
                            <div
                                v-if="showSearchDropdown && searchScanQuery.trim()"
                                class="absolute left-0 right-0 top-full z-50 mt-1.5 max-h-80 overflow-y-auto rounded-2xl border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-900 p-2 shadow-2xl space-y-1"
                            >
                                <div v-if="isSearchingApi" class="py-4 text-center text-xs text-slate-500 dark:text-slate-400 font-medium">
                                    Searching stock inventory...
                                </div>
                                <div v-else-if="searchApiResults.length === 0" class="py-4 text-center text-xs text-slate-400">
                                    No products matching "{{ searchScanQuery }}"
                                </div>
                                <div
                                    v-else
                                    v-for="(item, idx) in searchApiResults"
                                    :key="item.id"
                                    :id="`search-result-item-${idx}`"
                                    @click="selectApiProduct(item)"
                                    @mouseenter="selectedSearchIndex = idx"
                                    :class="[
                                        selectedSearchIndex === idx
                                            ? 'bg-[#003B7D] dark:bg-blue-600 text-white border-[#003B7D] dark:border-blue-500 shadow-md scale-[0.99]'
                                            : 'bg-slate-50/80 dark:bg-slate-800/80 border-slate-200/80 dark:border-slate-700/80 hover:bg-slate-100 dark:hover:bg-slate-700/90 text-slate-800 dark:text-slate-200',
                                        'flex cursor-pointer items-center justify-between p-2.5 text-xs rounded-xl border transition-all duration-150'
                                    ]"
                                >
                                    <div class="space-y-0.5">
                                        <div
                                            class="font-extrabold flex items-center gap-1.5"
                                            :class="selectedSearchIndex === idx ? 'text-white!' : 'text-slate-900 dark:text-white'"
                                        >
                                            <span>{{ formatProductName(item.name, item.brand) }}</span>
                                            <span
                                                v-if="item.is_serialized"
                                                :class="selectedSearchIndex === idx ? 'bg-white/20 text-white' : 'bg-indigo-100 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300'"
                                                class="text-[9px] font-black px-1.5 py-0.5 rounded"
                                            >
                                                MOBILE
                                            </span>
                                        </div>
                                        <div
                                            class="text-[10px] flex items-center gap-2"
                                            :class="selectedSearchIndex === idx ? 'text-blue-100!' : 'text-slate-500 dark:text-slate-400'"
                                        >
                                            <span>Category: {{ item.category || 'General' }}</span>
                                            <span v-if="item.barcode">Barcode: {{ item.barcode }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div
                                            class="text-sm font-black"
                                            :class="selectedSearchIndex === idx ? 'text-white!' : 'text-indigo-600 dark:text-blue-400'"
                                        >
                                            Rs {{ Number(item.sale_price).toLocaleString() }}
                                        </div>
                                        <div
                                            :class="selectedSearchIndex === idx
                                                ? 'bg-white/20 text-white border-white/30'
                                                : (item.stock_quantity > 0 ? 'text-emerald-700 bg-emerald-50 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800' : 'text-rose-700 bg-rose-50 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800')"
                                            class="text-[10px] font-bold px-2 py-0.5 rounded-full border inline-block mt-0.5"
                                        >
                                            Stock: {{ item.stock_quantity }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Manual Add Button -->
                        <button
                            type="button"
                            @click="handleScanSubmit"
                            class="flex h-10 px-3.5 items-center justify-center gap-1.5 rounded-xl bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-bold text-xs hover:from-indigo-500 hover:to-blue-500 active:scale-95 shadow-md transition"
                            title="Add Product"
                        >
                            <Plus class="h-4 w-4" />
                            <span>Add</span>
                        </button>
                    </div>
                </div>

                <!-- Modern Cart List Table -->
                <div class="flex-1 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/50">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead class="sticky top-0 bg-slate-100/95 backdrop-blur-xs text-[11px] font-black uppercase tracking-wider text-slate-700 border-b border-slate-200">
                            <tr>
                                <th class="px-3 py-3 w-10 text-center">#</th>
                                <th class="px-3 py-3">PRODUCT / DEVICE SPEC</th>
                                <th class="px-3 py-3 w-28 text-center">QTY</th>
                                <th class="px-3 py-3 w-28 text-right">UNIT PRICE</th>
                                <th class="px-3 py-3 w-24 text-right">DISCOUNT</th>
                                <th class="px-3 py-3 w-32 text-right">TOTAL</th>
                                <th class="px-3 py-3 w-12 text-center">DEL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/60 bg-white">
                            <!-- Empty Cart Placeholder -->
                            <tr v-if="cart.length === 0">
                                <td colspan="7" class="py-20 text-center">
                                    <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                                        <ShoppingCart class="h-8 w-8 text-indigo-600" />
                                    </div>
                                    <div class="text-sm font-extrabold text-slate-900">
                                        POS Terminal Ready
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
                                        Scan barcode with your scanner or type product/IMEI above to populate bill.
                                    </div>
                                </td>
                            </tr>

                            <!-- Cart Items Rows -->
                            <tr v-for="(item, idx) in cart" :key="item.key" class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-3 py-3 text-center font-bold text-slate-400">{{ idx + 1 }}</td>
                                <td class="px-3 py-3">
                                    <div class="font-extrabold text-slate-900 text-xs">
                                        {{ item.name }}
                                        <span v-if="item.brand" class="ml-1 text-[11px] font-semibold text-slate-500">({{ item.brand }})</span>
                                    </div>
                                    
                                    <!-- Mobile Serialized IMEI Details Badges -->
                                    <div v-if="item.is_serialized" class="mt-1 flex flex-wrap items-center gap-1 text-[10px]">
                                        <span v-if="item.imei_1" class="font-mono bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded border border-slate-200 font-bold">
                                            IMEI: {{ item.imei_1 }}
                                        </span>
                                        <span v-if="item.pta_status" :class="item.pta_status === 'approved' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'" class="px-1.5 py-0.5 rounded border uppercase font-black">
                                            {{ item.pta_status }}
                                        </span>
                                        <span v-if="item.storage" class="bg-indigo-50 text-indigo-700 border border-indigo-200 px-1.5 py-0.5 rounded font-bold">
                                            {{ item.storage }}
                                        </span>
                                        <span v-if="item.color" class="bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded border border-slate-200 capitalize">
                                            {{ item.color }}
                                        </span>
                                    </div>
                                </td>
                                
                                <td class="px-3 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button
                                            type="button"
                                            @click="item.quantity > 1 ? item.quantity-- : removeCartItem(idx)"
                                            class="h-6 w-6 rounded border border-slate-200 bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-700 font-bold"
                                        >
                                            -
                                        </button>
                                        <span class="w-8 text-center font-black text-xs text-slate-900">{{ item.quantity }}</span>
                                        <button
                                            type="button"
                                            @click="!item.is_serialized && item.quantity++"
                                            :disabled="item.is_serialized"
                                            class="h-6 w-6 rounded border border-slate-200 bg-slate-100 hover:bg-slate-200 disabled:opacity-40 flex items-center justify-center text-slate-700 font-bold"
                                        >
                                            +
                                        </button>
                                    </div>
                                </td>

                                <td class="px-3 py-3 text-right">
                                    <input
                                        v-model.number="item.unit_price"
                                        type="number"
                                        class="w-22 rounded-lg border border-slate-300 text-right py-1 px-2 text-xs font-bold text-slate-900 focus:border-indigo-600 focus:outline-none"
                                    />
                                </td>

                                <td class="px-3 py-3 text-right">
                                    <input
                                        v-model.number="item.item_discount"
                                        type="number"
                                        class="w-20 rounded-lg border border-slate-300 text-right py-1 px-2 text-xs font-bold text-rose-600 focus:border-rose-500 focus:outline-none"
                                    />
                                </td>

                                <td class="px-3 py-3 text-right font-black text-indigo-700 text-xs">
                                    Rs {{ ((item.quantity * item.unit_price) - (item.item_discount || 0)).toLocaleString() }}
                                </td>

                                <td class="px-3 py-3 text-center">
                                    <button
                                        type="button"
                                        @click="removeCartItem(idx)"
                                        class="text-slate-400 hover:text-rose-600 hover:bg-rose-50 p-1.5 rounded-lg transition"
                                        title="Remove item"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- RIGHT COLUMN: Modern Checkout Sidebar (~32% width) -->
            <div class="w-80 lg:w-96 flex flex-col shrink-0 overflow-y-auto bg-white rounded-2xl border border-slate-700/60 p-4 shadow-xl justify-between space-y-3.5">
                
                <!-- Checkout Header -->
                <div class="flex items-center justify-between pb-2.5 border-b border-slate-200">
                    <div class="flex items-center gap-2">
                        <Receipt class="h-4 w-4 text-indigo-600" />
                        <span class="text-xs font-black text-slate-900 uppercase tracking-wider">Payment Summary</span>
                    </div>
                    <span class="text-[11px] font-black text-indigo-700 bg-indigo-50 px-2.5 py-0.5 rounded-full border border-indigo-200">
                        {{ cart.reduce((acc, i) => acc + i.quantity, 0) }} ITEMS
                    </span>
                </div>

                <!-- Subtotal Breakdown Row -->
                <div class="space-y-1.5 text-xs text-slate-600">
                    <div class="flex justify-between font-semibold">
                        <span>Cart Subtotal</span>
                        <span class="font-bold text-slate-900">Rs {{ subtotal.toLocaleString() }}</span>
                    </div>
                    <div v-if="calculatedDiscountAmount > 0" class="flex justify-between text-rose-600 font-semibold">
                        <span>Total Discount</span>
                        <span class="font-bold">- Rs {{ calculatedDiscountAmount.toLocaleString() }}</span>
                    </div>
                    <div v-if="appliedTradeInAmount > 0" class="flex justify-between text-amber-600 font-semibold">
                        <span>Trade-In Credit</span>
                        <span class="font-bold">- Rs {{ appliedTradeInAmount.toLocaleString() }}</span>
                    </div>
                </div>

                <!-- Discount Input & Mode Selector -->
                <div>
                    <label class="block mb-1 text-[11px] font-extrabold text-slate-700 uppercase">
                        Bill Discount (F4)
                    </label>
                    <div class="flex items-center rounded-xl border border-slate-300 bg-slate-50 overflow-hidden focus-within:border-indigo-600 focus-within:ring-2 focus-within:ring-indigo-600/20">
                        <span class="pl-3 text-xs font-bold text-slate-500">Rs</span>
                        <input
                            id="discount-input-field"
                            v-model="discountInput"
                            type="number"
                            placeholder="0"
                            class="w-full bg-transparent px-2 py-1.5 text-xs font-bold text-slate-900 focus:outline-none"
                        />
                        <div class="flex items-center shrink-0 border-l border-slate-300">
                            <button
                                type="button"
                                @click="discountMode = 'amount'"
                                :class="discountMode === 'amount' ? 'bg-indigo-600 text-white font-black' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-2.5 py-1.5 text-xs transition"
                            >
                                Rs
                            </button>
                            <button
                                type="button"
                                @click="discountMode = 'percent'"
                                :class="discountMode === 'percent' ? 'bg-indigo-600 text-white font-black' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-2.5 py-1.5 text-xs transition"
                            >
                                %
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Trade-In / Exchange Panel -->
                <div>
                    <label class="block mb-1 text-[11px] font-extrabold text-slate-700 uppercase">
                        Trade-In / Exchange
                    </label>
                    <div
                        v-if="selectedTradeIn"
                        class="rounded-xl border border-amber-300 bg-amber-50 p-2.5 space-y-1"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5 text-xs font-black text-amber-900">
                                <Repeat class="h-3.5 w-3.5" />
                                <span class="truncate">{{ selectedTradeIn.device_model }}</span>
                            </div>
                            <button
                                type="button"
                                @click="selectedTradeIn = null"
                                class="shrink-0 text-amber-500 hover:text-rose-600 font-black"
                                title="Remove trade-in"
                            >
                                ✕
                            </button>
                        </div>
                        <div class="text-[10px] font-semibold text-amber-700">
                            IMEI: {{ selectedTradeIn.imei_1 }} • {{ selectedTradeIn.seller_name }} • {{ selectedTradeIn.voucher_no }}
                        </div>
                        <div class="text-sm font-black text-amber-900">
                            - Rs {{ appliedTradeInAmount.toLocaleString() }}
                        </div>
                    </div>
                    <button
                        v-else
                        type="button"
                        @click="isTradeInModalOpen = true"
                        class="w-full h-9 rounded-xl border border-dashed border-amber-400 bg-amber-50/50 hover:bg-amber-100 text-xs font-extrabold text-amber-700 transition active:scale-95 flex items-center justify-center gap-1.5"
                    >
                        <Repeat class="h-4 w-4" />
                        <span>Add Trade-In / Exchange</span>
                    </button>
                </div>

                <!-- Payment Method Selector Pills -->
                <div>
                    <label class="block mb-1 text-[11px] font-extrabold text-slate-700 uppercase">
                        Payment Method
                    </label>
                    <div class="grid grid-cols-3 gap-1.5">
                        <button
                            type="button"
                            @click="paymentMethod = 'cash'"
                            :class="paymentMethod === 'cash' ? 'bg-emerald-600 text-white border-emerald-600 font-extrabold shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100 font-semibold'"
                            class="py-1.5 px-2 rounded-xl text-xs border transition text-center active:scale-95"
                        >
                            💵 Cash
                        </button>
                        <button
                            type="button"
                            @click="paymentMethod = 'jazzcash'"
                            :class="paymentMethod === 'jazzcash' ? 'bg-rose-600 text-white border-rose-600 font-extrabold shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100 font-semibold'"
                            class="py-1.5 px-2 rounded-xl text-xs border transition text-center active:scale-95"
                        >
                            📱 JazzCash
                        </button>
                        <button
                            type="button"
                            @click="paymentMethod = 'easypaisa'"
                            :class="paymentMethod === 'easypaisa' ? 'bg-teal-600 text-white border-teal-600 font-extrabold shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100 font-semibold'"
                            class="py-1.5 px-2 rounded-xl text-xs border transition text-center active:scale-95"
                        >
                            📲 Easypaisa
                        </button>
                        <button
                            type="button"
                            @click="paymentMethod = 'bank'"
                            :class="paymentMethod === 'bank' ? 'bg-blue-600 text-white border-blue-600 font-extrabold shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100 font-semibold'"
                            class="py-1.5 px-2 rounded-xl text-xs border transition text-center active:scale-95"
                        >
                            🏛️ Bank
                        </button>
                        <button
                            type="button"
                            @click="paymentMethod = 'card'"
                            :class="paymentMethod === 'card' ? 'bg-purple-600 text-white border-purple-600 font-extrabold shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100 font-semibold'"
                            class="py-1.5 px-2 rounded-xl text-xs border transition text-center active:scale-95"
                        >
                            💳 Card
                        </button>
                        <button
                            type="button"
                            @click="paymentMethod = 'split'"
                            :class="paymentMethod === 'split' ? 'bg-indigo-600 text-white border-indigo-600 font-extrabold shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100 font-semibold'"
                            class="py-1.5 px-2 rounded-xl text-xs border transition text-center active:scale-95"
                        >
                            🔀 Split Tender
                        </button>
                        <button
                            type="button"
                            @click="paymentMethod = 'udhaar'"
                            :class="paymentMethod === 'udhaar' ? 'bg-amber-600 text-white border-amber-600 font-extrabold shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100 font-semibold'"
                            class="col-span-3 py-1.5 px-2 rounded-xl text-xs border transition text-center active:scale-95"
                        >
                            📖 Udhaar (Khata Ledger)
                        </button>
                    </div>

                    <!-- Split Tender Allocation Inputs -->
                    <div
                        v-if="paymentMethod === 'split'"
                        class="mt-2 rounded-xl border border-indigo-200 bg-indigo-50/60 p-2.5 space-y-2"
                    >
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="font-extrabold text-indigo-900 uppercase">Split Allocation</span>
                            <span class="font-black text-indigo-700">Allocated: Rs {{ splitTotal.toLocaleString() }}</span>
                        </div>
                        <div class="grid grid-cols-3 gap-1.5">
                            <div
                                v-for="method in splitMethods"
                                :key="method.key"
                                class="space-y-0.5"
                            >
                                <label class="block text-[10px] font-extrabold text-slate-600 uppercase tracking-wide">{{ method.label }}</label>
                                <div :class="[paymentMethod === 'split' && method.key === 'udhaar' && (Number(splitAmounts[method.key]) || 0) > 0 ? 'border-amber-400 bg-amber-50' : 'border-slate-300 bg-white', 'flex items-center rounded-lg border px-1.5 py-1 focus-within:border-indigo-500']">
                                    <span class="text-[10px] font-bold text-slate-400 mr-0.5">Rs</span>
                                    <input
                                        v-model.number="splitAmounts[method.key]"
                                        type="number"
                                        min="0"
                                        placeholder="0"
                                        class="w-full bg-transparent text-xs font-bold text-slate-900 focus:outline-none"
                                    />
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="font-semibold text-slate-500">
                                Remaining: <span class="font-black text-slate-900">Rs {{ splitRemaining.toLocaleString() }}</span>
                            </span>
                            <button
                                type="button"
                                @click="fillSplitRemaining"
                                class="text-[10px] font-extrabold text-indigo-600 hover:underline"
                            >
                                Fill Balance in Cash
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Cash Received & Due Amount Grid -->
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block mb-1 text-[11px] font-extrabold text-slate-700 uppercase">
                            Cash Received (Alt+P)
                        </label>
                        <div class="flex items-center rounded-xl border border-slate-300 bg-slate-50 px-2 py-1.5 focus-within:border-indigo-600">
                            <span class="text-xs font-bold text-slate-400 mr-1">Rs</span>
                            <input
                                id="total-payment-input"
                                v-model="paidInput"
                                type="number"
                                placeholder="0"
                                class="w-full bg-transparent text-xs font-bold text-slate-900 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block mb-1 text-[11px] font-extrabold text-slate-700 uppercase">
                            Due / Balance
                        </label>
                        <div class="rounded-xl border border-slate-200 bg-slate-100 px-2.5 py-1.5 text-xs font-black text-slate-900 h-9 flex items-center">
                            Rs {{ duePayment.toLocaleString() }}
                        </div>
                    </div>
                </div>

                <!-- Quick Cash Presets Bar -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-extrabold uppercase text-slate-400">Quick Cash Buttons</span>
                        <button type="button" @click="paidInput = netPayableAfterTradeIn" class="text-[10px] font-extrabold text-indigo-600 hover:underline">
                            Exact Amount
                        </button>
                    </div>
                    <div class="grid grid-cols-4 gap-1.5">
                        <button
                            type="button"
                            @click="addQuickCashPreset(100)"
                            class="h-8 rounded-xl bg-slate-100 hover:bg-indigo-600 hover:text-white text-xs font-bold text-slate-700 transition active:scale-95 border border-slate-200"
                        >
                            +100
                        </button>
                        <button
                            type="button"
                            @click="addQuickCashPreset(500)"
                            class="h-8 rounded-xl bg-slate-100 hover:bg-indigo-600 hover:text-white text-xs font-bold text-slate-700 transition active:scale-95 border border-slate-200"
                        >
                            +500
                        </button>
                        <button
                            type="button"
                            @click="addQuickCashPreset(1000)"
                            class="h-8 rounded-xl bg-slate-100 hover:bg-indigo-600 hover:text-white text-xs font-bold text-slate-700 transition active:scale-95 border border-slate-200"
                        >
                            +1k
                        </button>
                        <button
                            type="button"
                            @click="addQuickCashPreset(5000)"
                            class="h-8 rounded-xl bg-slate-100 hover:bg-indigo-600 hover:text-white text-xs font-bold text-slate-700 transition active:scale-95 border border-slate-200"
                        >
                            +5k
                        </button>
                    </div>
                </div>

                <!-- Giant Net Amount Hero Display Box -->
                <div class="rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-950 to-blue-900 p-4 text-center text-white shadow-xl border border-indigo-400/30">
                    <div class="text-[10px] font-black uppercase tracking-widest text-indigo-300 mb-0.5">
                        NET PAYABLE AMOUNT
                        <span v-if="appliedTradeInAmount > 0" class="normal-case tracking-normal text-amber-300">
                            (after trade-in)
                        </span>
                    </div>
                    <div class="text-3xl lg:text-4xl font-black tracking-tight">
                        Rs {{ netPayableAfterTradeIn.toLocaleString() }}
                    </div>
                    <div v-if="appliedTradeInAmount > 0" class="mt-1 text-[10px] line-through text-slate-400">
                        Rs {{ netPayable.toLocaleString() }} - Rs {{ appliedTradeInAmount.toLocaleString() }} trade-in
                    </div>
                    <div v-if="notesInput" class="mt-1.5 text-[10px] text-sky-200 truncate bg-white/10 px-2 py-0.5 rounded-full">
                        📝 {{ notesInput }}
                    </div>
                </div>

                <!-- Main Action Buttons -->
                <div class="space-y-2 pt-1">
                    <button
                        type="button"
                        @click="saveSale"
                        class="w-full h-12 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-sm font-black text-white shadow-lg active:scale-95 transition flex items-center justify-center gap-2"
                    >
                        <CheckCircle class="h-5 w-5" />
                        <span>COMPLETE SALE (Ctrl+Enter)</span>
                    </button>

                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            @click="holdCurrentSale"
                            class="h-10 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 text-xs font-extrabold active:scale-95 transition flex items-center justify-center gap-1.5"
                        >
                            <Pause class="h-4 w-4" />
                            <span>Hold Sale</span>
                        </button>

                        <button
                            type="button"
                            @click="clearCart"
                            class="h-10 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-extrabold active:scale-95 transition flex items-center justify-center gap-1.5"
                        >
                            <Trash2 class="h-4 w-4" />
                            <span>Clear</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- DIALOG 1: Register Customer -->
        <Dialog v-model:open="isCustomerModalOpen">
            <DialogContent class="max-w-md rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
                <DialogHeader>
                    <DialogTitle class="text-lg font-black text-slate-900">Register New Customer</DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">Add customer details for credit ledger (Khata).</DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitCustomerForm" class="space-y-3 py-2">
                    <div>
                        <label class="block mb-1 text-xs font-bold text-slate-700">Full Name *</label>
                        <input v-model="customerForm.name" placeholder="e.g. Ali Ahmed" class="w-full h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-indigo-600 focus:outline-none" required />
                    </div>
                    <div>
                        <label class="block mb-1 text-xs font-bold text-slate-700">Mobile Phone *</label>
                        <input v-model="customerForm.phone" placeholder="03001234567" class="w-full h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-indigo-600 focus:outline-none" required />
                    </div>
                    <div>
                        <label class="block mb-1 text-xs font-bold text-slate-700">City / Address</label>
                        <input v-model="customerForm.address" placeholder="Lahore" class="w-full h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-indigo-600 focus:outline-none" />
                    </div>

                    <DialogFooter class="pt-3">
                        <Button type="button" variant="outline" @click="isCustomerModalOpen = false">Cancel</Button>
                        <Button type="submit" class="bg-indigo-600 text-white hover:bg-indigo-700 font-bold">Save Customer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- DIALOG: Trade-In / Exchange -->
        <Dialog v-model:open="isTradeInModalOpen">
            <DialogContent class="max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
                <DialogHeader>
                    <DialogTitle class="text-lg font-black text-slate-900">Trade-In / Exchange</DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Offset this bill using credit from a purchased used phone, or register a new trade-in now.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-2 gap-1 bg-slate-100 p-1 rounded-xl mt-2">
                    <button
                        type="button"
                        @click="tradeInTab = 'apply'"
                        :class="tradeInTab === 'apply' ? 'bg-white shadow text-slate-900 font-black' : 'text-slate-500 hover:text-slate-700 font-bold'"
                        class="h-9 rounded-lg text-xs transition"
                    >
                        Apply Credit
                    </button>
                    <button
                        type="button"
                        @click="openNewTradeIn"
                        :class="tradeInTab === 'new' ? 'bg-white shadow text-slate-900 font-black' : 'text-slate-500 hover:text-slate-700 font-bold'"
                        class="h-9 rounded-lg text-xs transition"
                    >
                        New Trade-In
                    </button>
                </div>

                <div class="py-3 max-h-[55vh] overflow-y-auto">
                    <!-- APPLY CREDIT TAB -->
                    <div v-if="tradeInTab === 'apply'" class="space-y-2">
                        <div v-if="props.usedPhonePurchases.length === 0" class="text-center py-8">
                            <Repeat class="h-8 w-8 text-slate-300 mx-auto mb-2" />
                            <p class="text-xs font-bold text-slate-500">No unused trade-in credits available.</p>
                            <button type="button" @click="openNewTradeIn" class="mt-2 text-xs font-extrabold text-amber-600 hover:underline">
                                Register a new trade-in →
                            </button>
                        </div>
                        <button
                            v-for="purchase in props.usedPhonePurchases"
                            :key="purchase.id"
                            type="button"
                            @click="selectTradeIn(purchase)"
                            class="w-full text-left rounded-xl border border-slate-200 hover:border-amber-400 hover:bg-amber-50 transition p-3 flex items-center justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <div class="text-xs font-black text-slate-900 truncate">{{ purchase.device_model }}</div>
                                <div class="text-[10px] font-semibold text-slate-500 truncate">
                                    {{ purchase.voucher_no }} • IMEI {{ purchase.imei_1 }} • <span class="text-slate-700">{{ purchase.seller_name }}</span>
                                </div>
                                <div class="text-[10px] text-slate-400">{{ new Date(purchase.created_at).toLocaleDateString() }}</div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-sm font-black text-emerald-600">Rs {{ Number(purchase.purchase_amount).toLocaleString() }}</div>
                                <div class="text-[10px] font-bold text-amber-600">APPLY</div>
                            </div>
                        </button>
                    </div>

                    <!-- NEW TRADE-IN TAB -->
                    <form v-else @submit.prevent="submitTradeIn" class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block mb-1 text-[11px] font-bold text-slate-700 uppercase">Seller Name *</label>
                                <input v-model="tradeInForm.seller_name" placeholder="Full name of seller" class="w-full h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none" required />
                            </div>
                            <div>
                                <label class="block mb-1 text-[11px] font-bold text-slate-700 uppercase">Seller CNIC *</label>
                                <input v-model="tradeInForm.seller_cnic" placeholder="e.g. 35202-1234567-8" class="w-full h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none" required />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block mb-1 text-[11px] font-bold text-slate-700 uppercase">Seller Phone</label>
                                <input v-model="tradeInForm.seller_phone" placeholder="03001234567" class="w-full h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block mb-1 text-[11px] font-bold text-slate-700 uppercase">Seller Address</label>
                                <input v-model="tradeInForm.seller_address" placeholder="City / address" class="w-full h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block mb-1 text-[11px] font-bold text-slate-700 uppercase">Device Model *</label>
                                <input v-model="tradeInForm.device_model" placeholder="e.g. iPhone 11" class="w-full h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none" required />
                            </div>
                            <div>
                                <label class="block mb-1 text-[11px] font-bold text-slate-700 uppercase">Brand *</label>
                                <input v-model="tradeInForm.brand" placeholder="e.g. Apple" class="w-full h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none" required />
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block mb-1 text-[11px] font-bold text-slate-700 uppercase">Color</label>
                                <input v-model="tradeInForm.color" placeholder="Black" class="w-full h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block mb-1 text-[11px] font-bold text-slate-700 uppercase">Storage</label>
                                <input v-model="tradeInForm.storage" placeholder="128GB" class="w-full h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block mb-1 text-[11px] font-bold text-slate-700 uppercase">Purchase Amount *</label>
                                <input v-model="tradeInForm.purchase_amount" type="number" min="0" placeholder="45000" class="w-full h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none" required />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block mb-1 text-[11px] font-bold text-slate-700 uppercase">IMEI 1 *</label>
                                <input v-model="tradeInForm.imei_1" placeholder="15-digit IMEI" class="w-full h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none" required />
                            </div>
                            <div>
                                <label class="block mb-1 text-[11px] font-bold text-slate-700 uppercase">IMEI 2</label>
                                <input v-model="tradeInForm.imei_2" placeholder="Optional" class="w-full h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                            <div>
                                <label class="block mb-1 text-[11px] font-bold text-slate-700 uppercase">PTA Status</label>
                                <select v-model="tradeInForm.pta_status" class="w-full h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold bg-white focus:border-amber-600 focus:outline-none">
                                    <option value="approved">Approved</option>
                                    <option value="non_pta">Non-PTA</option>
                                    <option value="jv">JV</option>
                                    <option value="cpid">CPID</option>
                                    <option value="software">Software Unlock</option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-1 text-[11px] font-bold text-slate-700 uppercase">Payment Method</label>
                                <select v-model="tradeInForm.payment_method" class="w-full h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold bg-white focus:border-amber-600 focus:outline-none">
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank Transfer</option>
                                    <option value="jazzcash">JazzCash</option>
                                    <option value="easypaisa">Easypaisa</option>
                                </select>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 pt-1 cursor-pointer">
                            <input v-model="tradeInForm.auto_add_stock" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500" />
                            <span class="text-xs font-bold text-slate-700">Keep this phone in resale stock (auto created IMEI entry)</span>
                        </label>
                        <p class="text-[10px] font-semibold text-slate-500 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
                            ⚖️ Purchasing a used phone is recorded with the seller's name, CNIC and agreement for legal protection.
                        </p>

                        <DialogFooter class="pt-3">
                            <Button type="button" variant="outline" @click="isTradeInModalOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="tradeInForm.processing" class="bg-amber-600 text-white hover:bg-amber-700 font-bold">
                                <span v-if="tradeInForm.processing" class="inline-block h-3 w-3 border-2 border-white/40 border-t-white rounded-full animate-spin mr-1.5"></span>
                                Register & Apply Credit
                            </Button>
                        </DialogFooter>
                    </form>
                </div>
            </DialogContent>
        </Dialog>

        <!-- DIALOG 2: Thermal Receipt Print -->
        <Dialog v-model:open="isReceiptModalOpen">
            <DialogContent class="max-w-sm rounded-2xl bg-white p-5 shadow-2xl border border-slate-200">
                <DialogHeader class="no-print">
                    <DialogTitle class="text-center text-sm font-black text-slate-900">Thermal Invoice Receipt</DialogTitle>
                </DialogHeader>

                <div class="py-2 max-h-[70vh] overflow-y-auto">
                    <ThermalReceipt v-if="activeReceipt" :receipt="(activeReceipt as any)" :shop-info="shopInfo" />
                </div>

                <DialogFooter class="no-print flex justify-between pt-2">
                    <Button type="button" variant="outline" @click="isReceiptModalOpen = false">Close</Button>
                    <Button type="button" @click="printReceipt" class="bg-indigo-600 text-white hover:bg-indigo-700 font-bold">
                        <Printer class="mr-1 h-4 w-4" /> Print
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG 3: Held Sales Drawer / Modal -->
        <Dialog v-model:open="isHeldSalesModalOpen">
            <DialogContent class="max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
                <DialogHeader>
                    <DialogTitle class="text-base font-black text-slate-900 flex items-center gap-2">
                        <Pause class="h-5 w-5 text-amber-500" />
                        <span>Held Sales Pending</span>
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">Restore held customer carts or clear expired ones.</DialogDescription>
                </DialogHeader>

                <div class="py-2 space-y-2 max-h-[60vh] overflow-y-auto">
                    <div v-if="heldSales.length === 0" class="py-10 text-center text-xs text-slate-400 font-medium">
                        No sales currently on hold.
                    </div>
                    <div
                        v-else
                        v-for="(h, idx) in heldSales"
                        :key="h.id"
                        class="p-3 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-between"
                    >
                        <div>
                            <div class="font-extrabold text-slate-900 text-xs flex items-center gap-2">
                                <span>{{ h.id }}</span>
                                <span class="text-[10px] font-bold text-slate-500">({{ h.held_at }})</span>
                            </div>
                            <div class="text-[11px] text-slate-600 mt-0.5">
                                Customer: <strong>{{ h.customer_name }}</strong> | {{ h.cart.length }} Items
                            </div>
                            <div class="text-xs font-black text-indigo-700 mt-0.5">
                                Total: Rs {{ h.total.toLocaleString() }}
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                @click="restoreHeldSale(h, idx)"
                                class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 transition active:scale-95"
                            >
                                Resume
                            </button>
                            <button
                                type="button"
                                @click="deleteHeldSale(idx)"
                                class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                title="Delete"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="isHeldSalesModalOpen = false">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG 4: Quick Notes & Tags Modal -->
        <Dialog v-model:open="isNotesModalOpen">
            <DialogContent class="max-w-md rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
                <DialogHeader>
                    <DialogTitle class="text-base font-black text-slate-900">Sale Notes & Warranty Tags</DialogTitle>
                </DialogHeader>

                <div class="space-y-3 py-2">
                    <div>
                        <label class="block mb-1 text-xs font-bold text-slate-700">Quick Mobile Shop Tag Presets</label>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                type="button"
                                @click="addNotePreset('7 Days Warranty')"
                                class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-indigo-50 hover:text-indigo-700 text-xs font-bold text-slate-700 border border-slate-200"
                            >
                                + 7 Days Checking Warranty
                            </button>
                            <button
                                type="button"
                                @click="addNotePreset('Screen Guard Applied')"
                                class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-indigo-50 hover:text-indigo-700 text-xs font-bold text-slate-700 border border-slate-200"
                            >
                                + Glass Guard Applied
                            </button>
                            <button
                                type="button"
                                @click="addNotePreset('Box & Original Charger')"
                                class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-indigo-50 hover:text-indigo-700 text-xs font-bold text-slate-700 border border-slate-200"
                            >
                                + Box & Accessories Included
                            </button>
                            <button
                                type="button"
                                @click="addNotePreset('Clearance Deal - Non Returnable')"
                                class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-indigo-50 hover:text-indigo-700 text-xs font-bold text-slate-700 border border-slate-200"
                            >
                                + Clearance Deal
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block mb-1 text-xs font-bold text-slate-700">Note Content</label>
                        <textarea
                            v-model="notesInput"
                            rows="3"
                            placeholder="Type any custom invoice instructions or remarks..."
                            class="w-full rounded-xl border border-slate-300 p-2.5 text-xs font-medium focus:border-indigo-600 focus:outline-none"
                        ></textarea>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" class="bg-indigo-600 text-white hover:bg-indigo-700 font-bold" @click="isNotesModalOpen = false">Done</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG 5: POS Settings -->
        <Dialog v-model:open="isSettingsModalOpen">
            <DialogContent class="max-w-md rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
                <DialogHeader>
                    <DialogTitle class="text-base font-black text-slate-900">POS Preferences & Audio</DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-3 text-xs">
                    <div class="flex items-center justify-between border-b pb-3">
                        <div>
                            <div class="font-bold text-slate-900">Audio Beep Sound Effects</div>
                            <div class="text-[11px] text-slate-500">Play synth beep sound on barcode scan / action</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="posSettings.soundEnabled" class="sr-only peer">
                            <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between border-b pb-3">
                        <div>
                            <div class="font-bold text-slate-900">Auto Open Print Receipt Modal</div>
                            <div class="text-[11px] text-slate-500">Automatically open receipt preview after sale save</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="posSettings.autoPrint" class="sr-only peer">
                            <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>

                    <div>
                        <label class="block mb-1 font-bold text-slate-900">Default Payment Mode</label>
                        <select v-model="posSettings.defaultPayment" class="w-full h-9 rounded-xl border border-slate-300 px-3 text-xs font-bold">
                            <option value="cash">Cash</option>
                            <option value="jazzcash">JazzCash</option>
                            <option value="easypaisa">Easypaisa</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="isSettingsModalOpen = false">Cancel</Button>
                    <Button type="button" class="bg-indigo-600 text-white font-bold hover:bg-indigo-700" @click="savePosSettings">Save Settings</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG 6: Keyboard Shortcuts -->
        <Dialog v-model:open="isShortcutsModalOpen">
            <DialogContent class="max-w-md rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
                <DialogHeader>
                    <DialogTitle class="text-base font-black text-slate-900">POS Keyboard Shortcuts</DialogTitle>
                </DialogHeader>
                <div class="space-y-2 py-2 text-xs font-mono">
                    <div class="flex justify-between border-b pb-1.5"><span>New Sale / Clear Cart</span><span class="font-extrabold text-indigo-600">F1</span></div>
                    <div class="flex justify-between border-b pb-1.5"><span>Focus Search / Scan IMEI</span><span class="font-extrabold text-indigo-600">F2</span></div>
                    <div class="flex justify-between border-b pb-1.5"><span>Customer Select</span><span class="font-extrabold text-indigo-600">F3</span></div>
                    <div class="flex justify-between border-b pb-1.5"><span>Bill Discount Input</span><span class="font-extrabold text-indigo-600">F4</span></div>
                    <div class="flex justify-between border-b pb-1.5"><span>Complete Sale & Print Receipt</span><span class="font-extrabold text-indigo-600">Ctrl + Enter</span></div>
                    <div class="flex justify-between border-b pb-1.5"><span>Focus Search Bar</span><span class="font-extrabold text-indigo-600">Ctrl + F</span></div>
                    <div class="flex justify-between border-b pb-1.5"><span>Cash Received Input</span><span class="font-extrabold text-indigo-600">Alt + P</span></div>
                    <div class="flex justify-between border-b pb-1.5"><span>Hold Current Sale</span><span class="font-extrabold text-indigo-600">Alt + H</span></div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="isShortcutsModalOpen = false">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG 7: IMEI Selection Modal for Mobile Phones -->
        <Dialog v-model:open="isImeiSelectorOpen">
            <DialogContent class="max-w-md rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
                <DialogHeader>
                    <DialogTitle class="text-base font-black text-slate-900 flex items-center gap-2">
                        <Smartphone class="h-5 w-5 text-indigo-600" />
                        <span>Select Device Serial (IMEI)</span>
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Select an active IMEI unit for {{ imeiSelectProduct ? formatProductName(imeiSelectProduct.name, imeiSelectProduct.brand) : 'this device' }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="py-2 space-y-2 max-h-[50vh] overflow-y-auto">
                    <div v-if="!imeiSelectProduct || getAvailableImeis(imeiSelectProduct).length === 0" class="py-8 text-center text-xs text-slate-400 font-medium">
                        No active stock IMEI units available for this device.
                    </div>
                    <div
                        v-else
                        v-for="imei in getAvailableImeis(imeiSelectProduct)"
                        :key="imei.id"
                        @click="confirmImeiSelection(imei)"
                        class="p-3 rounded-xl border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/60 cursor-pointer transition flex items-center justify-between group"
                    >
                        <div class="space-y-1">
                            <div class="font-mono text-xs font-black text-slate-900 flex items-center gap-2">
                                <span>IMEI: {{ imei.imei_1 }}</span>
                                <span v-if="imei.imei_2" class="text-[10px] text-slate-400 font-normal">({{ imei.imei_2 }})</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px]">
                                <span v-if="imei.pta_status" :class="imei.pta_status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'" class="px-1.5 py-0.5 rounded font-black uppercase">
                                    {{ imei.pta_status }}
                                </span>
                                <span v-if="imei.storage" class="bg-indigo-100 text-indigo-800 px-1.5 py-0.5 rounded font-bold">
                                    {{ imei.storage }}
                                </span>
                                <span v-if="imei.color" class="bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded capitalize">
                                    {{ imei.color }}
                                </span>
                                <span v-if="imei.condition" class="bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded uppercase font-bold">
                                    {{ imei.condition }}
                                </span>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-bold text-xs group-hover:bg-indigo-700 transition active:scale-95 shadow-xs"
                        >
                            Select
                        </button>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="isImeiSelectorOpen = false">Cancel</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Toaster
            position="top-center"
            richColors
            closeButton
            :toastOptions="{
                style: {
                    borderRadius: '16px',
                    border: '1px solid rgba(0, 59, 125, 0.25)',
                    background: '#ffffff',
                    color: '#002654',
                    boxShadow: '0 12px 30px -4px rgba(0, 59, 125, 0.2), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
                    fontSize: '13px',
                    fontWeight: '700',
                    padding: '12px 16px',
                },
            }"
        />
        <ConfirmDialog />
    </div>
</template>
