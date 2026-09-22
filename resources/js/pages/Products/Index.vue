<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Boxes,
    Check,
    CheckCircle,
    Edit3,
    Layers,
    ListFilter,
    MoreVertical,
    Pencil,
    Plus,
    QrCode,
    Search,
    SlidersHorizontal,
    Smartphone,
    Tag,
    Trash2,
    X,
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
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import inventory from '@/routes/inventory';
import products from '@/routes/products';
import type { Team } from '@/types';

const { confirm } = useConfirm();

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
    per_page?: number;
}

const props = defineProps<{
    products?: {
        data?: ProductItem[];
        links?: Array<{ url: string | null; label: string; active: boolean }>;
        current_page?: number;
        last_page?: number;
        total?: number;
    };
    filters?: Filters;
    categories?: string[];
    brands?: string[];
    summary?: SummaryStats;
}>();

const page = usePage();
const currentTeamSlug = computed(
    () => (page.props.currentTeam as Team | undefined)?.slug || 'default',
);

const productList = computed<ProductItem[]>(
    () => props.products?.data || [],
);

const categoryList = computed<string[]>(
    () => props.categories || [],
);

const summaryStats = computed(() => ({
    total_products: props.summary?.total_products ?? 0,
    handsets_count: props.summary?.handsets_count ?? 0,
    accessories_count: props.summary?.accessories_count ?? 0,
    in_stock_imeis_count: props.summary?.in_stock_imeis_count ?? 0,
    total_stock_value: props.summary?.total_stock_value ?? 0,
}));

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam ? '/dashboard' : '/',
            },
            {
                title: 'Products & Stock',
                href: layoutProps.currentTeam
                    ? products.index(layoutProps.currentTeam.slug).url
                    : '/products',
            },
        ],
    }),
});

// Search & Filtering
const search = ref(props.filters?.search || '');
const selectedType = ref(props.filters?.type || 'all');
const selectedCategory = ref(props.filters?.category || 'all');
const selectedStockStatus = ref(props.filters?.stock_status || 'all');
const perPage = ref<number>(Number(props.filters?.per_page) || 15);

const PER_PAGE_STORAGE_KEY = 'faizan_mobile_products_per_page_v1';

const changePerPage = (val?: number) => {
    if (val) perPage.value = val;
    try {
        localStorage.setItem(PER_PAGE_STORAGE_KEY, perPage.value.toString());
    } catch (e) {
        console.error(e);
    }
    applyFilters();
};

let searchDebounceTimeout: ReturnType<typeof setTimeout> | null = null;

const applyFilters = () => {
    router.get(
        products.index(currentTeamSlug.value).url,
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
            per_page: perPage.value !== 15 ? perPage.value : undefined,
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

// Column Visibility Controls
const DEFAULT_VISIBLE_COLUMNS = {
    info: true,
    type: true,
    identifier: true,
    sale_price: true,
    cost_price: true,
    stock: true,
    actions: true,
};

const visibleColumns = ref({ ...DEFAULT_VISIBLE_COLUMNS });

if (typeof window !== 'undefined') {
    try {
        const saved = localStorage.getItem('products_visible_columns');
        if (saved) {
            visibleColumns.value = {
                ...DEFAULT_VISIBLE_COLUMNS,
                ...JSON.parse(saved),
            };
        }
    } catch {
        // ignore JSON parse error
    }
}

watch(
    visibleColumns,
    (val) => {
        try {
            localStorage.setItem(
                'products_visible_columns',
                JSON.stringify(val),
            );
        } catch {
            // ignore storage error
        }
    },
    { deep: true },
);

const activeColumnCount = computed(
    () => Object.values(visibleColumns.value).filter(Boolean).length,
);

const productColumnLabels: Record<string, string> = {
    info: 'Product Info',
    type: 'Product Type',
    identifier: 'Identifier / Barcode',
    sale_price: 'Sale Price',
    cost_price: 'Cost Price',
    stock: 'Stock Level',
    actions: 'Actions',
};

const toggleProductColumn = (key: string) => {
    if (key in visibleColumns.value) {
        visibleColumns.value[key as keyof typeof DEFAULT_VISIBLE_COLUMNS] =
            !visibleColumns.value[key as keyof typeof DEFAULT_VISIBLE_COLUMNS];
    }
};

const resetColumns = () => {
    visibleColumns.value = { ...DEFAULT_VISIBLE_COLUMNS };
};

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

// Quick Edit Price & Stock Modal
const isQuickEditModalOpen = ref(false);
const quickEditTarget = ref<ProductItem | null>(null);

const quickEditForm = useForm({
    name: '',
    brand: '',
    category: '',
    barcode: '',
    is_serialized: false,
    sale_price: '',
    cost_price: '',
    stock_quantity: 0,
    alert_quantity: 5,
});

const openQuickEditModal = (product: ProductItem) => {
    quickEditTarget.value = product;
    quickEditForm.clearErrors();
    quickEditForm.name = product.name;
    quickEditForm.brand = product.brand;
    quickEditForm.category = product.category;
    quickEditForm.barcode = product.barcode || '';
    quickEditForm.is_serialized = Boolean(product.is_serialized);
    quickEditForm.sale_price = String(product.sale_price);
    quickEditForm.cost_price = String(product.cost_price);
    quickEditForm.stock_quantity = product.stock_quantity;
    quickEditForm.alert_quantity = product.alert_quantity;
    isQuickEditModalOpen.value = true;
};

const submitQuickEditForm = () => {
    if (!quickEditTarget.value) return;
    quickEditForm.put(
        products.update([currentTeamSlug.value, quickEditTarget.value.id]).url,
        {
            onSuccess: () => {
                isQuickEditModalOpen.value = false;
            },
        },
    );
};

// Inline Cell Editing State for Products
const inlineEditingProductId = ref<number | null>(null);
const inlineEditingField = ref<'sale_price' | 'cost_price' | 'stock_quantity' | null>(null);
const inlineEditForm = useForm({
    name: '',
    brand: '',
    category: '',
    barcode: '',
    is_serialized: false,
    sale_price: '',
    cost_price: '',
    stock_quantity: 0,
    alert_quantity: 5,
});

const startInlineEdit = (product: ProductItem, field: 'sale_price' | 'cost_price' | 'stock_quantity') => {
    inlineEditingProductId.value = product.id;
    inlineEditingField.value = field;
    inlineEditForm.clearErrors();
    inlineEditForm.name = product.name;
    inlineEditForm.brand = product.brand;
    inlineEditForm.category = product.category;
    inlineEditForm.barcode = product.barcode || '';
    inlineEditForm.is_serialized = Boolean(product.is_serialized);
    inlineEditForm.sale_price = String(product.sale_price);
    inlineEditForm.cost_price = String(product.cost_price);
    inlineEditForm.stock_quantity = product.stock_quantity;
    inlineEditForm.alert_quantity = product.alert_quantity;
};

const cancelInlineEdit = () => {
    inlineEditingProductId.value = null;
    inlineEditingField.value = null;
};

const saveInlineEdit = () => {
    if (!inlineEditingProductId.value) return;
    inlineEditForm.put(
        products.update([currentTeamSlug.value, inlineEditingProductId.value]).url,
        {
            onSuccess: () => {
                inlineEditingProductId.value = null;
                inlineEditingField.value = null;
            },
        },
    );
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

const deleteProduct = async (product: ProductItem) => {
    const ok = await confirm({
        title: 'Delete Product',
        message: `Are you sure you want to delete "${product.name}"? This action cannot be undone.`,
        confirmText: 'Yes, Delete',
        cancelText: 'Cancel',
        variant: 'destructive',
    });
    if (ok) {
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
                const updated = productList.value.find(
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
                const updated = productList.value.find(
                    (p) => p.id === activeImeiProduct.value?.id,
                );
                if (updated) activeImeiProduct.value = updated;
            },
        },
    );
};

const deleteImei = async (imeiItem: ProductImeiItem) => {
    const ok = await confirm({
        title: 'Delete IMEI',
        message: `Are you sure you want to delete IMEI "${imeiItem.imei_1}"?`,
        confirmText: 'Yes, Delete',
        cancelText: 'Cancel',
        variant: 'destructive',
    });
    if (ok) {
        router.delete(imeis.destroy([currentTeamSlug.value, imeiItem.id]).url, {
            onSuccess: () => {
                const updated = productList.value.find(
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
        product || productList.value.find((p) => p.is_serialized);
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

const parseBulkTextarea = () => {
    const lines = bulkRawText.value
        .split('\n')
        .map((l) => l.trim())
        .filter(Boolean);

    const parsed: Array<{ imei_1: string; imei_2?: string }> = [];

    for (const line of lines) {
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

const formatCurrency = (val: number | string) =>
    `Rs. ${Number(val || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 })}`;

const serializedProductsList = computed(() =>
    productList.value.filter((p) => p.is_serialized),
);
</script>

<template>
    <Head title="Products & Stock Inventory" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <!-- Top Header (Matching Customers Page UI) -->
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-2xl"
                >
                    Products & Stock Inventory
                </h1>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Manage Mobile Handsets (IMEI tracked), Accessories & Stock Control
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    @click="openCreateProductModal(true)"
                    class="gap-1.5 bg-[#003B7D] text-xs font-semibold text-white hover:bg-[#002b5c]"
                >
                    <Smartphone class="h-4 w-4" /> + Add Handset
                </Button>
                <Button
                    @click="openCreateProductModal(false)"
                    variant="outline"
                    class="gap-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    <Tag class="h-4 w-4 text-[#003B7D]" /> + Add Accessory
                </Button>
                <Button
                    @click="openBulkImeiModal()"
                    variant="outline"
                    class="gap-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    <Layers class="h-4 w-4 text-[#003B7D]" /> Bulk Add IMEIs
                </Button>
            </div>
        </div>

        <!-- Summary Cards Grid (Matching Customers Page UI) -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div
                class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex items-center justify-between text-gray-500">
                    <span class="text-xs font-semibold">Total Catalog Items</span>
                    <Boxes class="h-4 w-4 text-[#003B7D]" />
                </div>
                <div
                    class="mt-2 text-2xl font-bold text-gray-900 dark:text-white"
                >
                    {{ summaryStats.total_products }}
                </div>
                <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                    {{ summaryStats.handsets_count }} Handsets &bull;
                    {{ summaryStats.accessories_count }} Accessories
                </div>
            </div>

            <div
                class="rounded-xl border border-blue-200 bg-blue-50/50 p-4 shadow-sm dark:border-blue-900/30 dark:bg-blue-950/20"
            >
                <div class="flex items-center justify-between text-blue-800 dark:text-blue-300">
                    <span class="text-xs font-semibold">In-Stock Handsets (IMEIs)</span>
                    <Smartphone class="h-4 w-4 text-[#003B7D]" />
                </div>
                <div class="mt-2 text-2xl font-bold text-[#003B7D] dark:text-blue-400">
                    {{ summaryStats.in_stock_imeis_count }} Units
                </div>
                <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                    Unique IMEI phones ready for sale
                </div>
            </div>

            <div
                class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 shadow-sm dark:border-amber-900/30 dark:bg-amber-950/20"
            >
                <div class="flex items-center justify-between text-amber-800 dark:text-amber-300">
                    <span class="text-xs font-semibold">Accessory Items</span>
                    <Tag class="h-4 w-4 text-amber-600" />
                </div>
                <div class="mt-2 text-2xl font-bold text-amber-600">
                    {{ summaryStats.accessories_count }} Products
                </div>
                <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                    Barcode & Standard Stock items
                </div>
            </div>

            <div
                class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 shadow-sm dark:border-emerald-900/30 dark:bg-emerald-950/20"
            >
                <div class="flex items-center justify-between text-emerald-800 dark:text-emerald-300">
                    <span class="text-xs font-semibold">Stock Valuation (COGS)</span>
                    <span class="text-xs font-bold text-emerald-600 uppercase">PKR</span>
                </div>
                <div class="mt-2 text-2xl font-bold text-emerald-600">
                    {{ formatCurrency(summaryStats.total_stock_value) }}
                </div>
                <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                    Calculated at purchase cost
                </div>
            </div>
        </div>

        <!-- Search & Filter Bar + Column Visibility Customizer -->
        <div
            class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:flex-row md:items-center md:justify-between"
        >
            <div class="relative max-w-md flex-1">
                <Search
                    class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2"
                />
                <Input
                    v-model="search"
                    placeholder="Search product name, brand, barcode, category or IMEI..."
                    class="h-9 pl-9 text-xs"
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="w-36">
                    <Select v-model="selectedType">
                        <SelectTrigger class="h-9 text-xs">
                            <SelectValue placeholder="All Types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Types</SelectItem>
                            <SelectItem value="serialized">Handsets (IMEI)</SelectItem>
                            <SelectItem value="accessories">Accessories / Parts</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="w-36">
                    <Select v-model="selectedCategory">
                        <SelectTrigger class="h-9 text-xs">
                            <SelectValue placeholder="All Categories" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Categories</SelectItem>
                            <SelectItem v-for="cat in categoryList" :key="cat" :value="cat">
                                {{ cat }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="w-36">
                    <Select v-model="selectedStockStatus">
                        <SelectTrigger class="h-9 text-xs">
                            <SelectValue placeholder="All Stock Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Stock Status</SelectItem>
                            <SelectItem value="low_stock">Low Stock Warning</SelectItem>
                            <SelectItem value="out_of_stock">Out of Stock</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Column Customizer Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-9 gap-1.5 text-xs text-gray-700 dark:text-gray-300"
                        >
                            <SlidersHorizontal class="h-3.5 w-3.5 text-[#003B7D]" />
                            <span>Columns</span>
                            <span
                                class="ml-1 rounded-full bg-[#003B7D]/10 px-1.5 py-0.5 text-[10px] font-bold text-[#003B7D]"
                            >
                                {{ activeColumnCount }}/7
                            </span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-60 p-2 space-y-1">
                        <DropdownMenuLabel class="flex items-center justify-between text-xs font-bold text-gray-900 dark:text-white px-1 py-1">
                            <span>Table Columns</span>
                            <button
                                type="button"
                                @click="resetColumns"
                                class="text-[11px] font-semibold text-[#003B7D] hover:underline cursor-pointer"
                            >
                                Reset All
                            </button>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator class="my-1" />
                        <div
                            v-for="(label, key) in productColumnLabels"
                            :key="key"
                            @click.stop="toggleProductColumn(key)"
                            class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer select-none transition-colors"
                        >
                            <span>{{ label }}</span>
                            <input
                                type="checkbox"
                                :checked="visibleColumns[key as keyof typeof visibleColumns]"
                                @change="toggleProductColumn(key)"
                                @click.stop
                                class="h-4 w-4 rounded border-gray-300 text-[#003B7D] focus:ring-[#003B7D] cursor-pointer"
                            />
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Products Data Table (Matching Customers Page UI) -->
        <div
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs min-w-[900px]">
                    <thead
                        class="border-b border-gray-200 bg-gray-50 text-gray-600 uppercase dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400"
                    >
                        <tr>
                            <th v-if="visibleColumns.info" class="px-4 py-3 font-semibold">Product Name & Category</th>
                            <th v-if="visibleColumns.type" class="px-4 py-3 font-semibold">Type</th>
                            <th v-if="visibleColumns.identifier" class="px-4 py-3 font-semibold">Identifier / Barcode</th>
                            <th v-if="visibleColumns.sale_price" class="px-4 py-3 font-semibold">Sale Price</th>
                            <th v-if="visibleColumns.cost_price" class="px-4 py-3 font-semibold">Cost Price</th>
                            <th v-if="visibleColumns.stock" class="px-4 py-3 text-center font-semibold">Stock Level</th>
                            <th v-if="visibleColumns.actions" class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr v-if="productList.length === 0">
                            <td
                                :colspan="activeColumnCount"
                                class="px-4 py-8 text-center text-gray-500"
                            >
                                No products found matching your filter criteria.
                            </td>
                        </tr>

                        <tr
                            v-for="product in productList"
                            :key="product.id"
                            class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors"
                        >
                            <!-- Info -->
                            <td v-if="visibleColumns.info" class="px-4 py-3">
                                <div class="font-bold text-gray-900 dark:text-white">
                                    {{ product.name }}
                                </div>
                                <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                    <span class="font-bold text-[#003B7D]">{{ product.brand }}</span>
                                    &bull; {{ product.category }}
                                </div>
                            </td>

                            <!-- Type -->
                            <td v-if="visibleColumns.type" class="px-4 py-3">
                                <span
                                    v-if="product.is_serialized"
                                    class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-[#003B7D] dark:border-blue-900/40 dark:bg-blue-950/40 dark:text-blue-300"
                                >
                                    <Smartphone class="h-3 w-3" />
                                    Handset (IMEI)
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    <Tag class="h-3 w-3 text-gray-500" />
                                    Standard Stock
                                </span>
                            </td>

                            <!-- Identifier / Barcode -->
                            <td v-if="visibleColumns.identifier" class="px-4 py-3 font-mono text-[11px]">
                                <div v-if="product.is_serialized" class="flex flex-col gap-0.5">
                                    <span class="font-bold text-[#003B7D] dark:text-blue-400">
                                        {{ product.in_stock_imeis_count || 0 }} IMEIs In Stock
                                    </span>
                                    <span class="text-gray-400 text-[10px]">
                                        ({{ product.imeis_count || 0 }} Lifetime Total)
                                    </span>
                                </div>
                                <div v-else-if="product.barcode" class="flex items-center gap-1 font-semibold text-gray-700 dark:text-gray-300">
                                    <QrCode class="h-3.5 w-3.5 text-gray-400" />
                                    {{ product.barcode }}
                                </div>
                                <div v-else class="text-gray-400 italic">
                                    No Barcode
                                </div>
                            </td>

                            <!-- Sale Price -->
                            <td v-if="visibleColumns.sale_price" class="px-4 py-3 font-bold text-gray-900 dark:text-white">
                                <div v-if="inlineEditingProductId === product.id && inlineEditingField === 'sale_price'" class="flex items-center gap-1">
                                    <Input
                                        v-model="inlineEditForm.sale_price"
                                        type="number"
                                        step="0.01"
                                        class="h-7 w-28 text-xs font-bold"
                                        @keydown.enter.prevent="saveInlineEdit"
                                        @keydown.escape.prevent="cancelInlineEdit"
                                        autofocus
                                    />
                                    <button @click="saveInlineEdit" :disabled="inlineEditForm.processing" class="flex h-6 w-6 items-center justify-center rounded bg-emerald-600 text-white hover:bg-emerald-700" title="Save Price">
                                        <Check class="h-3.5 w-3.5" />
                                    </button>
                                    <button @click="cancelInlineEdit" class="flex h-6 w-6 items-center justify-center rounded bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-800 dark:text-gray-300" title="Cancel">
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                                <div v-else class="flex items-center gap-1.5 group cursor-pointer" @click="startInlineEdit(product, 'sale_price')" title="Click to edit Sale Price inline">
                                    <span>{{ formatCurrency(product.sale_price) }}</span>
                                    <button
                                        class="text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity hover:text-[#003B7D]"
                                        title="Edit Sale Price"
                                    >
                                        <Edit3 class="h-3 w-3" />
                                    </button>
                                </div>
                            </td>

                            <!-- Cost Price -->
                            <td v-if="visibleColumns.cost_price" class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                <div v-if="inlineEditingProductId === product.id && inlineEditingField === 'cost_price'" class="flex items-center gap-1">
                                    <Input
                                        v-model="inlineEditForm.cost_price"
                                        type="number"
                                        step="0.01"
                                        class="h-7 w-28 text-xs"
                                        @keydown.enter.prevent="saveInlineEdit"
                                        @keydown.escape.prevent="cancelInlineEdit"
                                        autofocus
                                    />
                                    <button @click="saveInlineEdit" :disabled="inlineEditForm.processing" class="flex h-6 w-6 items-center justify-center rounded bg-emerald-600 text-white hover:bg-emerald-700" title="Save Cost Price">
                                        <Check class="h-3.5 w-3.5" />
                                    </button>
                                    <button @click="cancelInlineEdit" class="flex h-6 w-6 items-center justify-center rounded bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-800 dark:text-gray-300" title="Cancel">
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                                <div v-else-if="!product.is_serialized" class="flex items-center gap-1.5 group cursor-pointer" @click="startInlineEdit(product, 'cost_price')" title="Click to edit Cost Price inline">
                                    <span>{{ formatCurrency(product.cost_price) }}</span>
                                    <button
                                        class="text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity hover:text-[#003B7D]"
                                        title="Edit Cost Price"
                                    >
                                        <Edit3 class="h-3 w-3" />
                                    </button>
                                </div>
                                <span v-else class="text-[11px] italic text-gray-400">IMEI Specific</span>
                            </td>

                            <!-- Stock Level -->
                            <td v-if="visibleColumns.stock" class="px-4 py-3 text-center">
                                <div v-if="product.is_serialized" class="flex flex-col items-center gap-0.5">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[9px] font-bold uppercase"
                                        :class="
                                            (product.in_stock_imeis_count || 0) <= 0
                                                ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400'
                                                : (product.in_stock_imeis_count || 0) <= product.alert_quantity
                                                ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400'
                                                : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                        "
                                    >
                                        {{
                                            (product.in_stock_imeis_count || 0) <= 0
                                                ? 'Out of Stock'
                                                : (product.in_stock_imeis_count || 0) <= product.alert_quantity
                                                ? 'Low Stock'
                                                : 'In Stock'
                                        }}
                                    </span>
                                    <span class="font-bold text-gray-900 dark:text-white text-xs">
                                        {{ product.in_stock_imeis_count || 0 }} Units
                                    </span>
                                </div>
                                <div v-else-if="inlineEditingProductId === product.id && inlineEditingField === 'stock_quantity'" class="flex items-center justify-center gap-1">
                                    <Input
                                        v-model="inlineEditForm.stock_quantity"
                                        type="number"
                                        class="h-7 w-20 text-xs text-center font-bold"
                                        @keydown.enter.prevent="saveInlineEdit"
                                        @keydown.escape.prevent="cancelInlineEdit"
                                        autofocus
                                    />
                                    <button @click="saveInlineEdit" :disabled="inlineEditForm.processing" class="flex h-6 w-6 items-center justify-center rounded bg-emerald-600 text-white hover:bg-emerald-700" title="Save Stock">
                                        <Check class="h-3.5 w-3.5" />
                                    </button>
                                    <button @click="cancelInlineEdit" class="flex h-6 w-6 items-center justify-center rounded bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-800 dark:text-gray-300" title="Cancel">
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                                <div v-else class="flex flex-col items-center gap-0.5 group cursor-pointer" @click="startInlineEdit(product, 'stock_quantity')" title="Click to edit Stock inline">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[9px] font-bold uppercase"
                                        :class="
                                            product.stock_quantity <= 0
                                                ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400'
                                                : product.stock_quantity <= product.alert_quantity
                                                ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400'
                                                : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                        "
                                    >
                                        {{
                                            product.stock_quantity <= 0
                                                ? 'Out of Stock'
                                                : product.stock_quantity <= product.alert_quantity
                                                ? 'Low Stock'
                                                : 'In Stock'
                                        }}
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <span class="font-bold text-gray-900 dark:text-white text-xs">
                                            {{ product.stock_quantity }} Pcs
                                        </span>
                                        <Edit3 class="h-3 w-3 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity hover:text-[#003B7D]" />
                                    </div>
                                </div>
                            </td>

                            <!-- Actions -->
                            <td v-if="visibleColumns.actions" class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-2xs transition-all hover:bg-gray-50 hover:text-gray-900 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#003B7D]/20 data-[state=open]:bg-gray-100 data-[state=open]:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white dark:data-[state=open]:bg-gray-700"
                                                title="Product Actions"
                                            >
                                                <MoreVertical class="h-4 w-4" />
                                                <span class="sr-only">Product Actions</span>
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="end"
                                            class="w-52 rounded-xl border border-gray-200/80 bg-white/95 p-1.5 shadow-xl backdrop-blur-md dark:border-gray-800 dark:bg-gray-900/95"
                                        >
                                            <DropdownMenuItem
                                                v-if="product.is_serialized"
                                                @click="openImeiManageModal(product)"
                                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white"
                                            >
                                                <Smartphone class="h-4 w-4 text-blue-600 dark:text-blue-400 shrink-0" />
                                                <span>View & Manage IMEIs</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="product.is_serialized"
                                                @click="openAddSingleImeiModal(product)"
                                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white"
                                            >
                                                <Plus class="h-4 w-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                                <span>Add Single IMEI Unit</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="openQuickEditModal(product)"
                                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white"
                                            >
                                                <SlidersHorizontal class="h-4 w-4 text-amber-500 dark:text-amber-400 shrink-0" />
                                                <span>Quick Edit (Price & Stock)</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="openEditProductModal(product)"
                                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white"
                                            >
                                                <Edit3 class="h-4 w-4 text-indigo-500 dark:text-indigo-400 shrink-0" />
                                                <span>Edit Product Details</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator class="my-1 bg-gray-100 dark:bg-gray-800" />
                                            <DropdownMenuItem
                                                @click="deleteProduct(product)"
                                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"
                                            >
                                                <Trash2 class="h-4 w-4 text-rose-600 dark:text-rose-400 shrink-0" />
                                                <span>Delete Product</span>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer with Editable Items Per Page -->
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-gray-200 bg-gray-50/50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900/50"
            >
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <span class="font-semibold text-gray-700 dark:text-gray-300">Items per page:</span>
                    <select
                        v-model="perPage"
                        @change="changePerPage()"
                        class="h-8 rounded-lg border border-gray-200 bg-white px-2.5 text-xs font-bold text-gray-800 shadow-2xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#003B7D]"
                    >
                        <option :value="10">10 per page</option>
                        <option :value="15">15 per page (default)</option>
                        <option :value="25">25 per page</option>
                        <option :value="50">50 per page</option>
                        <option :value="100">100 per page</option>
                        <option :value="250">250 per page</option>
                        <option :value="500">500 per page (All)</option>
                    </select>
                    <span class="ml-1 text-gray-400">&bull;</span>
                    <span>Total {{ props.products?.total ?? productList.length }} items</span>
                </div>

                <div v-if="props.products?.links && props.products.links.length > 3" class="flex items-center gap-1">
                    <template v-for="(link, i) in props.products.links" :key="i">
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
                            class="h-7 px-2.5 text-xs"
                            :class="[
                                link.active
                                    ? 'bg-[#003B7D] text-white'
                                    : 'text-gray-700 dark:text-gray-300',
                            ]"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- Quick Edit Modal -->
        <Dialog v-model:open="isQuickEditModalOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">
                        Quick Edit &bull; {{ quickEditTarget?.name }}
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Adjust sale price, cost price and stock levels directly.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitQuickEditForm" class="space-y-3 py-2 text-xs">
                    <div class="space-y-1">
                        <Label class="font-medium">Sale Price (PKR) *</Label>
                        <Input
                            v-model="quickEditForm.sale_price"
                            type="number"
                            step="0.01"
                            class="h-9 text-xs font-bold"
                            required
                        />
                    </div>

                    <div v-if="!quickEditTarget?.is_serialized" class="space-y-1">
                        <Label class="font-medium">Cost Price (PKR)</Label>
                        <Input
                            v-model="quickEditForm.cost_price"
                            type="number"
                            step="0.01"
                            class="h-9 text-xs"
                        />
                    </div>

                    <div v-if="!quickEditTarget?.is_serialized" class="space-y-1">
                        <Label class="font-medium">Stock Quantity (Pcs)</Label>
                        <Input
                            v-model="quickEditForm.stock_quantity"
                            type="number"
                            class="h-9 text-xs font-bold"
                        />
                    </div>

                    <DialogFooter class="pt-3">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="isQuickEditModalOpen = false"
                            class="text-xs"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="quickEditForm.processing"
                            class="bg-[#003B7D] text-xs font-semibold text-white hover:bg-[#002b5c]"
                        >
                            Update Product
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Create / Edit Product Modal -->
        <Dialog :open="isProductModalOpen" @update:open="isProductModalOpen = $event">
            <DialogContent class="max-w-xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">
                        {{ editingProduct ? 'Edit Product Details' : productForm.is_serialized ? 'Add New Mobile Handset' : 'Add New Accessory / Spare Part' }}
                    </DialogTitle>
                    <DialogDescription class="text-xs text-gray-500">
                        {{ productForm.is_serialized ? 'Create phone model with IMEI serial tracking capability.' : 'Create accessory or spare part with barcode & standard quantity stock.' }}
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-3 py-2 text-xs" @submit.prevent="submitProductForm">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="prod-name" class="font-medium">Product Name *</Label>
                            <Input
                                id="prod-name"
                                v-model="productForm.name"
                                required
                                placeholder="e.g. iPhone 15 Pro / Charger"
                                class="h-9 text-xs"
                            />
                        </div>
                        <div class="space-y-1">
                            <Label for="prod-brand" class="font-medium">Brand *</Label>
                            <Input
                                id="prod-brand"
                                v-model="productForm.brand"
                                required
                                placeholder="e.g. Apple, Samsung, Anker"
                                class="h-9 text-xs"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="prod-category" class="font-medium">Category *</Label>
                            <Input
                                id="prod-category"
                                v-model="productForm.category"
                                required
                                placeholder="Mobile Handsets / Covers"
                                class="h-9 text-xs"
                            />
                        </div>
                        <div class="space-y-1">
                            <Label for="prod-barcode" class="font-medium flex items-center justify-between">
                                <span>Barcode / SKU</span>
                                <button type="button" class="text-[10px] font-semibold text-[#003B7D] hover:underline" @click="generateBarcode">Auto Generate</button>
                            </Label>
                            <Input
                                id="prod-barcode"
                                v-model="productForm.barcode"
                                placeholder="890123456789"
                                class="h-9 text-xs font-mono"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="prod-sale-price" class="font-medium">Sale Price (PKR) *</Label>
                            <Input
                                id="prod-sale-price"
                                v-model="productForm.sale_price"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                placeholder="0"
                                class="h-9 text-xs font-bold"
                            />
                        </div>
                        <div class="space-y-1">
                            <Label for="prod-alert-qty" class="font-medium">Low Stock Alert Qty *</Label>
                            <Input
                                id="prod-alert-qty"
                                v-model="productForm.alert_quantity"
                                type="number"
                                min="0"
                                required
                                placeholder="5"
                                class="h-9 text-xs"
                            />
                        </div>
                    </div>

                    <div v-if="!productForm.is_serialized" class="grid grid-cols-2 gap-3 rounded-lg border border-gray-200 bg-gray-50 p-2.5 dark:border-gray-800 dark:bg-gray-800/40">
                        <div class="space-y-1">
                            <Label for="prod-cost-price" class="font-medium">Cost Price (PKR)</Label>
                            <Input
                                id="prod-cost-price"
                                v-model="productForm.cost_price"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0"
                                class="h-9 text-xs bg-white dark:bg-gray-900"
                            />
                        </div>
                        <div class="space-y-1">
                            <Label for="prod-stock-qty" class="font-medium">Initial Stock Qty</Label>
                            <Input
                                id="prod-stock-qty"
                                v-model="productForm.stock_quantity"
                                type="number"
                                min="0"
                                placeholder="0"
                                class="h-9 text-xs bg-white dark:bg-gray-900"
                            />
                        </div>
                    </div>

                    <!-- Optional Initial IMEI registration for Handsets -->
                    <div v-if="productForm.is_serialized && !editingProduct" class="rounded-lg border border-blue-200 bg-blue-50/40 p-3 space-y-2 dark:border-blue-900/40 dark:bg-blue-950/20">
                        <Label class="text-[11px] font-bold text-[#003B7D] dark:text-blue-300 uppercase tracking-wider block">+ Register First Handset IMEI (Optional)</Label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <Label class="text-[10px] font-medium">Primary IMEI (IMEI 1) *</Label>
                                <Input v-model="productForm.initial_imei_1" placeholder="358901234567890" class="h-8 text-xs font-mono bg-white dark:bg-gray-900" />
                            </div>
                            <div>
                                <Label class="text-[10px] font-medium text-gray-500">Secondary IMEI (IMEI 2)</Label>
                                <Input v-model="productForm.initial_imei_2" placeholder="358901234567891" class="h-8 text-xs font-mono bg-white dark:bg-gray-900" />
                            </div>
                        </div>
                        <div class="grid grid-cols-4 gap-2">
                            <div>
                                <Label class="text-[10px] font-medium text-gray-500">Color</Label>
                                <Input v-model="productForm.initial_color" placeholder="Titanium" class="h-8 text-xs bg-white dark:bg-gray-900" />
                            </div>
                            <div>
                                <Label class="text-[10px] font-medium text-gray-500">Storage</Label>
                                <Input v-model="productForm.initial_storage" placeholder="256GB" class="h-8 text-xs bg-white dark:bg-gray-900" />
                            </div>
                            <div>
                                <Label class="text-[10px] font-medium text-gray-500">Condition</Label>
                                <Select v-model="productForm.initial_condition">
                                    <SelectTrigger class="h-8 text-xs bg-white dark:bg-gray-900"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="new">New</SelectItem>
                                        <SelectItem value="used">Used</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label class="text-[10px] font-medium text-gray-500">PTA Status</Label>
                                <Select v-model="productForm.initial_pta_status">
                                    <SelectTrigger class="h-8 text-xs bg-white dark:bg-gray-900"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="approved">Approved</SelectItem>
                                        <SelectItem value="non_pta">Non-PTA</SelectItem>
                                        <SelectItem value="jv">JV</SelectItem>
                                        <SelectItem value="cpid">CPID</SelectItem>
                                        <SelectItem value="software">Software</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <Label class="text-[10px] font-medium">Purchase Cost (PKR) *</Label>
                                <Input v-model="productForm.initial_purchase_cost" type="number" min="0" placeholder="0" class="h-8 text-xs bg-white dark:bg-gray-900 font-bold" />
                            </div>
                            <div>
                                <Label class="text-[10px] font-medium text-gray-500">Warranty Days</Label>
                                <Input v-model="productForm.initial_warranty_days" type="number" min="0" placeholder="7" class="h-8 text-xs bg-white dark:bg-gray-900" />
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="pt-3">
                        <Button type="button" variant="outline" size="sm" @click="isProductModalOpen = false" class="text-xs">Cancel</Button>
                        <Button size="sm" class="bg-[#003B7D] text-xs font-semibold text-white hover:bg-[#002b5c]" :disabled="productForm.processing">
                            {{ editingProduct ? 'Save Changes' : 'Create Item' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Manage IMEIs Modal -->
        <Dialog :open="isImeiManageModalOpen" @update:open="isImeiManageModalOpen = $event">
            <DialogContent class="max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                <DialogHeader class="border-b border-gray-200 dark:border-gray-800 pb-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <DialogTitle class="text-base font-bold flex items-center gap-2">
                                <Smartphone class="h-4 w-4 text-[#003B7D]" />
                                Registered IMEIs &bull; {{ activeImeiProduct?.name }}
                            </DialogTitle>
                            <DialogDescription class="text-xs text-gray-500">
                                View stock status, PTA status, and purchase history for individual handset units.
                            </DialogDescription>
                        </div>
                        <Button size="sm" class="bg-[#003B7D] text-xs font-semibold text-white hover:bg-[#002b5c]" @click="openAddSingleImeiModal(activeImeiProduct!)">
                            + Add Single IMEI
                        </Button>
                    </div>
                </DialogHeader>

                <div class="flex-1 overflow-y-auto p-2 space-y-3">
                    <div class="rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-200 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">IMEI Serial Numbers</th>
                                    <th class="px-4 py-3">Variant / Spec</th>
                                    <th class="px-4 py-3">PTA & Condition</th>
                                    <th class="px-4 py-3 text-right">Purchase Cost</th>
                                    <th class="px-4 py-3 text-center">Status</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="imeiItem in activeImeiProduct?.imeis" :key="imeiItem.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                    <td class="px-4 py-3 font-mono">
                                        <div class="font-bold text-gray-900 dark:text-white">{{ imeiItem.imei_1 }}</div>
                                        <div v-if="imeiItem.imei_2" class="text-[10px] text-gray-400">IMEI2: {{ imeiItem.imei_2 }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        <div>{{ imeiItem.color || '-' }} &bull; {{ imeiItem.storage || '-' }}</div>
                                        <div class="text-[10px] text-gray-400">Warranty: {{ imeiItem.warranty_days }} Days</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase bg-gray-100 text-gray-800 mr-1 dark:bg-gray-800 dark:text-gray-200">
                                            {{ imeiItem.condition }}
                                        </span>
                                        <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase bg-blue-50 text-[#003B7D] dark:bg-blue-950/40 dark:text-blue-300">
                                            {{ imeiItem.pta_status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">
                                        {{ formatCurrency(imeiItem.purchase_cost) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[9px] font-bold uppercase"
                                            :class="
                                                imeiItem.status === 'in_stock'
                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400'
                                                    : imeiItem.status === 'sold'
                                                    ? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-400'
                                            "
                                        >
                                            {{ imeiItem.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex justify-end gap-1">
                                            <Button size="sm" variant="ghost" class="h-7 w-7 p-0" @click="openEditImeiModal(imeiItem)">
                                                <Pencil class="h-3.5 w-3.5 text-gray-500" />
                                            </Button>
                                            <Button size="sm" variant="ghost" class="h-7 w-7 p-0 text-rose-500" @click="deleteImei(imeiItem)">
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!activeImeiProduct?.imeis?.length">
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                        No IMEI records registered for this handset yet. Click "+ Add Single IMEI" to register stock.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Add Single IMEI Modal -->
        <Dialog :open="isAddSingleImeiModalOpen" @update:open="isAddSingleImeiModalOpen = $event">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">
                        + Add Single IMEI &bull; {{ activeImeiProduct?.name }}
                    </DialogTitle>
                </DialogHeader>

                <form class="space-y-3 py-1 text-xs" @submit.prevent="submitSingleImeiForm">
                    <div class="space-y-1">
                        <Label class="font-medium">IMEI 1 *</Label>
                        <Input v-model="singleImeiForm.imei_1" required placeholder="15 Digit IMEI 1" class="h-9 text-xs font-mono" />
                    </div>
                    <div class="space-y-1">
                        <Label class="font-medium text-gray-500">IMEI 2 (Optional)</Label>
                        <Input v-model="singleImeiForm.imei_2" placeholder="15 Digit IMEI 2" class="h-9 text-xs font-mono" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="font-medium">Color</Label>
                            <Input v-model="singleImeiForm.color" placeholder="Black" class="h-9 text-xs" />
                        </div>
                        <div class="space-y-1">
                            <Label class="font-medium">Storage</Label>
                            <Input v-model="singleImeiForm.storage" placeholder="128GB" class="h-9 text-xs" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="font-medium">Condition</Label>
                            <Select v-model="singleImeiForm.condition">
                                <SelectTrigger class="h-9 text-xs"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="new">New</SelectItem>
                                    <SelectItem value="used">Used</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label class="font-medium">PTA Status</Label>
                            <Select v-model="singleImeiForm.pta_status">
                                <SelectTrigger class="h-9 text-xs"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="approved">Approved</SelectItem>
                                    <SelectItem value="non_pta">Non-PTA</SelectItem>
                                    <SelectItem value="jv">JV</SelectItem>
                                    <SelectItem value="cpid">CPID</SelectItem>
                                    <SelectItem value="software">Software</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="font-medium">Purchase Cost (PKR) *</Label>
                            <Input v-model="singleImeiForm.purchase_cost" type="number" min="0" required placeholder="0" class="h-9 text-xs font-bold" />
                        </div>
                        <div class="space-y-1">
                            <Label class="font-medium">Warranty Days</Label>
                            <Input v-model="singleImeiForm.warranty_days" type="number" min="0" placeholder="0" class="h-9 text-xs" />
                        </div>
                    </div>

                    <DialogFooter class="pt-3">
                        <Button type="button" variant="outline" size="sm" @click="isAddSingleImeiModalOpen = false" class="text-xs">Cancel</Button>
                        <Button size="sm" class="bg-[#003B7D] text-xs font-semibold text-white hover:bg-[#002b5c]" :disabled="singleImeiForm.processing">Save IMEI Unit</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit IMEI Modal -->
        <Dialog :open="isEditImeiModalOpen" @update:open="isEditImeiModalOpen = $event">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">Edit IMEI Unit Record</DialogTitle>
                </DialogHeader>

                <form class="space-y-3 py-1 text-xs" @submit.prevent="submitEditImeiForm">
                    <div class="space-y-1">
                        <Label class="font-medium">IMEI 1 *</Label>
                        <Input v-model="editImeiForm.imei_1" required class="h-9 text-xs font-mono" />
                    </div>
                    <div class="space-y-1">
                        <Label class="font-medium">IMEI 2</Label>
                        <Input v-model="editImeiForm.imei_2" class="h-9 text-xs font-mono" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="font-medium">Color</Label>
                            <Input v-model="editImeiForm.color" class="h-9 text-xs" />
                        </div>
                        <div class="space-y-1">
                            <Label class="font-medium">Storage</Label>
                            <Input v-model="editImeiForm.storage" class="h-9 text-xs" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="font-medium">Condition</Label>
                            <Select v-model="editImeiForm.condition">
                                <SelectTrigger class="h-9 text-xs"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="new">New</SelectItem>
                                    <SelectItem value="used">Used</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label class="font-medium">PTA Status</Label>
                            <Select v-model="editImeiForm.pta_status">
                                <SelectTrigger class="h-9 text-xs"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="approved">Approved</SelectItem>
                                    <SelectItem value="non_pta">Non-PTA</SelectItem>
                                    <SelectItem value="jv">JV</SelectItem>
                                    <SelectItem value="cpid">CPID</SelectItem>
                                    <SelectItem value="software">Software</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="font-medium">Purchase Cost (PKR) *</Label>
                            <Input v-model="editImeiForm.purchase_cost" type="number" min="0" required class="h-9 text-xs font-bold" />
                        </div>
                        <div class="space-y-1">
                            <Label class="font-medium">Stock Status</Label>
                            <Select v-model="editImeiForm.status">
                                <SelectTrigger class="h-9 text-xs"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="in_stock">In Stock</SelectItem>
                                    <SelectItem value="sold">Sold</SelectItem>
                                    <SelectItem value="repairing">Repairing</SelectItem>
                                    <SelectItem value="returned">Returned</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <DialogFooter class="pt-3">
                        <Button type="button" variant="outline" size="sm" @click="isEditImeiModalOpen = false" class="text-xs">Cancel</Button>
                        <Button size="sm" class="bg-[#003B7D] text-xs font-semibold text-white hover:bg-[#002b5c]" :disabled="editImeiForm.processing">Save IMEI Changes</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Bulk Add IMEIs Modal -->
        <Dialog :open="isBulkImeiModalOpen" @update:open="isBulkImeiModalOpen = $event">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">
                        Bulk Register Handset IMEIs
                    </DialogTitle>
                    <DialogDescription class="text-xs text-gray-500">
                        Import multiple IMEIs into stock for a handset model at once.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-3 py-2 text-xs" @submit.prevent="submitBulkImeiForm">
                    <div class="space-y-1">
                        <Label class="font-medium">Select Handset Product Model *</Label>
                        <Select v-model="bulkImeiForm.product_id">
                            <SelectTrigger class="h-9 text-xs"><SelectValue placeholder="Choose a handset model" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="p in serializedProductsList" :key="p.id" :value="p.id">
                                    {{ p.name }} ({{ p.brand }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid grid-cols-4 gap-2">
                        <div class="space-y-1">
                            <Label class="font-medium">Color</Label>
                            <Input v-model="bulkImeiForm.color" placeholder="Black" class="h-9 text-xs" />
                        </div>
                        <div class="space-y-1">
                            <Label class="font-medium">Storage</Label>
                            <Input v-model="bulkImeiForm.storage" placeholder="128GB" class="h-9 text-xs" />
                        </div>
                        <div class="space-y-1">
                            <Label class="font-medium">Condition</Label>
                            <Select v-model="bulkImeiForm.condition">
                                <SelectTrigger class="h-9 text-xs"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="new">New</SelectItem>
                                    <SelectItem value="used">Used</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label class="font-medium">PTA Status</Label>
                            <Select v-model="bulkImeiForm.pta_status">
                                <SelectTrigger class="h-9 text-xs"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="approved">Approved</SelectItem>
                                    <SelectItem value="non_pta">Non-PTA</SelectItem>
                                    <SelectItem value="jv">JV</SelectItem>
                                    <SelectItem value="cpid">CPID</SelectItem>
                                    <SelectItem value="software">Software</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="font-medium">Unit Purchase Cost (PKR) *</Label>
                            <Input v-model="bulkImeiForm.purchase_cost" type="number" min="0" required placeholder="0" class="h-9 text-xs font-bold" />
                        </div>
                        <div class="space-y-1">
                            <Label class="font-medium">Warranty Days</Label>
                            <Input v-model="bulkImeiForm.warranty_days" type="number" min="0" placeholder="0" class="h-9 text-xs" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <Label class="font-medium block">Paste IMEIs List (One per line or separated by comma/space)</Label>
                        <textarea
                            v-model="bulkRawText"
                            rows="5"
                            placeholder="358901234567890&#10;358901234567891, 358901234567892&#10;358901234567893"
                            class="w-full rounded-md border border-gray-200 bg-white p-2.5 font-mono text-xs text-gray-900 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                        ></textarea>
                    </div>

                    <DialogFooter class="pt-3">
                        <Button type="button" variant="outline" size="sm" @click="isBulkImeiModalOpen = false" class="text-xs">Cancel</Button>
                        <Button size="sm" class="bg-[#003B7D] text-xs font-semibold text-white hover:bg-[#002b5c]" :disabled="bulkImeiForm.processing">Import Bulk IMEIs</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
