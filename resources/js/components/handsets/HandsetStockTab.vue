<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Copy,
    Edit3,
    Filter,
    Plus,
    RefreshCcw,
    Search,
    ShieldCheck,
    Smartphone,
    Tag,
    Trash2,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
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
import { useConfirm } from '@/composables/useConfirm';
import imeis from '@/routes/imeis';
import mobilePhones from '@/routes/mobile-phones';
import mobileSales from '@/routes/mobile-sales';

const { confirm } = useConfirm();

type PhoneCondition = 'new' | 'used';
type PtaStatus = 'approved' | 'non_pta' | 'jv' | 'cpid' | 'software';
type ImeiStatus = 'in_stock' | 'sold' | 'repairing' | 'returned';

interface StockProduct {
    id: number;
    name: string;
    brand: string;
    sale_price: number | string;
}

interface ProductImeiItem {
    id: number;
    product_id: number;
    imei_1: string;
    imei_2?: string | null;
    color?: string | null;
    storage?: string | null;
    condition: PhoneCondition;
    pta_status: PtaStatus;
    purchase_cost: number | string;
    warranty_days: number;
    status: ImeiStatus;
    sold_at?: string | null;
    created_at: string;
    product?: StockProduct;
}

interface PaginatedImeis {
    data: ProductImeiItem[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    currentTeamSlug: string;
    stockImeis: PaginatedImeis;
    stockBrands: string[];
    stockSummary: {
        in_stock_count: number;
        new_stock_count: number;
        used_stock_count: number;
        total_cost_value: number;
    };
    stockFilters: {
        search?: string;
        condition?: string;
        status?: string;
        pta_status?: string;
        brand?: string;
        per_page?: number;
    };
}>();

const MAX_PRICE = 1000000;

const searchInput = ref(props.stockFilters.search || '');
const conditionFilter = ref(props.stockFilters.condition || 'all');
const statusFilter = ref(props.stockFilters.status || 'in_stock');
const ptaFilter = ref(props.stockFilters.pta_status || 'all');
const brandFilter = ref(props.stockFilters.brand || 'all');

const applyFilters = () => {
    router.get(
        mobileSales.index(props.currentTeamSlug, {
            query: {
                imei_search: searchInput.value || undefined,
                imei_condition:
                    conditionFilter.value !== 'all'
                        ? conditionFilter.value
                        : undefined,
                imei_status:
                    statusFilter.value !== 'all'
                        ? statusFilter.value
                        : undefined,
                imei_pta_status:
                    ptaFilter.value !== 'all' ? ptaFilter.value : undefined,
                imei_brand:
                    brandFilter.value !== 'all' ? brandFilter.value : undefined,
            },
        }).url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['stockImeis', 'stockBrands', 'stockSummary', 'stockFilters'],
        },
    );
};

watch([conditionFilter, statusFilter, ptaFilter, brandFilter], () => {
    applyFilters();
});

const resetFilters = () => {
    searchInput.value = '';
    conditionFilter.value = 'all';
    statusFilter.value = 'in_stock';
    ptaFilter.value = 'all';
    brandFilter.value = 'all';
    applyFilters();
};

const goToPage = (url: string) => {
    router.get(
        url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            only: ['stockImeis', 'stockBrands', 'stockSummary', 'stockFilters'],
        },
    );
};

const isAddMobileModalOpen = ref(false);
const isEditImeiModalOpen = ref(false);

const addMobileForm = useForm({
    brand: 'Apple',
    model_name: '',
    color: '',
    storage: '128GB',
    condition: 'new' as PhoneCondition,
    pta_status: 'approved' as PtaStatus,
    imei_1: '',
    imei_2: '',
    purchase_cost: 0,
    sale_price: 0,
    warranty_days: 365,
});

const editingImei = ref<ProductImeiItem | null>(null);
const editImeiForm = useForm({
    imei_1: '',
    imei_2: '',
    color: '',
    storage: '',
    condition: 'new' as PhoneCondition,
    pta_status: 'approved' as PtaStatus,
    purchase_cost: 0,
    warranty_days: 0,
    status: 'in_stock' as ImeiStatus,
});

const openAddMobileModal = () => {
    addMobileForm.reset();
    addMobileForm.clearErrors();
    isAddMobileModalOpen.value = true;
};

const submitAddMobile = () => {
    addMobileForm.clearErrors();

    if (Number(addMobileForm.sale_price) > MAX_PRICE) {
        addMobileForm.setError(
            'sale_price',
            'موبائل کی فروخت کی قیمت 10 لاکھ (Rs 1,000,000) سے زیادہ نہیں ہو سکتی / Sale price cannot exceed Rs 1,000,000.',
        );
        toast.error('قیمت کی حد سے تجاوز', {
            description:
                'موبائل کی سیل پرائس زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔',
        });
        return;
    }
    if (Number(addMobileForm.purchase_cost) > MAX_PRICE) {
        addMobileForm.setError(
            'purchase_cost',
            'موبائل کی خریداری لاگت 10 لاکھ (Rs 1,000,000) سے زیادہ نہیں ہو سکتی / Purchase cost cannot exceed Rs 1,000,000.',
        );
        toast.error('قیمت کی حد سے تجاوز', {
            description:
                'موبائل کی خریداری لاگت زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔',
        });
        return;
    }

    addMobileForm.post(mobilePhones.store(props.currentTeamSlug).url, {
        preserveState: true,
        onSuccess: () => {
            isAddMobileModalOpen.value = false;
            toast.success('Mobile phone added to stock successfully!');
        },
    });
};

const openEditImeiModal = (item: ProductImeiItem) => {
    editingImei.value = item;
    editImeiForm.clearErrors();
    editImeiForm.imei_1 = item.imei_1;
    editImeiForm.imei_2 = item.imei_2 || '';
    editImeiForm.color = item.color || '';
    editImeiForm.storage = item.storage || '';
    editImeiForm.condition = item.condition;
    editImeiForm.pta_status = item.pta_status;
    editImeiForm.purchase_cost = Number(item.purchase_cost);
    editImeiForm.warranty_days = item.warranty_days || 0;
    editImeiForm.status = item.status;
    isEditImeiModalOpen.value = true;
};

const submitEditImei = () => {
    if (!editingImei.value) return;

    editImeiForm.clearErrors();

    if (Number(editImeiForm.purchase_cost) > MAX_PRICE) {
        editImeiForm.setError(
            'purchase_cost',
            'خریداری لاگت 10 لاکھ (Rs 1,000,000) سے زیادہ نہیں ہو سکتی / Purchase cost cannot exceed Rs 1,000,000.',
        );
        toast.error('قیمت کی حد سے تجاوز', {
            description:
                'خریداری لاگت زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔',
        });
        return;
    }

    editImeiForm.put(
        imeis.update({
            current_team: props.currentTeamSlug,
            imei: editingImei.value.id,
        }).url,
        {
            preserveState: true,
            onSuccess: () => {
                isEditImeiModalOpen.value = false;
                toast.success('IMEI details updated!');
            },
        },
    );
};

const deleteImei = async (item: ProductImeiItem) => {
    const confirmed = await confirm({
        title: 'Delete IMEI Record?',
        message: `Are you sure you want to delete IMEI ${item.imei_1}? This action cannot be undone.`,
        confirmText: 'Delete',
        variant: 'destructive',
    });
    if (!confirmed) return;

    router.delete(
        imeis.destroy({
            current_team: props.currentTeamSlug,
            imei: item.id,
        }).url,
        {
            preserveState: true,
            onSuccess: () => toast.success('IMEI record deleted.'),
        },
    );
};

const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    toast.success(`Copied IMEI: ${text}`);
};

const formatRs = (val: number | string) =>
    'Rs. ' + Number(val || 0).toLocaleString('en-PK');
</script>

<template>
    <div class="space-y-6">
        <!-- ACTION BUTTONS -->
        <div class="flex flex-wrap items-center justify-end gap-2">
            <Button
                class="bg-[#003B7D] text-white shadow-sm hover:bg-[#002855]"
                @click="openAddMobileModal"
            >
                <Plus class="mr-1.5 h-4 w-4" />
                Add Mobile Phone
            </Button>
        </div>

        <!-- SUMMARY STATS WIDGETS -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div
                class="rounded-xl border border-blue-100 bg-blue-50/50 p-4 shadow-sm"
            >
                <div
                    class="text-xs font-semibold tracking-wider text-blue-700 uppercase"
                >
                    In-Stock Handsets
                </div>
                <div class="mt-2 text-2xl font-extrabold text-blue-950">
                    {{ stockSummary.in_stock_count }}
                </div>
                <div class="mt-1 text-xs text-blue-600">
                    Available on counter
                </div>
            </div>

            <div
                class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 shadow-sm"
            >
                <div
                    class="text-xs font-semibold tracking-wider text-emerald-700 uppercase"
                >
                    Brand New Phones
                </div>
                <div class="mt-2 text-2xl font-extrabold text-emerald-950">
                    {{ stockSummary.new_stock_count }}
                </div>
                <div class="mt-1 text-xs text-emerald-600">
                    Box Packed / Pin Pack
                </div>
            </div>

            <div
                class="rounded-xl border border-amber-100 bg-amber-50/50 p-4 shadow-sm"
            >
                <div
                    class="text-xs font-semibold tracking-wider text-amber-700 uppercase"
                >
                    Used Phones
                </div>
                <div class="mt-2 text-2xl font-extrabold text-amber-950">
                    {{ stockSummary.used_stock_count }}
                </div>
                <div class="mt-1 text-xs text-amber-600">Checking Warranty</div>
            </div>

            <div
                class="rounded-xl border border-purple-100 bg-purple-50/50 p-4 shadow-sm"
            >
                <div
                    class="text-xs font-semibold tracking-wider text-purple-700 uppercase"
                >
                    Total Stock Value
                </div>
                <div class="mt-2 text-2xl font-extrabold text-purple-950">
                    {{ formatRs(stockSummary.total_cost_value) }}
                </div>
                <div class="mt-1 text-xs text-purple-600">Cost investment</div>
            </div>
        </div>

        <!-- SEARCH & FILTERS TOOLBAR -->
        <div
            class="flex flex-col gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm md:flex-row md:items-center"
        >
            <div class="relative flex-1">
                <Search class="absolute top-2.5 left-3 h-4 w-4 text-gray-400" />
                <Input
                    v-model="searchInput"
                    placeholder="Search model, brand, IMEI-1, IMEI-2, color..."
                    class="pl-9 text-sm"
                    @keyup.enter="applyFilters"
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Select v-model="conditionFilter">
                    <SelectTrigger class="w-[130px] text-sm">
                        <SelectValue placeholder="Condition" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Conditions</SelectItem>
                        <SelectItem value="new">Brand New</SelectItem>
                        <SelectItem value="used">Used Handset</SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="statusFilter">
                    <SelectTrigger class="w-[130px] text-sm">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="in_stock">In Stock</SelectItem>
                        <SelectItem value="sold">Sold Out</SelectItem>
                        <SelectItem value="all">All Records</SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="ptaFilter">
                    <SelectTrigger class="w-[140px] text-sm">
                        <SelectValue placeholder="PTA Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All PTA Status</SelectItem>
                        <SelectItem value="approved">PTA Approved</SelectItem>
                        <SelectItem value="non_pta">Non-PTA</SelectItem>
                        <SelectItem value="cpid">CPID Approved</SelectItem>
                        <SelectItem value="jv">JV SIM Lock</SelectItem>
                        <SelectItem value="software">Software Patch</SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="brandFilter">
                    <SelectTrigger class="w-[130px] text-sm">
                        <SelectValue placeholder="Brand" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Brands</SelectItem>
                        <SelectItem
                            v-for="b in stockBrands"
                            :key="b"
                            :value="b"
                        >
                            {{ b }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Button
                    class="px-3"
                    size="sm"
                    variant="secondary"
                    @click="applyFilters"
                >
                    <Filter class="mr-1 h-3.5 w-3.5" /> Filter
                </Button>

                <Button
                    class="px-2 text-gray-500"
                    size="sm"
                    variant="ghost"
                    @click="resetFilters"
                >
                    <RefreshCcw class="h-3.5 w-3.5" />
                </Button>
            </div>
        </div>

        <!-- HANDSETS & IMEIs TABLE -->
        <div
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead
                        class="border-b border-gray-200 bg-gray-50/80 text-xs font-semibold text-gray-700 uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3">Device & Model</th>
                            <th class="px-4 py-3">IMEI 1 & 2</th>
                            <th class="px-4 py-3">Variant / Color</th>
                            <th class="px-4 py-3">Condition</th>
                            <th class="px-4 py-3">PTA Status</th>
                            <th class="px-4 py-3 text-right">Cost Price</th>
                            <th class="px-4 py-3 text-right">Sale Price</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="item in stockImeis.data"
                            :key="item.id"
                            class="transition-colors hover:bg-gray-50/60"
                        >
                            <td class="px-4 py-3.5 font-medium text-gray-900">
                                <div class="font-bold text-gray-900">
                                    {{ item.product?.name || 'Mobile Phone' }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ item.product?.brand }} •
                                    {{
                                        item.warranty_days
                                            ? item.warranty_days + 'd Warranty'
                                            : 'No Warranty'
                                    }}
                                </div>
                            </td>

                            <td class="px-4 py-3.5 font-mono text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-semibold text-gray-800">{{
                                        item.imei_1
                                    }}</span>
                                    <button
                                        class="text-gray-400 hover:text-gray-600"
                                        title="Copy IMEI"
                                        @click="copyToClipboard(item.imei_1)"
                                    >
                                        <Copy class="h-3 w-3" />
                                    </button>
                                </div>
                                <div
                                    v-if="item.imei_2"
                                    class="mt-0.5 text-gray-500"
                                >
                                    SIM2: {{ item.imei_2 }}
                                </div>
                            </td>

                            <td class="px-4 py-3.5 text-xs text-gray-700">
                                <div>{{ item.storage || 'Standard' }}</div>
                                <div v-if="item.color" class="text-gray-500">
                                    {{ item.color }}
                                </div>
                            </td>

                            <td class="px-4 py-3.5">
                                <span
                                    v-if="item.condition === 'new'"
                                    class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20 ring-inset"
                                >
                                    NEW
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-600/20 ring-inset"
                                >
                                    USED
                                </span>
                            </td>

                            <td class="px-4 py-3.5">
                                <span
                                    v-if="item.pta_status === 'approved'"
                                    class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-blue-700/10 ring-inset"
                                >
                                    PTA Approved
                                </span>
                                <span
                                    v-else-if="item.pta_status === 'non_pta'"
                                    class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-rose-600/10 ring-inset"
                                >
                                    Non-PTA
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-md bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-700 uppercase ring-1 ring-purple-700/10 ring-inset"
                                >
                                    {{ item.pta_status }}
                                </span>
                            </td>

                            <td
                                class="px-4 py-3.5 text-right font-medium text-gray-800"
                            >
                                {{ formatRs(item.purchase_cost) }}
                            </td>

                            <td
                                class="px-4 py-3.5 text-right font-bold text-[#003B7D]"
                            >
                                {{ formatRs(item.product?.sale_price || 0) }}
                            </td>

                            <td class="px-4 py-3.5 text-center">
                                <span
                                    v-if="item.status === 'in_stock'"
                                    class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800"
                                >
                                    In Stock
                                </span>
                                <span
                                    v-else-if="item.status === 'sold'"
                                    class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600"
                                >
                                    Sold
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 uppercase"
                                >
                                    {{ item.status }}
                                </span>
                            </td>

                            <td class="px-4 py-3.5 text-right">
                                <div
                                    class="flex items-center justify-end gap-1.5"
                                >
                                    <button
                                        class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                                        title="Edit Device IMEI & Cost"
                                        @click="openEditImeiModal(item)"
                                    >
                                        <Edit3 class="h-4 w-4" />
                                    </button>

                                    <button
                                        class="rounded p-1 text-rose-500 hover:bg-rose-50 hover:text-rose-700"
                                        title="Delete IMEI"
                                        @click="deleteImei(item)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="stockImeis.data.length === 0">
                            <td
                                colspan="9"
                                class="py-12 text-center text-gray-500"
                            >
                                <Smartphone
                                    class="mx-auto h-10 w-10 text-gray-300"
                                />
                                <p class="mt-2 font-medium text-gray-600">
                                    No Mobile Phones found
                                </p>
                                <p class="text-xs text-gray-400">
                                    Click "Add Mobile Phone" to add stock.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div
                v-if="stockImeis.last_page > 1"
                class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-4 py-3"
            >
                <div class="text-xs text-gray-500">
                    Showing page
                    <span class="font-semibold">{{
                        stockImeis.current_page
                    }}</span>
                    of
                    <span class="font-semibold">{{
                        stockImeis.last_page
                    }}</span>
                    (Total {{ stockImeis.total }} items)
                </div>
                <div class="flex gap-1">
                    <Button
                        v-for="link in stockImeis.links"
                        :key="link.label"
                        :disabled="!link.url || link.active"
                        class="h-8 min-w-[32px] px-2 text-xs"
                        size="sm"
                        variant="outline"
                        @click="link.url && goToPage(link.url)"
                    >
                        <span v-html="link.label"></span>
                    </Button>
                </div>
            </div>
        </div>

        <!-- MODAL: ADD NEW MOBILE PHONE STOCK -->
        <Dialog
            :open="isAddMobileModalOpen"
            @update:open="isAddMobileModalOpen = $event"
        >
            <DialogContent
                class="max-w-2xl rounded-3xl border border-slate-200/90 bg-white p-5 shadow-2xl sm:p-6 dark:border-slate-800 dark:bg-slate-900"
            >
                <DialogHeader
                    class="border-b border-slate-100 pb-3 dark:border-slate-800"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#003B7D]/10 text-[#003B7D] shadow-xs dark:bg-blue-500/20 dark:text-blue-400"
                        >
                            <Smartphone class="h-6 w-6" />
                        </div>
                        <div>
                            <DialogTitle
                                class="text-base font-black text-slate-900 dark:text-white"
                            >
                                Add Mobile Phone Handset
                            </DialogTitle>
                            <DialogDescription
                                class="mt-0.5 text-xs text-slate-500"
                            >
                                Register a brand new or stock handset with
                                serialised IMEIs and pricing.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form
                    class="space-y-4 py-1 text-xs"
                    @submit.prevent="submitAddMobile"
                >
                    <!-- Section 1: Device Brand & Model -->
                    <div
                        class="space-y-3 rounded-2xl border border-slate-200/80 bg-slate-50/60 p-3.5 dark:border-slate-800 dark:bg-slate-800/40"
                    >
                        <div
                            class="flex items-center gap-1.5 text-[11px] font-black tracking-wider text-slate-700 uppercase dark:text-slate-300"
                        >
                            <Smartphone
                                class="h-3.5 w-3.5 text-[#003B7D] dark:text-blue-400"
                            />
                            <span>1. Device Specifications</span>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Brand
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    v-model="addMobileForm.brand"
                                    required
                                    placeholder="e.g. Apple, Samsung, Xiaomi..."
                                    class="h-9 rounded-xl border-slate-200 bg-white text-xs font-semibold dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="addMobileForm.errors.brand"
                                    class="block text-xs font-bold text-rose-500"
                                    >{{ addMobileForm.errors.brand }}</span
                                >
                            </div>
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Model Name
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    v-model="addMobileForm.model_name"
                                    required
                                    placeholder="e.g. iPhone 15 Pro Max"
                                    class="h-9 rounded-xl border-slate-200 bg-white text-xs font-semibold dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="addMobileForm.errors.model_name"
                                    class="block text-xs font-bold text-rose-500"
                                    >{{ addMobileForm.errors.model_name }}</span
                                >
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 pt-1 sm:grid-cols-3">
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Storage</Label
                                >
                                <Input
                                    v-model="addMobileForm.storage"
                                    placeholder="e.g. 256GB"
                                    class="h-9 rounded-xl border-slate-200 bg-white text-xs dark:border-slate-700 dark:bg-slate-800"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Color Variant</Label
                                >
                                <Input
                                    v-model="addMobileForm.color"
                                    placeholder="e.g. Titanium Blue"
                                    class="h-9 rounded-xl border-slate-200 bg-white text-xs dark:border-slate-700 dark:bg-slate-800"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Condition</Label
                                >
                                <select
                                    v-model="addMobileForm.condition"
                                    class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-800 shadow-2xs focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                >
                                    <option value="new">
                                        Brand New (Box Pack)
                                    </option>
                                    <option value="used">
                                        Used / Open Box
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: PTA Status & Warranty -->
                    <div
                        class="space-y-3 rounded-2xl border border-blue-200/80 bg-blue-50/40 p-3.5 dark:border-blue-900/40 dark:bg-blue-950/20"
                    >
                        <div
                            class="flex items-center gap-1.5 text-[11px] font-black tracking-wider text-[#003B7D] uppercase dark:text-blue-300"
                        >
                            <ShieldCheck class="h-3.5 w-3.5" />
                            <span>2. PTA Approval &amp; Warranty</span>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >PTA Status</Label
                                >
                                <select
                                    v-model="addMobileForm.pta_status"
                                    class="h-9 w-full rounded-xl border border-blue-200 bg-white px-2.5 text-xs font-bold text-slate-800 shadow-2xs focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                >
                                    <option value="approved">
                                        PTA Approved (Official)
                                    </option>
                                    <option value="non_pta">
                                        Non-PTA (SIM Lock)
                                    </option>
                                    <option value="cpid">CPID Approved</option>
                                    <option value="jv">JV SIM Lock</option>
                                    <option value="software">
                                        Software Patch
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Warranty Days</Label
                                >
                                <Input
                                    v-model.number="addMobileForm.warranty_days"
                                    type="number"
                                    placeholder="365"
                                    class="h-9 rounded-xl border-slate-200 bg-white text-xs font-semibold dark:border-slate-700 dark:bg-slate-800"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Serialized IMEI Identifiers -->
                    <div
                        class="space-y-3 rounded-2xl border border-slate-200/80 bg-slate-50/60 p-3.5 dark:border-slate-800 dark:bg-slate-800/40"
                    >
                        <div
                            class="flex items-center gap-1.5 text-[11px] font-black tracking-wider text-slate-700 uppercase dark:text-slate-300"
                        >
                            <Tag class="h-3.5 w-3.5 text-slate-500" />
                            <span>3. Serialized IMEI Numbers</span>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Primary IMEI 1
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    v-model="addMobileForm.imei_1"
                                    required
                                    placeholder="15 Digit Primary IMEI"
                                    class="h-9 rounded-xl border-slate-200 bg-white font-mono text-xs font-bold dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="addMobileForm.errors.imei_1"
                                    class="block text-xs font-bold text-rose-500"
                                    >{{ addMobileForm.errors.imei_1 }}</span
                                >
                            </div>
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Secondary IMEI 2 (Optional)</Label
                                >
                                <Input
                                    v-model="addMobileForm.imei_2"
                                    placeholder="15 Digit Secondary IMEI"
                                    class="h-9 rounded-xl border-slate-200 bg-white font-mono text-xs dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="addMobileForm.errors.imei_2"
                                    class="block text-xs font-bold text-rose-500"
                                    >{{ addMobileForm.errors.imei_2 }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Cost & Sale Pricing -->
                    <div
                        class="space-y-3 rounded-2xl border border-emerald-200/80 bg-emerald-50/40 p-3.5 dark:border-emerald-900/40 dark:bg-emerald-950/20"
                    >
                        <div
                            class="flex items-center gap-1.5 text-[11px] font-black tracking-wider text-emerald-800 uppercase dark:text-emerald-300"
                        >
                            <CheckCircle2
                                class="h-3.5 w-3.5 text-emerald-600"
                            />
                            <span>4. Inventory Pricing (PKR)</span>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <Label
                                        class="font-bold text-slate-700 dark:text-slate-300"
                                        >Purchase Cost
                                        <span class="text-rose-500"
                                            >*</span
                                        ></Label
                                    >
                                    <span class="text-[10px] text-slate-400"
                                        >Max: 10 Lakh</span
                                    >
                                </div>
                                <Input
                                    v-model.number="addMobileForm.purchase_cost"
                                    type="number"
                                    min="0"
                                    max="1000000"
                                    required
                                    placeholder="0"
                                    class="h-10 rounded-xl border-slate-200 bg-white text-sm font-black dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="addMobileForm.errors.purchase_cost"
                                    class="block text-xs font-bold text-rose-500"
                                    >{{
                                        addMobileForm.errors.purchase_cost
                                    }}</span
                                >
                            </div>
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <Label
                                        class="font-bold text-slate-700 dark:text-slate-300"
                                        >Target Selling Price
                                        <span class="text-rose-500"
                                            >*</span
                                        ></Label
                                    >
                                    <span class="text-[10px] text-slate-400"
                                        >Max: 10 Lakh</span
                                    >
                                </div>
                                <Input
                                    v-model.number="addMobileForm.sale_price"
                                    type="number"
                                    min="0"
                                    max="1000000"
                                    required
                                    placeholder="0"
                                    class="h-10 rounded-xl border-slate-200 bg-white text-sm font-black text-[#003B7D] dark:border-slate-700 dark:bg-slate-800 dark:text-blue-400"
                                />
                                <span
                                    v-if="addMobileForm.errors.sale_price"
                                    class="block text-xs font-bold text-rose-500"
                                    >{{ addMobileForm.errors.sale_price }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <DialogFooter
                        class="gap-2 border-t border-slate-100 pt-2 dark:border-slate-800"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-xl text-xs font-bold"
                            @click="isAddMobileModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="addMobileForm.processing"
                            class="rounded-xl bg-[#003B7D] text-xs font-bold text-white shadow-sm transition hover:bg-[#002b5c] active:scale-95"
                        >
                            <span
                                v-if="addMobileForm.processing"
                                class="flex items-center gap-1.5"
                            >
                                <div
                                    class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent"
                                ></div>
                                <span>Saving Handset...</span>
                            </span>
                            <span v-else>Save Handset to Stock</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL: EDIT IMEI DETAILS -->
        <Dialog
            :open="isEditImeiModalOpen"
            @update:open="isEditImeiModalOpen = $event"
        >
            <DialogContent
                class="max-w-lg rounded-3xl border border-slate-200/90 bg-white p-5 shadow-2xl sm:p-6 dark:border-slate-800 dark:bg-slate-900"
            >
                <DialogHeader
                    class="border-b border-slate-100 pb-3 dark:border-slate-800"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#003B7D]/10 text-[#003B7D] dark:bg-blue-500/20 dark:text-blue-400"
                        >
                            <Edit3 class="h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle
                                class="text-base font-black text-slate-900 dark:text-white"
                            >
                                Edit Handset IMEI &amp; Pricing
                            </DialogTitle>
                            <DialogDescription
                                class="mt-0.5 text-xs text-slate-500"
                            >
                                Update IMEI identifier numbers, condition,
                                warranty, or inventory status.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form
                    class="space-y-4 py-1 text-xs"
                    @submit.prevent="submitEditImei"
                >
                    <div
                        class="space-y-3 rounded-2xl border border-slate-200/80 bg-slate-50/60 p-3.5 dark:border-slate-800 dark:bg-slate-800/40"
                    >
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Primary IMEI 1</Label
                                >
                                <Input
                                    v-model="editImeiForm.imei_1"
                                    required
                                    class="h-9 rounded-xl border-slate-200 bg-white font-mono text-xs font-bold dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="editImeiForm.errors.imei_1"
                                    class="block text-xs font-bold text-rose-500"
                                    >{{ editImeiForm.errors.imei_1 }}</span
                                >
                            </div>
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Secondary IMEI 2</Label
                                >
                                <Input
                                    v-model="editImeiForm.imei_2"
                                    class="h-9 rounded-xl border-slate-200 bg-white font-mono text-xs dark:border-slate-700 dark:bg-slate-800"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Color Variant</Label
                                >
                                <Input
                                    v-model="editImeiForm.color"
                                    class="h-9 rounded-xl border-slate-200 bg-white text-xs dark:border-slate-700 dark:bg-slate-800"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Storage</Label
                                >
                                <Input
                                    v-model="editImeiForm.storage"
                                    class="h-9 rounded-xl border-slate-200 bg-white text-xs dark:border-slate-700 dark:bg-slate-800"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Condition</Label
                                >
                                <select
                                    v-model="editImeiForm.condition"
                                    class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-800 shadow-2xs focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                >
                                    <option value="new">Brand New</option>
                                    <option value="used">Used Handset</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >PTA Status</Label
                                >
                                <select
                                    v-model="editImeiForm.pta_status"
                                    class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-800 shadow-2xs focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                >
                                    <option value="approved">
                                        PTA Approved
                                    </option>
                                    <option value="non_pta">Non-PTA</option>
                                    <option value="cpid">CPID Approved</option>
                                    <option value="jv">JV SIM Lock</option>
                                    <option value="software">
                                        Software Patch
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Purchase Cost (PKR)</Label
                                >
                                <Input
                                    v-model.number="editImeiForm.purchase_cost"
                                    type="number"
                                    min="0"
                                    max="1000000"
                                    required
                                    class="h-9 rounded-xl border-slate-200 bg-white text-xs font-black dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="editImeiForm.errors.purchase_cost"
                                    class="block text-xs font-bold text-rose-500"
                                    >{{
                                        editImeiForm.errors.purchase_cost
                                    }}</span
                                >
                            </div>
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Stock Status</Label
                                >
                                <select
                                    v-model="editImeiForm.status"
                                    class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-800 shadow-2xs focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                >
                                    <option value="in_stock">
                                        In Stock (Available)
                                    </option>
                                    <option value="sold">Sold Out</option>
                                    <option value="repairing">
                                        In Repair Lab
                                    </option>
                                    <option value="returned">Returned</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <DialogFooter
                        class="gap-2 border-t border-slate-100 pt-2 dark:border-slate-800"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-xl text-xs font-bold"
                            @click="isEditImeiModalOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="editImeiForm.processing"
                            class="rounded-xl bg-[#003B7D] text-xs font-bold text-white shadow-sm transition hover:bg-[#002b5c] active:scale-95"
                        >
                            Update Details
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
