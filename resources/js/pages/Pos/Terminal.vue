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
    ChevronRight,
    CreditCard,
    DollarSign,
    Edit,
    ExternalLink,
    Eye,
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
    MessageSquare,
    Share2,
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
    cashier_id?: number | null;
    total_amount: number | string;
    discount_amount: number | string;
    trade_in_amount?: number | string;
    net_amount: number | string;
    paid_amount: number | string;
    change_amount: number | string;
    payment_method: string;
    payment_details?: Record<string, number> | null;
    previous_customer_balance?: number | string;
    new_customer_balance?: number | string;
    created_at: string;
    customer?: CustomerItem | null;
    cashier?: { id: number; name: string };
    salesman?: { id: number; name: string } | null;
    used_phone_purchase?: { id: number; device_model?: string; purchase_amount?: number | string } | null;
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

export interface SalesmanItem {
    id: number;
    name: string;
    email?: string;
}

const props = defineProps<{
    products: ProductItem[];
    customers: CustomerItem[];
    usedPhonePurchases: TradeInItem[];
    shopInfo: ShopInfo;
    salesmen?: SalesmanItem[];
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

// POS Settings
const isSettingsModalOpen = ref(false);
const posSettings = ref({
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
        localStorage.setItem(
            'pos_user_settings',
            JSON.stringify(posSettings.value),
        );
        toast.success('POS preferences saved.');
        isSettingsModalOpen.value = false;
    } catch (e) {}
};

// Fullscreen
const isFullscreen = ref(false);
const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement
            .requestFullscreen()
            .then(() => {
                isFullscreen.value = true;
            })
            .catch(() => {
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
const selectedSalesmanId = ref<number | string>('');
const rawSaleDate = ref<string>('');

const currentTime = ref<Date>(new Date());
const currentDateFormatted = ref('');
const updateCurrentTime = () => {
    const now = new Date();
    currentTime.value = now;
    currentDateFormatted.value =
        now.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        }) +
        ' • ' +
        now.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
};

const formattedSaleDateDisplay = computed(() => {
    let dateObj: Date;
    if (rawSaleDate.value) {
        dateObj = new Date(rawSaleDate.value);
    } else {
        dateObj = currentTime.value;
    }

    if (isNaN(dateObj.getTime())) {
        dateObj = new Date();
    }

    const monthName = dateObj.toLocaleString('en-US', { month: 'long' });
    const dayStr = String(dateObj.getDate()).padStart(2, '0');
    const yearStr = dateObj.getFullYear();

    let hours = dateObj.getHours();
    const minutes = String(dateObj.getMinutes()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    const hoursStr = String(hours).padStart(2, '0');

    return `${monthName} ${dayStr}, ${yearStr} ${hoursStr}:${minutes} ${ampm}`;
});

const resetSaleDate = () => {
    rawSaleDate.value = '';
};

watch(
    () => props.salesmen,
    (newSalesmen) => {
        if (!selectedSalesmanId.value) {
            const authUser = (page.props.auth as any)?.user;
            if (authUser?.id) {
                selectedSalesmanId.value = authUser.id;
            } else if (newSalesmen && newSalesmen.length > 0) {
                selectedSalesmanId.value = newSalesmen[0].id;
            }
        }
    },
    { immediate: true },
);

// --- Quick Expense State & Form ---
const isExpenseModalOpen = ref(false);
const expenseForm = useForm({
    category: 'Tea/Refreshments',
    amount: '',
    notes: '',
});

const expenseCategories = [
    'Tea/Refreshments',
    'Electricity / Utilities',
    'Shop Supplies / Stationery',
    'Staff Salaries / Allowance',
    'Freight & Transport',
    'Shop Rent & Maintenance',
    'Miscellaneous Expense',
];

const submitExpense = () => {
    if (!expenseForm.amount || Number(expenseForm.amount) <= 0) {
        toast.error('Invalid Amount', {
            description: 'Please enter a valid expense amount.',
        });
        return;
    }
    expenseForm.post(`/${currentTeamSlug.value}/expenses`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Expense Recorded', {
                description: `Rs ${Number(expenseForm.amount).toLocaleString()} saved successfully.`,
            });
            expenseForm.reset();
            isExpenseModalOpen.value = false;
        },
        onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            toast.error('Expense Failed', {
                description: Array.isArray(firstErr) ? firstErr[0] : String(firstErr || 'Check expense details.'),
            });
        },
    });
};

// --- Recent Sales State & Functions ---
const isRecentSalesModalOpen = ref(false);
const recentSalesList = ref<CompletedSale[]>([]);
const isLoadingRecentSales = ref(false);
const recentSalesQuery = ref('');
const recentSalesPaymentFilter = ref('all');
const selectedSaleDetails = ref<CompletedSale | null>(null);
const isSaleDetailsModalOpen = ref(false);

// In-POS Sale Edit State & Handlers
const isEditSaleModalOpen = ref(false);
const editingSale = ref<CompletedSale | null>(null);
const isSavingEditSale = ref(false);
const editSaleForm = ref({
    customer_id: '' as string | number,
    cashier_id: '' as string | number,
    payment_method: 'cash',
    discount_amount: 0,
    paid_amount: 0,
});

const fetchRecentSales = async () => {
    isLoadingRecentSales.value = true;
    try {
        const res = await fetch(`/${currentTeamSlug.value}/pos/recent-sales`, {
            headers: { Accept: 'application/json' },
        });
        if (res.ok) {
            const data = await res.json();
            recentSalesList.value = Array.isArray(data) ? data : [];
        }
    } catch (err) {
        console.error('Failed to load recent sales:', err);
    } finally {
        isLoadingRecentSales.value = false;
    }
};

// ==========================================
// SALE RETURN MODULE (POS RETURN SALE)
// ==========================================
const isReturnSaleModalOpen = ref(false);
const returnSaleStep = ref<'search' | 'items'>('search');
const returnSearchQuery = ref('');
const returnSearchDate = ref('');
const isSearchingSalesForReturn = ref(false);
const returnSearchResults = ref<any[]>([]);
const selectedReturnSale = ref<any | null>(null);

interface PosReturnLineItem {
    sale_item_id: number;
    product_id: number;
    product_imei_id: number | null;
    product_name: string;
    imei_1: string | null;
    is_serialized: boolean;
    quantity: number;
    returned_quantity: number;
    remaining_quantity: number;
    unit_price: number;
    return_qty: number;
    is_selected: boolean;
}

const returnLineItems = ref<PosReturnLineItem[]>([]);
const returnRefundMethod = ref<string>('cash');
const returnNotes = ref('');
const customRefundAmount = ref<string>('');
const isSubmittingReturn = ref(false);

const completedReturnData = ref<any | null>(null);
const isReturnReceiptModalOpen = ref(false);

const searchSalesForReturn = async () => {
    isSearchingSalesForReturn.value = true;
    try {
        const params = new URLSearchParams();
        if (returnSearchQuery.value.trim()) {
            params.append('search', returnSearchQuery.value.trim());
        }
        if (returnSearchDate.value) {
            params.append('date', returnSearchDate.value);
        }
        const res = await fetch(`/${currentTeamSlug.value}/pos/sales-search?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        if (res.ok) {
            const data = await res.json();
            returnSearchResults.value = Array.isArray(data) ? data : [];
        }
    } catch (err) {
        console.error('Failed to search sales for return:', err);
        toast.error('Search failed', { description: 'Could not fetch sales from server.' });
    } finally {
        isSearchingSalesForReturn.value = false;
    }
};

const openReturnSaleModal = () => {
    returnSaleStep.value = 'search';
    returnSearchQuery.value = '';
    returnSearchDate.value = '';
    selectedReturnSale.value = null;
    returnLineItems.value = [];
    isReturnSaleModalOpen.value = true;
    searchSalesForReturn();
};

const openReturnSaleForSpecificSale = (sale: any) => {
    selectSaleForReturn(sale);
    isReturnSaleModalOpen.value = true;
};

const selectSaleForReturn = (sale: any) => {
    selectedReturnSale.value = sale;
    returnLineItems.value = (sale.items || []).map((item: any) => {
        const purchased = Number(item.quantity) || 1;
        const returned = Number(item.returned_quantity) || 0;
        const remaining = Number(item.remaining_quantity) !== undefined 
            ? Number(item.remaining_quantity) 
            : Math.max(0, purchased - returned);

        return {
            sale_item_id: item.id,
            product_id: item.product_id,
            product_imei_id: item.product_imei_id || null,
            product_name: item.product_name || item.product?.name || 'Item',
            imei_1: item.imei_1 || item.product_imei?.imei_1 || null,
            is_serialized: Boolean(item.is_serialized || item.product?.is_serialized),
            quantity: purchased,
            returned_quantity: returned,
            remaining_quantity: remaining,
            unit_price: Number(item.unit_price) || 0,
            return_qty: 0,
            is_selected: false,
        };
    });
    returnRefundMethod.value = 'cash';
    returnNotes.value = '';
    customRefundAmount.value = '';
    returnSaleStep.value = 'items';
};

const returnAllReturnableItems = () => {
    returnLineItems.value.forEach((item) => {
        if (item.remaining_quantity > 0) {
            item.return_qty = item.remaining_quantity;
            item.is_selected = true;
        }
    });
};

const clearReturnSelection = () => {
    returnLineItems.value.forEach((item) => {
        item.return_qty = 0;
        item.is_selected = false;
    });
};

const toggleReturnItemSelection = (item: PosReturnLineItem) => {
    item.is_selected = !item.is_selected;
    if (item.is_selected && item.return_qty === 0) {
        item.return_qty = item.is_serialized ? 1 : Math.min(1, item.remaining_quantity);
    } else if (!item.is_selected) {
        item.return_qty = 0;
    }
};

const updateReturnItemQty = (item: PosReturnLineItem, newQty: number) => {
    const clamped = Math.max(0, Math.min(newQty, item.remaining_quantity));
    item.return_qty = clamped;
    item.is_selected = clamped > 0;
};

const totalReturnAmount = computed(() => {
    return returnLineItems.value
        .filter((item) => item.is_selected && item.return_qty > 0)
        .reduce((sum, item) => sum + (item.return_qty * item.unit_price), 0);
});

const calculatedDueOffset = computed(() => {
    if (!selectedReturnSale.value) return 0;
    const saleDue = Number(selectedReturnSale.value.due_amount) || 0;
    const custDebt = selectedReturnSale.value.customer
        ? Math.max(0, Number(selectedReturnSale.value.customer.current_balance) || 0)
        : 0;
    return Math.min(totalReturnAmount.value, saleDue, custDebt);
});

const calculatedNetRefund = computed(() => {
    return Math.max(0, totalReturnAmount.value - calculatedDueOffset.value);
});

const effectiveRefundAmount = computed(() => {
    if (customRefundAmount.value !== '' && customRefundAmount.value !== null && !isNaN(Number(customRefundAmount.value))) {
        return Math.max(0, Number(customRefundAmount.value));
    }
    return calculatedNetRefund.value;
});

const submitPosReturn = async () => {
    const activeItems = returnLineItems.value.filter((i) => i.is_selected && i.return_qty > 0);
    if (activeItems.length === 0) {
        toast.error('No items selected', { description: 'Please select at least one product with return quantity > 0.' });
        return;
    }

    for (const item of activeItems) {
        if (item.return_qty > item.remaining_quantity) {
            toast.error('Invalid quantity', {
                description: `Return quantity for "${item.product_name}" exceeds remaining returnable quantity (${item.remaining_quantity}).`,
            });
            return;
        }
    }

    if (effectiveRefundAmount.value > calculatedNetRefund.value + 0.01) {
        toast.error('Invalid Refund Amount', {
            description: `Refund amount (Rs ${effectiveRefundAmount.value.toLocaleString()}) cannot exceed maximum payable refund of Rs ${calculatedNetRefund.value.toLocaleString()}.`,
        });
        return;
    }

    const payload = {
        sale_id: selectedReturnSale.value?.id || null,
        customer_id: selectedReturnSale.value?.customer?.id || null,
        refund_amount: effectiveRefundAmount.value,
        refund_payment_method: returnRefundMethod.value,
        notes: returnNotes.value || null,
        items: activeItems.map((i) => ({
            sale_item_id: i.sale_item_id,
            product_id: i.product_id,
            product_imei_id: i.product_imei_id || null,
            quantity: i.return_qty,
            unit_price: i.unit_price,
        })),
    };

    isSubmittingReturn.value = true;
    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        const res = await fetch(`/${currentTeamSlug.value}/pos/returns`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();
        if (res.ok && data.success) {
            toast.success('Sale Return Processed Successfully', {
                description: `Return #${data.return?.return_no || ''} completed. Stock restored to inventory.`,
            });

            // Restore in-memory product stock in POS props
            activeItems.forEach((item) => {
                const prod = props.products.find((p) => p.id === item.product_id);
                if (prod) {
                    prod.stock_quantity = (prod.stock_quantity || 0) + item.return_qty;
                    if (prod.is_serialized && item.imei_1 && prod.in_stock_imeis) {
                        const exists = prod.in_stock_imeis.some((im: any) => im.id === item.product_imei_id);
                        if (!exists && item.product_imei_id) {
                            prod.in_stock_imeis.push({
                                id: item.product_imei_id,
                                product_id: item.product_id,
                                imei_1: item.imei_1,
                                status: 'in_stock',
                                condition: 'used',
                                pta_status: 'approved',
                                purchase_cost: item.unit_price,
                                warranty_days: 0,
                            });
                        }
                    }
                }
            });

            completedReturnData.value = {
                ...data.return,
                original_sale: selectedReturnSale.value,
                due_offset: calculatedDueOffset.value,
                items: activeItems,
            };

            isReturnSaleModalOpen.value = false;
            isReturnReceiptModalOpen.value = true;
        } else {
            toast.error('Return Failed', {
                description: data.message || Object.values(data.errors || {}).flat().join(' ') || 'Could not process sale return.',
            });
        }
    } catch (err: any) {
        console.error('Return error:', err);
        toast.error('Return Request Error', { description: err?.message || 'Server error processing return.' });
    } finally {
        isSubmittingReturn.value = false;
    }
};

const printReturnReceipt = () => {
    window.print();
};

const openRecentSalesModal = () => {
    isRecentSalesModalOpen.value = true;
    fetchRecentSales();
};

const printRecentSaleInvoice = (sale: CompletedSale) => {
    activeReceipt.value = sale;
    isReceiptModalOpen.value = true;
};

const viewSaleDetails = (sale: CompletedSale) => {
    selectedSaleDetails.value = sale;
    isSaleDetailsModalOpen.value = true;
};

const formatShortProductSummary = (sale: CompletedSale) => {
    if (!sale.items || sale.items.length === 0) return '0 items';
    const firstProduct = sale.items[0]?.product?.name || 'Item';
    const shortName = firstProduct.length > 15 ? firstProduct.substring(0, 15) + '…' : firstProduct;
    if (sale.items.length === 1) {
        return shortName;
    }
    return `${shortName} (+${sale.items.length - 1})`;
};

const editSaleItems = ref<Array<{ id: number; product_name: string; imei?: string; quantity: number; unit_price: number }>>([]);

const openEditSaleModal = (sale: CompletedSale) => {
    editingSale.value = sale;
    editSaleForm.value = {
        customer_id: sale.customer_id || '',
        cashier_id: sale.cashier_id || '',
        payment_method: sale.payment_method || 'cash',
        discount_amount: Number(sale.discount_amount) || 0,
        paid_amount: Number(sale.paid_amount) || 0,
    };
    editSaleItems.value = (sale.items || []).map((item) => ({
        id: item.id,
        product_name: item.product?.name || 'Item',
        imei: item.product_imei?.imei_1 || '',
        quantity: Number(item.quantity) || 1,
        unit_price: Number(item.unit_price) || 0,
    }));
    isEditSaleModalOpen.value = true;
};

const editSaleCalculatedSubtotal = computed(() => {
    return editSaleItems.value.reduce(
        (sum, item) => sum + (Number(item.quantity) || 0) * (Number(item.unit_price) || 0),
        0,
    );
});

const editSaleCalculatedNet = computed(() => {
    const subtotal = editSaleCalculatedSubtotal.value;
    const disc = Number(editSaleForm.value.discount_amount) || 0;
    return Math.max(0, subtotal - disc);
});

const editSaleCalculatedChange = computed(() => {
    const net = editSaleCalculatedNet.value;
    const paid = Number(editSaleForm.value.paid_amount) || 0;
    if (editSaleForm.value.payment_method === 'udhaar') {
        return 0;
    }
    return Math.max(0, paid - net);
});

const editSaleCalculatedUdhaar = computed(() => {
    const net = editSaleCalculatedNet.value;
    const paid = Number(editSaleForm.value.paid_amount) || 0;
    if (editSaleForm.value.payment_method === 'udhaar') {
        return Math.max(0, net - paid);
    }
    return paid < net ? Math.max(0, net - paid) : 0;
});

const saveEditSale = async () => {
    if (!editingSale.value) return;

    const invalidEditItem = editSaleItems.value.find((item) => Number(item.unit_price) > 1000000);
    if (invalidEditItem) {
        toast.error('قیمت کی حد سے تجاوز / Price Limit Exceeded', {
            description: `آئٹم "${invalidEditItem.product_name}" کی قیمت زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔ / Unit price cannot exceed Rs 1,000,000.`,
        });
        return;
    }

    if (editSaleForm.value.payment_method === 'udhaar' && !editSaleForm.value.customer_id) {
        toast.error('Customer Required', {
            description: 'Please select a customer for Udhaar (Khata) sale.',
        });
        return;
    }

    isSavingEditSale.value = true;
    try {
        const getCsrfToken = () => {
            const el = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
            if (el && el.content) return el.content;
            const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
            if (match) return decodeURIComponent(match[1]);
            return (page.props as any)?.csrf_token || '';
        };

        const csrfToken = getCsrfToken();

        const response = await fetch(`/${currentTeamSlug.value}/pos/sales/${editingSale.value.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-XSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                customer_id: editSaleForm.value.customer_id || null,
                cashier_id: editSaleForm.value.cashier_id || null,
                payment_method: editSaleForm.value.payment_method,
                discount_amount: editSaleForm.value.discount_amount,
                paid_amount: editSaleForm.value.paid_amount,
                items: editSaleItems.value.map((item) => ({
                    id: item.id,
                    quantity: item.quantity,
                    unit_price: item.unit_price,
                })),
            }),
        });

        if (response.ok) {
            const updatedSale = await response.json();
            toast.success('Sale Updated Successfully', {
                description: `Invoice #${updatedSale.invoice_no} has been updated in POS.`,
            });

            // Update item in lists
            const index = recentSalesList.value.findIndex((s) => s.id === updatedSale.id);
            if (index !== -1) {
                recentSalesList.value[index] = updatedSale;
            }
            if (selectedSaleDetails.value?.id === updatedSale.id) {
                selectedSaleDetails.value = updatedSale;
            }
            if (activeReceipt.value?.id === updatedSale.id) {
                activeReceipt.value = updatedSale;
            }

            isEditSaleModalOpen.value = false;
        } else {
            const errData = await response.json().catch(() => ({}));
            toast.error('Update Failed', {
                description: errData.message || 'Could not update sale. Please check values.',
            });
        }
    } catch (err) {
        console.error('Failed to update sale:', err);
        toast.error('Error', { description: 'An unexpected error occurred while updating sale.' });
    } finally {
        isSavingEditSale.value = false;
    }
};

const filteredRecentSales = computed(() => {
    let sales = recentSalesList.value;

    if (recentSalesPaymentFilter.value !== 'all') {
        sales = sales.filter((s) => s.payment_method === recentSalesPaymentFilter.value);
    }

    const q = recentSalesQuery.value.trim().toLowerCase();
    if (!q) return sales;

    return sales.filter((s) => {
        const inv = (s.invoice_no || '').toLowerCase();
        const cust = (s.customer?.name || '').toLowerCase();
        const phone = (s.customer?.phone || '').toLowerCase();
        const cashier = (s.cashier?.name || '').toLowerCase();
        return inv.includes(q) || cust.includes(q) || phone.includes(q) || cashier.includes(q);
    });
});

const recentSalesPage = ref(1);
const recentSalesPerPage = ref(10);
const totalRecentSalesPages = computed(() => {
    return Math.ceil(filteredRecentSales.value.length / recentSalesPerPage.value) || 1;
});
const paginatedRecentSales = computed(() => {
    const start = (recentSalesPage.value - 1) * recentSalesPerPage.value;
    return filteredRecentSales.value.slice(start, start + recentSalesPerPage.value);
});
watch([recentSalesQuery, recentSalesPaymentFilter], () => {
    recentSalesPage.value = 1;
});

// Discount Mode ($ or %)
const discountMode = ref<'amount' | 'percent'>('amount');
const discountInput = ref<number | string>(0);

// Notes & Payments
const isPaidUserOverridden = ref(false);
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
    { key: 'cash', label: 'Cash' },
    { key: 'jazzcash', label: 'JazzCash' },
    { key: 'easypaisa', label: 'Easypaisa' },
    { key: 'bank', label: 'Bank' },
    { key: 'card', label: 'Card' },
    { key: 'udhaar', label: 'Udhaar (Khata)' },
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

const appliedTradeInAmount = computed(
    () => Number(selectedTradeIn.value?.purchase_amount) || 0,
);

const selectTradeIn = (purchase: TradeInItem) => {
    selectedTradeIn.value = purchase;
    tradeInTab.value = 'apply';
    isTradeInModalOpen.value = false;
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
            router.reload({
                only: ['usedPhonePurchases'],
                onSuccess: (page) => {
                    const refreshed =
                        (page.props.usedPhonePurchases as TradeInItem[]) ||
                        props.usedPhonePurchases;
                    const created = refreshed.find(
                        (p) => p.imei_1 === submittedImei,
                    );
                    if (created) {
                        selectTradeIn(created);
                        toast.success('Trade-In Added', {
                            description: `Rs ${Number(created.purchase_amount).toLocaleString()} credit applied to this bill.`,
                        });
                    }
                },
            });
        },
        onError: (errors) => {
            const errorMsg =
                Object.values(errors).flat().join(' ') ||
                'Could not register the trade-in.';
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
        toast.error('Cart is Empty', {
            description: 'Search items to add before holding sale.',
        });
        return;
    }
    const customerName = selectedCustomer.value
        ? selectedCustomer.value.name
        : 'Walk-in Customer';
    const heldItem: HeldSale = {
        id: 'HOLD-' + Math.floor(1000 + Math.random() * 9000),
        customer_id: selectedCustomerId.value,
        customer_name: customerName,
        cart: JSON.parse(JSON.stringify(cart.value)),
        discount: discountInput.value,
        total: netPayable.value,
        held_at: new Date().toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        }),
    };

    heldSales.value.unshift(heldItem);
    saveHeldSalesToStorage();

    cart.value = [];
    discountInput.value = 0;
    isPaidUserOverridden.value = false;
    paidInput.value = 0;
    notesInput.value = '';

    toast.success('Sale Saved to Hold!', {
        description: `Ref: ${heldItem.id} (${heldItem.cart.length} items)`,
    });
};

const restoreHeldSale = (held: HeldSale, index: number) => {
    isPaidUserOverridden.value = false;
    cart.value = JSON.parse(JSON.stringify(held.cart));
    discountInput.value = held.discount;
    selectedCustomerId.value = held.customer_id;
    heldSales.value.splice(index, 1);
    saveHeldSalesToStorage();
    isHeldSalesModalOpen.value = false;
    toast.success('Held Sale Loaded into Cart!');
};

const deleteHeldSale = (index: number) => {
    heldSales.value.splice(index, 1);
    saveHeldSalesToStorage();
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
        if (p.category) {
            const cat = p.category.trim().toLowerCase();
            if (
                !cat.includes('smartphone') &&
                !cat.includes('mobile handset') &&
                !cat.includes('mobile phone') &&
                cat !== 'mobiles' &&
                cat !== 'phones'
            ) {
                set.add(p.category);
            }
        }
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
        const catParam =
            selectedCategory.value !== 'all'
                ? `&category=${encodeURIComponent(selectedCategory.value)}`
                : '';
        const url = `/${currentTeamSlug.value}/pos/products?search=${encodeURIComponent(q)}${catParam}`;
        const res = await fetch(url, {
            headers: { Accept: 'application/json' },
        });
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
    if (!showSearchDropdown.value || searchApiResults.value.length === 0)
        return;
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

const isCustomerDropdownOpen = ref(false);
const customerSearchQuery = ref('');
const customerDropdownRef = ref<HTMLElement | null>(null);

const filteredCustomerOptions = computed(() => {
    const q = customerSearchQuery.value.trim().toLowerCase();
    if (!q) return props.customers;
    return props.customers.filter(
        (c) =>
            c.name.toLowerCase().includes(q) ||
            (c.phone && c.phone.toLowerCase().includes(q)),
    );
});

const selectCustomer = (customerId: string) => {
    selectedCustomerId.value = customerId;
    isCustomerDropdownOpen.value = false;
    customerSearchQuery.value = '';
};

const handleClickOutside = (event: MouseEvent) => {
    if (
        searchContainerRef.value &&
        !searchContainerRef.value.contains(event.target as Node)
    ) {
        closeSearchDropdown();
    }
    if (
        customerDropdownRef.value &&
        !customerDropdownRef.value.contains(event.target as Node)
    ) {
        isCustomerDropdownOpen.value = false;
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

const selectApiProduct = (
    product: ProductItem,
    specificImei?: ProductImeiItem,
) => {
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

const includePreviousDue = ref(true);
const useAdvanceCredit = ref(true);

const selectedCustomer = computed(() => {
    if (selectedCustomerId.value === 'walk_in') return null;
    return (
        props.customers.find(
            (c) => c.id === Number(selectedCustomerId.value),
        ) || null
    );
});

const customerPreviousDue = computed(() => {
    if (!selectedCustomer.value) return 0;
    const bal = Number(selectedCustomer.value.current_balance) || 0;
    return bal > 0 ? bal : 0;
});

const customerPreviousAdvance = computed(() => {
    if (!selectedCustomer.value) return 0;
    const bal = Number(selectedCustomer.value.current_balance) || 0;
    return bal < 0 ? Math.abs(bal) : 0;
});

const subtotal = computed(() => {
    return cart.value.reduce(
        (acc, item) =>
            acc + item.quantity * item.unit_price - (item.item_discount || 0),
        0,
    );
});

const calculatedDiscountAmount = computed(() => {
    const inputVal = Number(discountInput.value) || 0;
    if (discountMode.value === 'percent') {
        return Math.min(
            subtotal.value,
            Math.round((subtotal.value * inputVal) / 100),
        );
    }
    return Math.min(inputVal, subtotal.value);
});

const netPayable = computed(() => {
    return Math.max(0, subtotal.value - calculatedDiscountAmount.value);
});

const netPayableAfterTradeIn = computed(() => {
    return Math.max(0, netPayable.value - appliedTradeInAmount.value);
});

const appliedAdvanceCredit = computed(() => {
    if (!useAdvanceCredit.value) return 0;
    return Math.min(netPayableAfterTradeIn.value, customerPreviousAdvance.value);
});

const currentSaleNetPayable = computed(() => {
    return Math.max(0, netPayableAfterTradeIn.value - appliedAdvanceCredit.value);
});

const payablePreviousDue = computed(() => {
    return includePreviousDue.value ? customerPreviousDue.value : 0;
});

const grandTotalPayable = computed(() => {
    return currentSaleNetPayable.value + payablePreviousDue.value;
});

const effectivePaid = computed(() => {
    if (paymentMethod.value === 'split') {
        return splitTotal.value;
    }
    return Number(paidInput.value) || 0;
});

const duePayment = computed(() => {
    return Math.max(0, grandTotalPayable.value - effectivePaid.value);
});

const changeAmount = computed(() => {
    return Math.max(0, effectivePaid.value - grandTotalPayable.value);
});

watch(
    grandTotalPayable,
    (newPayable) => {
        if (!isPaidUserOverridden.value) {
            paidInput.value = newPayable;
        }
    },
    { immediate: true },
);

watch(
    () => cart.value.length,
    (newLen) => {
        if (newLen === 0) {
            isPaidUserOverridden.value = false;
            paidInput.value = 0;
        }
    },
);

const splitRemaining = computed(() =>
    Math.max(0, grandTotalPayable.value - splitTotal.value),
);

const fillSplitRemaining = () => {
    splitAmounts.value.cash =
        (Number(splitAmounts.value.cash) || 0) + splitRemaining.value;
};

watch(
    () => props.latestSale,
    (newSale) => {
        if (newSale) {
            activeReceipt.value = newSale;
            isReceiptModalOpen.value = true;
            cart.value = [];
            discountInput.value = 0;
            isPaidUserOverridden.value = false;
            paidInput.value = 0;
            notesInput.value = '';
            paymentMethod.value = posSettings.value.defaultPayment || 'cash';
            splitAmounts.value = {
                cash: 0,
                jazzcash: 0,
                easypaisa: 0,
                bank: 0,
                card: 0,
                udhaar: 0,
            };
            selectedCustomerId.value = 'walk_in';
            selectedTradeIn.value = null;
        }
    },
    { immediate: true },
);

const openRecentReceipt = () => {
    if (props.latestSale) {
        activeReceipt.value = props.latestSale;
        isReceiptModalOpen.value = true;
    } else if (activeReceipt.value) {
        isReceiptModalOpen.value = true;
    } else {
        toast.info('No Recent Receipt', {
            description: 'No recent sale invoice found in current session.',
        });
    }
};

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
                        (i.imei_2 &&
                            i.imei_2.toLowerCase() === q.toLowerCase()),
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
                const existing = cart.value.find(
                    (item) => item.key === fallbackKey,
                );
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
        toast.success('Item Added to Invoice', {
            description: `"${fullName}" added to cart.`,
        });
    }
};

const removeCartItem = (index: number) => {
    cart.value.splice(index, 1);
};

const clearCart = () => {
    if (cart.value.length === 0) return;
    cart.value = [];
    discountInput.value = 0;
    isPaidUserOverridden.value = false;
    paidInput.value = 0;
    notesInput.value = '';
    paymentMethod.value = posSettings.value.defaultPayment || 'cash';
    splitAmounts.value = {
        cash: 0,
        jazzcash: 0,
        easypaisa: 0,
        bank: 0,
        card: 0,
        udhaar: 0,
    };
    selectedCustomerId.value = 'walk_in';
    selectedTradeIn.value = null;
    toast.info('Terminal Reset', {
        description: 'Cart and invoice inputs cleared.',
    });
};

const addQuickCashPreset = (amount: number) => {
    isPaidUserOverridden.value = true;
    const current = Number(paidInput.value) || 0;
    paidInput.value = current + amount;
};

const saveSale = () => {
    if (cart.value.length === 0) {
        toast.error('Cart is Empty', {
            description: 'Please add products before processing payment.',
        });
        return;
    }

    const isWalkIn = selectedCustomerId.value === 'walk_in';
    const isUdhaar = paymentMethod.value === 'udhaar';
    const isSplitWithUdhaar =
        paymentMethod.value === 'split' &&
        ((Number(splitAmounts.value.udhaar) || 0) > 0 ||
            splitTotal.value < grandTotalPayable.value);
    const hasUnpaidDue = duePayment.value > 0;

    if (isWalkIn && (isUdhaar || isSplitWithUdhaar || hasUnpaidDue)) {
        const unpaidAmount = isUdhaar
            ? grandTotalPayable.value
            : duePayment.value;
        toast.error('Customer Account Required for Due / Udhaar', {
            description: `Walk-In Customers cannot have an unpaid due of Rs ${unpaidAmount.toLocaleString()}. Please select or register a customer account.`,
        });
        isCustomerModalOpen.value = true;
        return;
    }

    const invalidCartItem = cart.value.find((item) => Number(item.unit_price) > 1000000);
    if (invalidCartItem) {
        toast.error('قیمت کی حد سے تجاوز / Price Limit Exceeded', {
            description: `آئٹم "${invalidCartItem.name}" کی قیمت زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔ / Unit price cannot exceed Rs 1,000,000.`,
        });
        return;
    }

    const payload: any = {
        customer_id:
            selectedCustomerId.value === 'walk_in'
                ? null
                : Number(selectedCustomerId.value),
        salesman_id: selectedSalesmanId.value ? Number(selectedSalesmanId.value) : null,
        sale_date: rawSaleDate.value || null,
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
        payload.paid_amount =
            Number(paidInput.value) ?? grandTotalPayable.value;
    }

    router.post(pos.sales.store(currentTeamSlug.value).url, payload, {
        preserveScroll: true,
        onSuccess: (page) => {
            // Instantly decrement stock in UI props array
            cart.value.forEach((cartItem) => {
                const prod = props.products.find(
                    (p) => p.id === cartItem.product_id,
                );
                if (prod) {
                    prod.stock_quantity = Math.max(
                        0,
                        prod.stock_quantity - cartItem.quantity,
                    );
                    if (prod.is_serialized && cartItem.product_imei_id) {
                        const imeis =
                            prod.in_stock_imeis || (prod as any).inStockImeis;
                        if (Array.isArray(imeis)) {
                            const idx = imeis.findIndex(
                                (i: any) => i.id === cartItem.product_imei_id,
                            );
                            if (idx !== -1) imeis.splice(idx, 1);
                        }
                    }
                }
            });

            toast.success('Transaction Completed', {
                description:
                    'Sale processed and invoice recorded successfully.',
            });
            isReceiptModalOpen.value = true;
        },
        onError: (errors) => {
            const errorMsg =
                Object.values(errors).flat().join(' ') ||
                'Could not complete transaction.';
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
            toast.success('Customer Registered', {
                description: 'New customer account created for credit ledger.',
            });

            const updatedCustomers =
                (page.props.customers as CustomerItem[]) || props.customers;
            const newCust = updatedCustomers.find(
                (c: CustomerItem) => c.phone === regPhone,
            );
            if (newCust) {
                selectedCustomerId.value = String(newCust.id);
            }
        },
        onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            toast.error('Registration Failed', {
                description: Array.isArray(firstErr)
                    ? firstErr[0]
                    : String(firstErr || 'Please check customer details.'),
            });
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
    } else if (e.ctrlKey && (e.key === 'd' || e.key === 'D')) {
        e.preventDefault();
        document.getElementById('sale-date-input')?.focus();
    } else if (e.altKey && (e.key === 'u' || e.key === 'U')) {
        e.preventDefault();
        document.getElementById('salesman-select-trigger')?.focus();
    } else if (e.key === 'F7') {
        e.preventDefault();
        openReturnSaleModal();
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

const paperWidthSize = ref<'80mm' | '58mm'>('80mm');

const shareWhatsAppInvoice = () => {
    if (!activeReceipt.value) return;
    const r = activeReceipt.value;
    const phoneRaw = r.customer?.phone || selectedCustomer.value?.phone || '';
    const cleanPhone = phoneRaw.replace(/[^0-9]/g, '');

    let text = `*${props.shopInfo?.name || currentShopName.value}*\n`;
    text += `*Official Sale Invoice*\n`;
    text += `---------------------------------\n`;
    text += `Invoice #: ${r.invoice_no}\n`;
    text += `Customer: ${r.customer?.name || 'Walk-In Customer'}\n`;
    text += `Payment Method: ${r.payment_method?.toUpperCase() || 'CASH'}\n`;
    text += `Net Amount: Rs. ${Number(r.net_amount).toLocaleString('en-PK')}\n`;
    if (r.new_customer_balance !== undefined) {
        text += `Khata Balance: ${Number(r.new_customer_balance) > 0 ? 'Due Rs. ' + Number(r.new_customer_balance).toLocaleString() : 'Clear'}\n`;
    }
    text += `---------------------------------\n`;
    text += `Thank you for your business!`;

    const encoded = encodeURIComponent(text);
    const targetPhone = cleanPhone ? (cleanPhone.startsWith('92') ? cleanPhone : '92' + cleanPhone.replace(/^0/, '')) : '';
    const url = targetPhone
        ? `https://wa.me/${targetPhone}?text=${encoded}`
        : `https://wa.me/?text=${encoded}`;

    window.open(url, '_blank');
};

const printReceipt = () => {
    window.print();
};
</script>

<template>
    <Head :title="`${currentShopName} - POS Billing`" />

    <div
        class="flex h-screen w-screen flex-col overflow-hidden bg-[#f1f4fa] font-sans text-slate-800 antialiased select-none dark:bg-[#090d16] dark:text-slate-100"
    >
        <!-- TOP NAVBAR WITH ACTION TOOLS & EXIT CROSS BUTTON -->
        <header
            class="z-20 flex h-14 shrink-0 items-center justify-between border-b border-white/12 bg-gradient-to-r from-[#002654] via-[#003B7D] to-[#002752] px-4 text-white shadow-lg backdrop-blur-2xl"
        >
            <!-- Left: Quick Action Tools -->
            <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-1">
                <Link
                    :href="`/${currentTeamSlug}/mobile-sales`"
                    class="flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur-md transition hover:bg-white/20 active:scale-95"
                    title="Open mobile sales"
                >
                    <ExternalLink class="h-4 w-4 text-emerald-400" />
                    <span>Sell Mobile</span>
                </Link>

                <!-- Add Expense Button -->
                <button
                    type="button"
                    @click="isExpenseModalOpen = true"
                    class="flex items-center gap-1.5 rounded-xl border border-amber-400/40 bg-amber-500/20 px-3 py-1.5 text-xs font-bold text-amber-200 backdrop-blur-md transition hover:bg-amber-500/30 active:scale-95"
                    title="Record Quick Shop Expense"
                >
                    <Wallet class="h-4 w-4 text-amber-300" />
                    <span>+ Expense</span>
                </button>

                <!-- Recent Sales Button -->
                <button
                    type="button"
                    @click="openRecentSalesModal"
                    class="flex items-center gap-1.5 rounded-xl border border-sky-400/40 bg-sky-500/20 px-3 py-1.5 text-xs font-bold text-sky-200 backdrop-blur-md transition hover:bg-sky-500/30 active:scale-95"
                    title="View & Re-print Recent Invoices"
                >
                    <History class="h-4 w-4 text-sky-300" />
                    <span>Recent Sales</span>
                </button>

                <!-- Return Sale Button -->
                <button
                    type="button"
                    @click="openReturnSaleModal"
                    class="flex items-center gap-1.5 rounded-xl border border-rose-400/40 bg-rose-500/20 px-3 py-1.5 text-xs font-bold text-rose-200 backdrop-blur-md transition hover:bg-rose-500/30 active:scale-95"
                    title="Customer Sale Return & Stock Restore (F7)"
                >
                    <RotateCcw class="h-4 w-4 text-rose-300" />
                    <span>Return Sale</span>
                </button>

                <!-- POS Settings Button -->
                <button
                    type="button"
                    @click="isSettingsModalOpen = true"
                    class="flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur-md transition hover:bg-white/20 active:scale-95"
                    title="POS Terminal Settings"
                >
                    <Settings class="h-4 w-4" />
                    <span class="hidden sm:inline">Settings</span>
                </button>

                <!-- Fullscreen Toggle Button -->
                <button
                    type="button"
                    @click="toggleFullscreen"
                    class="flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur-md transition hover:bg-white/20 active:scale-95"
                    title="Toggle Fullscreen Mode"
                >
                    <component :is="isFullscreen ? Minimize2 : Maximize2" class="h-4 w-4" />
                    <span class="hidden sm:inline">{{ isFullscreen ? 'Exit Full' : 'Fullscreen' }}</span>
                </button>

                <!-- Shortcuts Help Button -->
                <button
                    type="button"
                    @click="isShortcutsModalOpen = true"
                    class="flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur-md transition hover:bg-white/20 active:scale-95"
                    title="Keyboard Shortcuts (F1-F12)"
                >
                    <Keyboard class="h-4 w-4" />
                    <span class="hidden sm:inline">Shortcuts</span>
                </button>
            </div>

            <!-- Right: Exit POS Cross Button -->
            <div class="flex items-center gap-2">
                <Link
                    :href="dashboardUrl"
                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-white/20 bg-white/10 text-white backdrop-blur-md transition hover:bg-rose-600 hover:border-rose-500 hover:text-white active:scale-95"
                    title="Exit POS & Return to Website"
                >
                    <X class="h-5 w-5" />
                </Link>
            </div>
        </header>

        <!-- MAIN LAYOUT CONTENT -->
        <div
            class="flex flex-1 gap-3.5 overflow-hidden bg-transparent p-3.5 text-slate-800"
        >
            <!-- LEFT COLUMN: Search, Filters & Interactive Cart (~68% width) -->
            <div
                class="glass-card flex flex-1 flex-col space-y-3.5 overflow-hidden rounded-3xl border border-slate-200/90 bg-white/95 p-4 shadow-[0_16px_40px_rgba(0,35,90,0.06)] backdrop-blur-xl dark:border-white/10 dark:bg-[#131b2e]"
            >
                <!-- Category Filter Pills Bar -->
                <div
                    class="flex scrollbar-none items-center gap-1.5 overflow-x-auto pb-1"
                >
                    <button
                        type="button"
                        @click="selectedCategory = 'all'"
                        :class="
                            selectedCategory === 'all'
                                ? 'bg-gradient-to-r from-[#003B7D] to-[#004f9e] font-black text-white shadow-md'
                                : 'bg-slate-100 font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200'
                        "
                        class="flex shrink-0 items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs transition active:scale-95"
                    >
                        <Layers class="h-3.5 w-3.5" />
                        <span>All Items</span>
                    </button>
                    <button
                        v-for="cat in availableCategories"
                        :key="cat"
                        type="button"
                        @click="selectedCategory = cat"
                        :class="
                            selectedCategory === cat
                                ? 'bg-gradient-to-r from-[#003B7D] to-[#004f9e] font-black text-white shadow-md'
                                : 'bg-slate-100 font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200'
                        "
                        class="shrink-0 rounded-xl px-3.5 py-1.5 text-xs capitalize transition active:scale-95"
                    >
                        {{ cat }}
                    </button>
                </div>

                <!-- ROW 1: Customer Selection Card (Standalone Row) -->
                <div
                    class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-3.5 shadow-xs dark:border-slate-800 dark:bg-slate-900/60"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <label
                            class="flex items-center gap-1.5 text-xs font-black tracking-wide text-[#003B7D] dark:text-blue-400"
                        >
                            <User class="h-4 w-4" />
                            <span>Select Customer</span>
                        </label>
                        <button
                            type="button"
                            @click="isCustomerModalOpen = true"
                            class="flex items-center gap-1 text-xs font-bold text-[#003B7D] hover:underline dark:text-blue-400"
                        >
                            <UserPlus class="h-3.5 w-3.5" />
                            <span>+ New Customer</span>
                        </button>
                    </div>

                    <!-- Custom Searchable Combobox Trigger & Dropdown -->
                    <div ref="customerDropdownRef" class="relative flex-1">
                        <button
                            id="customer-select-trigger"
                            type="button"
                            @click="isCustomerDropdownOpen = !isCustomerDropdownOpen"
                            class="h-12 w-full flex items-center justify-between rounded-xl border border-slate-300 bg-white px-3.5 text-sm font-bold text-slate-900 shadow-xs transition hover:border-[#003B7D] focus:border-[#003B7D] focus:ring-2 focus:ring-[#003B7D]/20 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        >
                            <div class="flex items-center gap-2 truncate">
                                <span class="truncate text-slate-900 dark:text-white font-bold">
                                    {{ selectedCustomer ? `${selectedCustomer.name} (${selectedCustomer.phone})` : 'Walk In Customer' }}
                                </span>
                                <span
                                    v-if="selectedCustomer && Number(selectedCustomer.current_balance) > 0"
                                    class="shrink-0 rounded-md bg-red-100 px-2 py-0.5 text-xs font-black text-red-600 dark:bg-red-950/80 dark:text-red-400 border border-red-200 dark:border-red-900/50"
                                >
                                    Due: Rs {{ Number(selectedCustomer.current_balance).toLocaleString() }}
                                </span>
                                <span
                                    v-else-if="selectedCustomer && Number(selectedCustomer.current_balance) < 0"
                                    class="shrink-0 rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-black text-emerald-600 dark:bg-emerald-950/80 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50"
                                >
                                    Advance: Rs {{ Math.abs(Number(selectedCustomer.current_balance)).toLocaleString() }}
                                </span>
                            </div>
                            <ChevronDown class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': isCustomerDropdownOpen }" />
                        </button>

                        <!-- Dropdown Menu -->
                        <div
                            v-if="isCustomerDropdownOpen"
                            class="absolute left-0 right-0 top-full z-50 mt-1.5 max-h-72 w-full overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
                        >
                            <!-- Search Bar inside Dropdown -->
                            <div class="relative mb-1.5 p-1">
                                <Search class="absolute left-3 top-3 h-3.5 w-3.5 text-slate-400" />
                                <input
                                    v-model="customerSearchQuery"
                                    type="text"
                                    placeholder="Type name or phone to search..."
                                    class="h-8 w-full rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-3 text-xs font-semibold text-slate-900 focus:border-[#003B7D] focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    @click.stop
                                />
                            </div>

                            <!-- Options Scroll Area -->
                            <div class="max-h-56 overflow-y-auto space-y-0.5 [scrollbar-width:none]">
                                <!-- Walk-In Customer -->
                                <button
                                    type="button"
                                    @click="selectCustomer('walk_in')"
                                    class="w-full flex items-center justify-between rounded-lg px-3 py-2 text-left text-xs font-bold transition hover:bg-slate-100 dark:hover:bg-slate-800"
                                    :class="selectedCustomerId === 'walk_in' ? 'bg-blue-50 text-[#003B7D] dark:bg-blue-950/50 dark:text-blue-400' : 'text-slate-900 dark:text-white'"
                                >
                                    <span>Walk In Customer</span>
                                    <Check v-if="selectedCustomerId === 'walk_in'" class="h-3.5 w-3.5 text-[#003B7D] dark:text-blue-400" />
                                </button>

                                <!-- Customer Items -->
                                <button
                                    v-for="c in filteredCustomerOptions"
                                    :key="c.id"
                                    type="button"
                                    @click="selectCustomer(String(c.id))"
                                    class="w-full flex items-center justify-between rounded-lg px-3 py-2 text-left text-xs transition hover:bg-slate-100 dark:hover:bg-slate-800"
                                    :class="selectedCustomerId === String(c.id) ? 'bg-blue-50 dark:bg-blue-950/50' : ''"
                                >
                                    <div class="truncate font-bold text-slate-900 dark:text-white">
                                        {{ c.name }} <span class="font-normal text-slate-500">({{ c.phone }})</span>
                                    </div>

                                    <!-- Right Side Balance Pill (Only shown if non-zero due/advance) -->
                                    <div class="flex items-center gap-1.5 shrink-0 ml-2">
                                        <span
                                            v-if="Number(c.current_balance) > 0"
                                            class="rounded-md bg-red-100 px-2 py-0.5 text-[11px] font-black text-red-600 dark:bg-red-950/80 dark:text-red-400 border border-red-200 dark:border-red-900/50"
                                        >
                                            Due: Rs {{ Number(c.current_balance).toLocaleString() }}
                                        </span>
                                        <span
                                            v-else-if="Number(c.current_balance) < 0"
                                            class="rounded-md bg-emerald-100 px-2 py-0.5 text-[11px] font-black text-emerald-600 dark:bg-emerald-950/80 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50"
                                        >
                                            Advance: Rs {{ Math.abs(Number(c.current_balance)).toLocaleString() }}
                                        </span>
                                        <Check v-if="selectedCustomerId === String(c.id)" class="h-3.5 w-3.5 text-[#003B7D] dark:text-blue-400" />
                                    </div>
                                </button>

                                <div v-if="filteredCustomerOptions.length === 0" class="p-3 text-center text-xs text-slate-400 font-medium">
                                    No matching customer found.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-row: Sale Date (Ctrl+D) & Salesman (Alt+U) -->
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <!-- Sale Date -->
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Sale Date <span class="text-[11px] font-medium text-slate-400">(Ctrl+D)</span>
                            </label>
                            <div class="relative flex h-11 items-center rounded-xl border border-slate-300 bg-white px-3 shadow-xs transition focus-within:border-[#003B7D] focus-within:ring-2 focus-within:ring-[#003B7D]/20 dark:border-slate-700 dark:bg-slate-800">
                                <input
                                    id="sale-date-input"
                                    type="datetime-local"
                                    v-model="rawSaleDate"
                                    class="absolute inset-0 z-10 h-full w-full opacity-0 cursor-pointer"
                                />
                                <span class="flex-1 truncate text-xs font-bold text-slate-900 dark:text-white pointer-events-none">
                                    {{ formattedSaleDateDisplay }}
                                </span>
                                <button
                                    v-if="rawSaleDate"
                                    type="button"
                                    @click.stop="resetSaleDate"
                                    title="Reset to current time"
                                    class="z-20 flex h-5 w-5 items-center justify-center rounded-full text-slate-400 hover:bg-slate-200 hover:text-slate-700 dark:hover:bg-slate-700 dark:hover:text-white"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Salesman -->
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Salesman <span class="text-[11px] font-medium text-slate-400">(Alt+U)</span>
                            </label>
                            <div class="relative flex h-11 items-center rounded-xl border border-slate-300 bg-white px-3 shadow-xs transition focus-within:border-[#003B7D] focus-within:ring-2 focus-within:ring-[#003B7D]/20 dark:border-slate-700 dark:bg-slate-800">
                                <select
                                    id="salesman-select-trigger"
                                    v-model="selectedSalesmanId"
                                    class="h-full w-full bg-transparent text-xs font-bold text-slate-900 focus:outline-none dark:text-white cursor-pointer"
                                >
                                    <option v-for="s in (salesmen || [])" :key="s.id" :value="s.id">
                                        {{ s.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ROW 2: Live Product Search Bar (Standalone Separate Row) -->
                <div
                    class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-3.5 shadow-xs dark:border-slate-800 dark:bg-slate-900/60"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <label
                            class="flex items-center gap-1.5 text-xs font-black tracking-wide text-[#003B7D] dark:text-blue-400"
                        >
                            <Barcode class="h-4 w-4" />
                            <span>Search Product / Barcode</span>
                        </label>
                    </div>

                    <div
                        class="flex items-center gap-2"
                        ref="searchContainerRef"
                    >
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
                                placeholder="Scan barcode or type model name, SKU, IMEI..."
                                class="h-12 w-full rounded-xl border border-slate-300 bg-white px-4 pr-10 text-sm font-semibold text-slate-900 transition placeholder:text-slate-400 focus:border-[#003B7D] focus:ring-2 focus:ring-[#003B7D]/20 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            />
                            <Search
                                class="pointer-events-none absolute top-3.5 right-3.5 h-5 w-5 text-slate-400"
                            />

                            <!-- Live Search API Dropdown Results -->
                            <div
                                v-if="
                                    showSearchDropdown && searchScanQuery.trim()
                                "
                                class="absolute top-full right-0 left-0 z-50 mt-1.5 max-h-80 space-y-1 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl dark:border-slate-700/80 dark:bg-slate-900"
                            >
                                <div
                                    v-if="isSearchingApi"
                                    class="py-4 text-center text-xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Searching stock inventory...
                                </div>
                                <div
                                    v-else-if="searchApiResults.length === 0"
                                    class="py-4 text-center text-xs text-slate-400"
                                >
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
                                            ? 'scale-[0.99] border-[#003B7D] bg-[#003B7D] text-white shadow-md dark:border-blue-500 dark:bg-blue-600'
                                            : 'border-slate-200/80 bg-slate-50/80 text-slate-800 hover:bg-slate-100 dark:border-slate-700/80 dark:bg-slate-800/80 dark:text-slate-200 dark:hover:bg-slate-700/90',
                                        'flex cursor-pointer items-center justify-between rounded-xl border p-2.5 text-xs transition-all duration-150',
                                    ]"
                                >
                                    <div class="space-y-0.5">
                                        <div
                                            class="flex items-center gap-1.5 font-extrabold"
                                            :class="
                                                selectedSearchIndex === idx
                                                    ? 'text-white!'
                                                    : 'text-slate-900 dark:text-white'
                                            "
                                        >
                                            <span>{{
                                                formatProductName(
                                                    item.name,
                                                    item.brand,
                                                )
                                            }}</span>
                                            <span
                                                v-if="item.is_serialized"
                                                :class="
                                                    selectedSearchIndex === idx
                                                        ? 'bg-white/20 text-white'
                                                        : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300'
                                                "
                                                class="rounded px-1.5 py-0.5 text-[9px] font-black"
                                            >
                                                MOBILE
                                            </span>
                                        </div>
                                        <div
                                            class="flex items-center gap-2 text-[10px]"
                                            :class="
                                                selectedSearchIndex === idx
                                                    ? 'text-blue-100!'
                                                    : 'text-slate-500 dark:text-slate-400'
                                            "
                                        >
                                            <span
                                                >Category:
                                                {{
                                                    item.category || 'General'
                                                }}</span
                                            >
                                            <span v-if="item.barcode"
                                                >Barcode:
                                                {{ item.barcode }}</span
                                            >
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div
                                            class="text-sm font-black"
                                            :class="
                                                selectedSearchIndex === idx
                                                    ? 'text-white!'
                                                    : 'text-[#003B7D] dark:text-blue-400'
                                            "
                                        >
                                            Rs
                                            {{
                                                Number(
                                                    item.sale_price,
                                                ).toLocaleString()
                                            }}
                                        </div>
                                        <div
                                            :class="
                                                selectedSearchIndex === idx
                                                    ? 'border-white/30 bg-white/20 text-white'
                                                    : item.stock_quantity > 0
                                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                        : 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950/60 dark:text-rose-300'
                                            "
                                            class="mt-0.5 inline-block rounded-full border px-2 py-0.5 text-[10px] font-bold"
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
                            class="flex h-12 items-center justify-center gap-1.5 rounded-xl bg-gradient-to-r from-[#003B7D] to-[#004f9e] px-4 text-xs font-black text-white shadow-md transition hover:brightness-105 active:scale-95 shrink-0"
                            title="Add Scanned Barcode Product"
                        >
                            <Plus class="h-4 w-4" />
                            <span>Add</span>
                        </button>
                    </div>
                </div>

                <!-- Modern Cart List Table -->
                <div
                    class="flex-1 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/50"
                >
                    <table class="w-full border-collapse text-left text-xs">
                        <thead
                            class="sticky top-0 border-b border-slate-200 bg-slate-100/95 text-[11px] font-black tracking-wider text-slate-700 uppercase backdrop-blur-xs"
                        >
                            <tr>
                                <th class="w-10 px-3 py-3 text-center">#</th>
                                <th class="px-3 py-3">PRODUCT</th>
                                <th class="w-28 px-3 py-3 text-center">QTY</th>
                                <th class="w-28 px-3 py-3 text-right">PRICE</th>
                                <th class="w-24 px-3 py-3 text-right">DISCOUNT</th>
                                <th class="w-32 px-3 py-3 text-right">TOTAL</th>
                                <th class="w-12 px-3 py-3 text-center">ACTION</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/60 bg-white">
                            <!-- Empty Cart Placeholder -->
                            <tr v-if="cart.length === 0">
                                <td colspan="7" class="py-16 text-center">
                                    <div
                                        class="mx-auto mb-2 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-slate-800 dark:text-blue-400"
                                    >
                                        <ShoppingCart
                                            class="h-7 w-7"
                                        />
                                    </div>
                                    <div
                                        class="text-sm font-extrabold text-slate-800 dark:text-slate-200"
                                    >
                                        Cart Empty
                                    </div>
                                    <div
                                        class="mx-auto mt-0.5 text-xs text-slate-400"
                                    >
                                        Scan or search items above to add to bill.
                                    </div>
                                </td>
                            </tr>

                            <!-- Cart Items Rows -->
                            <tr
                                v-for="(item, idx) in cart"
                                :key="item.key"
                                class="transition-colors hover:bg-slate-50/80"
                            >
                                <td
                                    class="px-3 py-3 text-center font-bold text-slate-400"
                                >
                                    {{ idx + 1 }}
                                </td>
                                <td class="px-3 py-3">
                                    <div
                                        class="text-xs font-extrabold text-slate-900"
                                    >
                                        {{ item.name }}
                                        <span
                                            v-if="item.brand"
                                            class="ml-1 text-[11px] font-semibold text-slate-500"
                                            >({{ item.brand }})</span
                                        >
                                    </div>

                                    <!-- Mobile Serialized IMEI Details Badges -->
                                    <div
                                        v-if="item.is_serialized"
                                        class="mt-1 flex flex-wrap items-center gap-1 text-[10px]"
                                    >
                                        <span
                                            v-if="item.imei_1"
                                            class="rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 font-mono font-bold text-slate-700"
                                        >
                                            IMEI: {{ item.imei_1 }}
                                        </span>
                                        <span
                                            v-if="item.pta_status"
                                            :class="
                                                item.pta_status === 'approved'
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                    : 'border-amber-200 bg-amber-50 text-amber-700'
                                            "
                                            class="rounded border px-1.5 py-0.5 font-black uppercase"
                                        >
                                            {{ item.pta_status }}
                                        </span>
                                        <span
                                            v-if="item.storage"
                                            class="rounded border border-indigo-200 bg-indigo-50 px-1.5 py-0.5 font-bold text-indigo-700"
                                        >
                                            {{ item.storage }}
                                        </span>
                                        <span
                                            v-if="item.color"
                                            class="rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 text-slate-600 capitalize"
                                        >
                                            {{ item.color }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 text-center">
                                    <div
                                        class="flex items-center justify-center gap-1"
                                    >
                                        <button
                                            type="button"
                                            @click="
                                                item.quantity > 1
                                                    ? item.quantity--
                                                    : removeCartItem(idx)
                                            "
                                            class="flex h-6 w-6 items-center justify-center rounded border border-slate-200 bg-slate-100 font-bold text-slate-700 hover:bg-slate-200"
                                        >
                                            -
                                        </button>
                                        <span
                                            class="w-8 text-center text-xs font-black text-slate-900"
                                            >{{ item.quantity }}</span
                                        >
                                        <button
                                            type="button"
                                            @click="
                                                !item.is_serialized &&
                                                item.quantity++
                                            "
                                            :disabled="item.is_serialized"
                                            class="flex h-6 w-6 items-center justify-center rounded border border-slate-200 bg-slate-100 font-bold text-slate-700 hover:bg-slate-200 disabled:opacity-40"
                                        >
                                            +
                                        </button>
                                    </div>
                                </td>

                                <td class="px-3 py-3 text-right">
                                    <input
                                        v-model.number="item.unit_price"
                                        type="number"
                                        min="0"
                                        max="1000000"
                                        class="w-22 rounded-lg border border-slate-300 px-2 py-1 text-right text-xs font-bold text-slate-900 focus:border-indigo-600 focus:outline-none"
                                    />
                                </td>

                                <td class="px-3 py-3 text-right">
                                    <input
                                        v-model.number="item.item_discount"
                                        type="number"
                                        class="w-20 rounded-lg border border-slate-300 px-2 py-1 text-right text-xs font-bold text-rose-600 focus:border-rose-500 focus:outline-none"
                                    />
                                </td>

                                <td
                                    class="px-3 py-3 text-right text-xs font-black text-indigo-700"
                                >
                                    Rs
                                    {{
                                        (
                                            item.quantity * item.unit_price -
                                            (item.item_discount || 0)
                                        ).toLocaleString()
                                    }}
                                </td>

                                <td class="px-3 py-3 text-center">
                                    <button
                                        type="button"
                                        @click="removeCartItem(idx)"
                                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
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

            <!-- RIGHT COLUMN: Modern Checkout Sidebar (Expanded Width Towards Left) -->
            <div
                class="glass-card flex w-96 shrink-0 flex-col justify-between space-y-2 overflow-hidden rounded-3xl border border-slate-200/90 bg-white/95 p-3.5 shadow-[0_16px_40px_rgba(0,35,90,0.06)] backdrop-blur-xl lg:w-[450px] xl:w-[480px] dark:border-white/10 dark:bg-[#131b2e]"
            >
                <!-- 1. Header -->
                <div
                    class="flex items-center justify-between border-b border-slate-200/80 pb-2 dark:border-slate-800"
                >
                    <div class="flex items-center gap-1.5">
                        <Receipt class="h-4 w-4 text-[#003B7D] dark:text-blue-400" />
                        <span
                            class="text-xs font-black tracking-wider text-slate-900 uppercase dark:text-white"
                            >Payment Summary</span
                        >
                    </div>
                    <span
                        class="rounded-full border border-[#003B7D]/20 bg-[#003B7D]/10 px-2.5 py-0.5 text-[10px] font-black text-[#003B7D] dark:bg-blue-950/60 dark:text-blue-300"
                    >
                        {{ cart.reduce((acc, i) => acc + i.quantity, 0) }} ITEMS
                    </span>
                </div>

                <!-- 2. Bill Discount Box (Label Outside on Top, Input Box Below) -->
                <div>
                    <label class="mb-1 block text-xs font-black text-slate-700 dark:text-slate-300">
                        Discount (F4)
                    </label>
                    <div class="flex items-center overflow-hidden rounded-xl border border-slate-300 bg-slate-50/80 shadow-xs focus-within:border-[#003B7D] focus-within:bg-white focus-within:ring-2 focus-within:ring-[#003B7D]/20 dark:border-slate-700 dark:bg-slate-800">
                        <span class="pl-3 text-xs font-bold text-slate-400">Rs</span>
                        <input
                            id="discount-input-field"
                            v-model="discountInput"
                            type="number"
                            placeholder="0"
                            class="h-10 w-full bg-transparent px-2.5 text-xs font-bold text-slate-900 focus:outline-none dark:text-white"
                        />
                        <div class="flex shrink-0 border-l border-slate-200 dark:border-slate-700">
                            <button
                                type="button"
                                @click="discountMode = 'amount'"
                                :class="discountMode === 'amount' ? 'bg-[#003B7D] font-black text-white' : 'bg-slate-100 font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                                class="h-10 px-3 text-xs font-extrabold transition"
                            >
                                Rs
                            </button>
                            <button
                                type="button"
                                @click="discountMode = 'percent'"
                                :class="discountMode === 'percent' ? 'bg-[#003B7D] font-black text-white' : 'bg-slate-100 font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                                class="h-10 px-3 text-xs font-extrabold transition"
                            >
                                %
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 3. Trade-In / Exchange Trigger -->
                <div>
                    <div v-if="selectedTradeIn" class="flex items-center justify-between rounded-xl border border-amber-300 bg-amber-50/90 px-3 py-1.5 text-xs dark:border-amber-700 dark:bg-amber-950/60">
                        <div class="flex items-center gap-1.5 font-bold text-amber-900 dark:text-amber-200 truncate">
                            <Repeat class="h-3.5 w-3.5 shrink-0" />
                            <span class="truncate">{{ selectedTradeIn.device_model }} (-Rs {{ appliedTradeInAmount.toLocaleString() }})</span>
                        </div>
                        <button type="button" @click="selectedTradeIn = null" class="font-black text-amber-600 hover:text-rose-600">✕</button>
                    </div>
                    <button
                        v-else
                        type="button"
                        @click="isTradeInModalOpen = true"
                        class="flex h-8 w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-amber-400/80 bg-amber-50/50 text-[11px] font-bold text-amber-800 transition hover:bg-amber-100 active:scale-95 dark:border-amber-600 dark:bg-amber-950/40 dark:text-amber-300"
                    >
                        <Repeat class="h-3.5 w-3.5" />
                        <span>+ Trade-In / Exchange</span>
                    </button>
                </div>

                <!-- 3.5 Customer Balance Adjustments (Advance Credit / Previous Due) -->
                <div v-if="customerPreviousAdvance > 0" class="rounded-xl border border-emerald-300 bg-emerald-50/90 p-2 text-xs text-emerald-900 shadow-xs dark:border-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-200">
                    <div class="flex items-center justify-between font-bold">
                        <span class="flex items-center gap-1">
                            <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                            Advance Credit Available:
                        </span>
                        <span class="font-black">Rs {{ customerPreviousAdvance.toLocaleString() }}</span>
                    </div>
                    <label class="mt-1.5 flex cursor-pointer items-center gap-2 rounded-lg border border-emerald-200 bg-white p-1.5 text-[11px] font-extrabold text-emerald-900 shadow-xs transition hover:bg-emerald-100/60 dark:border-emerald-900 dark:bg-slate-900 dark:text-emerald-200">
                        <input
                            type="checkbox"
                            v-model="useAdvanceCredit"
                            class="h-4 w-4 rounded border-emerald-400 text-emerald-600 focus:ring-emerald-500"
                        />
                        <span>Deduct Advance Credit (-Rs {{ Math.min(netPayableAfterTradeIn, customerPreviousAdvance).toLocaleString() }})</span>
                    </label>
                    <div v-if="useAdvanceCredit && customerPreviousAdvance > appliedAdvanceCredit" class="mt-1 text-[10px] font-medium text-emerald-600 dark:text-emerald-400">
                        Remaining Advance: Rs {{ (customerPreviousAdvance - appliedAdvanceCredit).toLocaleString() }}
                    </div>
                </div>

                <div v-if="customerPreviousDue > 0" class="rounded-xl border border-rose-300 bg-rose-50/90 p-2 text-xs shadow-xs dark:border-rose-800 dark:bg-rose-950/60">
                    <div class="flex items-center justify-between font-bold text-rose-900 dark:text-rose-200">
                        <span class="flex items-center gap-1">
                            <span class="inline-block h-2 w-2 rounded-full bg-rose-500"></span>
                            Previous Unpaid Due:
                        </span>
                        <span class="font-black text-rose-600 dark:text-rose-400">Rs {{ customerPreviousDue.toLocaleString() }}</span>
                    </div>
                    <label class="mt-1.5 flex cursor-pointer items-center gap-2 rounded-lg border border-rose-200 bg-white p-1.5 text-[11px] font-extrabold text-rose-900 shadow-xs transition hover:bg-rose-100/60 dark:border-rose-900 dark:bg-slate-900 dark:text-rose-200">
                        <input
                            type="checkbox"
                            v-model="includePreviousDue"
                            class="h-4 w-4 rounded border-rose-400 text-[#003B7D] focus:ring-[#003B7D]"
                        />
                        <span>Add Previous Due (+Rs {{ customerPreviousDue.toLocaleString() }})</span>
                    </label>
                </div>

                <!-- 4. Payment Method Selector Dropdown -->
                <div>
                    <label class="mb-1 block text-xs font-black text-slate-700 dark:text-slate-300">
                        Payment Method (Alt+M)
                    </label>
                    <div class="relative">
                        <select
                            id="payment-method-select"
                            v-model="paymentMethod"
                            class="h-10 w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3 py-1.5 text-xs font-bold text-slate-900 shadow-xs transition focus:border-[#003B7D] focus:bg-white focus:ring-2 focus:ring-[#003B7D]/20 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        >
                            <option value="cash">Cash</option>
                            <option value="bank">Bank Transfer / Cheque</option>
                            <option value="jazzcash">JazzCash</option>
                            <option value="easypaisa">Easypaisa</option>
                            <option value="card">Card</option>
                            <option value="split">Split Tender</option>
                            <option value="udhaar">Udhaar (Khata Ledger)</option>
                        </select>
                    </div>

                    <!-- Split Tender Inputs -->
                    <div v-if="paymentMethod === 'split'" class="mt-1.5 space-y-1.5 rounded-xl border border-[#003B7D]/20 bg-blue-50/60 p-2 dark:border-blue-900/60 dark:bg-slate-900/80">
                        <div class="flex items-center justify-between text-[10px] font-black text-[#003B7D] dark:text-blue-400">
                            <span>SPLIT TENDER</span>
                            <span>Allocated: Rs {{ splitTotal.toLocaleString() }}</span>
                        </div>
                        <div class="grid grid-cols-3 gap-1">
                            <div v-for="method in splitMethods" :key="method.key" class="space-y-0.5">
                                <label class="block text-[9px] font-bold text-slate-500 uppercase">{{ method.label }}</label>
                                <div class="flex items-center rounded-lg border border-slate-300 bg-white px-1.5 py-0.5 text-[11px] focus-within:border-[#003B7D] dark:border-slate-700 dark:bg-slate-800">
                                    <input v-model.number="splitAmounts[method.key]" type="number" min="0" placeholder="0" class="w-full bg-transparent font-bold text-slate-900 focus:outline-none dark:text-white" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Cash Received & Balance Rows -->
                <div class="space-y-1.5">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-0.5 block text-[10px] font-black text-slate-600 uppercase dark:text-slate-400">Paid (Alt+P)</label>
                            <div class="flex h-9 items-center rounded-xl border border-slate-300 bg-white px-2.5 focus-within:border-[#003B7D] dark:border-slate-700 dark:bg-slate-800">
                                <span class="mr-1 text-[11px] font-bold text-slate-400">Rs</span>
                                <input id="total-payment-input" v-model="paidInput" @input="isPaidUserOverridden = true" type="number" placeholder="0" class="w-full bg-transparent text-xs font-bold text-slate-900 focus:outline-none dark:text-white" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-0.5 block text-[10px] font-black text-slate-600 uppercase dark:text-slate-400">Due / Balance</label>
                            <div class="flex h-9 items-center rounded-xl border border-slate-200 bg-slate-100 px-2.5 text-xs font-black text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                Rs {{ duePayment.toLocaleString() }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. Grand Hero Checkout Card (Original Total, Final Total, You Save & Side-by-Side Save/Clear Buttons) -->
                <div class="rounded-2xl border border-white/20 bg-gradient-to-br from-[#002654] via-[#003B7D] to-[#002752] p-3 text-center text-white shadow-xl shadow-[#003B7D]/20">
                    <!-- Original Total (If Discount or Trade-in applied) -->
                    <div v-if="calculatedDiscountAmount > 0 || appliedTradeInAmount > 0" class="mb-0.5">
                        <div class="text-[10px] font-bold tracking-wider text-slate-300">Original Total</div>
                        <div class="text-sm font-extrabold text-slate-300 line-through">
                            Rs {{ subtotal.toLocaleString() }}
                        </div>
                    </div>

                    <!-- Final Total -->
                    <div class="my-1">
                        <div class="text-[10px] font-black tracking-widest text-emerald-400 uppercase">Final Total</div>
                        <div class="tnum text-2xl lg:text-3xl font-black text-emerald-400">
                            Rs {{ grandTotalPayable.toLocaleString() }}
                        </div>
                        <div v-if="appliedAdvanceCredit > 0 || payablePreviousDue > 0" class="mt-1 flex flex-wrap items-center justify-center gap-x-2 text-[10px] font-bold text-slate-300">
                            <span>Sale Net: Rs {{ netPayableAfterTradeIn.toLocaleString() }}</span>
                            <span v-if="appliedAdvanceCredit > 0" class="text-emerald-300 font-extrabold">Adv: -Rs {{ appliedAdvanceCredit.toLocaleString() }}</span>
                            <span v-if="payablePreviousDue > 0" class="text-rose-300 font-extrabold">Due: +Rs {{ payablePreviousDue.toLocaleString() }}</span>
                        </div>
                    </div>

                    <!-- You Save Highlight -->
                    <div v-if="calculatedDiscountAmount + appliedTradeInAmount > 0" class="mb-2.5 text-[11px] font-extrabold text-rose-300">
                        You Save: Rs {{ (calculatedDiscountAmount + appliedTradeInAmount).toLocaleString() }}
                    </div>

                    <!-- Side-by-Side Save & Clear Buttons inside the Blue Hero Card -->
                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <button
                            type="button"
                            @click="saveSale"
                            class="flex h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-2 text-xs font-black text-white shadow-md transition hover:bg-emerald-500 active:scale-95"
                            title="Complete & Save Sale (Ctrl+Enter / Alt+Enter)"
                        >
                            <CheckCircle class="h-4 w-4 shrink-0" />
                            <span>Save (Alt+Enter)</span>
                        </button>

                        <button
                            type="button"
                            @click="clearCart"
                            class="flex h-10 items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-2 text-xs font-black text-white shadow-md transition hover:bg-rose-500 active:scale-95"
                            title="Clear Invoice Cart"
                        >
                            <Trash2 class="h-4 w-4 shrink-0" />
                            <span>Clear (Alt+Del)</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- DIALOG 1: Register Customer -->
        <Dialog v-model:open="isCustomerModalOpen">
            <DialogContent
                class="max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <DialogHeader>
                    <DialogTitle class="text-lg font-black text-slate-900"
                        >Register New Customer</DialogTitle
                    >
                    <DialogDescription class="text-xs text-slate-500"
                        >Add customer details for credit ledger
                        (Khata).</DialogDescription
                    >
                </DialogHeader>

                <form
                    @submit.prevent="submitCustomerForm"
                    class="space-y-3 py-2"
                >
                    <div>
                        <label
                            class="mb-1 block text-xs font-bold text-slate-700"
                            >Full Name *</label
                        >
                        <input
                            v-model="customerForm.name"
                            placeholder="e.g. Ali Ahmed"
                            class="h-10 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-indigo-600 focus:outline-none"
                            required
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-bold text-slate-700"
                            >Mobile Phone *</label
                        >
                        <input
                            v-model="customerForm.phone"
                            placeholder="03001234567"
                            class="h-10 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-indigo-600 focus:outline-none"
                            required
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-bold text-slate-700"
                            >City / Address</label
                        >
                        <input
                            v-model="customerForm.address"
                            placeholder="Lahore"
                            class="h-10 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-indigo-600 focus:outline-none"
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
                            class="bg-gradient-to-r from-[#003B7D] to-[#004f9e] font-black text-white shadow-md hover:brightness-105"
                            >Save Customer</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- DIALOG: Trade-In / Exchange -->
        <Dialog v-model:open="isTradeInModalOpen">
            <DialogContent
                class="max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <DialogHeader>
                    <DialogTitle class="text-lg font-black text-slate-900"
                        >Trade-In / Exchange</DialogTitle
                    >
                    <DialogDescription class="text-xs text-slate-500">
                        Offset this bill using credit from a purchased used
                        phone, or register a new trade-in now.
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="mt-2 grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1"
                >
                    <button
                        type="button"
                        @click="tradeInTab = 'apply'"
                        :class="
                            tradeInTab === 'apply'
                                ? 'bg-white font-black text-slate-900 shadow'
                                : 'font-bold text-slate-500 hover:text-slate-700'
                        "
                        class="h-9 rounded-lg text-xs transition"
                    >
                        Apply Credit
                    </button>
                    <button
                        type="button"
                        @click="openNewTradeIn"
                        :class="
                            tradeInTab === 'new'
                                ? 'bg-white font-black text-slate-900 shadow'
                                : 'font-bold text-slate-500 hover:text-slate-700'
                        "
                        class="h-9 rounded-lg text-xs transition"
                    >
                        New Trade-In
                    </button>
                </div>

                <div class="max-h-[55vh] overflow-y-auto py-3">
                    <!-- APPLY CREDIT TAB -->
                    <div v-if="tradeInTab === 'apply'" class="space-y-2">
                        <div
                            v-if="props.usedPhonePurchases.length === 0"
                            class="py-8 text-center"
                        >
                            <Repeat
                                class="mx-auto mb-2 h-8 w-8 text-slate-300"
                            />
                            <p class="text-xs font-bold text-slate-500">
                                No unused trade-in credits available.
                            </p>
                            <button
                                type="button"
                                @click="openNewTradeIn"
                                class="mt-2 text-xs font-extrabold text-amber-600 hover:underline"
                            >
                                Register a new trade-in →
                            </button>
                        </div>
                        <button
                            v-for="purchase in props.usedPhonePurchases"
                            :key="purchase.id"
                            type="button"
                            @click="selectTradeIn(purchase)"
                            class="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 p-3 text-left transition hover:border-amber-400 hover:bg-amber-50"
                        >
                            <div class="min-w-0">
                                <div
                                    class="truncate text-xs font-black text-slate-900"
                                >
                                    {{ purchase.device_model }}
                                </div>
                                <div
                                    class="truncate text-[10px] font-semibold text-slate-500"
                                >
                                    {{ purchase.voucher_no }} • IMEI
                                    {{ purchase.imei_1 }} •
                                    <span class="text-slate-700">{{
                                        purchase.seller_name
                                    }}</span>
                                </div>
                                <div class="text-[10px] text-slate-400">
                                    {{
                                        new Date(
                                            purchase.created_at,
                                        ).toLocaleDateString()
                                    }}
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <div
                                    class="text-sm font-black text-emerald-600"
                                >
                                    Rs
                                    {{
                                        Number(
                                            purchase.purchase_amount,
                                        ).toLocaleString()
                                    }}
                                </div>
                                <div
                                    class="text-[10px] font-bold text-amber-600"
                                >
                                    APPLY
                                </div>
                            </div>
                        </button>
                    </div>

                    <!-- NEW TRADE-IN TAB -->
                    <form
                        v-else
                        @submit.prevent="submitTradeIn"
                        class="space-y-3"
                    >
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                                    >Seller Name *</label
                                >
                                <input
                                    v-model="tradeInForm.seller_name"
                                    placeholder="Full name of seller"
                                    class="h-10 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none"
                                    required
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                                    >Seller CNIC *</label
                                >
                                <input
                                    v-model="tradeInForm.seller_cnic"
                                    placeholder="e.g. 35202-1234567-8"
                                    class="h-10 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none"
                                    required
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                                    >Seller Phone</label
                                >
                                <input
                                    v-model="tradeInForm.seller_phone"
                                    placeholder="03001234567"
                                    class="h-10 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                                    >Seller Address</label
                                >
                                <input
                                    v-model="tradeInForm.seller_address"
                                    placeholder="City / address"
                                    class="h-10 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                                    >Device Model *</label
                                >
                                <input
                                    v-model="tradeInForm.device_model"
                                    placeholder="e.g. iPhone 11"
                                    class="h-10 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none"
                                    required
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                                    >Brand *</label
                                >
                                <input
                                    v-model="tradeInForm.brand"
                                    placeholder="e.g. Apple"
                                    class="h-10 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none"
                                    required
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label
                                    class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                                    >Color</label
                                >
                                <input
                                    v-model="tradeInForm.color"
                                    placeholder="Black"
                                    class="h-10 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                                    >Storage</label
                                >
                                <input
                                    v-model="tradeInForm.storage"
                                    placeholder="128GB"
                                    class="h-10 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                                    >Purchase Amount *</label
                                >
                                <input
                                    v-model="tradeInForm.purchase_amount"
                                    type="number"
                                    min="0"
                                    placeholder="45000"
                                    class="h-10 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none"
                                    required
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                                    >IMEI 1 *</label
                                >
                                <input
                                    v-model="tradeInForm.imei_1"
                                    placeholder="15-digit IMEI"
                                    class="h-10 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none"
                                    required
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                                    >IMEI 2</label
                                >
                                <input
                                    v-model="tradeInForm.imei_2"
                                    placeholder="Optional"
                                    class="h-10 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none"
                                />
                            </div>
                        </div>
                        <div
                            class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2"
                        >
                            <div>
                                <label
                                    class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                                    >PTA Status</label
                                >
                                <select
                                    v-model="tradeInForm.pta_status"
                                    class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none"
                                >
                                    <option value="approved">Approved</option>
                                    <option value="non_pta">Non-PTA</option>
                                    <option value="jv">JV</option>
                                    <option value="cpid">CPID</option>
                                    <option value="software">
                                        Software Unlock
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                                    >Payment Method</label
                                >
                                <select
                                    v-model="tradeInForm.payment_method"
                                    class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-semibold focus:border-amber-600 focus:outline-none"
                                >
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank Transfer</option>
                                    <option value="jazzcash">JazzCash</option>
                                    <option value="easypaisa">Easypaisa</option>
                                </select>
                            </div>
                        </div>
                        <label
                            class="flex cursor-pointer items-center gap-2 pt-1"
                        >
                            <input
                                v-model="tradeInForm.auto_add_stock"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500"
                            />
                            <span class="text-xs font-bold text-slate-700"
                                >Keep this phone in resale stock (auto created
                                IMEI entry)</span
                            >
                        </label>
                        <p
                            class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[10px] font-semibold text-slate-500"
                        >
                            ⚖️ Purchasing a used phone is recorded with the
                            seller's name, CNIC and agreement for legal
                            protection.
                        </p>

                        <DialogFooter class="pt-3">
                            <Button
                                type="button"
                                variant="outline"
                                @click="isTradeInModalOpen = false"
                                >Cancel</Button
                            >
                            <Button
                                type="submit"
                                :disabled="tradeInForm.processing"
                                class="bg-amber-600 font-bold text-white hover:bg-amber-700"
                            >
                                <span
                                    v-if="tradeInForm.processing"
                                    class="mr-1.5 inline-block h-3 w-3 animate-spin rounded-full border-2 border-white/40 border-t-white"
                                ></span>
                                Register & Apply Credit
                            </Button>
                        </DialogFooter>
                    </form>
                </div>
            </DialogContent>
        </Dialog>

        <!-- DIALOG 2: Thermal Receipt Print -->
        <Dialog v-model:open="isReceiptModalOpen">
            <DialogContent
                class="max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl z-[120]"
                overlayClass="z-[110]"
            >
                <DialogHeader class="no-print border-b border-slate-100 pb-3 pr-14">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <Receipt class="h-5 w-5 text-[#003B7D]" />
                            <DialogTitle class="text-base font-black text-slate-900">
                                Official Invoice Receipt
                            </DialogTitle>
                        </div>

                        <!-- Paper Width Selector -->
                        <div class="flex items-center rounded-xl bg-slate-100 p-0.5 dark:bg-slate-800">
                            <button
                                type="button"
                                @click="paperWidthSize = '80mm'"
                                :class="paperWidthSize === '80mm' ? 'bg-[#003B7D] font-black text-white shadow-xs' : 'text-slate-600 font-bold hover:text-slate-900 dark:text-slate-400'"
                                class="rounded-lg px-2.5 py-1 text-[11px] transition"
                            >
                                80mm
                            </button>
                            <button
                                type="button"
                                @click="paperWidthSize = '58mm'"
                                :class="paperWidthSize === '58mm' ? 'bg-[#003B7D] font-black text-white shadow-xs' : 'text-slate-600 font-bold hover:text-slate-900 dark:text-slate-400'"
                                class="rounded-lg px-2.5 py-1 text-[11px] transition"
                            >
                                58mm
                            </button>
                        </div>
                    </div>
                </DialogHeader>

                <div class="max-h-[65vh] overflow-y-auto py-2">
                    <ThermalReceipt
                        v-if="activeReceipt"
                        :receipt="activeReceipt as any"
                        :shop-info="shopInfo"
                        :paper-width="paperWidthSize"
                    />
                </div>

                <DialogFooter class="no-print flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isReceiptModalOpen = false"
                        class="rounded-xl font-bold"
                    >
                        Close
                    </Button>

                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            @click="shareWhatsAppInvoice"
                            class="bg-emerald-600 font-bold text-white hover:bg-emerald-500 rounded-xl shadow-xs"
                            title="Share Invoice summary via WhatsApp"
                        >
                            <MessageSquare class="mr-1.5 h-4 w-4" /> WhatsApp
                        </Button>

                        <Button
                            type="button"
                            @click="printReceipt"
                            class="bg-gradient-to-r from-[#003B7D] to-[#004f9e] font-black text-white shadow-md hover:brightness-105 rounded-xl"
                        >
                            <Printer class="mr-1.5 h-4 w-4" /> Print Thermal
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG 3: Held Sales Drawer / Modal -->
        <Dialog v-model:open="isHeldSalesModalOpen">
            <DialogContent
                class="max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <DialogHeader>
                    <DialogTitle
                        class="flex items-center gap-2 text-base font-black text-slate-900"
                    >
                        <Pause class="h-5 w-5 text-amber-500" />
                        <span>Held Sales Pending</span>
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500"
                        >Restore held customer carts or clear expired
                        ones.</DialogDescription
                    >
                </DialogHeader>

                <div class="max-h-[60vh] space-y-2 overflow-y-auto py-2">
                    <div
                        v-if="heldSales.length === 0"
                        class="py-10 text-center text-xs font-medium text-slate-400"
                    >
                        No sales currently on hold.
                    </div>
                    <div
                        v-else
                        v-for="(h, idx) in heldSales"
                        :key="h.id"
                        class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3"
                    >
                        <div>
                            <div
                                class="flex items-center gap-2 text-xs font-extrabold text-slate-900"
                            >
                                <span>{{ h.id }}</span>
                                <span
                                    class="text-[10px] font-bold text-slate-500"
                                    >({{ h.held_at }})</span
                                >
                            </div>
                            <div class="mt-0.5 text-[11px] text-slate-600">
                                Customer:
                                <strong>{{ h.customer_name }}</strong> |
                                {{ h.cart.length }} Items
                            </div>
                            <div
                                class="mt-0.5 text-xs font-black text-indigo-700"
                            >
                                Total: Rs {{ h.total.toLocaleString() }}
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                @click="restoreHeldSale(h, idx)"
                                class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-indigo-700 active:scale-95"
                            >
                                Resume
                            </button>
                            <button
                                type="button"
                                @click="deleteHeldSale(idx)"
                                class="rounded-lg p-1.5 text-rose-600 transition hover:bg-rose-50"
                                title="Delete"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="isHeldSalesModalOpen = false"
                        >Close</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG 4: Quick Notes & Tags Modal -->
        <Dialog v-model:open="isNotesModalOpen">
            <DialogContent
                class="max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <DialogHeader>
                    <DialogTitle class="text-base font-black text-slate-900"
                        >Sale Notes & Warranty Tags</DialogTitle
                    >
                </DialogHeader>

                <div class="space-y-3 py-2">
                    <div>
                        <label
                            class="mb-1 block text-xs font-bold text-slate-700"
                            >Quick Mobile Shop Tag Presets</label
                        >
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                type="button"
                                @click="addNotePreset('7 Days Warranty')"
                                class="rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700"
                            >
                                + 7 Days Checking Warranty
                            </button>
                            <button
                                type="button"
                                @click="addNotePreset('Screen Guard Applied')"
                                class="rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700"
                            >
                                + Glass Guard Applied
                            </button>
                            <button
                                type="button"
                                @click="addNotePreset('Box & Original Charger')"
                                class="rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700"
                            >
                                + Box & Accessories Included
                            </button>
                            <button
                                type="button"
                                @click="
                                    addNotePreset(
                                        'Clearance Deal - Non Returnable',
                                    )
                                "
                                class="rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700"
                            >
                                + Clearance Deal
                            </button>
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-xs font-bold text-slate-700"
                            >Note Content</label
                        >
                        <textarea
                            v-model="notesInput"
                            rows="3"
                            placeholder="Type any custom invoice instructions or remarks..."
                            class="w-full rounded-xl border border-slate-300 p-2.5 text-xs font-medium focus:border-indigo-600 focus:outline-none"
                        ></textarea>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        class="bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                        @click="isNotesModalOpen = false"
                        >Done</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG 5: POS Settings -->
        <Dialog v-model:open="isSettingsModalOpen">
            <DialogContent
                class="max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <DialogHeader>
                    <DialogTitle class="text-base font-black text-slate-900"
                        >POS Preferences & Audio</DialogTitle
                    >
                </DialogHeader>

                <div class="space-y-4 py-3 text-xs">


                    <div
                        class="flex items-center justify-between border-b pb-3"
                    >
                        <div>
                            <div class="font-bold text-slate-900">
                                Auto Open Print Receipt Modal
                            </div>
                            <div class="text-[11px] text-slate-500">
                                Automatically open receipt preview after sale
                                save
                            </div>
                        </div>
                        <label
                            class="relative inline-flex cursor-pointer items-center"
                        >
                            <input
                                type="checkbox"
                                v-model="posSettings.autoPrint"
                                class="peer sr-only"
                            />
                            <div
                                class="peer h-5 w-9 rounded-full bg-slate-200 peer-checked:bg-indigo-600 peer-focus:outline-none after:absolute after:top-[2px] after:left-[2px] after:h-4 after:w-4 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white"
                            ></div>
                        </label>
                    </div>

                    <div>
                        <label class="mb-1 block font-bold text-slate-900"
                            >Default Payment Mode</label
                        >
                        <select
                            v-model="posSettings.defaultPayment"
                            class="h-9 w-full rounded-xl border border-slate-300 px-3 text-xs font-bold"
                        >
                            <option value="cash">Cash</option>
                            <option value="jazzcash">JazzCash</option>
                            <option value="easypaisa">Easypaisa</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="isSettingsModalOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        class="bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                        @click="savePosSettings"
                        >Save Settings</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG 6: Keyboard Shortcuts -->
        <Dialog v-model:open="isShortcutsModalOpen">
            <DialogContent
                class="max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <DialogHeader>
                    <DialogTitle class="text-base font-black text-slate-900"
                        >POS Keyboard Shortcuts</DialogTitle
                    >
                </DialogHeader>
                <div class="space-y-2 py-2 font-mono text-xs">
                    <div class="flex justify-between border-b pb-1.5">
                        <span>New Sale / Clear Cart</span
                        ><span class="font-extrabold text-indigo-600">F1</span>
                    </div>
                    <div class="flex justify-between border-b pb-1.5">
                        <span>Focus Search / Scan IMEI</span
                        ><span class="font-extrabold text-indigo-600">F2</span>
                    </div>
                    <div class="flex justify-between border-b pb-1.5">
                        <span>Customer Select</span
                        ><span class="font-extrabold text-indigo-600">F3</span>
                    </div>
                    <div class="flex justify-between border-b pb-1.5">
                        <span>Bill Discount Input</span
                        ><span class="font-extrabold text-indigo-600">F4</span>
                    </div>
                    <div class="flex justify-between border-b pb-1.5">
                        <span>Complete Sale & Print Receipt</span
                        ><span class="font-extrabold text-indigo-600"
                            >Ctrl + Enter</span
                        >
                    </div>
                    <div class="flex justify-between border-b pb-1.5">
                        <span>Focus Search Bar</span
                        ><span class="font-extrabold text-indigo-600"
                            >Ctrl + F</span
                        >
                    </div>
                    <div class="flex justify-between border-b pb-1.5">
                        <span>Cash Received Input</span
                        ><span class="font-extrabold text-indigo-600"
                            >Alt + P</span
                        >
                    </div>
                    <div class="flex justify-between border-b pb-1.5">
                        <span>Hold Current Sale</span
                        ><span class="font-extrabold text-indigo-600"
                            >Alt + H</span
                        >
                    </div>
                    <div class="flex justify-between border-b pb-1.5">
                        <span>Sale Return / Refund</span
                        ><span class="font-extrabold text-rose-600">F7</span>
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="isShortcutsModalOpen = false"
                        >Close</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG 7: IMEI Selection Modal for Mobile Phones -->
        <Dialog v-model:open="isImeiSelectorOpen">
            <DialogContent
                class="max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <DialogHeader>
                    <DialogTitle
                        class="flex items-center gap-2 text-base font-black text-slate-900"
                    >
                        <Smartphone class="h-5 w-5 text-indigo-600" />
                        <span>Select Device Serial (IMEI)</span>
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Select an active IMEI unit for
                        {{
                            imeiSelectProduct
                                ? formatProductName(
                                      imeiSelectProduct.name,
                                      imeiSelectProduct.brand,
                                  )
                                : 'this device'
                        }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-[50vh] space-y-2 overflow-y-auto py-2">
                    <div
                        v-if="
                            !imeiSelectProduct ||
                            getAvailableImeis(imeiSelectProduct).length === 0
                        "
                        class="py-8 text-center text-xs font-medium text-slate-400"
                    >
                        No active stock IMEI units available for this device.
                    </div>
                    <div
                        v-else
                        v-for="imei in getAvailableImeis(imeiSelectProduct)"
                        :key="imei.id"
                        @click="confirmImeiSelection(imei)"
                        class="group flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 p-3 transition hover:border-indigo-500 hover:bg-indigo-50/60"
                    >
                        <div class="space-y-1">
                            <div
                                class="flex items-center gap-2 font-mono text-xs font-black text-slate-900"
                            >
                                <span>IMEI: {{ imei.imei_1 }}</span>
                                <span
                                    v-if="imei.imei_2"
                                    class="text-[10px] font-normal text-slate-400"
                                    >({{ imei.imei_2 }})</span
                                >
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px]">
                                <span
                                    v-if="imei.pta_status"
                                    :class="
                                        imei.pta_status === 'approved'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-amber-100 text-amber-800'
                                    "
                                    class="rounded px-1.5 py-0.5 font-black uppercase"
                                >
                                    {{ imei.pta_status }}
                                </span>
                                <span
                                    v-if="imei.storage"
                                    class="rounded bg-indigo-100 px-1.5 py-0.5 font-bold text-indigo-800"
                                >
                                    {{ imei.storage }}
                                </span>
                                <span
                                    v-if="imei.color"
                                    class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-700 capitalize"
                                >
                                    {{ imei.color }}
                                </span>
                                <span
                                    v-if="imei.condition"
                                    class="rounded bg-slate-100 px-1.5 py-0.5 font-bold text-slate-600 uppercase"
                                >
                                    {{ imei.condition }}
                                </span>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white shadow-xs transition group-hover:bg-indigo-700 active:scale-95"
                        >
                            Select
                        </button>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="isImeiSelectorOpen = false"
                        >Cancel</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG: Quick Add Shop Expense -->
        <Dialog v-model:open="isExpenseModalOpen">
            <DialogContent class="max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-base font-black text-slate-900 dark:text-white">
                        <Wallet class="h-5 w-5 text-amber-500" />
                        <span>Record Shop Expense</span>
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Record day-to-day shop expenses directly from the POS terminal.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitExpense" class="space-y-4 py-2">
                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-700 dark:text-slate-300">Category / Type</label>
                        <select
                            v-model="expenseForm.category"
                            class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-900 focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        >
                            <option v-for="cat in expenseCategories" :key="cat" :value="cat">
                                {{ cat }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-700 dark:text-slate-300">Amount (Rs) *</label>
                        <div class="relative flex h-10 items-center rounded-xl border border-slate-300 bg-white px-3 focus-within:border-[#003B7D] dark:border-slate-700 dark:bg-slate-800">
                            <span class="mr-1.5 text-xs font-bold text-slate-400">Rs</span>
                            <input
                                v-model="expenseForm.amount"
                                type="number"
                                step="any"
                                placeholder="0.00"
                                required
                                class="w-full bg-transparent text-xs font-bold text-slate-900 focus:outline-none dark:text-white"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-700 dark:text-slate-300">Notes / Description</label>
                        <textarea
                            v-model="expenseForm.notes"
                            rows="2"
                            placeholder="Optional expense description..."
                            class="w-full rounded-xl border border-slate-300 bg-white p-2.5 text-xs font-medium text-slate-900 focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        ></textarea>
                    </div>

                    <DialogFooter class="gap-2 pt-2">
                        <Button type="button" variant="outline" @click="isExpenseModalOpen = false">Cancel</Button>
                        <Button type="submit" class="bg-amber-600 font-bold text-white hover:bg-amber-700" :disabled="expenseForm.processing">
                            Save Expense
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- DIALOG: Recent Sales History & Re-print / Edit -->
        <Dialog v-model:open="isRecentSalesModalOpen">
            <DialogContent class="max-w-[96vw] w-[96vw] h-[92vh] max-h-[95vh] rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900 flex flex-col">
                <DialogHeader class="flex flex-row items-center justify-between border-b pb-3 pr-14 shrink-0">
                    <div>
                        <DialogTitle class="flex items-center gap-2 text-lg font-black text-slate-900 dark:text-white">
                            <History class="h-6 w-6 text-[#003B7D] dark:text-blue-400" />
                            <span>Recent Sales History</span>
                        </DialogTitle>
                        <DialogDescription class="text-xs text-slate-500">
                            View details, edit sale info, or print thermal/A4 invoices directly inside POS.
                        </DialogDescription>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-xl bg-blue-50 px-3 py-1 text-xs font-extrabold text-[#003B7D] dark:bg-blue-950/60 dark:text-blue-300">
                            {{ filteredRecentSales.length }} Sales Found
                        </span>
                    </div>
                </DialogHeader>

                <!-- Search & Filters -->
                <div class="my-3 flex flex-wrap items-center justify-between gap-3 shrink-0">
                    <div class="relative flex-1 min-w-[240px]">
                        <Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                        <input
                            v-model="recentSalesQuery"
                            type="text"
                            placeholder="Search by Invoice #, Customer Name, Phone, or Cashier..."
                            class="h-9 w-full rounded-xl border border-slate-300 bg-slate-50 pl-9 pr-3 text-xs font-semibold text-slate-900 focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <select
                            v-model="recentSalesPaymentFilter"
                            class="h-9 rounded-xl border border-slate-300 bg-slate-50 px-3 text-xs font-bold text-slate-700 focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <option value="all">All Payment Methods</option>
                            <option value="cash">Cash</option>
                            <option value="jazzcash">JazzCash</option>
                            <option value="easypaisa">Easypaisa</option>
                            <option value="bank">Bank</option>
                            <option value="card">Card</option>
                            <option value="udhaar">Udhaar (Khata)</option>
                        </select>
                        <button
                            type="button"
                            @click="fetchRecentSales"
                            class="flex h-9 items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        >
                            <RotateCcw class="h-3.5 w-3.5" />
                            <span>Refresh</span>
                        </button>
                    </div>
                </div>

                <!-- Sales List Table -->
                <div class="flex-1 min-h-0 overflow-y-auto [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden rounded-xl border border-slate-200 dark:border-slate-800">
                    <div v-if="isLoadingRecentSales" class="py-16 text-center text-xs font-bold text-slate-500">
                        <div class="inline-block h-6 w-6 animate-spin rounded-full border-2 border-[#003B7D] border-t-transparent mb-2"></div>
                        <div>Loading recent sales...</div>
                    </div>
                    <div v-else-if="filteredRecentSales.length === 0" class="py-16 text-center text-xs font-bold text-slate-500">
                        No recent sales matching search/filter.
                    </div>
                    <table v-else class="w-full text-left text-xs">
                        <thead class="sticky top-0 bg-slate-100 uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300 z-10 shadow-sm">
                            <tr>
                                <th class="px-4 py-2.5">Invoice #</th>
                                <th class="px-4 py-2.5">Date & Time</th>
                                <th class="px-4 py-2.5">Customer</th>
                                <th class="px-4 py-2.5">Salesman</th>
                                <th class="px-4 py-2.5">Products Summary</th>
                                <th class="px-4 py-2.5">Net Amount</th>
                                <th class="px-4 py-2.5">Payment</th>
                                <th class="px-4 py-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            <tr v-for="sale in paginatedRecentSales" :key="sale.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-4 py-2.5 font-mono font-black text-[#003B7D] dark:text-blue-400 text-sm">
                                    {{ sale.invoice_no }}
                                </td>
                                <td class="px-4 py-2.5 text-[11px] text-slate-600 dark:text-slate-400">
                                    {{ new Date(sale.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                                    <span class="block text-[10px] text-slate-400">{{ new Date(sale.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) }}</span>
                                </td>
                                <td class="px-4 py-2.5 font-bold text-slate-800 dark:text-slate-200">
                                    <div>{{ sale.customer?.name || 'Walk-In Customer' }}</div>
                                    <div v-if="sale.customer?.phone" class="text-[10px] font-mono text-slate-400 font-normal">{{ sale.customer.phone }}</div>
                                </td>
                                <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300 font-semibold">
                                    {{ sale.cashier?.name || 'Admin' }}
                                </td>
                                <td class="px-4 py-2.5 text-slate-600 dark:text-slate-400">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-100/90 px-2 py-0.5 text-[11px] font-bold text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                        :title="sale.items?.map(i => i.product?.name).join(', ')"
                                    >
                                        {{ formatShortProductSummary(sale) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 font-extrabold text-slate-900 dark:text-white text-sm">
                                    Rs {{ Number(sale.net_amount).toLocaleString() }}
                                    <span v-if="Number(sale.discount_amount) > 0" class="block text-[10px] text-emerald-600 font-bold">
                                        Disc: -Rs {{ Number(sale.discount_amount).toLocaleString() }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 capitalize font-semibold">
                                    <span
                                        class="inline-block rounded-md px-2.5 py-0.5 text-[11px] font-extrabold uppercase"
                                        :class="sale.payment_method === 'udhaar' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'"
                                    >
                                        {{ sale.payment_method }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Print Receipt -->
                                        <button
                                            type="button"
                                            @click="printRecentSaleInvoice(sale)"
                                            class="flex h-8 items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-2.5 text-xs font-bold text-blue-700 hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300"
                                            title="Print Receipt / Invoice"
                                        >
                                            <Printer class="h-3.5 w-3.5" />
                                            <span>Print</span>
                                        </button>
                                        <!-- View Details -->
                                        <button
                                            type="button"
                                            @click="viewSaleDetails(sale)"
                                            class="flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-slate-100 px-2.5 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                            title="View Details"
                                        >
                                            <Eye class="h-3.5 w-3.5" />
                                            <span>View</span>
                                        </button>
                                        <!-- In-POS Edit -->
                                        <button
                                            type="button"
                                            @click="openEditSaleModal(sale)"
                                            class="flex h-8 items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2.5 text-xs font-bold text-amber-700 hover:bg-amber-100 dark:border-amber-900/50 dark:bg-amber-950 dark:text-amber-300"
                                            title="Edit Sale Details in POS"
                                        >
                                            <Edit class="h-3.5 w-3.5" />
                                            <span>Edit</span>
                                        </button>
                                        <!-- Return Sale -->
                                        <button
                                            type="button"
                                            @click="openReturnSaleForSpecificSale(sale)"
                                            class="flex h-8 items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2.5 text-xs font-bold text-rose-700 hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-950 dark:text-rose-300"
                                            title="Process Return for this Sale"
                                        >
                                            <RotateCcw class="h-3.5 w-3.5" />
                                            <span>Return</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Recent Sales Pagination Footer -->
                <div v-if="filteredRecentSales.length > recentSalesPerPage" class="flex items-center justify-between border-t border-slate-200 pt-3 dark:border-slate-800 shrink-0 text-xs">
                    <span class="text-slate-500 font-medium">
                        Showing {{ (recentSalesPage - 1) * recentSalesPerPage + 1 }} to {{ Math.min(recentSalesPage * recentSalesPerPage, filteredRecentSales.length) }} of {{ filteredRecentSales.length }} sales
                    </span>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 text-xs font-bold rounded-xl"
                            :disabled="recentSalesPage <= 1"
                            @click="recentSalesPage--"
                        >
                            Previous
                        </Button>
                        <span class="px-2 text-xs font-bold text-slate-700 dark:text-slate-300">
                            Page {{ recentSalesPage }} of {{ totalRecentSalesPages }}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 text-xs font-bold rounded-xl"
                            :disabled="recentSalesPage >= totalRecentSalesPages"
                            @click="recentSalesPage++"
                        >
                            Next
                        </Button>
                    </div>
                </div>

                <DialogFooter class="pt-3 shrink-0">
                    <Button type="button" variant="outline" @click="isRecentSalesModalOpen = false">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG: Sale Details (In-POS View & Print/Edit) -->
        <Dialog v-model:open="isSaleDetailsModalOpen">
            <DialogContent class="max-w-3xl w-full rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900 max-h-[90vh] overflow-y-auto [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden z-[75]" overlayClass="z-[70]">
                <DialogHeader v-if="selectedSaleDetails" class="border-b pb-3">
                    <DialogTitle class="flex items-center justify-between text-lg font-black text-slate-900 dark:text-white">
                        <div class="flex items-center gap-2">
                            <FileText class="h-5 w-5 text-[#003B7D] dark:text-blue-400" />
                            <span>Invoice #{{ selectedSaleDetails.invoice_no }}</span>
                        </div>
                        <span class="text-sm font-black text-[#003B7D] dark:text-blue-400">
                            Rs {{ Number(selectedSaleDetails.net_amount).toLocaleString() }}
                        </span>
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Date: {{ new Date(selectedSaleDetails.created_at).toLocaleString() }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedSaleDetails" class="space-y-4 py-3 text-xs">
                    <div class="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3.5 dark:bg-slate-800/60">
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400">Customer Info</div>
                            <div class="font-black text-slate-900 text-sm dark:text-white">{{ selectedSaleDetails.customer?.name || 'Walk-In Customer' }}</div>
                            <div v-if="selectedSaleDetails.customer?.phone" class="font-mono text-slate-500 text-[11px]">{{ selectedSaleDetails.customer.phone }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Salesman / Cashier</div>
                            <div class="font-bold text-slate-900 text-sm dark:text-white">{{ selectedSaleDetails.cashier?.name || 'Admin' }}</div>
                            <div class="text-[11px] capitalize font-bold text-emerald-600 dark:text-emerald-400">
                                Payment: {{ selectedSaleDetails.payment_method }}
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <span class="font-black text-slate-700 dark:text-slate-300 uppercase text-[11px]">Items Purchased</span>
                        <div class="max-h-60 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-800 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-100 uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    <tr>
                                        <th class="px-3 py-2">Item Description</th>
                                        <th class="px-3 py-2 text-center">Qty</th>
                                        <th class="px-3 py-2 text-right">Unit Price</th>
                                        <th class="px-3 py-2 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="item in selectedSaleDetails.items" :key="item.id">
                                        <td class="px-3 py-2">
                                            <div class="font-bold text-slate-800 dark:text-slate-200">{{ item.product?.name }}</div>
                                            <div v-if="item.product_imei" class="font-mono text-[10px] text-blue-600 dark:text-blue-400">IMEI: {{ item.product_imei.imei_1 }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-center font-bold text-slate-700 dark:text-slate-300">
                                            {{ item.quantity }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-medium text-slate-600 dark:text-slate-400">
                                            Rs {{ Number(item.unit_price).toLocaleString() }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-extrabold text-slate-900 dark:text-white">
                                            Rs {{ Number(item.line_total).toLocaleString() }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Financial Summary Breakdown -->
                    <div class="rounded-xl border border-slate-200 p-3 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/40 space-y-1">
                        <div class="flex justify-between text-slate-600 dark:text-slate-400">
                            <span>Subtotal:</span>
                            <span class="font-bold">Rs {{ Number(selectedSaleDetails.total_amount).toLocaleString() }}</span>
                        </div>
                        <div v-if="Number(selectedSaleDetails.discount_amount) > 0" class="flex justify-between text-emerald-600 font-semibold">
                            <span>Discount:</span>
                            <span>-Rs {{ Number(selectedSaleDetails.discount_amount).toLocaleString() }}</span>
                        </div>
                        <div v-if="Number(selectedSaleDetails.trade_in_amount) > 0" class="flex justify-between text-amber-600 font-semibold">
                            <span>Trade-In Credit:</span>
                            <span>-Rs {{ Number(selectedSaleDetails.trade_in_amount).toLocaleString() }}</span>
                        </div>
                        <div class="flex justify-between text-sm font-black text-slate-900 dark:text-white border-t pt-1">
                            <span>Net Amount:</span>
                            <span>Rs {{ Number(selectedSaleDetails.net_amount).toLocaleString() }}</span>
                        </div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-400 pt-0.5">
                            <span>Paid Amount:</span>
                            <span class="font-bold">Rs {{ Number(selectedSaleDetails.paid_amount).toLocaleString() }}</span>
                        </div>
                        <div v-if="Number(selectedSaleDetails.change_amount) > 0" class="flex justify-between text-blue-600 font-bold">
                            <span>Change Returned:</span>
                            <span>Rs {{ Number(selectedSaleDetails.change_amount).toLocaleString() }}</span>
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2 pt-3 border-t">
                    <Button type="button" variant="outline" @click="isSaleDetailsModalOpen = false">Close</Button>
                    <Button
                        type="button"
                        @click="printRecentSaleInvoice(selectedSaleDetails!)"
                        class="bg-blue-600 font-bold text-white hover:bg-blue-700"
                    >
                        <Printer class="mr-1.5 h-4 w-4" /> Print Invoice
                    </Button>
                    <Button
                        type="button"
                        @click="openEditSaleModal(selectedSaleDetails!)"
                        class="bg-amber-600 font-bold text-white hover:bg-amber-700"
                    >
                        <Edit class="mr-1.5 h-4 w-4" /> Edit Sale
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG: Edit Sale in POS -->
        <Dialog v-model:open="isEditSaleModalOpen">
            <DialogContent class="max-w-2xl w-full rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900 max-h-[90vh] overflow-y-auto [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden z-[90]" overlayClass="z-[85]">
                <DialogHeader class="border-b pb-3">
                    <DialogTitle class="flex items-center gap-2 text-base font-black text-slate-900 dark:text-white">
                        <Edit class="h-5 w-5 text-amber-600" />
                        <span>Edit Sale Invoice #{{ editingSale?.invoice_no }}</span>
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Update customer, products (prices/qty), payment method, discount, or paid amount directly in POS.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="editingSale" class="space-y-4 py-3 text-xs">
                    <!-- Customer Dropdown -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block font-bold text-slate-700 dark:text-slate-300">Customer</label>
                            <select
                                v-model="editSaleForm.customer_id"
                                class="w-full rounded-xl border border-slate-300 bg-slate-50 p-2.5 text-xs font-bold text-slate-900 focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="">Walk-In Customer</option>
                                <option
                                    v-for="cust in customers"
                                    :key="cust.id"
                                    :value="cust.id"
                                >
                                    {{ cust.name }} ({{ cust.phone }}) — {{ Number(cust.current_balance) > 0 ? `Due: Rs ${Number(cust.current_balance).toLocaleString()}` : Number(cust.current_balance) < 0 ? `Advance: Rs ${Math.abs(Number(cust.current_balance)).toLocaleString()}` : 'Bal: Rs 0' }}
                                </option>
                            </select>
                        </div>

                        <!-- Cashier / Salesman Dropdown -->
                        <div>
                            <label class="mb-1 block font-bold text-slate-700 dark:text-slate-300">Salesman / Cashier</label>
                            <select
                                v-model="editSaleForm.cashier_id"
                                class="w-full rounded-xl border border-slate-300 bg-slate-50 p-2.5 text-xs font-bold text-slate-900 focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option v-for="sm in salesmen" :key="sm.id" :value="sm.id">
                                    {{ sm.name }} ({{ sm.email }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Products Table for Price & Qty Editing -->
                    <div class="space-y-1.5">
                        <span class="font-black uppercase text-[11px] text-slate-700 dark:text-slate-300">Edit Products & Prices</span>
                        <div class="rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-100 uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    <tr>
                                        <th class="px-3 py-2">Product Name</th>
                                        <th class="px-3 py-2 text-center w-24">Qty</th>
                                        <th class="px-3 py-2 text-right w-32">Unit Price (Rs)</th>
                                        <th class="px-3 py-2 text-right w-32">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="item in editSaleItems" :key="item.id">
                                        <td class="px-3 py-2">
                                            <div class="font-bold text-slate-800 dark:text-slate-200">{{ item.product_name }}</div>
                                            <div v-if="item.imei" class="font-mono text-[10px] text-blue-600 dark:text-blue-400">IMEI: {{ item.imei }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <input
                                                v-model.number="item.quantity"
                                                type="number"
                                                min="1"
                                                step="1"
                                                class="h-7 w-16 text-center rounded-lg border border-slate-300 bg-white font-bold text-slate-900 focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                            />
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <input
                                                v-model.number="item.unit_price"
                                                type="number"
                                                min="0"
                                                max="1000000"
                                                class="h-7 w-28 text-right rounded-lg border border-slate-300 bg-white font-bold text-slate-900 focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                            />
                                        </td>
                                        <td class="px-3 py-2 text-right font-extrabold text-slate-900 dark:text-white">
                                            Rs {{ ((Number(item.quantity) || 0) * (Number(item.unit_price) || 0)).toLocaleString() }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <!-- Payment Method -->
                        <div>
                            <label class="mb-1 block font-bold text-slate-700 dark:text-slate-300">Payment Method</label>
                            <select
                                v-model="editSaleForm.payment_method"
                                class="w-full rounded-xl border border-slate-300 bg-slate-50 p-2.5 text-xs font-bold text-slate-900 focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="cash">Cash</option>
                                <option value="jazzcash">JazzCash</option>
                                <option value="easypaisa">Easypaisa</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="card">Card</option>
                                <option value="udhaar">Udhaar (Khata)</option>
                            </select>
                        </div>

                        <!-- Discount Amount -->
                        <div>
                            <label class="mb-1 block font-bold text-slate-700 dark:text-slate-300">Discount Amount (Rs)</label>
                            <input
                                v-model.number="editSaleForm.discount_amount"
                                type="number"
                                min="0"
                                class="w-full rounded-xl border border-slate-300 bg-slate-50 p-2.5 text-xs font-bold text-slate-900 focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            />
                        </div>
                    </div>

                    <!-- Paid Amount -->
                    <div>
                        <label class="mb-1 block font-bold text-slate-700 dark:text-slate-300">Paid Amount (Rs)</label>
                        <input
                            v-model.number="editSaleForm.paid_amount"
                            type="number"
                            min="0"
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 p-2.5 text-xs font-bold text-slate-900 focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                    </div>

                    <!-- Calculations Summary -->
                    <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-3.5 dark:border-amber-900/50 dark:bg-amber-950/30 space-y-1.5 text-xs">
                        <div class="flex justify-between text-slate-600 dark:text-slate-400">
                            <span>Subtotal Items:</span>
                            <span class="font-bold">Rs {{ editSaleCalculatedSubtotal.toLocaleString() }}</span>
                        </div>
                        <div class="flex justify-between text-slate-900 dark:text-white font-black text-sm border-t pt-1 dark:border-slate-700">
                            <span>Calculated Net:</span>
                            <span class="text-[#003B7D] dark:text-blue-400">Rs {{ editSaleCalculatedNet.toLocaleString() }}</span>
                        </div>
                        <div v-if="editSaleCalculatedChange > 0" class="flex justify-between text-blue-700 dark:text-blue-300 font-bold">
                            <span>Change to Return:</span>
                            <span>Rs {{ editSaleCalculatedChange.toLocaleString() }}</span>
                        </div>
                        <div v-if="editSaleCalculatedUdhaar > 0" class="flex justify-between text-amber-700 dark:text-amber-300 font-bold">
                            <span>Udhaar Balance:</span>
                            <span>Rs {{ editSaleCalculatedUdhaar.toLocaleString() }}</span>
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2 pt-3 border-t">
                    <Button type="button" variant="outline" @click="isEditSaleModalOpen = false">Cancel</Button>
                    <Button
                        type="button"
                        @click="saveEditSale"
                        class="bg-[#003B7D] font-bold text-white hover:bg-[#002752]"
                        :disabled="isSavingEditSale"
                    >
                        <span v-if="isSavingEditSale">Saving...</span>
                        <span v-else>Save Changes</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG: POS Sale Return & Refund Module -->
        <Dialog v-model:open="isReturnSaleModalOpen">
            <DialogContent class="max-w-6xl w-[95vw] h-[88vh] max-h-[90vh] rounded-3xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-800 dark:bg-slate-900 flex flex-col z-[80] overflow-hidden" overlayClass="z-[75]">
                <!-- Dialog Header -->
                <DialogHeader class="flex flex-row items-center justify-between border-b border-slate-100 pb-3 pr-14 shrink-0 dark:border-slate-800">
                    <div>
                        <DialogTitle class="flex items-center gap-2.5 text-lg font-black text-slate-900 dark:text-white">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400">
                                <RotateCcw class="h-5 w-5" />
                            </div>
                            <span>Sale Return & Refund Module</span>
                        </DialogTitle>
                        <DialogDescription class="text-xs text-slate-500 mt-0.5">
                            Locate previous sales, restore products to inventory, offset customer khata dues, and issue refunds.
                        </DialogDescription>
                    </div>

                    <div class="flex items-center gap-2">
                        <span v-if="returnSaleStep === 'items'" class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-1.5 font-mono text-xs font-black text-[#003B7D] dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300">
                            Invoice #{{ selectedReturnSale?.invoice_no }}
                        </span>
                        <button
                            v-if="returnSaleStep === 'items'"
                            type="button"
                            @click="returnSaleStep = 'search'"
                            class="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <ArrowLeft class="h-3.5 w-3.5" />
                            <span>Back to Search</span>
                        </button>
                    </div>
                </DialogHeader>

                <!-- Workflow Stepper Indicator -->
                <div class="flex items-center gap-2 rounded-xl bg-slate-50 px-4 py-2 text-xs font-bold text-slate-500 dark:bg-slate-800/60 shrink-0">
                    <div class="flex items-center gap-1.5" :class="returnSaleStep === 'search' ? 'text-[#003B7D] dark:text-blue-400 font-black' : 'text-slate-400'">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full text-[11px]" :class="returnSaleStep === 'search' ? 'bg-[#003B7D] text-white shadow-xs' : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'">1</span>
                        <span>1. Search & Select Sale</span>
                    </div>
                    <ChevronRight class="h-3.5 w-3.5 text-slate-300 dark:text-slate-600" />
                    <div class="flex items-center gap-1.5" :class="returnSaleStep === 'items' ? 'text-[#003B7D] dark:text-blue-400 font-black' : 'text-slate-400'">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full text-[11px]" :class="returnSaleStep === 'items' ? 'bg-[#003B7D] text-white shadow-xs' : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'">2</span>
                        <span>2. Select Return Quantities</span>
                    </div>
                    <ChevronRight class="h-3.5 w-3.5 text-slate-300 dark:text-slate-600" />
                    <div class="flex items-center gap-1.5" :class="returnSaleStep === 'items' && totalReturnAmount > 0 ? 'text-emerald-600 dark:text-emerald-400 font-black' : 'text-slate-400'">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full text-[11px]" :class="returnSaleStep === 'items' && totalReturnAmount > 0 ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'">3</span>
                        <span>3. Refund Settlement</span>
                    </div>
                </div>

                <!-- STEP 1: Search & Select Sale -->
                <div v-if="returnSaleStep === 'search'" class="flex-1 flex flex-col min-h-0 pt-1 gap-2.5">
                    <!-- Search Bar & Filters -->
                    <div class="flex flex-wrap items-center justify-between gap-2.5 shrink-0 rounded-2xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-800/40">
                        <div class="relative flex-1 min-w-[260px]">
                            <Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                            <input
                                v-model="returnSearchQuery"
                                @keyup.enter="searchSalesForReturn"
                                type="text"
                                placeholder="Search by Invoice #, Customer Name, or Phone..."
                                class="h-9 w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 text-xs font-semibold text-slate-900 shadow-xs focus:border-[#003B7D] focus:ring-1 focus:ring-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <input
                                v-model="returnSearchDate"
                                type="date"
                                @change="searchSalesForReturn"
                                class="h-9 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 shadow-xs focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            />
                            <button
                                v-if="returnSearchDate"
                                type="button"
                                @click="returnSearchDate = ''; searchSalesForReturn()"
                                class="text-[11px] font-bold text-slate-500 hover:text-slate-800 dark:hover:text-slate-200"
                            >
                                Clear Date
                            </button>
                            <button
                                type="button"
                                @click="searchSalesForReturn"
                                class="flex h-9 items-center gap-1.5 rounded-xl bg-[#003B7D] px-4 text-xs font-bold text-white shadow-sm hover:bg-[#002752] transition active:scale-95"
                            >
                                <Search class="h-3.5 w-3.5" />
                                <span>Find Sales</span>
                            </button>
                        </div>
                    </div>

                    <!-- Sales Results Table -->
                    <div class="flex-1 min-h-0 overflow-y-auto rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs [scrollbar-width:thin]">
                        <div v-if="isSearchingSalesForReturn" class="py-20 text-center text-xs font-bold text-slate-500">
                            <div class="inline-block h-7 w-7 animate-spin rounded-full border-2 border-[#003B7D] border-t-transparent mb-2"></div>
                            <div>Searching previous completed sales...</div>
                        </div>
                        <div v-else-if="returnSearchResults.length === 0" class="py-20 text-center text-xs text-slate-400">
                            <Receipt class="mx-auto h-9 w-9 text-slate-300 mb-2" />
                            <div class="font-bold text-slate-600 dark:text-slate-300">No Sales Found</div>
                            <div class="text-[11px] mt-0.5">Try searching with a different invoice number, phone, or date.</div>
                        </div>
                        <table v-else class="w-full text-left text-xs">
                            <thead class="sticky top-0 bg-slate-100 text-[11px] uppercase tracking-wider text-slate-600 dark:bg-slate-800 dark:text-slate-300 z-10 shadow-xs">
                                <tr>
                                    <th class="px-4 py-2.5">Invoice #</th>
                                    <th class="px-4 py-2.5">Date & Time</th>
                                    <th class="px-4 py-2.5">Customer</th>
                                    <th class="px-4 py-2.5">Products Summary</th>
                                    <th class="px-4 py-2.5 text-right">Net Bill</th>
                                    <th class="px-4 py-2.5">Paid / Due</th>
                                    <th class="px-4 py-2.5 text-center">Status</th>
                                    <th class="px-4 py-2.5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                <tr
                                    v-for="sale in returnSearchResults"
                                    :key="sale.id"
                                    :class="sale.return_status === 'fully_returned' ? 'opacity-60 bg-slate-50/50 dark:bg-slate-900/40' : 'hover:bg-blue-50/30 dark:hover:bg-slate-800/50 cursor-pointer'"
                                    @click="sale.return_status !== 'fully_returned' && selectSaleForReturn(sale)"
                                    class="transition-colors"
                                >
                                    <td class="px-4 py-2.5 font-mono font-black text-[#003B7D] dark:text-blue-400 text-sm">
                                        {{ sale.invoice_no }}
                                    </td>
                                    <td class="px-4 py-2.5 text-[11px] text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                        {{ sale.sale_date }}
                                    </td>
                                    <td class="px-4 py-2.5 font-bold text-slate-800 dark:text-slate-200">
                                        <div class="flex items-center gap-1.5">
                                            <span class="truncate">{{ sale.customer?.name || 'Walk-In Customer' }}</span>
                                        </div>
                                        <div v-if="sale.customer?.phone" class="text-[10px] font-mono text-slate-400 font-normal">
                                            {{ sale.customer.phone }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-400">
                                        <span class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-bold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                            {{ sale.items?.length || 0 }} Items
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-black text-slate-900 dark:text-white text-sm">
                                        Rs {{ Number(sale.net_amount).toLocaleString() }}
                                    </td>
                                    <td class="px-4 py-2.5 text-xs">
                                        <span class="font-bold text-emerald-600">Paid: Rs {{ Number(sale.paid_amount).toLocaleString() }}</span>
                                        <span v-if="Number(sale.due_amount) > 0" class="block font-bold text-amber-600">
                                            Due: Rs {{ Number(sale.due_amount).toLocaleString() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span
                                            v-if="sale.return_status === 'fully_returned'"
                                            class="inline-block rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[10px] font-extrabold uppercase text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/60 dark:text-rose-300"
                                        >
                                            Fully Returned
                                        </span>
                                        <span
                                            v-else-if="sale.return_status === 'partially_returned'"
                                            class="inline-block rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-extrabold uppercase text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/60 dark:text-amber-300"
                                        >
                                            Partial Return
                                        </span>
                                        <span
                                            v-else
                                            class="inline-block rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-extrabold uppercase text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/60 dark:text-emerald-300"
                                        >
                                            Returnable
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right" @click.stop>
                                        <button
                                            type="button"
                                            :disabled="sale.return_status === 'fully_returned'"
                                            @click="selectSaleForReturn(sale)"
                                            class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 disabled:opacity-40 disabled:cursor-not-allowed dark:border-rose-900/50 dark:bg-rose-950/60 dark:text-rose-300 transition active:scale-95 shadow-2xs"
                                        >
                                            <RotateCcw class="h-3.5 w-3.5" />
                                            <span>Select Sale</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- STEP 2: Return Items Selection & Refund Calculation -->
                <div v-else-if="returnSaleStep === 'items'" class="flex-1 flex flex-col min-h-0 pt-1 gap-2.5">
                    <!-- Sale Banner Info: 4 Clean Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 shrink-0">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-2.5 text-xs dark:border-slate-800 dark:bg-slate-800/40">
                            <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">Original Invoice</span>
                            <span class="font-mono font-black text-[#003B7D] dark:text-blue-400 text-sm">{{ selectedReturnSale?.invoice_no }}</span>
                            <span class="text-[10px] text-slate-500 block">{{ selectedReturnSale?.sale_date }}</span>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-2.5 text-xs dark:border-slate-800 dark:bg-slate-800/40">
                            <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">Customer Details</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 truncate block">{{ selectedReturnSale?.customer?.name || 'Walk-In Customer' }}</span>
                            <span v-if="selectedReturnSale?.customer?.phone" class="text-[10px] text-slate-500 font-mono block">{{ selectedReturnSale.customer.phone }}</span>
                            <span v-else class="text-[10px] text-slate-400 block">Walk-In</span>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-2.5 text-xs dark:border-slate-800 dark:bg-slate-800/40">
                            <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">Invoice Billing</span>
                            <div class="flex items-baseline gap-1.5">
                                <span class="font-black text-slate-900 dark:text-white text-sm">Rs {{ Number(selectedReturnSale?.net_amount).toLocaleString() }}</span>
                                <span class="text-[10px] text-emerald-600 font-bold">(Paid Rs {{ Number(selectedReturnSale?.paid_amount).toLocaleString() }})</span>
                            </div>
                        </div>
                        <div class="rounded-2xl border p-2.5 text-xs" :class="Number(selectedReturnSale?.due_amount) > 0 ? 'border-amber-200 bg-amber-50/80 dark:border-amber-900/50 dark:bg-amber-950/40' : 'border-emerald-200 bg-emerald-50/80 dark:border-emerald-900/50 dark:bg-emerald-950/40'">
                            <span class="block text-[10px] uppercase font-bold tracking-wider" :class="Number(selectedReturnSale?.due_amount) > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-emerald-700 dark:text-emerald-400'">
                                Unpaid Invoice Due
                            </span>
                            <span class="font-black text-sm" :class="Number(selectedReturnSale?.due_amount) > 0 ? 'text-amber-800 dark:text-amber-300' : 'text-emerald-800 dark:text-emerald-300'">
                                {{ Number(selectedReturnSale?.due_amount) > 0 ? `Rs ${Number(selectedReturnSale.due_amount).toLocaleString()}` : 'Rs 0 (Fully Paid)' }}
                            </span>
                            <span v-if="selectedReturnSale?.customer && Number(selectedReturnSale.customer.current_balance) > 0" class="text-[10px] text-amber-600 block font-semibold">
                                Total Khata Due: Rs {{ Number(selectedReturnSale.customer.current_balance).toLocaleString() }}
                            </span>
                        </div>
                    </div>

                    <!-- Action buttons for selection -->
                    <div class="flex items-center justify-between shrink-0 px-0.5">
                        <div class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wide">
                            Select Products to Return
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="returnAllReturnableItems"
                                class="flex items-center gap-1 rounded-xl border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700 hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-950/60 dark:text-rose-300 transition"
                            >
                                <Check class="h-3.5 w-3.5" />
                                <span>Return All Available</span>
                            </button>
                            <button
                                type="button"
                                @click="clearReturnSelection"
                                class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 transition"
                            >
                                <X class="h-3.5 w-3.5" />
                                <span>Clear Selection</span>
                            </button>
                        </div>
                    </div>

                    <!-- Return Items Table -->
                    <div class="flex-1 min-h-0 overflow-y-auto rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs [scrollbar-width:thin]">
                        <table class="w-full text-left text-xs">
                            <thead class="sticky top-0 bg-slate-100 text-[11px] uppercase tracking-wider text-slate-600 dark:bg-slate-800 dark:text-slate-300 z-10 shadow-xs">
                                <tr>
                                    <th class="px-3 py-2.5 w-10 text-center">#</th>
                                    <th class="px-4 py-2.5">Product & Details</th>
                                    <th class="px-4 py-2.5 text-center">Sold Qty</th>
                                    <th class="px-4 py-2.5 text-center">Already Returned</th>
                                    <th class="px-4 py-2.5 text-center">Available Return</th>
                                    <th class="px-4 py-2.5 text-right">Unit Price</th>
                                    <th class="px-4 py-2.5 text-center">Return Qty</th>
                                    <th class="px-4 py-2.5 text-right">Return Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                <tr
                                    v-for="item in returnLineItems"
                                    :key="item.sale_item_id"
                                    :class="[
                                        item.is_selected ? 'bg-blue-50/50 dark:bg-blue-950/20' : '',
                                        item.remaining_quantity <= 0 ? 'opacity-50' : 'hover:bg-slate-50/80 dark:hover:bg-slate-800/40'
                                    ]"
                                    class="transition-colors"
                                >
                                    <td class="px-3 py-2.5 text-center">
                                        <input
                                            type="checkbox"
                                            :checked="item.is_selected"
                                            :disabled="item.remaining_quantity <= 0"
                                            @change="toggleReturnItemSelection(item)"
                                            class="h-4 w-4 rounded border-slate-300 text-[#003B7D] focus:ring-[#003B7D]"
                                        />
                                    </td>
                                    <td class="px-4 py-2.5 font-bold text-slate-800 dark:text-slate-200">
                                        <div>{{ item.product_name }}</div>
                                        <div v-if="item.is_serialized && item.imei_1" class="mt-0.5 inline-block font-mono text-[10px] font-bold text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-200 dark:bg-indigo-950 dark:text-indigo-300 dark:border-indigo-800">
                                            IMEI: {{ item.imei_1 }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-center font-bold text-slate-700 dark:text-slate-300">
                                        {{ item.quantity }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center font-semibold text-slate-500">
                                        {{ item.returned_quantity }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center font-black" :class="item.remaining_quantity > 0 ? 'text-emerald-600' : 'text-slate-400'">
                                        {{ item.remaining_quantity }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-semibold text-slate-700 dark:text-slate-300">
                                        Rs {{ Number(item.unit_price).toLocaleString() }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <div v-if="item.remaining_quantity > 0" class="inline-flex items-center gap-1 border border-slate-300 rounded-xl p-0.5 bg-white dark:border-slate-700 dark:bg-slate-800 shadow-2xs">
                                            <button
                                                type="button"
                                                @click="updateReturnItemQty(item, item.return_qty - 1)"
                                                :disabled="item.return_qty <= 0"
                                                class="h-6 w-6 flex items-center justify-center rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 disabled:opacity-40 transition active:scale-95"
                                            >
                                                <Minus class="h-3 w-3" />
                                            </button>
                                            <input
                                                type="number"
                                                min="0"
                                                :max="item.remaining_quantity"
                                                :value="item.return_qty"
                                                @input="updateReturnItemQty(item, Number(($event.target as HTMLInputElement).value))"
                                                class="h-6 w-12 text-center text-xs font-black border-none focus:outline-none bg-transparent"
                                            />
                                            <button
                                                type="button"
                                                @click="updateReturnItemQty(item, item.return_qty + 1)"
                                                :disabled="item.return_qty >= item.remaining_quantity"
                                                class="h-6 w-6 flex items-center justify-center rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 disabled:opacity-40 transition active:scale-95"
                                            >
                                                <Plus class="h-3 w-3" />
                                            </button>
                                        </div>
                                        <span v-else class="text-[11px] font-bold text-slate-400">All Returned</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-black text-slate-900 dark:text-white">
                                        Rs {{ (item.return_qty * item.unit_price).toLocaleString() }}
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
                                    <span class="font-black text-slate-900 dark:text-white">Rs {{ totalReturnAmount.toLocaleString() }}</span>
                                </div>
                                <div v-if="calculatedDueOffset > 0" class="flex justify-between text-amber-600 dark:text-amber-400 font-bold">
                                    <span>Offset Unpaid Due:</span>
                                    <span>-Rs {{ calculatedDueOffset.toLocaleString() }}</span>
                                </div>
                                <div class="flex justify-between text-emerald-600 dark:text-emerald-400 font-black text-sm border-t pt-1.5 border-slate-200 dark:border-slate-700">
                                    <span>Net Refund Payable:</span>
                                    <span>Rs {{ calculatedNetRefund.toLocaleString() }}</span>
                                </div>
                                <div v-if="calculatedDueOffset > 0" class="text-[10px] text-amber-600">
                                    * Due debt on this invoice will automatically be reduced by Rs {{ calculatedDueOffset.toLocaleString() }}.
                                </div>
                            </div>

                            <!-- Col 2: Refund Method & Custom Amount -->
                            <div class="space-y-2 rounded-xl bg-slate-50/70 p-3 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                                <div>
                                    <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300 block mb-1">Refund Payout Mode:</label>
                                    <select
                                        v-model="returnRefundMethod"
                                        class="h-8 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-xs font-bold text-slate-800 shadow-2xs focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    >
                                        <option value="cash">Cash Refund</option>
                                        <option value="jazzcash">JazzCash Payout</option>
                                        <option value="easypaisa">EasyPaisa Payout</option>
                                        <option value="bank">Bank Transfer</option>
                                        <option value="card">Card Refund</option>
                                        <option value="khata_credit">Khata Credit (Keep as Advance)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300 block mb-1">Refund Amount (PKR):</label>
                                    <input
                                        type="number"
                                        min="0"
                                        :max="calculatedNetRefund"
                                        step="0.01"
                                        :placeholder="String(calculatedNetRefund)"
                                        v-model="customRefundAmount"
                                        class="h-8 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-xs font-black text-slate-900 shadow-2xs focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>
                            </div>

                            <!-- Col 3: Notes & Atomic Safety Notice -->
                            <div class="space-y-2 rounded-xl bg-slate-50/70 p-3 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
                                <div>
                                    <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300 block mb-1">Return Reason / Notes (Optional):</label>
                                    <input
                                        type="text"
                                        v-model="returnNotes"
                                        placeholder="e.g. Defective piece, wrong model, customer request..."
                                        class="h-8 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-xs font-medium text-slate-800 shadow-2xs focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>
                                <div class="text-[10px] text-slate-500 flex items-center gap-1.5">
                                    <CheckCircle class="h-3.5 w-3.5 text-emerald-600 shrink-0" />
                                    <span>Returned quantities will instantly be restored to inventory stock with audit log.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dialog Footer -->
                <DialogFooter class="pt-3 border-t border-slate-100 dark:border-slate-800 shrink-0 flex items-center justify-between">
                    <div>
                        <span v-if="returnSaleStep === 'items'" class="text-xs font-bold text-slate-600 dark:text-slate-300">
                            {{ returnLineItems.filter(i => i.is_selected && i.return_qty > 0).length }} products ({{ returnLineItems.reduce((acc, i) => acc + (i.is_selected ? i.return_qty : 0), 0) }} units) selected for return.
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button type="button" variant="outline" @click="isReturnSaleModalOpen = false" class="rounded-xl font-bold">Close</Button>
                        <Button
                            v-if="returnSaleStep === 'items'"
                            type="button"
                            @click="submitPosReturn"
                            :disabled="isSubmittingReturn || totalReturnAmount <= 0"
                            class="rounded-xl bg-rose-600 font-bold text-white shadow-md hover:bg-rose-700 active:scale-95 transition disabled:opacity-40"
                        >
                            <span v-if="isSubmittingReturn" class="flex items-center gap-1.5">
                                <div class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent"></div>
                                <span>Processing Return...</span>
                            </span>
                            <span v-else>Confirm & Process Return (Rs {{ effectiveRefundAmount.toLocaleString() }})</span>
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG: Return Receipt / Credit Voucher Print -->
        <Dialog v-model:open="isReturnReceiptModalOpen">
            <DialogContent class="max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900 z-[90]" overlayClass="z-[85]">
                <DialogHeader class="border-b pb-3">
                    <DialogTitle class="flex items-center justify-between text-base font-black text-slate-900 dark:text-white">
                        <div class="flex items-center gap-2">
                            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                                <CheckCircle class="h-5 w-5" />
                            </div>
                            <span>Return Voucher #{{ completedReturnData?.return_no }}</span>
                        </div>
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Stock has been restored to inventory and transaction recorded.
                    </DialogDescription>
                </DialogHeader>

                <div id="return-receipt-print-area" class="space-y-3 py-3 font-mono text-xs bg-slate-50/50 p-3 rounded-2xl border border-dashed border-slate-200 dark:bg-slate-800/40 dark:border-slate-700">
                    <div class="border-b border-dashed border-slate-300 pb-2 text-center dark:border-slate-700">
                        <div class="text-sm font-black text-slate-900 dark:text-white">{{ currentShopName }}</div>
                        <div class="text-[10px] text-slate-500 uppercase tracking-widest font-bold">Sale Return Voucher</div>
                        <div class="text-[11px] text-slate-600 mt-1">Voucher: <span class="font-bold">{{ completedReturnData?.return_no }}</span></div>
                        <div class="text-[11px] text-slate-600">Original Invoice: <span class="font-bold">{{ completedReturnData?.original_sale?.invoice_no }}</span></div>
                        <div class="text-[10px] text-slate-400">{{ new Date().toLocaleString() }}</div>
                    </div>

                    <!-- Customer -->
                    <div class="text-[11px] border-b border-dashed border-slate-300 pb-2 dark:border-slate-700">
                        <span class="text-slate-500">Customer: </span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ completedReturnData?.original_sale?.customer?.name || 'Walk-In Customer' }}</span>
                        <span v-if="completedReturnData?.original_sale?.customer?.phone" class="text-slate-400 block font-normal">Phone: {{ completedReturnData.original_sale.customer.phone }}</span>
                    </div>

                    <!-- Items -->
                    <div class="space-y-1.5 border-b border-dashed border-slate-300 pb-2 dark:border-slate-700">
                        <div class="text-[10px] uppercase font-bold text-slate-400">Returned Products:</div>
                        <div v-for="item in completedReturnData?.items" :key="item.sale_item_id" class="flex justify-between text-[11px]">
                            <div>
                                <span class="font-bold">{{ item.product_name }}</span>
                                <span v-if="item.imei_1" class="text-indigo-600 dark:text-indigo-400 block text-[10px]">IMEI: {{ item.imei_1 }}</span>
                                <span class="text-slate-400 block text-[10px]">Qty: {{ item.return_qty }} &times; Rs {{ item.unit_price }}</span>
                            </div>
                            <div class="font-bold text-slate-900 dark:text-white">
                                Rs {{ (item.return_qty * item.unit_price).toLocaleString() }}
                            </div>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="space-y-1 pt-1 text-xs">
                        <div class="flex justify-between font-bold">
                            <span>Total Return Value:</span>
                            <span>Rs {{ Number(completedReturnData?.total_return_amount || 0).toLocaleString() }}</span>
                        </div>
                        <div v-if="Number(completedReturnData?.due_offset || 0) > 0" class="flex justify-between text-amber-600 font-bold">
                            <span>Offset Unpaid Due:</span>
                            <span>-Rs {{ Number(completedReturnData.due_offset).toLocaleString() }}</span>
                        </div>
                        <div class="flex justify-between text-emerald-600 font-black text-sm border-t border-dashed border-slate-300 pt-1 dark:border-slate-700">
                            <span>Refund Paid ({{ completedReturnData?.refund_payment_method }}):</span>
                            <span>Rs {{ Number(completedReturnData?.refund_amount || 0).toLocaleString() }}</span>
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2 pt-3 border-t">
                    <Button type="button" variant="outline" @click="isReturnReceiptModalOpen = false" class="rounded-xl font-bold">Close</Button>
                    <Button type="button" @click="printReturnReceipt" class="bg-[#003B7D] font-bold text-white hover:bg-[#002752] flex items-center gap-1.5 rounded-xl shadow-sm">
                        <Printer class="h-4 w-4" />
                        <span>Print Voucher</span>
                    </Button>
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
                    boxShadow:
                        '0 12px 30px -4px rgba(0, 59, 125, 0.2), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
                    fontSize: '13px',
                    fontWeight: '700',
                    padding: '12px 16px',
                },
            }"
        />
        <ConfirmDialog />
    </div>
</template>
