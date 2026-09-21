<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Boxes,
    CheckCircle,
    Edit3,
    Layers,
    ListFilter,
    Plus,
    QrCode,
    Search,
    Smartphone,
    Tag,
    Trash2,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import imeis from '@/routes/imeis';
import inventory from '@/routes/inventory';
import products from '@/routes/products';
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
    sold_at?: string | null;
    created_at: string;
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
    imeis_count?: number;
    in_stock_imeis_count?: number;
    imeis?: ProductImeiItem[];
    created_at: string;
}

interface SummaryStats {
    total_products: number;
    handsets_count: number;
    accessories_count: number;
    in_stock_imeis_count: number;
    total_stock_value: number;
}

interface Filters {
    search: string;
    type: string;
    category: string;
    stock_status: string;
}

const props = defineProps<{
    products: {
        data: ProductItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: Filters;
    categories: string[];
    brands: string[];
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
                href: layoutProps.currentTeam ? `/dashboard` : '/',
            },
            {
                title: 'Inventory',
                href: layoutProps.currentTeam
                    ? inventory.index(layoutProps.currentTeam.slug).url
                    : '/inventory',
            },
        ],
    }),
});

// Search & Filtering
const search = ref(props.filters.search || '');
const selectedType = ref(props.filters.type || 'all');
const selectedCategory = ref(props.filters.category || 'all');
const selectedStockStatus = ref(props.filters.stock_status || 'all');

let searchDebounceTimeout: ReturnType<typeof setTimeout> | null = null;

const applyFilters = () => {
    router.get(
        inventory.index(currentTeamSlug.value).url,
        {
            search: search.value || undefined,
            type: selectedType.value !== 'all' ? selectedType.value : undefined,
            category:
                selectedCategory.value !== 'all'
                    ? selectedCategory.value
                    : undefined,
            stock_status:
                selectedStockStatus.value !== 'all'
                    ? selectedStockStatus.value
                    : undefined,
        },
        { preserveState: true, replace: true },
    );
};

watch(search, () => {
    if (searchDebounceTimeout) clearTimeout(searchDebounceTimeout);
    searchDebounceTimeout = setTimeout(() => {
        applyFilters();
    }, 350);
});

watch([selectedType, selectedCategory, selectedStockStatus], () => {
    applyFilters();
});

// Create/Edit Product Modal
const isProductModalOpen = ref(false);
const editingProduct = ref<ProductItem | null>(null);

const productForm = useForm({
    name: '',
    brand: '',
    category: '',
    barcode: '',
    is_serialized: false,
    sale_price: '',
    cost_price: '',
    stock_quantity: 0,
    alert_quantity: 5,
    // Optional initial IMEI
    initial_imei_1: '',
    initial_imei_2: '',
    initial_color: '',
    initial_storage: '',
    initial_condition: 'new',
    initial_pta_status: 'approved',
    initial_purchase_cost: '',
    initial_warranty_days: 0,
});

const openCreateProductModal = (isHandset = false) => {
    editingProduct.value = null;
    productForm.reset();
    productForm.clearErrors();
    productForm.is_serialized = isHandset;
    if (isHandset && !productForm.category) {
        productForm.category = 'Mobile Handsets';
    }
    isProductModalOpen.value = true;
};

const openEditProductModal = (product: ProductItem) => {
    editingProduct.value = product;
    productForm.clearErrors();
    productForm.name = product.name;
    productForm.brand = product.brand;
    productForm.category = product.category;
    productForm.barcode = product.barcode || '';
    productForm.is_serialized = Boolean(product.is_serialized);
    productForm.sale_price = String(product.sale_price);
    productForm.cost_price = String(product.cost_price);
    productForm.stock_quantity = product.stock_quantity;
    productForm.alert_quantity = product.alert_quantity;
    isProductModalOpen.value = true;
};

const generateBarcode = () => {
    productForm.barcode =
        '890' + Math.floor(100000000 + Math.random() * 900000000);
};

const submitProductForm = () => {
    if (editingProduct.value) {
        productForm.put(
            products.update([currentTeamSlug.value, editingProduct.value.id])
                .url,
            {
                onSuccess: () => {
                    isProductModalOpen.value = false;
                },
            },
        );
    } else {
        productForm.post(products.store(currentTeamSlug.value).url, {
            onSuccess: () => {
                isProductModalOpen.value = false;
            },
        });
    }
};

const deleteProduct = (product: ProductItem) => {
    if (
        confirm(
            `Are you sure you want to delete "${product.name}"? This action cannot be undone.`,
        )
    ) {
        router.delete(
            products.destroy([currentTeamSlug.value, product.id]).url,
        );
    }
};

// Manage IMEIs Modal
const isImeiManageModalOpen = ref(false);
const activeImeiProduct = ref<ProductItem | null>(null);

const openImeiManageModal = (product: ProductItem) => {
    activeImeiProduct.value = product;
    isImeiManageModalOpen.value = true;
};

// Add Single IMEI Form
const isAddSingleImeiModalOpen = ref(false);
const singleImeiForm = useForm({
    imei_1: '',
    imei_2: '',
    color: '',
    storage: '',
    condition: 'new',
    pta_status: 'approved',
    purchase_cost: '',
    warranty_days: 0,
});

const openAddSingleImeiModal = (product?: ProductItem) => {
    if (product) {
        activeImeiProduct.value = product;
    }
    if (!activeImeiProduct.value) return;

    singleImeiForm.reset();
    singleImeiForm.clearErrors();
    singleImeiForm.purchase_cost = String(
        activeImeiProduct.value.sale_price || '',
    );
    isAddSingleImeiModalOpen.value = true;
};

const submitSingleImeiForm = () => {
    if (!activeImeiProduct.value) return;

    singleImeiForm.post(
        imeis.store([currentTeamSlug.value, activeImeiProduct.value.id]).url,
        {
            onSuccess: () => {
                isAddSingleImeiModalOpen.value = false;
                // update active product in reference if modal open
                const updated = props.products.data.find(
                    (p) => p.id === activeImeiProduct.value?.id,
                );
                if (updated) activeImeiProduct.value = updated;
            },
        },
    );
};

// Edit IMEI Modal
const isEditImeiModalOpen = ref(false);
const editingImei = ref<ProductImeiItem | null>(null);

const editImeiForm = useForm({
    imei_1: '',
    imei_2: '',
    color: '',
    storage: '',
    condition: 'new',
    pta_status: 'approved',
    purchase_cost: '',
    warranty_days: 0,
    status: 'in_stock',
});

const openEditImeiModal = (imeiItem: ProductImeiItem) => {
    editingImei.value = imeiItem;
    editImeiForm.clearErrors();
    editImeiForm.imei_1 = imeiItem.imei_1;
    editImeiForm.imei_2 = imeiItem.imei_2 || '';
    editImeiForm.color = imeiItem.color || '';
    editImeiForm.storage = imeiItem.storage || '';
    editImeiForm.condition = imeiItem.condition;
    editImeiForm.pta_status = imeiItem.pta_status;
    editImeiForm.purchase_cost = String(imeiItem.purchase_cost);
    editImeiForm.warranty_days = imeiItem.warranty_days;
    editImeiForm.status = imeiItem.status;
    isEditImeiModalOpen.value = true;
};

const submitEditImeiForm = () => {
    if (!editingImei.value) return;

    editImeiForm.put(
        imeis.update([currentTeamSlug.value, editingImei.value.id]).url,
        {
            onSuccess: () => {
                isEditImeiModalOpen.value = false;
                const updated = props.products.data.find(
                    (p) => p.id === activeImeiProduct.value?.id,
                );
                if (updated) activeImeiProduct.value = updated;
            },
        },
    );
};

const deleteImei = (imeiItem: ProductImeiItem) => {
    if (confirm(`Delete IMEI "${imeiItem.imei_1}"?`)) {
        router.delete(imeis.destroy([currentTeamSlug.value, imeiItem.id]).url, {
            onSuccess: () => {
                const updated = props.products.data.find(
                    (p) => p.id === activeImeiProduct.value?.id,
                );
                if (updated) activeImeiProduct.value = updated;
            },
        });
    }
};

// Bulk IMEI Entry Modal
const isBulkImeiModalOpen = ref(false);
const bulkImeiMode = ref<'textarea' | 'rows'>('textarea');
const bulkRawText = ref('');

const bulkImeiForm = useForm({
    product_id: 0,
    color: '',
    storage: '',
    condition: 'new',
    pta_status: 'approved',
    purchase_cost: '',
    warranty_days: 0,
    imeis: [] as Array<{ imei_1: string; imei_2?: string }>,
});

const openBulkImeiModal = (product?: ProductItem) => {
    const targetProduct =
        product || props.products.data.find((p) => p.is_serialized);
    bulkImeiForm.reset();
    bulkImeiForm.clearErrors();
    bulkRawText.value = '';

    if (targetProduct) {
        bulkImeiForm.product_id = targetProduct.id;
        bulkImeiForm.purchase_cost = String(targetProduct.sale_price || '');
    }

    bulkImeiForm.imeis = [{ imei_1: '', imei_2: '' }];
    isBulkImeiModalOpen.value = true;
};

const addBulkRow = () => {
    bulkImeiForm.imeis.push({ imei_1: '', imei_2: '' });
};

const removeBulkRow = (index: number) => {
    if (bulkImeiForm.imeis.length > 1) {
        bulkImeiForm.imeis.splice(index, 1);
    }
};

const parseBulkTextarea = () => {
    const lines = bulkRawText.value
        .split('\n')
        .map((l) => l.trim())
        .filter(Boolean);

    const parsed: Array<{ imei_1: string; imei_2?: string }> = [];

    for (const line of lines) {
        // match comma, tab, space, or slash separated IMEIs
        const parts = line.split(/[\s,\t/]+/).filter(Boolean);
        if (parts.length >= 2) {
            parsed.push({ imei_1: parts[0], imei_2: parts[1] });
        } else if (parts.length === 1) {
            parsed.push({ imei_1: parts[0] });
        }
    }

    return parsed;
};

const submitBulkImeiForm = () => {
    if (!bulkImeiForm.product_id) {
        bulkImeiForm.setError('product_id' as any, 'Please select a product.');
        return;
    }

    if (bulkImeiMode.value === 'textarea') {
        const parsed = parseBulkTextarea();
        if (parsed.length === 0) {
            bulkImeiForm.setError(
                'imeis' as any,
                'Please enter at least one valid IMEI number.',
            );
            return;
        }
        bulkImeiForm.imeis = parsed;
    } else {
        // filter empty rows
        bulkImeiForm.imeis = bulkImeiForm.imeis.filter(
            (i) => i.imei_1.trim() !== '',
        );
        if (bulkImeiForm.imeis.length === 0) {
            bulkImeiForm.setError(
                'imeis' as any,
                'Please enter at least one valid IMEI number.',
            );
            return;
        }
    }

    bulkImeiForm.post(
        imeis.bulkStore([currentTeamSlug.value, bulkImeiForm.product_id]).url,
        {
            onSuccess: () => {
                isBulkImeiModalOpen.value = false;
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

const serializedProductsList = computed(() =>
    props.products.data.filter((p) => p.is_serialized),
);
</script>

<template>
    <Head title="Product & IMEI Inventory" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Top Banner Header -->
        <div
            class="bg-card/60 flex flex-col gap-4 rounded-2xl border border-gray-200 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-bold tracking-tight text-gray-900"
                >
                    <Boxes class="h-7 w-7 text-[#003B7D]" />
                    Product & IMEI Inventory Catalog
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Manage Mobile Handsets (IMEI tracked), Accessories, Spare
                    Parts & Bulk Stock Inflow.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    @click="openCreateProductModal(true)"
                    class="gap-2 bg-[#003B7D] font-bold text-white shadow-sm hover:bg-[#002b5c]"
                >
                    <Smartphone class="h-4 w-4" />
                    + Add Handset
                </Button>
                <Button
                    @click="openCreateProductModal(false)"
                    variant="outline"
                    class="gap-2 border-[#003B7D]/20 bg-[#003B7D]/5 text-[#003B7D] hover:border-[#003B7D]/40 hover:bg-[#003B7D]/5"
                >
                    <Tag class="h-4 w-4 text-[#003B7D]" />
                    + Add Accessory / Part
                </Button>
                <Button
                    @click="openBulkImeiModal()"
                    variant="secondary"
                    class="gap-2 bg-gray-50 font-bold text-gray-900 hover:bg-gray-100"
                >
                    <Layers class="h-4 w-4 text-[#003B7D]" />
                    Bulk Add IMEIs
                </Button>
            </div>
        </div>

        <!-- Summary Stats Grid -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                class="bg-card/60 rounded-2xl border border-gray-200 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >Total Catalog Items</span
                    >
                    <Boxes class="h-5 w-5 text-[#003B7D]" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-gray-900">
                    {{ summary.total_products }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    {{ summary.handsets_count }} Handset Models &bull;
                    {{ summary.accessories_count }} Accessories
                </div>
            </div>

            <div
                class="bg-card/60 rounded-2xl border border-gray-200 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >In-Stock Handsets (IMEIs)</span
                    >
                    <Smartphone class="h-5 w-5 text-[#003B7D]" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-[#003B7D]">
                    {{ summary.in_stock_imeis_count }} Units
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Unique IMEI phones ready for sale
                </div>
            </div>

            <div
                class="bg-card/60 rounded-2xl border border-gray-200 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >Accessory Items</span
                    >
                    <Tag class="h-5 w-5 text-amber-600" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-gray-900">
                    {{ summary.accessories_count }} Products
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Barcode / Standard Stock Items
                </div>
            </div>

            <div
                class="bg-card/60 rounded-2xl border border-gray-200 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >Total Inventory Valuation</span
                    >
                    <span class="text-xs font-bold text-[#003B7D]">COGS</span>
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-[#003B7D]">
                    {{ formatCurrency(summary.total_stock_value) }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Calculated at purchase cost
                </div>
            </div>
        </div>

        <!-- Filter & Search Section -->
        <div
            class="bg-card/60 flex flex-col gap-4 rounded-2xl border border-gray-200 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl md:flex-row md:items-center md:justify-between"
        >
            <div class="relative flex-1">
                <Search
                    class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2"
                />
                <Input
                    v-model="search"
                    placeholder="Search by product name, brand, barcode, category or IMEI..."
                    class="pl-9"
                />
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="w-40">
                    <Select v-model="selectedType">
                        <SelectTrigger>
                            <SelectValue placeholder="All Types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Types</SelectItem>
                            <SelectItem value="serialized"
                                >Mobile Handsets</SelectItem
                            >
                            <SelectItem value="accessories"
                                >Accessories / Parts</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>

                <div class="w-40">
                    <Select v-model="selectedCategory">
                        <SelectTrigger>
                            <SelectValue placeholder="All Categories" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Categories</SelectItem>
                            <SelectItem
                                v-for="cat in categories"
                                :key="cat"
                                :value="cat"
                            >
                                {{ cat }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="w-40">
                    <Select v-model="selectedStockStatus">
                        <SelectTrigger>
                            <SelectValue placeholder="All Stock Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all"
                                >All Stock Status</SelectItem
                            >
                            <SelectItem value="low_stock"
                                >Low Stock Warning</SelectItem
                            >
                            <SelectItem value="out_of_stock"
                                >Out of Stock</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </div>

        <!-- Products Table -->
        <div
            class="bg-card/60 overflow-hidden rounded-2xl border border-gray-200 shadow-sm"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-gray-200 bg-gray-50 text-xs font-semibold tracking-wider text-slate-500 uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3">Product Info</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Identifier / Code</th>
                            <th class="px-4 py-3">Sale Price</th>
                            <th class="px-4 py-3">Cost Price</th>
                            <th class="px-4 py-3 text-center">Stock Level</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-if="products.data.length === 0">
                            <td
                                colspan="7"
                                class="text-muted-foreground px-4 py-8 text-center"
                            >
                                No products found matching your filter criteria.
                            </td>
                        </tr>

                        <tr
                            v-for="product in products.data"
                            :key="product.id"
                            class="transition-colors hover:bg-gray-50"
                        >
                            <td class="px-4 py-3">
                                <div class="text-foreground font-medium">
                                    {{ product.name }}
                                </div>
                                <div class="text-muted-foreground text-xs">
                                    <span
                                        class="font-semibold text-[#003B7D]"
                                        >{{ product.brand }}</span
                                    >
                                    &bull; {{ product.category }}
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <span
                                    v-if="product.is_serialized"
                                    class="inline-flex items-center gap-1 rounded-full border border-[#003B7D]/20 bg-[#003B7D]/5 px-2.5 py-0.5 text-xs font-semibold text-[#003B7D]"
                                >
                                    <Smartphone class="h-3.5 w-3.5" />
                                    Handset (IMEI)
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-xs font-semibold text-slate-600"
                                >
                                    <Tag class="h-3.5 w-3.5" />
                                    Standard Stock
                                </span>
                            </td>

                            <td class="px-4 py-3 font-mono text-xs">
                                <div
                                    v-if="product.is_serialized"
                                    class="flex flex-col gap-0.5"
                                >
                                    <span class="font-semibold text-[#003B7D]">
                                        {{ product.in_stock_imeis_count || 0 }}
                                        IMEIs In Stock
                                    </span>
                                    <span class="text-muted-foreground"
                                        >({{ product.imeis_count || 0 }} Total
                                        Record)</span
                                    >
                                </div>
                                <div
                                    v-else-if="product.barcode"
                                    class="text-muted-foreground flex items-center gap-1.5"
                                >
                                    <QrCode class="h-3.5 w-3.5" />
                                    {{ product.barcode }}
                                </div>
                                <div
                                    v-else
                                    class="text-muted-foreground italic"
                                >
                                    No Barcode
                                </div>
                            </td>

                            <td
                                class="text-foreground tnum px-4 py-3 font-semibold"
                            >
                                {{ formatCurrency(product.sale_price) }}
                            </td>

                            <td class="text-muted-foreground tnum px-4 py-3">
                                <span v-if="!product.is_serialized">{{
                                    formatCurrency(product.cost_price)
                                }}</span>
                                <span v-else class="text-xs italic"
                                    >IMEI specific</span
                                >
                            </td>

                            <td class="px-4 py-3 text-center">
                                <template v-if="product.is_serialized">
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium',
                                            (product.in_stock_imeis_count ||
                                                0) <= 0
                                                ? 'border-rose-200 bg-rose-50 text-rose-600'
                                                : (product.in_stock_imeis_count ||
                                                        0) <=
                                                    product.alert_quantity
                                                  ? 'border-amber-200 bg-amber-50 text-amber-600'
                                                  : 'border-[#003B7D]/20 bg-[#003B7D]/5 text-[#003B7D]',
                                        ]"
                                    >
                                        {{ product.in_stock_imeis_count || 0 }}
                                        Available
                                    </span>
                                </template>
                                <template v-else>
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium',
                                            product.stock_quantity <= 0
                                                ? 'border-rose-200 bg-rose-50 text-rose-600'
                                                : product.stock_quantity <=
                                                    product.alert_quantity
                                                  ? 'border-amber-200 bg-amber-50 text-amber-600'
                                                  : 'border-[#003B7D]/20 bg-[#003B7D]/5 text-[#003B7D]',
                                        ]"
                                    >
                                        {{ product.stock_quantity }} Units
                                    </span>
                                </template>
                            </td>

                            <td class="px-4 py-3 text-right">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <Button
                                        v-if="product.is_serialized"
                                        size="sm"
                                        variant="outline"
                                        @click="openImeiManageModal(product)"
                                        title="View IMEIs"
                                        class="h-8 gap-1 border-[#003B7D]/20 bg-[#003B7D]/5 text-[#003B7D] hover:border-[#003B7D]/40 hover:bg-[#003B7D]/10"
                                    >
                                        <Smartphone class="h-3.5 w-3.5" />
                                        IMEIs ({{
                                            product.in_stock_imeis_count || 0
                                        }})
                                    </Button>

                                    <Button
                                        v-if="product.is_serialized"
                                        size="sm"
                                        variant="secondary"
                                        @click="openAddSingleImeiModal(product)"
                                        title="Add Single IMEI"
                                        class="h-8 px-2"
                                    >
                                        <Plus class="h-3.5 w-3.5" />
                                    </Button>

                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        @click="openEditProductModal(product)"
                                        title="Edit Product"
                                        class="h-8 w-8 p-0"
                                    >
                                        <Edit3
                                            class="text-muted-foreground h-4 w-4"
                                        />
                                    </Button>

                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        @click="deleteProduct(product)"
                                        title="Delete Product"
                                        class="h-8 w-8 p-0 text-rose-600 hover:bg-rose-50 hover:text-rose-600"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div
                v-if="products.links.length > 3"
                class="bg-card/60 flex items-center justify-between border-t border-gray-200 px-4 py-3"
            >
                <div class="text-muted-foreground text-xs">
                    Showing page
                    <span class="font-semibold">{{
                        products.current_page
                    }}</span>
                    of
                    <span class="font-semibold">{{ products.last_page }}</span>
                    ({{ products.total }} total items)
                </div>
                <div class="flex gap-1">
                    <template v-for="(link, i) in products.links" :key="i">
                        <Button
                            v-if="link.url"
                            size="sm"
                            :variant="link.active ? 'default' : 'outline'"
                            @click="
                                router.get(
                                    link.url,
                                    {},
                                    { preserveState: true },
                                )
                            "
                            class="h-8 px-3 text-xs"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- MODAL 1: Create / Edit Product Modal -->
        <Dialog v-model:open="isProductModalOpen">
            <DialogContent class="max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{
                        editingProduct ? 'Edit Product' : 'Create New Product'
                    }}</DialogTitle>
                    <DialogDescription>
                        Add a new mobile handset (IMEI tracked) or standard
                        accessory to your inventory catalog.
                    </DialogDescription>
                </DialogHeader>

                <form
                    @submit.prevent="submitProductForm"
                    class="space-y-4 py-2"
                >
                    <!-- Product Type Selection (Only for Create) -->
                    <div v-if="!editingProduct" class="grid grid-cols-2 gap-3">
                        <div
                            @click="productForm.is_serialized = true"
                            :class="[
                                'flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 p-3 transition-all',
                                productForm.is_serialized
                                    ? 'border-[#003B7D] bg-[#003B7D]/5 text-[#003B7D] shadow-[0_0_20px_rgba(0,59,125,0.15)]'
                                    : 'border-gray-200 hover:border-[#003B7D]/50',
                            ]"
                        >
                            <Smartphone class="mb-1 h-6 w-6 text-[#003B7D]" />
                            <span class="text-xs font-semibold"
                                >Mobile Handset</span
                            >
                            <span class="text-muted-foreground text-[10px]"
                                >IMEI tracked, unique units</span
                            >
                        </div>

                        <div
                            @click="productForm.is_serialized = false"
                            :class="[
                                'flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 p-3 transition-all',
                                !productForm.is_serialized
                                    ? 'border-[#003B7D] bg-[#003B7D]/5 text-[#003B7D] shadow-[0_0_20px_rgba(0,59,125,0.15)]'
                                    : 'border-gray-200 hover:border-[#003B7D]/50',
                            ]"
                        >
                            <Tag class="mb-1 h-6 w-6 text-[#003B7D]" />
                            <span class="text-xs font-semibold"
                                >Accessory / Spare Part</span
                            >
                            <span class="text-muted-foreground text-[10px]"
                                >Barcode / Bulk stock quantity</span
                            >
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="name">Product Name *</Label>
                            <Input
                                id="name"
                                v-model="productForm.name"
                                placeholder="e.g. iPhone 15 Pro Max or Fast Charger 20W"
                            />
                            <span
                                v-if="productForm.errors.name"
                                class="text-xs text-rose-600"
                                >{{ productForm.errors.name }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="brand">Brand *</Label>
                            <Input
                                id="brand"
                                v-model="productForm.brand"
                                placeholder="e.g. Apple, Samsung, Anker"
                            />
                            <span
                                v-if="productForm.errors.brand"
                                class="text-xs text-rose-600"
                                >{{ productForm.errors.brand }}</span
                            >
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="category">Category *</Label>
                            <Input
                                id="category"
                                v-model="productForm.category"
                                placeholder="e.g. Mobile Handsets, Chargers"
                            />
                            <span
                                v-if="productForm.errors.category"
                                class="text-xs text-rose-600"
                                >{{ productForm.errors.category }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="barcode">Barcode (Optional)</Label>
                            <div class="flex gap-2">
                                <Input
                                    id="barcode"
                                    v-model="productForm.barcode"
                                    placeholder="Scan or enter barcode"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="generateBarcode"
                                    title="Auto-generate Barcode"
                                >
                                    <QrCode class="h-4 w-4" />
                                </Button>
                            </div>
                            <span
                                v-if="productForm.errors.barcode"
                                class="text-xs text-rose-600"
                                >{{ productForm.errors.barcode }}</span
                            >
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="sale_price"
                                >Retail Sale Price (PKR) *</Label
                            >
                            <Input
                                id="sale_price"
                                type="number"
                                step="0.01"
                                v-model="productForm.sale_price"
                                placeholder="0.00"
                            />
                            <span
                                v-if="productForm.errors.sale_price"
                                class="text-xs text-rose-600"
                                >{{ productForm.errors.sale_price }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="alert_quantity"
                                >Low Stock Alert Quantity *</Label
                            >
                            <Input
                                id="alert_quantity"
                                type="number"
                                v-model="productForm.alert_quantity"
                                placeholder="5"
                            />
                            <span
                                v-if="productForm.errors.alert_quantity"
                                class="text-xs text-rose-600"
                                >{{ productForm.errors.alert_quantity }}</span
                            >
                        </div>
                    </div>

                    <!-- Fields specific to Accessories -->
                    <template v-if="!productForm.is_serialized">
                        <div class="grid grid-cols-2 gap-4 border-t pt-2">
                            <div class="space-y-1">
                                <Label for="cost_price"
                                    >Unit Purchase Cost (PKR) *</Label
                                >
                                <Input
                                    id="cost_price"
                                    type="number"
                                    step="0.01"
                                    v-model="productForm.cost_price"
                                    placeholder="0.00"
                                />
                                <span
                                    v-if="productForm.errors.cost_price"
                                    class="text-xs text-rose-600"
                                    >{{ productForm.errors.cost_price }}</span
                                >
                            </div>

                            <div class="space-y-1">
                                <Label for="stock_quantity"
                                    >Current Stock Quantity *</Label
                                >
                                <Input
                                    id="stock_quantity"
                                    type="number"
                                    v-model="productForm.stock_quantity"
                                    placeholder="0"
                                />
                                <span
                                    v-if="productForm.errors.stock_quantity"
                                    class="text-xs text-rose-600"
                                    >{{
                                        productForm.errors.stock_quantity
                                    }}</span
                                >
                            </div>
                        </div>
                    </template>

                    <!-- Optional initial IMEI field for new Handset -->
                    <template
                        v-if="!editingProduct && productForm.is_serialized"
                    >
                        <div
                            class="mt-3 space-y-3 rounded-lg border border-[#003B7D]/20 bg-[#003B7D]/5 p-3"
                        >
                            <div
                                class="flex items-center gap-1.5 text-xs font-semibold text-[#003B7D]"
                            >
                                <Smartphone class="h-4 w-4" />
                                Add First Handset Unit (Optional Initial IMEI)
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <Label for="initial_imei_1" class="text-xs"
                                        >IMEI 1 Number</Label
                                    >
                                    <Input
                                        id="initial_imei_1"
                                        v-model="productForm.initial_imei_1"
                                        placeholder="15-digit IMEI"
                                        class="h-8 font-mono text-xs"
                                    />
                                    <span
                                        v-if="productForm.errors.initial_imei_1"
                                        class="text-[10px] text-rose-600"
                                        >{{
                                            productForm.errors.initial_imei_1
                                        }}</span
                                    >
                                </div>
                                <div class="space-y-1">
                                    <Label for="initial_imei_2" class="text-xs"
                                        >IMEI 2 (SIM 2)</Label
                                    >
                                    <Input
                                        id="initial_imei_2"
                                        v-model="productForm.initial_imei_2"
                                        placeholder="Optional IMEI 2"
                                        class="h-8 font-mono text-xs"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <Label class="text-xs">Condition</Label>
                                    <Select
                                        v-model="productForm.initial_condition"
                                    >
                                        <SelectTrigger class="h-8 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="new"
                                                >Brand New</SelectItem
                                            >
                                            <SelectItem value="used"
                                                >Used / Second Hand</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div>
                                    <Label class="text-xs">PTA Status</Label>
                                    <Select
                                        v-model="productForm.initial_pta_status"
                                    >
                                        <SelectTrigger class="h-8 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="approved"
                                                >PTA Approved</SelectItem
                                            >
                                            <SelectItem value="non_pta"
                                                >Non-PTA</SelectItem
                                            >
                                            <SelectItem value="jv"
                                                >JV Locked</SelectItem
                                            >
                                            <SelectItem value="cpid"
                                                >CPID Approved</SelectItem
                                            >
                                            <SelectItem value="software"
                                                >Software Approved</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div>
                                    <Label class="text-xs"
                                        >Purchase Cost (PKR)</Label
                                    >
                                    <Input
                                        v-model="
                                            productForm.initial_purchase_cost
                                        "
                                        type="number"
                                        placeholder="Cost"
                                        class="h-8 text-xs"
                                    />
                                </div>
                            </div>
                        </div>
                    </template>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isProductModalOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="productForm.processing"
                            class="bg-[#003B7D] text-white shadow-sm hover:bg-[#002b5c]"
                        >
                            {{
                                productForm.processing
                                    ? 'Saving...'
                                    : editingProduct
                                      ? 'Update Product'
                                      : 'Save Product'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL 2: Manage IMEIs Modal for Handset -->
        <Dialog v-model:open="isImeiManageModalOpen">
            <DialogContent class="max-h-[85vh] max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <Smartphone class="h-5 w-5 text-[#003B7D]" />
                            IMEI Management &bull; {{ activeImeiProduct?.name }}
                        </span>
                        <div class="mr-6 flex gap-2">
                            <Button
                                size="sm"
                                @click="openAddSingleImeiModal()"
                                class="h-8 gap-1 bg-[#003B7D] text-xs text-white shadow-sm hover:bg-[#002b5c]"
                            >
                                <Plus class="h-3.5 w-3.5" /> Single IMEI
                            </Button>
                            <Button
                                size="sm"
                                variant="secondary"
                                @click="
                                    openBulkImeiModal(
                                        activeImeiProduct || undefined,
                                    )
                                "
                                class="h-8 gap-1 text-xs"
                            >
                                <Layers class="h-3.5 w-3.5" /> Bulk Add
                            </Button>
                        </div>
                    </DialogTitle>
                    <DialogDescription>
                        All unique serialized units for
                        {{ activeImeiProduct?.brand }}
                        {{ activeImeiProduct?.name }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <div class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-left text-xs">
                            <thead
                                class="bg-gray-50 font-semibold text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="p-2.5">IMEI 1 & 2</th>
                                    <th class="p-2.5">Specs (Color/Storage)</th>
                                    <th class="p-2.5">Condition</th>
                                    <th class="p-2.5">PTA Status</th>
                                    <th class="p-2.5">Purchase Cost</th>
                                    <th class="p-2.5">Status</th>
                                    <th class="p-2.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr
                                    v-if="
                                        !activeImeiProduct?.imeis ||
                                        activeImeiProduct.imeis.length === 0
                                    "
                                >
                                    <td
                                        colspan="7"
                                        class="text-muted-foreground p-4 text-center"
                                    >
                                        No IMEIs registered for this model yet.
                                        Click "+ Single IMEI" or "Bulk Add"
                                        above.
                                    </td>
                                </tr>
                                <tr
                                    v-for="imeiItem in activeImeiProduct?.imeis"
                                    :key="imeiItem.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="p-2.5 font-mono">
                                        <div
                                            class="text-foreground font-semibold"
                                        >
                                            {{ imeiItem.imei_1 }}
                                        </div>
                                        <div
                                            v-if="imeiItem.imei_2"
                                            class="text-muted-foreground text-[11px]"
                                        >
                                            SIM2: {{ imeiItem.imei_2 }}
                                        </div>
                                    </td>
                                    <td class="p-2.5">
                                        <span>{{
                                            imeiItem.storage || 'N/A'
                                        }}</span>
                                        <span
                                            v-if="imeiItem.color"
                                            class="text-muted-foreground"
                                        >
                                            &bull; {{ imeiItem.color }}</span
                                        >
                                    </td>
                                    <td class="p-2.5">
                                        <span
                                            :class="
                                                imeiItem.condition === 'new'
                                                    ? 'font-semibold text-[#003B7D]'
                                                    : 'text-amber-600'
                                            "
                                        >
                                            {{
                                                imeiItem.condition === 'new'
                                                    ? 'Brand New'
                                                    : 'Used'
                                            }}
                                        </span>
                                    </td>
                                    <td class="p-2.5">
                                        <span
                                            class="inline-flex rounded border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-semibold text-slate-600 uppercase"
                                        >
                                            {{
                                                imeiItem.pta_status.replace(
                                                    '_',
                                                    ' ',
                                                )
                                            }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 font-semibold">
                                        {{
                                            formatCurrency(
                                                imeiItem.purchase_cost,
                                            )
                                        }}
                                    </td>
                                    <td class="p-2.5">
                                        <span
                                            :class="[
                                                'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase',
                                                imeiItem.status === 'in_stock'
                                                    ? 'border-[#003B7D]/20 bg-[#003B7D]/5 text-[#003B7D]'
                                                    : imeiItem.status === 'sold'
                                                      ? 'border-sky-400/25 bg-sky-400/10 text-sky-600'
                                                      : 'border-amber-200 bg-amber-50 text-amber-600',
                                            ]"
                                        >
                                            {{
                                                imeiItem.status.replace(
                                                    '_',
                                                    ' ',
                                                )
                                            }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-right">
                                        <div
                                            class="flex items-center justify-end gap-1"
                                        >
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                @click="
                                                    openEditImeiModal(imeiItem)
                                                "
                                                class="h-7 w-7 p-0"
                                            >
                                                <Edit3
                                                    class="text-muted-foreground h-3.5 w-3.5"
                                                />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                @click="deleteImei(imeiItem)"
                                                class="h-7 w-7 p-0 text-rose-600 hover:bg-rose-50 hover:text-rose-600"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- MODAL 3: Add Single IMEI -->
        <Dialog v-model:open="isAddSingleImeiModalOpen">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add Single IMEI Unit</DialogTitle>
                    <DialogDescription>
                        Add an individual handset unit to
                        {{ activeImeiProduct?.name }}.
                    </DialogDescription>
                </DialogHeader>

                <form
                    @submit.prevent="submitSingleImeiForm"
                    class="space-y-4 py-2"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="single_imei_1">IMEI 1 *</Label>
                            <Input
                                id="single_imei_1"
                                v-model="singleImeiForm.imei_1"
                                placeholder="15-digit IMEI"
                                font-mono
                            />
                            <span
                                v-if="singleImeiForm.errors.imei_1"
                                class="text-xs text-rose-600"
                                >{{ singleImeiForm.errors.imei_1 }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="single_imei_2">IMEI 2 (SIM 2)</Label>
                            <Input
                                id="single_imei_2"
                                v-model="singleImeiForm.imei_2"
                                placeholder="Optional 2nd IMEI"
                                font-mono
                            />
                            <span
                                v-if="singleImeiForm.errors.imei_2"
                                class="text-xs text-rose-600"
                                >{{ singleImeiForm.errors.imei_2 }}</span
                            >
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="single_color">Color</Label>
                            <Input
                                id="single_color"
                                v-model="singleImeiForm.color"
                                placeholder="e.g. Natural Titanium, Black"
                            />
                        </div>

                        <div class="space-y-1">
                            <Label for="single_storage">Storage</Label>
                            <Input
                                id="single_storage"
                                v-model="singleImeiForm.storage"
                                placeholder="e.g. 128GB, 256GB"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label>Condition *</Label>
                            <Select v-model="singleImeiForm.condition">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="new"
                                        >Brand New (Box Pack)</SelectItem
                                    >
                                    <SelectItem value="used"
                                        >Used (Kit / 2nd Hand)</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-1">
                            <Label>PTA Status *</Label>
                            <Select v-model="singleImeiForm.pta_status">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="approved"
                                        >PTA Approved</SelectItem
                                    >
                                    <SelectItem value="non_pta"
                                        >Non-PTA</SelectItem
                                    >
                                    <SelectItem value="jv"
                                        >JV Locked</SelectItem
                                    >
                                    <SelectItem value="cpid"
                                        >CPID Approved</SelectItem
                                    >
                                    <SelectItem value="software"
                                        >Software Approved</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="single_cost"
                                >Purchase Cost (PKR) *</Label
                            >
                            <Input
                                id="single_cost"
                                type="number"
                                step="0.01"
                                v-model="singleImeiForm.purchase_cost"
                                placeholder="0.00"
                            />
                            <span
                                v-if="singleImeiForm.errors.purchase_cost"
                                class="text-xs text-rose-600"
                                >{{ singleImeiForm.errors.purchase_cost }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="single_warranty"
                                >Shop Checking Warranty (Days)</Label
                            >
                            <Input
                                id="single_warranty"
                                type="number"
                                v-model="singleImeiForm.warranty_days"
                                placeholder="0"
                            />
                        </div>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isAddSingleImeiModalOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="singleImeiForm.processing"
                            class="bg-[#003B7D] text-white shadow-sm hover:bg-[#002b5c]"
                        >
                            {{
                                singleImeiForm.processing
                                    ? 'Adding...'
                                    : 'Save IMEI'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL 4: Edit IMEI Modal -->
        <Dialog v-model:open="isEditImeiModalOpen">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit IMEI Record</DialogTitle>
                </DialogHeader>

                <form
                    @submit.prevent="submitEditImeiForm"
                    class="space-y-4 py-2"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="edit_imei_1">IMEI 1 *</Label>
                            <Input
                                id="edit_imei_1"
                                v-model="editImeiForm.imei_1"
                                font-mono
                            />
                            <span
                                v-if="editImeiForm.errors.imei_1"
                                class="text-xs text-rose-600"
                                >{{ editImeiForm.errors.imei_1 }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="edit_imei_2">IMEI 2</Label>
                            <Input
                                id="edit_imei_2"
                                v-model="editImeiForm.imei_2"
                                font-mono
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="edit_color">Color</Label>
                            <Input
                                id="edit_color"
                                v-model="editImeiForm.color"
                            />
                        </div>

                        <div class="space-y-1">
                            <Label for="edit_storage">Storage</Label>
                            <Input
                                id="edit_storage"
                                v-model="editImeiForm.storage"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label>Condition</Label>
                            <Select v-model="editImeiForm.condition">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="new"
                                        >Brand New</SelectItem
                                    >
                                    <SelectItem value="used">Used</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-1">
                            <Label>PTA Status</Label>
                            <Select v-model="editImeiForm.pta_status">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="approved"
                                        >PTA Approved</SelectItem
                                    >
                                    <SelectItem value="non_pta"
                                        >Non-PTA</SelectItem
                                    >
                                    <SelectItem value="jv"
                                        >JV Locked</SelectItem
                                    >
                                    <SelectItem value="cpid"
                                        >CPID Approved</SelectItem
                                    >
                                    <SelectItem value="software"
                                        >Software Approved</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="edit_cost">Purchase Cost (PKR)</Label>
                            <Input
                                id="edit_cost"
                                type="number"
                                step="0.01"
                                v-model="editImeiForm.purchase_cost"
                            />
                        </div>

                        <div class="space-y-1">
                            <Label>Stock Status</Label>
                            <Select v-model="editImeiForm.status">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="in_stock"
                                        >In Stock</SelectItem
                                    >
                                    <SelectItem value="sold">Sold</SelectItem>
                                    <SelectItem value="repairing"
                                        >In Repair</SelectItem
                                    >
                                    <SelectItem value="returned"
                                        >Returned</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isEditImeiModalOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="editImeiForm.processing"
                            class="bg-[#003B7D] text-white shadow-sm hover:bg-[#002b5c]"
                        >
                            Save Changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL 5: Bulk IMEI Entry Modal -->
        <Dialog v-model:open="isBulkImeiModalOpen">
            <DialogContent class="max-h-[85vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Layers class="h-5 w-5 text-[#003B7D]" />
                        Bulk IMEI Inward Entry
                    </DialogTitle>
                    <DialogDescription>
                        Quickly batch enter multiple IMEIs for a handset
                        shipment.
                    </DialogDescription>
                </DialogHeader>

                <form
                    @submit.prevent="submitBulkImeiForm"
                    class="space-y-4 py-2"
                >
                    <div class="space-y-1">
                        <Label for="bulk_product">Select Handset Model *</Label>
                        <Select v-model="bulkImeiForm.product_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Choose product..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="p in serializedProductsList"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    {{ p.brand }} - {{ p.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <span
                            v-if="bulkImeiForm.errors.product_id"
                            class="text-xs text-rose-600"
                            >{{ bulkImeiForm.errors.product_id }}</span
                        >
                    </div>

                    <div
                        class="grid grid-cols-3 gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs"
                    >
                        <div>
                            <Label class="text-xs">Color</Label>
                            <Input
                                v-model="bulkImeiForm.color"
                                placeholder="e.g. Black"
                                class="h-8 text-xs"
                            />
                        </div>
                        <div>
                            <Label class="text-xs">Storage</Label>
                            <Input
                                v-model="bulkImeiForm.storage"
                                placeholder="e.g. 128GB"
                                class="h-8 text-xs"
                            />
                        </div>
                        <div>
                            <Label class="text-xs">Purchase Cost (PKR) *</Label>
                            <Input
                                v-model="bulkImeiForm.purchase_cost"
                                type="number"
                                placeholder="Cost"
                                class="h-8 text-xs"
                            />
                        </div>
                        <div>
                            <Label class="text-xs">Condition</Label>
                            <Select v-model="bulkImeiForm.condition">
                                <SelectTrigger class="h-8 text-xs"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="new"
                                        >Brand New</SelectItem
                                    >
                                    <SelectItem value="used">Used</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label class="text-xs">PTA Status</Label>
                            <Select v-model="bulkImeiForm.pta_status">
                                <SelectTrigger class="h-8 text-xs"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="approved"
                                        >PTA Approved</SelectItem
                                    >
                                    <SelectItem value="non_pta"
                                        >Non-PTA</SelectItem
                                    >
                                    <SelectItem value="jv"
                                        >JV Locked</SelectItem
                                    >
                                    <SelectItem value="cpid"
                                        >CPID Approved</SelectItem
                                    >
                                    <SelectItem value="software"
                                        >Software Approved</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label class="text-xs"
                                >Checking Warranty (Days)</Label
                            >
                            <Input
                                v-model="bulkImeiForm.warranty_days"
                                type="number"
                                placeholder="0"
                                class="h-8 text-xs"
                            />
                        </div>
                    </div>

                    <!-- Input Mode Toggle -->
                    <div
                        class="flex items-center justify-between border-b pb-2"
                    >
                        <span class="text-xs font-semibold"
                            >IMEI Input Mode</span
                        >
                        <div
                            class="flex gap-1 rounded-md border border-gray-200 bg-gray-50 p-0.5"
                        >
                            <button
                                type="button"
                                @click="bulkImeiMode = 'textarea'"
                                :class="[
                                    'rounded px-2.5 py-1 text-xs font-medium transition-colors',
                                    bulkImeiMode === 'textarea'
                                        ? 'bg-[#003B7D]/10 text-[#003B7D] shadow-sm'
                                        : 'text-muted-foreground',
                                ]"
                            >
                                Textarea (Paste List)
                            </button>
                            <button
                                type="button"
                                @click="bulkImeiMode = 'rows'"
                                :class="[
                                    'rounded px-2.5 py-1 text-xs font-medium transition-colors',
                                    bulkImeiMode === 'rows'
                                        ? 'bg-[#003B7D]/10 text-[#003B7D] shadow-sm'
                                        : 'text-muted-foreground',
                                ]"
                            >
                                Row List
                            </button>
                        </div>
                    </div>

                    <!-- Mode A: Textarea -->
                    <div v-if="bulkImeiMode === 'textarea'" class="space-y-2">
                        <Label for="raw_imeis" class="text-xs"
                            >Paste IMEIs (One per line, or comma separated for
                            SIM1, SIM2)</Label
                        >
                        <textarea
                            id="raw_imeis"
                            v-model="bulkRawText"
                            rows="6"
                            placeholder="Example:
358901234567890
358901234567891, 358901234567892
358901234567893"
                            class="w-full rounded-md border border-gray-200 bg-transparent p-3 font-mono text-xs focus:ring-2 focus:ring-[#003B7D] focus:outline-none"
                        ></textarea>
                        <p class="text-muted-foreground text-[11px]">
                            You can directly scan barcodes line-by-line or paste
                            from Excel columns.
                        </p>
                    </div>

                    <!-- Mode B: Row list -->
                    <div v-else class="space-y-2">
                        <div class="max-h-48 space-y-2 overflow-y-auto pr-1">
                            <div
                                v-for="(row, idx) in bulkImeiForm.imeis"
                                :key="idx"
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="w-6 text-right font-mono text-xs text-slate-500"
                                    >#{{ idx + 1 }}</span
                                >
                                <Input
                                    v-model="row.imei_1"
                                    placeholder="IMEI 1 *"
                                    class="h-8 flex-1 font-mono text-xs"
                                />
                                <Input
                                    v-model="row.imei_2"
                                    placeholder="IMEI 2 (Optional)"
                                    class="h-8 flex-1 font-mono text-xs"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="removeBulkRow(idx)"
                                    class="h-8 w-8 p-0 text-rose-600 hover:bg-rose-50 hover:text-rose-600"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addBulkRow"
                            class="gap-1 border-gray-200 text-xs text-slate-600 hover:bg-gray-100"
                        >
                            <Plus class="h-3.5 w-3.5" /> Add Row
                        </Button>
                    </div>

                    <span
                        v-if="bulkImeiForm.errors.imeis"
                        class="block text-xs text-rose-600"
                        >{{ bulkImeiForm.errors.imeis }}</span
                    >

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isBulkImeiModalOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="bulkImeiForm.processing"
                            class="bg-[#003B7D] text-white shadow-sm hover:bg-[#002b5c]"
                        >
                            {{
                                bulkImeiForm.processing
                                    ? 'Importing...'
                                    : 'Save All IMEIs'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
