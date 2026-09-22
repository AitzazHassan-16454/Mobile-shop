<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownLeft,
    ArrowUpRight,
    Boxes,
    CheckCircle,
    Plus,
    QrCode,
    Search,
    SlidersHorizontal,
    Smartphone,
    Tag,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
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
import { Label } from '@/components/ui/label';

// Table Column Customizer State
const defaultVisibleColumns = {
    date: true,
    product: true,
    type: true,
    qty: true,
    reason: true,
    notes: true,
    user: true,
    actions: true,
};

const visibleColumns = ref({ ...defaultVisibleColumns });

const adjustmentColumnLabels: Record<keyof typeof defaultVisibleColumns, string> = {
    date: 'Date & Time',
    product: 'Product & Details',
    type: 'Adjustment Type',
    qty: 'Qty',
    reason: 'Reason',
    notes: 'Notes / Remarks',
    user: 'Recorded By',
    actions: 'Actions',
};

const STORAGE_KEY = 'faizan_mobile_stock_adjustments_table_columns_v1';

onMounted(() => {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            visibleColumns.value = { ...defaultVisibleColumns, ...JSON.parse(saved) };
        }
    } catch (e) {
        console.error(e);
    }
});

const toggleAdjustmentColumn = (key: string) => {
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useConfirm } from '@/composables/useConfirm';
import type { Team } from '@/types';

const { confirm } = useConfirm();

interface ProductImeiItem {
    id: number;
    imei_1: string;
    imei_2?: string | null;
    color?: string | null;
    storage?: string | null;
    status: string;
}

interface ProductItem {
    id: number;
    name: string;
    brand: string;
    category: string;
    barcode?: string | null;
    is_serialized: boolean;
    stock_quantity: number;
    sale_price: number | string;
    imeis?: ProductImeiItem[];
}

interface UserItem {
    id: number;
    name: string;
}

interface StockAdjustmentItem {
    id: number;
    product_id: number;
    product_imei_id?: number | null;
    user_id?: number | null;
    type: 'addition' | 'subtraction';
    quantity: number;
    reason: string;
    notes?: string | null;
    created_at: string;
    product?: ProductItem;
    imei?: ProductImeiItem;
    user?: UserItem;
}

interface SummaryStats {
    total_adjustments: number;
    total_additions: number;
    total_subtractions: number;
    damaged_count: number;
}

interface Filters {
    search: string;
    reason: string;
    type: string;
}

const props = defineProps<{
    adjustments: {
        data: StockAdjustmentItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    products: ProductItem[];
    filters: Filters;
    summary: SummaryStats;
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
                title: 'Stock Adjustments',
                href: layoutProps.currentTeam
                    ? `/${layoutProps.currentTeam.slug}/stock-adjustments`
                    : '/stock-adjustments',
            },
        ],
    }),
});

// Search & Filtering
const search = ref(props.filters?.search || '');
const selectedType = ref(props.filters?.type || 'all');
const selectedReason = ref(props.filters?.reason || 'all');

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const applyFilters = () => {
    router.get(
        `/${currentTeamSlug.value}/stock-adjustments`,
        {
            search: search.value || undefined,
            type: selectedType.value !== 'all' ? selectedType.value : undefined,
            reason:
                selectedReason.value !== 'all'
                    ? selectedReason.value
                    : undefined,
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

watch([selectedType, selectedReason], () => {
    applyFilters();
});

// Create Modal State
const isAdjustmentModalOpen = ref(false);
const selectedProduct = ref<ProductItem | null>(null);

const form = useForm({
    product_id: '',
    product_imei_id: '',
    type: 'subtraction' as 'addition' | 'subtraction',
    quantity: 1,
    reason: 'damaged',
    notes: '',
});

const onProductSelect = (productIdVal: string) => {
    form.product_id = productIdVal;
    form.product_imei_id = '';
    const found = props.products.find((p) => String(p.id) === String(productIdVal));
    selectedProduct.value = found || null;
};

const openCreateAdjustmentModal = () => {
    form.reset();
    form.clearErrors();
    form.type = 'subtraction';
    form.quantity = 1;
    form.reason = 'damaged';
    selectedProduct.value = null;
    isAdjustmentModalOpen.value = true;
};

const submitAdjustment = () => {
    form.post(`/${currentTeamSlug.value}/stock-adjustments`, {
        onSuccess: () => {
            isAdjustmentModalOpen.value = false;
        },
    });
};

const deleteAdjustment = async (adjustment: StockAdjustmentItem) => {
    const ok = await confirm({
        title: 'Revert Stock Adjustment',
        message: `Are you sure you want to revert this stock adjustment record for "${adjustment.product?.name || 'Product'}"?`,
        confirmText: 'Yes, Revert',
        cancelText: 'Cancel',
        variant: 'destructive',
    });

    if (ok) {
        router.delete(`/${currentTeamSlug.value}/stock-adjustments/${adjustment.id}`);
    }
};

const formatDate = (dateString: string) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-PK', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatReasonLabel = (r: string) => {
    const map: Record<string, string> = {
        damaged: 'Damaged / Defective',
        lost: 'Lost / Missing',
        stolen: 'Stolen',
        audit_reconciliation: 'Inventory Audit Count',
        found: 'Found Stock',
        other: 'Other / Correction',
    };
    return map[r] || r;
};
</script>

<template>
    <Head title="Stock Adjustments & Audit Log" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Top Banner Header -->
        <section
            class="glass-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="eyebrow mb-1.5">Inventory Audit & Control</p>
                <h1
                    class="flex items-center gap-2.5 text-2xl font-black text-slate-900"
                >
                    <SlidersHorizontal class="h-7 w-7 text-primary" /> Stock Adjustments
                </h1>
                <p class="mt-1 text-xs text-slate-500 font-medium">
                    Reconcile stock count discrepancies, damaged items, lost units, or manual audit adjustments.
                </p>
            </div>
            <Button
                @click="openCreateAdjustmentModal"
                class="gap-2 bg-primary font-bold text-white shadow-md hover:bg-primary/90"
            >
                <Plus class="h-4 w-4" />
                Record Stock Adjustment
            </Button>
        </section>

        <!-- Summary Metric Cards -->
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="glass-card p-4">
                <div class="flex items-center justify-between">
                    <span class="eyebrow text-slate-500">Total Audit Entries</span>
                    <SlidersHorizontal class="h-5 w-5 text-primary" />
                </div>
                <div class="tnum mt-2 text-2xl font-black text-slate-900">
                    {{ summary.total_adjustments }}
                </div>
                <div class="mt-1 text-xs text-slate-500 font-medium">
                    Recorded adjustment logs
                </div>
            </div>

            <div class="glass-card p-4">
                <div class="flex items-center justify-between">
                    <span class="eyebrow text-emerald-600">Stock Additions (+)</span>
                    <ArrowUpRight class="h-5 w-5 text-emerald-600" />
                </div>
                <div class="tnum mt-2 text-2xl font-black text-emerald-600">
                    +{{ summary.total_additions }} Units
                </div>
                <div class="mt-1 text-xs text-slate-500 font-medium">
                    Found or corrected items added
                </div>
            </div>

            <div class="glass-card p-4">
                <div class="flex items-center justify-between">
                    <span class="eyebrow text-rose-600">Stock Deductions (-)</span>
                    <ArrowDownLeft class="h-5 w-5 text-rose-600" />
                </div>
                <div class="tnum mt-2 text-2xl font-black text-rose-600">
                    -{{ summary.total_subtractions }} Units
                </div>
                <div class="mt-1 text-xs text-slate-500 font-medium">
                    Damaged, lost, or stolen items
                </div>
            </div>

            <div class="glass-card p-4">
                <div class="flex items-center justify-between">
                    <span class="eyebrow text-amber-600">Damaged Items</span>
                    <AlertTriangle class="h-5 w-5 text-amber-600" />
                </div>
                <div class="tnum mt-2 text-2xl font-black text-amber-600">
                    {{ summary.damaged_count }} Items
                </div>
                <div class="mt-1 text-xs text-slate-500 font-medium">
                    Marked as damaged or defective
                </div>
            </div>
        </section>

        <!-- Search & Filter Section -->
        <section class="glass-card flex flex-col gap-4 p-4 md:flex-row md:items-center md:justify-between">
            <div class="relative flex-1">
                <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <Input
                    v-model="search"
                    placeholder="Search by product name, barcode, IMEI or notes..."
                    class="pl-9 bg-white text-xs text-slate-900 border-slate-200"
                />
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="w-44">
                    <Select v-model="selectedType">
                        <SelectTrigger class="h-9 text-xs border-slate-200 bg-white">
                            <SelectValue placeholder="All Adjustment Types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Types</SelectItem>
                            <SelectItem value="addition">Stock Addition (+)</SelectItem>
                            <SelectItem value="subtraction">Stock Subtraction (-)</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="w-48">
                    <Select v-model="selectedReason">
                        <SelectTrigger class="h-9 text-xs border-slate-200 bg-white">
                            <SelectValue placeholder="All Reasons" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Reasons</SelectItem>
                            <SelectItem value="damaged">Damaged / Defective</SelectItem>
                            <SelectItem value="lost">Lost / Missing</SelectItem>
                            <SelectItem value="stolen">Stolen</SelectItem>
                            <SelectItem value="audit_reconciliation">Audit Reconciliation</SelectItem>
                            <SelectItem value="found">Found Stock</SelectItem>
                            <SelectItem value="other">Other / Correction</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Table Columns Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-9 gap-1.5 text-xs font-semibold whitespace-nowrap bg-white border-slate-200"
                        >
                            <SlidersHorizontal class="h-3.5 w-3.5 text-primary" />
                            <span>Columns</span>
                            <span class="ml-1 rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold text-primary">
                                {{ activeColumnCount }}/8
                            </span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56 p-2 space-y-1">
                        <DropdownMenuLabel class="flex items-center justify-between text-xs font-bold px-1 py-1">
                            <span>Table Columns</span>
                            <button
                                type="button"
                                @click="resetColumns"
                                class="text-[11px] font-semibold text-primary hover:underline cursor-pointer"
                            >
                                Reset All
                            </button>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator class="my-1" />
                        <div
                            v-for="(label, key) in adjustmentColumnLabels"
                            :key="key"
                            @click.stop="toggleAdjustmentColumn(key)"
                            class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-xs font-medium hover:bg-slate-100 cursor-pointer select-none transition-colors"
                        >
                            <span>{{ label }}</span>
                            <input
                                type="checkbox"
                                :checked="visibleColumns[key as keyof typeof visibleColumns]"
                                @change="toggleAdjustmentColumn(key)"
                                @click.stop
                                class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary cursor-pointer"
                            />
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </section>

        <!-- Stock Adjustments Data Table -->
        <section class="glass-card overflow-hidden rounded-2xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-black tracking-wider text-slate-500 uppercase">
                        <tr>
                            <th v-if="visibleColumns.date" class="px-5 py-3.5">Date & Time</th>
                            <th v-if="visibleColumns.product" class="px-5 py-3.5">Product & Details</th>
                            <th v-if="visibleColumns.type" class="px-5 py-3.5">Adjustment Type</th>
                            <th v-if="visibleColumns.qty" class="px-5 py-3.5 text-center">Qty</th>
                            <th v-if="visibleColumns.reason" class="px-5 py-3.5">Reason</th>
                            <th v-if="visibleColumns.notes" class="px-5 py-3.5">Notes / Remarks</th>
                            <th v-if="visibleColumns.user" class="px-5 py-3.5">Recorded By</th>
                            <th v-if="visibleColumns.actions" class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="adjustments.data.length === 0">
                            <td colspan="8" class="px-5 py-12 text-center text-slate-400 font-medium">
                                No stock adjustments found. Click "Record Stock Adjustment" to create an audit record.
                            </td>
                        </tr>

                        <tr
                            v-for="adj in adjustments.data"
                            :key="adj.id"
                            class="transition-colors hover:bg-slate-50/60 text-slate-600"
                        >
                            <td v-if="visibleColumns.date" class="px-5 py-4 text-xs text-slate-500 whitespace-nowrap">
                                {{ formatDate(adj.created_at) }}
                            </td>

                            <td v-if="visibleColumns.product" class="px-5 py-4">
                                <div class="font-bold text-slate-900 text-sm">
                                    {{ adj.product?.name || 'Unknown Product' }}
                                </div>
                                <div class="mt-0.5 text-xs text-slate-500 font-medium flex items-center gap-1.5">
                                    <span class="font-bold text-primary">{{ adj.product?.brand }}</span>
                                    <span>&bull; {{ adj.product?.category }}</span>
                                    <span v-if="adj.imei" class="font-mono text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded text-[10px]">
                                        IMEI: {{ adj.imei.imei_1 }}
                                    </span>
                                </div>
                            </td>

                            <td v-if="visibleColumns.type" class="px-5 py-4">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-extrabold uppercase"
                                    :class="
                                        adj.type === 'addition'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-rose-100 text-rose-800'
                                    "
                                >
                                    <component :is="adj.type === 'addition' ? ArrowUpRight : ArrowDownLeft" class="h-3.5 w-3.5" />
                                    {{ adj.type === 'addition' ? 'Stock Addition' : 'Stock Deduction' }}
                                </span>
                            </td>

                            <td v-if="visibleColumns.qty" class="px-5 py-4 text-center tnum font-black text-slate-900 text-base">
                                {{ adj.type === 'addition' ? '+' : '-' }}{{ adj.quantity }}
                            </td>

                            <td v-if="visibleColumns.reason" class="px-5 py-4">
                                <span
                                    class="rounded-md px-2 py-0.5 text-[11px] font-bold"
                                    :class="
                                        adj.reason === 'damaged'
                                            ? 'bg-amber-100 text-amber-800'
                                            : adj.reason === 'lost' || adj.reason === 'stolen'
                                            ? 'bg-rose-100 text-rose-800'
                                            : adj.reason === 'found'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-slate-100 text-slate-700'
                                    "
                                >
                                    {{ formatReasonLabel(adj.reason) }}
                                </span>
                            </td>

                            <td v-if="visibleColumns.notes" class="px-5 py-4 text-xs text-slate-500 max-w-[200px] truncate">
                                {{ adj.notes || '-' }}
                            </td>

                            <td v-if="visibleColumns.user" class="px-5 py-4 text-xs text-slate-700 font-semibold">
                                {{ adj.user?.name || 'Admin' }}
                            </td>

                            <td v-if="visibleColumns.actions" class="px-5 py-4 text-right">
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="h-8 w-8 p-0 text-rose-500 hover:bg-rose-50 hover:text-rose-700"
                                    @click="deleteAdjustment(adj)"
                                    title="Revert / Delete Adjustment Record"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- New Stock Adjustment Modal Window -->
        <Dialog :open="isAdjustmentModalOpen" @update:open="isAdjustmentModalOpen = $event">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle class="text-lg font-black text-slate-900 flex items-center gap-2">
                        <SlidersHorizontal class="h-5 w-5 text-primary" />
                        Record Stock Adjustment
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Adjust product inventory levels for damage, lost units, returns, or physical count audits.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4 py-2" @submit.prevent="submitAdjustment">
                    <!-- Product Selector -->
                    <div>
                        <Label class="text-xs font-bold text-slate-700">Select Product Catalog Item *</Label>
                        <Select :model-value="form.product_id" @update:model-value="onProductSelect">
                            <SelectTrigger class="mt-1 bg-white border-slate-200 text-xs">
                                <SelectValue placeholder="Choose a product from catalog..." />
                            </SelectTrigger>
                            <SelectContent class="max-h-60">
                                <SelectItem v-for="p in products" :key="p.id" :value="String(p.id)">
                                    {{ p.name }} ({{ p.brand }}) &bull; Stock: {{ p.is_serialized ? p.imeis?.length || 0 : p.stock_quantity }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Optional Serial / IMEI Selector for Handsets -->
                    <div v-if="selectedProduct?.is_serialized" class="rounded-xl border border-primary/20 bg-primary/5 p-3 space-y-2">
                        <Label class="text-xs font-bold text-primary">Select Specific IMEI Unit (Optional)</Label>
                        <Select v-model="form.product_imei_id">
                            <SelectTrigger class="bg-white border-slate-200 text-xs"><SelectValue placeholder="Select IMEI serial..." /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="i in selectedProduct.imeis" :key="i.id" :value="String(i.id)">
                                    IMEI: {{ i.imei_1 }} ({{ i.color || 'No color' }} &bull; {{ i.storage || 'No storage' }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Adjustment Type Toggle -->
                    <div>
                        <Label class="text-xs font-bold text-slate-700 mb-1.5 block">Adjustment Type</Label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="flex items-center justify-center gap-2 rounded-xl border p-2.5 text-xs font-bold transition-all text-left"
                                :class="
                                    form.type === 'subtraction'
                                        ? 'border-rose-500 bg-rose-50 text-rose-900 ring-2 ring-rose-500/20 shadow-xs'
                                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                                "
                                @click="form.type = 'subtraction'"
                            >
                                <ArrowDownLeft class="h-4 w-4 text-rose-600" />
                                <div>
                                    <div>- Deduct Stock</div>
                                    <div class="text-[10px] font-normal text-slate-500">Damaged / Lost / Missing</div>
                                </div>
                            </button>

                            <button
                                type="button"
                                class="flex items-center justify-center gap-2 rounded-xl border p-2.5 text-xs font-bold transition-all text-left"
                                :class="
                                    form.type === 'addition'
                                        ? 'border-emerald-500 bg-emerald-50 text-emerald-900 ring-2 ring-emerald-500/20 shadow-xs'
                                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                                "
                                @click="form.type = 'addition'"
                            >
                                <ArrowUpRight class="h-4 w-4 text-emerald-600" />
                                <div>
                                    <div>+ Add Stock</div>
                                    <div class="text-[10px] font-normal text-slate-500">Found / Reconciled Item</div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Quantity & Reason -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label for="adj-qty" class="text-xs font-bold">Quantity *</Label>
                            <Input
                                id="adj-qty"
                                v-model="form.quantity"
                                type="number"
                                min="1"
                                required
                                class="mt-1 text-xs bg-white font-bold"
                            />
                        </div>
                        <div>
                            <Label class="text-xs font-bold">Reason *</Label>
                            <Select v-model="form.reason">
                                <SelectTrigger class="mt-1 h-9 text-xs bg-white"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="damaged">Damaged / Defective</SelectItem>
                                    <SelectItem value="lost">Lost / Missing</SelectItem>
                                    <SelectItem value="stolen">Stolen</SelectItem>
                                    <SelectItem value="audit_reconciliation">Audit Reconciliation</SelectItem>
                                    <SelectItem value="found">Found Stock</SelectItem>
                                    <SelectItem value="other">Other / Correction</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <Label for="adj-notes" class="text-xs font-semibold">Notes / Audit Explanation</Label>
                        <Input
                            id="adj-notes"
                            v-model="form.notes"
                            placeholder="e.g. Screen cracked during display setup"
                            class="mt-1 text-xs bg-white"
                        />
                    </div>

                    <DialogFooter class="pt-3 border-t border-slate-100">
                        <Button type="button" variant="outline" @click="isAdjustmentModalOpen = false">Cancel</Button>
                        <Button class="bg-primary text-white font-bold shadow-md" :disabled="form.processing">
                            Save Adjustment Log
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
