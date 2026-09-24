<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Check,
    CheckCircle2,
    Copy,
    Edit3,
    FilePlus,
    Filter,
    Plus,
    QrCode,
    RefreshCcw,
    Search,
    ShieldCheck,
    Smartphone,
    Tag,
    Trash2,
    UserCheck,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

interface Product {
    id: number;
    name: string;
    brand: string;
    category: string;
    sale_price: number | string;
    alert_quantity: number;
}

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
    sold_at?: string | null;
    created_at: string;
    product?: Product;
}

interface SummaryStats {
    in_stock_count: number;
    new_stock_count: number;
    used_stock_count: number;
    total_cost_value: number;
}

const props = defineProps<{
    imeis: {
        data: ProductImeiItem[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    products: Product[];
    brands: string[];
    filters: {
        search?: string;
        condition?: string;
        status?: string;
        pta_status?: string;
        brand?: string;
        per_page?: number;
    };
    summary: SummaryStats;
}>();

const page = usePage();
const currentTeam = computed(() => (page.props.currentTeam as any)?.slug || 'default');

// Filters state
const searchInput = ref(props.filters.search || '');
const conditionFilter = ref(props.filters.condition || 'all');
const statusFilter = ref(props.filters.status || 'in_stock');
const ptaFilter = ref(props.filters.pta_status || 'all');
const brandFilter = ref(props.filters.brand || 'all');

const applyFilters = () => {
    router.get(
        `/${currentTeam.value}/mobile-phones`,
        {
            search: searchInput.value || undefined,
            condition: conditionFilter.value !== 'all' ? conditionFilter.value : undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
            pta_status: ptaFilter.value !== 'all' ? ptaFilter.value : undefined,
            brand: brandFilter.value !== 'all' ? brandFilter.value : undefined,
        },
        { preserveState: true, replace: true }
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

// Modal Controls
const isAddMobileModalOpen = ref(false);
const isPurchaseUsedModalOpen = ref(false);
const isEditImeiModalOpen = ref(false);

// Forms
const addMobileForm = useForm({
    brand: 'Apple',
    model_name: '',
    color: '',
    storage: '128GB',
    condition: 'new' as 'new' | 'used',
    pta_status: 'approved' as 'approved' | 'non_pta' | 'jv' | 'cpid' | 'software',
    imei_1: '',
    imei_2: '',
    purchase_cost: 0,
    sale_price: 0,
    warranty_days: 365,
});

const usedPurchaseForm = useForm({
    seller_name: '',
    seller_father_name: '',
    seller_cnic: '',
    seller_phone: '',
    seller_address: '',
    brand: 'Apple',
    device_model: '',
    color: '',
    storage: '128GB',
    pta_status: 'approved' as 'approved' | 'non_pta' | 'jv' | 'cpid' | 'software',
    imei_1: '',
    imei_2: '',
    purchase_amount: 0,
    sale_price: 0,
    payment_method: 'cash',
});

const editingImei = ref<ProductImeiItem | null>(null);
const editImeiForm = useForm({
    imei_1: '',
    imei_2: '',
    color: '',
    storage: '',
    condition: 'new' as 'new' | 'used',
    pta_status: 'approved' as 'approved' | 'non_pta' | 'jv' | 'cpid' | 'software',
    purchase_cost: 0,
    warranty_days: 0,
    status: 'in_stock' as 'in_stock' | 'sold' | 'repairing' | 'returned',
});

const openAddMobileModal = () => {
    addMobileForm.reset();
    isAddMobileModalOpen.value = true;
};

const submitAddMobile = () => {
    addMobileForm.post(`/${currentTeam.value}/mobile-phones`, {
        onSuccess: () => {
            isAddMobileModalOpen.value = false;
            toast.success('Mobile phone added to stock successfully!');
        },
    });
};

const openPurchaseUsedModal = () => {
    usedPurchaseForm.reset();
    isPurchaseUsedModalOpen.value = true;
};

const submitUsedPurchase = () => {
    usedPurchaseForm.post(`/${currentTeam.value}/mobile-phones/used-purchase`, {
        onSuccess: () => {
            isPurchaseUsedModalOpen.value = false;
            toast.success('Used phone purchased & added to stock!');
        },
    });
};

const openEditImeiModal = (item: ProductImeiItem) => {
    editingImei.value = item;
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
    editImeiForm.put(`/${currentTeam.value}/imeis/${editingImei.value.id}`, {
        onSuccess: () => {
            isEditImeiModalOpen.value = false;
            toast.success('IMEI details updated!');
        },
    });
};

const deleteImei = async (item: ProductImeiItem) => {
    const confirmed = await confirm({
        title: 'Delete IMEI Record?',
        message: `Are you sure you want to delete IMEI ${item.imei_1}? This action cannot be undone.`,
        confirmText: 'Delete',
        variant: 'destructive',
    });
    if (!confirmed) return;

    router.delete(`/${currentTeam.value}/imeis/${item.id}`, {
        onSuccess: () => toast.success('IMEI record deleted.'),
    });
};

const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    toast.success(`Copied IMEI: ${text}`);
};

const formatRs = (val: number | string) => {
    return 'Rs. ' + Number(val || 0).toLocaleString('en-PK');
};
</script>

<template>
    <Head title="Mobile Phones & Devices" />

    <div class="space-y-6 p-4 md:p-6">
        <!-- TOP HEADER & TITLE -->
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight text-gray-900">
                    <Smartphone class="h-7 w-7 text-[#003B7D]" />
                    Mobile Phones & Handsets
                </h1>
                <p class="mt-1 text-sm text-gray-500">
                    Manage new & used smartphones, IMEI tracking, PTA status, and purchase records.
                </p>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    @click="openAddMobileModal"
                    class="bg-[#003B7D] hover:bg-[#002855] text-white shadow-sm"
                >
                    <Plus class="mr-1.5 h-4 w-4" />
                    Add Mobile Phone
                </Button>

                <Button
                    @click="openPurchaseUsedModal"
                    variant="outline"
                    class="border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100"
                >
                    <UserCheck class="mr-1.5 h-4 w-4 text-amber-600" />
                    Purchase Used Phone
                </Button>
            </div>
        </div>

        <!-- SUMMARY STATS WIDGETS -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wider text-blue-700">In-Stock Handsets</div>
                <div class="mt-2 text-2xl font-extrabold text-blue-950">{{ summary.in_stock_count }}</div>
                <div class="mt-1 text-xs text-blue-600">Available on counter</div>
            </div>

            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Brand New Phones</div>
                <div class="mt-2 text-2xl font-extrabold text-emerald-950">{{ summary.new_stock_count }}</div>
                <div class="mt-1 text-xs text-emerald-600">Box Packed / Pin Pack</div>
            </div>

            <div class="rounded-xl border border-amber-100 bg-amber-50/50 p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wider text-amber-700">Used Phones</div>
                <div class="mt-2 text-2xl font-extrabold text-amber-950">{{ summary.used_stock_count }}</div>
                <div class="mt-1 text-xs text-amber-600">Checking Warranty</div>
            </div>

            <div class="rounded-xl border border-purple-100 bg-purple-50/50 p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wider text-purple-700">Total Stock Value</div>
                <div class="mt-2 text-2xl font-extrabold text-purple-950">{{ formatRs(summary.total_cost_value) }}</div>
                <div class="mt-1 text-xs text-purple-600">Cost investment</div>
            </div>
        </div>

        <!-- SEARCH & FILTERS TOOLBAR -->
        <div class="flex flex-col gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm md:flex-row md:items-center">
            <!-- Search Input -->
            <div class="relative flex-1">
                <Search class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                <Input
                    v-model="searchInput"
                    @keyup.enter="applyFilters"
                    placeholder="Search model, brand, IMEI-1, IMEI-2, color..."
                    class="pl-9 text-sm"
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Condition Dropdown -->
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

                <!-- Stock Status Dropdown -->
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

                <!-- PTA Status Dropdown -->
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

                <Button @click="applyFilters" variant="secondary" size="sm" class="px-3">
                    <Filter class="mr-1 h-3.5 w-3.5" /> Filter
                </Button>

                <Button @click="resetFilters" variant="ghost" size="sm" class="px-2 text-gray-500">
                    <RefreshCcw class="h-3.5 w-3.5" />
                </Button>
            </div>
        </div>

        <!-- HANDSETS & IMEIs TABLE -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="border-b border-gray-200 bg-gray-50/80 text-xs uppercase font-semibold text-gray-700">
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
                            v-for="item in imeis.data"
                            :key="item.id"
                            class="hover:bg-gray-50/60 transition-colors"
                        >
                            <!-- Device Name & Brand -->
                            <td class="px-4 py-3.5 font-medium text-gray-900">
                                <div class="font-bold text-gray-900">
                                    {{ item.product?.name || 'Mobile Phone' }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ item.product?.brand }} • {{ item.warranty_days ? item.warranty_days + 'd Warranty' : 'No Warranty' }}
                                </div>
                            </td>

                            <!-- IMEI Numbers -->
                            <td class="px-4 py-3.5 font-mono text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-semibold text-gray-800">{{ item.imei_1 }}</span>
                                    <button
                                        @click="copyToClipboard(item.imei_1)"
                                        title="Copy IMEI"
                                        class="text-gray-400 hover:text-gray-600"
                                    >
                                        <Copy class="h-3 w-3" />
                                    </button>
                                </div>
                                <div v-if="item.imei_2" class="mt-0.5 text-gray-500">
                                    SIM2: {{ item.imei_2 }}
                                </div>
                            </td>

                            <!-- Color & Storage -->
                            <td class="px-4 py-3.5 text-xs text-gray-700">
                                <div>{{ item.storage || 'Standard' }}</div>
                                <div v-if="item.color" class="text-gray-500">{{ item.color }}</div>
                            </td>

                            <!-- Condition Badge -->
                            <td class="px-4 py-3.5">
                                <span
                                    v-if="item.condition === 'new'"
                                    class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20"
                                >
                                    NEW
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/20"
                                >
                                    USED
                                </span>
                            </td>

                            <!-- PTA Status Badge -->
                            <td class="px-4 py-3.5">
                                <span
                                    v-if="item.pta_status === 'approved'"
                                    class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10"
                                >
                                    PTA Approved
                                </span>
                                <span
                                    v-else-if="item.pta_status === 'non_pta'"
                                    class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/10"
                                >
                                    Non-PTA
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-md bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10 uppercase"
                                >
                                    {{ item.pta_status }}
                                </span>
                            </td>

                            <!-- Cost Price -->
                            <td class="px-4 py-3.5 text-right font-medium text-gray-800">
                                {{ formatRs(item.purchase_cost) }}
                            </td>

                            <!-- Sale Price -->
                            <td class="px-4 py-3.5 text-right font-bold text-[#003B7D]">
                                {{ formatRs(item.product?.sale_price || 0) }}
                            </td>

                            <!-- Status -->
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
                                <span v-else class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 uppercase">
                                    {{ item.status }}
                                </span>
                            </td>

                            <!-- Action Buttons -->
                            <td class="px-4 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        @click="openEditImeiModal(item)"
                                        class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                                        title="Edit Device IMEI & Cost"
                                    >
                                        <Edit3 class="h-4 w-4" />
                                    </button>

                                    <button
                                        @click="deleteImei(item)"
                                        class="rounded p-1 text-rose-500 hover:bg-rose-50 hover:text-rose-700"
                                        title="Delete IMEI"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="imeis.data.length === 0">
                            <td colspan="9" class="py-12 text-center text-gray-500">
                                <Smartphone class="mx-auto h-10 w-10 text-gray-300" />
                                <p class="mt-2 font-medium text-gray-600">No Mobile Phones found</p>
                                <p class="text-xs text-gray-400">Click "Add Mobile Phone" or "Purchase Used Phone" to add stock.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div v-if="imeis.last_page > 1" class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs text-gray-500">
                    Showing page <span class="font-semibold">{{ imeis.current_page }}</span> of <span class="font-semibold">{{ imeis.last_page }}</span> (Total {{ imeis.total }} items)
                </div>
                <div class="flex gap-1">
                    <Button
                        v-for="link in imeis.links"
                        :key="link.label"
                        :disabled="!link.url || link.active"
                        @click="router.get(link.url)"
                        variant="outline"
                        size="sm"
                        class="h-8 min-w-[32px] px-2 text-xs"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- MODAL 1: ADD NEW MOBILE PHONE STOCK -->
        <Dialog :open="isAddMobileModalOpen" @update:open="isAddMobileModalOpen = $event">
            <DialogContent class="max-w-xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Smartphone class="h-5 w-5 text-[#003B7D]" />
                        Add Mobile Phone Stock
                    </DialogTitle>
                    <DialogDescription>
                        Register a new mobile phone handset and record its initial IMEI number.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitAddMobile" class="space-y-4 py-2">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Brand <span class="text-red-500">*</span></Label>
                            <Input v-model="addMobileForm.brand" required placeholder="e.g. Apple, Samsung, Vivo..." />
                        </div>
                        <div>
                            <Label>Model Name <span class="text-red-500">*</span></Label>
                            <Input v-model="addMobileForm.model_name" required placeholder="e.g. iPhone 15 Pro Max" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <Label>Storage</Label>
                            <Input v-model="addMobileForm.storage" placeholder="e.g. 256GB" />
                        </div>
                        <div>
                            <Label>Color</Label>
                            <Input v-model="addMobileForm.color" placeholder="e.g. Titanium Gray" />
                        </div>
                        <div>
                            <Label>Condition</Label>
                            <select v-model="addMobileForm.condition" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm">
                                <option value="new">Brand New</option>
                                <option value="used">Used Handset</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label>PTA Status</Label>
                            <select v-model="addMobileForm.pta_status" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm">
                                <option value="approved">PTA Approved</option>
                                <option value="non_pta">Non-PTA</option>
                                <option value="cpid">CPID Approved</option>
                                <option value="jv">JV SIM Lock</option>
                                <option value="software">Software Patch</option>
                            </select>
                        </div>
                        <div>
                            <Label>Warranty Days</Label>
                            <Input v-model.number="addMobileForm.warranty_days" type="number" placeholder="365" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label>IMEI 1 <span class="text-red-500">*</span></Label>
                            <Input v-model="addMobileForm.imei_1" required placeholder="15 Digit Primary IMEI" />
                            <span v-if="addMobileForm.errors.imei_1" class="text-xs text-red-500">{{ addMobileForm.errors.imei_1 }}</span>
                        </div>
                        <div>
                            <Label>IMEI 2 (Optional)</Label>
                            <Input v-model="addMobileForm.imei_2" placeholder="Secondary IMEI" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Purchase Cost (Rs) <span class="text-red-500">*</span></Label>
                            <Input v-model.number="addMobileForm.purchase_cost" type="number" required />
                        </div>
                        <div>
                            <Label>Selling Price (Rs) <span class="text-red-500">*</span></Label>
                            <Input v-model.number="addMobileForm.sale_price" type="number" required />
                        </div>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="isAddMobileModalOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="addMobileForm.processing" class="bg-[#003B7D] text-white">
                            Save Mobile Stock
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL 2: PURCHASE USED PHONE (WITH SELLER CNIC LOG) -->
        <Dialog :open="isPurchaseUsedModalOpen" @update:open="isPurchaseUsedModalOpen = $event">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <UserCheck class="h-5 w-5 text-amber-600" />
                        Purchase Used Phone from Seller
                    </DialogTitle>
                    <DialogDescription>
                        Record seller CNIC details and add used mobile phone directly to stock.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitUsedPurchase" class="space-y-4 py-2">
                    <div class="rounded-lg bg-amber-50/70 p-3 border border-amber-200">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-amber-900">1. Seller Info</h3>
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            <div>
                                <Label>Seller Full Name <span class="text-red-500">*</span></Label>
                                <Input v-model="usedPurchaseForm.seller_name" required placeholder="e.g. Ali Raza" />
                            </div>
                            <div>
                                <Label>Seller CNIC No. <span class="text-red-500">*</span></Label>
                                <Input v-model="usedPurchaseForm.seller_cnic" required placeholder="35202-XXXXXXX-X" />
                            </div>
                            <div>
                                <Label>Phone Number <span class="text-red-500">*</span></Label>
                                <Input v-model="usedPurchaseForm.seller_phone" required placeholder="0300-1234567" />
                            </div>
                            <div>
                                <Label>Father Name</Label>
                                <Input v-model="usedPurchaseForm.seller_father_name" placeholder="Optional" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-blue-50/70 p-3 border border-blue-200">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-blue-900">2. Phone & IMEI Details</h3>
                        <div class="mt-2 grid grid-cols-3 gap-3">
                            <div>
                                <Label>Brand <span class="text-red-500">*</span></Label>
                                <Input v-model="usedPurchaseForm.brand" required placeholder="Apple / Samsung" />
                            </div>
                            <div class="col-span-2">
                                <Label>Model Name <span class="text-red-500">*</span></Label>
                                <Input v-model="usedPurchaseForm.device_model" required placeholder="e.g. iPhone 13 Pro 128GB" />
                            </div>
                            <div>
                                <Label>Color</Label>
                                <Input v-model="usedPurchaseForm.color" placeholder="Sierra Blue" />
                            </div>
                            <div>
                                <Label>Storage</Label>
                                <Input v-model="usedPurchaseForm.storage" placeholder="128GB" />
                            </div>
                            <div>
                                <Label>PTA Status</Label>
                                <select v-model="usedPurchaseForm.pta_status" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm">
                                    <option value="approved">PTA Approved</option>
                                    <option value="non_pta">Non-PTA</option>
                                    <option value="cpid">CPID Approved</option>
                                    <option value="jv">JV SIM Lock</option>
                                    <option value="software">Software Patch</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div>
                                <Label>IMEI 1 <span class="text-red-500">*</span></Label>
                                <Input v-model="usedPurchaseForm.imei_1" required placeholder="15 Digit Primary IMEI" />
                            </div>
                            <div>
                                <Label>IMEI 2</Label>
                                <Input v-model="usedPurchaseForm.imei_2" placeholder="Secondary IMEI" />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <Label>Purchase Amount (Paid) <span class="text-red-500">*</span></Label>
                            <Input v-model.number="usedPurchaseForm.purchase_amount" type="number" required />
                        </div>
                        <div>
                            <Label>Target Sale Price <span class="text-red-500">*</span></Label>
                            <Input v-model.number="usedPurchaseForm.sale_price" type="number" required />
                        </div>
                        <div>
                            <Label>Payment Mode</Label>
                            <select v-model="usedPurchaseForm.payment_method" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm">
                                <option value="cash">Cash</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="jazzcash">JazzCash</option>
                                <option value="easypaisa">EasyPaisa</option>
                            </select>
                        </div>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="isPurchaseUsedModalOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="usedPurchaseForm.processing" class="bg-amber-600 text-white hover:bg-amber-700">
                            Save Purchase Record & Add Stock
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL 3: EDIT IMEI DETAILS -->
        <Dialog :open="isEditImeiModalOpen" @update:open="isEditImeiModalOpen = $event">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit Handset IMEI & Pricing</DialogTitle>
                </DialogHeader>

                <form @submit.prevent="submitEditImei" class="space-y-4 py-2">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label>IMEI 1</Label>
                            <Input v-model="editImeiForm.imei_1" required />
                        </div>
                        <div>
                            <Label>IMEI 2</Label>
                            <Input v-model="editImeiForm.imei_2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Color</Label>
                            <Input v-model="editImeiForm.color" />
                        </div>
                        <div>
                            <Label>Storage</Label>
                            <Input v-model="editImeiForm.storage" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Condition</Label>
                            <select v-model="editImeiForm.condition" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm">
                                <option value="new">Brand New</option>
                                <option value="used">Used Handset</option>
                            </select>
                        </div>
                        <div>
                            <Label>PTA Status</Label>
                            <select v-model="editImeiForm.pta_status" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm">
                                <option value="approved">PTA Approved</option>
                                <option value="non_pta">Non-PTA</option>
                                <option value="cpid">CPID Approved</option>
                                <option value="jv">JV SIM Lock</option>
                                <option value="software">Software Patch</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Purchase Cost (Rs)</Label>
                            <Input v-model.number="editImeiForm.purchase_cost" type="number" required />
                        </div>
                        <div>
                            <Label>Status</Label>
                            <select v-model="editImeiForm.status" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm">
                                <option value="in_stock">In Stock</option>
                                <option value="sold">Sold</option>
                                <option value="repairing">Repairing</option>
                                <option value="returned">Returned</option>
                            </select>
                        </div>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="isEditImeiModalOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="editImeiForm.processing" class="bg-[#003B7D] text-white">
                            Update Details
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
