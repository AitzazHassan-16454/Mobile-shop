<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Edit3,
    MoreVertical,
    Plus,
    Search,
    SlidersHorizontal,
    Tag,
    Trash2,
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
    DropdownMenuContent,
    DropdownMenuItem,
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
import products from '@/routes/products';
import type { Team } from '@/types';
import { toast } from 'vue-sonner';

const { confirm } = useConfirm();

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
    created_at: string;
}

interface SummaryStats {
    total_products?: number;
    accessories_count?: number;
    total_stock_units?: number;
    total_stock_value?: number;
}

interface Filters {
    search: string;
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

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam ? '/dashboard' : '/',
            },
            {
                title: 'Accessories',
                href: layoutProps.currentTeam
                    ? products.index(layoutProps.currentTeam.slug).url
                    : '/products',
            },
        ],
    }),
});

const productList = computed<ProductItem[]>(() =>
    (props.products?.data || []).filter((product) => !product.is_serialized),
);
const categoryList = computed<string[]>(() => props.categories || []);
const summaryStats = computed(() => ({
    total_products:
        props.summary?.accessories_count ?? props.summary?.total_products ?? 0,
    total_stock_units: props.summary?.total_stock_units ?? 0,
    total_stock_value: props.summary?.total_stock_value ?? 0,
}));

const search = ref(props.filters?.search || '');
const selectedCategory = ref(props.filters?.category || 'all');
const selectedStockStatus = ref(props.filters?.stock_status || 'all');
const perPage = ref<number>(Number(props.filters?.per_page) || 15);

const PER_PAGE_STORAGE_KEY = 'accessories_per_page_v1';

const applyFilters = () => {
    router.get(
        products.index(currentTeamSlug.value).url,
        {
            search: search.value || undefined,
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

let searchDebounceTimeout: ReturnType<typeof setTimeout> | null = null;

watch(search, () => {
    if (searchDebounceTimeout) clearTimeout(searchDebounceTimeout);
    searchDebounceTimeout = setTimeout(applyFilters, 350);
});

watch([selectedCategory, selectedStockStatus], applyFilters);

const changePerPage = (val?: number) => {
    if (val) perPage.value = val;
    try {
        localStorage.setItem(PER_PAGE_STORAGE_KEY, perPage.value.toString());
    } catch (e) {
        console.error(e);
    }
    applyFilters();
};

const DEFAULT_VISIBLE_COLUMNS = {
    info: true,
    sale_price: true,
    cost_price: true,
    stock: true,
    actions: true,
} as const;

type ProductColumn = keyof typeof DEFAULT_VISIBLE_COLUMNS;

const visibleColumns = ref<Record<ProductColumn, boolean>>({
    ...DEFAULT_VISIBLE_COLUMNS,
});

if (typeof window !== 'undefined') {
    try {
        const saved = localStorage.getItem('accessories_visible_columns');
        if (saved) {
            const parsed = JSON.parse(saved);
            for (const col of Object.keys(DEFAULT_VISIBLE_COLUMNS) as ProductColumn[]) {
                if (typeof parsed[col] === 'boolean') {
                    visibleColumns.value[col] = parsed[col];
                }
            }
        }
    } catch {}
}

watch(
    visibleColumns,
    (value) => {
        try {
            localStorage.setItem(
                'accessories_visible_columns',
                JSON.stringify(value),
            );
        } catch {}
    },
    { deep: true },
);

const productColumnLabels: Record<ProductColumn, string> = {
    info: 'Accessory Info',
    sale_price: 'Sale Price',
    cost_price: 'Cost Price',
    stock: 'Stock Level',
    actions: 'Actions',
};
const columnOptions = Object.entries(productColumnLabels) as [
    ProductColumn,
    string,
][];
const activeColumnCount = computed(
    () => Object.values(visibleColumns.value).filter(Boolean).length,
);

const toggleColumn = (key: string) => {
    if (key in visibleColumns.value) {
        visibleColumns.value[key as ProductColumn] =
            !visibleColumns.value[key as ProductColumn];
    }
};

const resetColumns = () => {
    visibleColumns.value = { ...DEFAULT_VISIBLE_COLUMNS };
};

const isProductModalOpen = ref(false);
const editingProduct = ref<ProductItem | null>(null);

const productForm = useForm({
    name: '',
    brand: '',
    category: '',
    is_serialized: false,
    sale_price: '',
    cost_price: '',
    stock_quantity: 0,
    alert_quantity: 5,
});

const openCreateAccessoryModal = () => {
    editingProduct.value = null;
    productForm.reset();
    productForm.clearErrors();
    productForm.is_serialized = false;
    if (!productForm.category) productForm.category = 'Accessories';
    isProductModalOpen.value = true;
};

const openEditProductModal = (product: ProductItem) => {
    editingProduct.value = product;
    productForm.clearErrors();
    productForm.name = product.name;
    productForm.brand = product.brand;
    productForm.category = product.category;
    productForm.is_serialized = false;
    productForm.sale_price = String(product.sale_price);
    productForm.cost_price = String(product.cost_price);
    productForm.stock_quantity = product.stock_quantity;
    productForm.alert_quantity = product.alert_quantity;
    isProductModalOpen.value = true;
};

const submitProductForm = () => {
    productForm.clearErrors();
    productForm.is_serialized = false;

    if (Number(productForm.sale_price) > 1000000) {
        productForm.setError('sale_price', 'قیمت 10 لاکھ (Rs 1,000,000) سے زیادہ نہیں ہو سکتی / Sale price cannot exceed Rs 1,000,000.');
        toast.error('قیمت کی حد سے تجاوز', {
            description: 'پراڈکٹ کی فروخت کی قیمت زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔',
        });
        return;
    }

    if (productForm.cost_price && Number(productForm.cost_price) > 1000000) {
        productForm.setError('cost_price', 'خریداری لاگت 10 لاکھ (Rs 1,000,000) سے زیادہ نہیں ہو سکتی / Cost price cannot exceed Rs 1,000,000.');
        toast.error('قیمت کی حد سے تجاوز', {
            description: 'پراڈکٹ کی خریداری لاگت زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔',
        });
        return;
    }

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
        return;
    }

    productForm.post(products.store(currentTeamSlug.value).url, {
        onSuccess: () => {
            isProductModalOpen.value = false;
        },
    });
};

const isQuickEditModalOpen = ref(false);
const quickEditTarget = ref<ProductItem | null>(null);
const quickEditForm = useForm({
    sale_price: '',
    cost_price: '',
    stock_quantity: 0,
});

const openQuickEditModal = (product: ProductItem) => {
    quickEditTarget.value = product;
    quickEditForm.clearErrors();
    quickEditForm.sale_price = String(product.sale_price);
    quickEditForm.cost_price = String(product.cost_price);
    quickEditForm.stock_quantity = product.stock_quantity;
    isQuickEditModalOpen.value = true;
};

const submitQuickEditForm = () => {
    if (!quickEditTarget.value) return;

    quickEditForm.clearErrors();

    if (Number(quickEditForm.sale_price) > 1000000) {
        quickEditForm.setError('sale_price', 'قیمت 10 لاکھ (Rs 1,000,000) سے زیادہ نہیں ہو سکتی / Sale price cannot exceed Rs 1,000,000.');
        toast.error('قیمت کی حد سے تجاوز', {
            description: 'پراڈکٹ کی فروخت کی قیمت زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔',
        });
        return;
    }

    if (quickEditForm.cost_price && Number(quickEditForm.cost_price) > 1000000) {
        quickEditForm.setError('cost_price', 'خریداری لاگت 10 لاکھ (Rs 1,000,000) سے زیادہ نہیں ہو سکتی / Cost price cannot exceed Rs 1,000,000.');
        toast.error('قیمت کی حد سے تجاوز', {
            description: 'پراڈکٹ کی خریداری لاگت زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔',
        });
        return;
    }

    quickEditForm.put(
        products.update([currentTeamSlug.value, quickEditTarget.value.id]).url,
        {
            onSuccess: () => {
                isQuickEditModalOpen.value = false;
            },
        },
    );
};

const deleteProduct = async (product: ProductItem) => {
    const ok = await confirm({
        title: 'Delete Accessory',
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

const formatCurrency = (val: number | string) =>
    `Rs. ${Number(val || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 })}`;
</script>

<template>
    <Head title="Accessories" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-xl font-bold tracking-tight text-gray-900 sm:text-2xl dark:text-white"
                >
                    Accessories
                </h1>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Manage accessories, spare parts, and stock.
                </p>
            </div>

            <Button
                @click="openCreateAccessoryModal"
                class="gap-1.5 bg-[#003B7D] text-xs font-semibold text-white hover:bg-[#002b5c]"
            >
                <Plus class="h-4 w-4" /> Add Accessories
            </Button>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div
                class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex items-center justify-between text-gray-500">
                    <span class="text-xs font-semibold">Total Accessories</span>
                    <Tag class="h-4 w-4 text-[#003B7D]" />
                </div>
                <div
                    class="mt-2 text-2xl font-bold text-gray-900 dark:text-white"
                >
                    {{ summaryStats.total_products }}
                </div>
                <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                    Accessory catalog items
                </div>
            </div>

            <div
                class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 shadow-sm dark:border-amber-900/30 dark:bg-amber-950/20"
            >
                <div
                    class="flex items-center justify-between text-amber-800 dark:text-amber-300"
                >
                    <span class="text-xs font-semibold">Units in Stock</span>
                    <Tag class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                </div>
                <div
                    class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400"
                >
                    {{ summaryStats.total_stock_units }}
                </div>
                <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                    Available accessory quantity
                </div>
            </div>

            <div
                class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 shadow-sm dark:border-emerald-900/30 dark:bg-emerald-950/20"
            >
                <div
                    class="flex items-center justify-between text-emerald-800 dark:text-emerald-300"
                >
                    <span class="text-xs font-semibold">Stock Valuation</span>
                    <span
                        class="text-xs font-bold text-emerald-600 uppercase dark:text-emerald-400"
                        >PKR</span
                    >
                </div>
                <div
                    class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400"
                >
                    {{ formatCurrency(summaryStats.total_stock_value) }}
                </div>
                <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                    Calculated at purchase cost
                </div>
            </div>
        </div>

        <div
            class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm md:flex-row md:items-center md:justify-between dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="relative max-w-md flex-1">
                <Search
                    class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2"
                />
                <Input
                    v-model="search"
                    placeholder="Search accessory name, brand, or category..."
                    class="h-9 pl-9 text-xs"
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="w-40">
                    <Select v-model="selectedCategory">
                        <SelectTrigger class="h-9 text-xs">
                            <SelectValue placeholder="All Categories" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Categories</SelectItem>
                            <SelectItem
                                v-for="category in categoryList"
                                :key="category"
                                :value="category"
                            >
                                {{ category }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="w-40">
                    <Select v-model="selectedStockStatus">
                        <SelectTrigger class="h-9 text-xs">
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

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-9 gap-1.5 text-xs text-gray-700 dark:text-gray-300"
                        >
                            <SlidersHorizontal
                                class="h-3.5 w-3.5 text-[#003B7D]"
                            />
                            <span>Columns</span>
                            <span
                                class="ml-1 rounded-full bg-[#003B7D]/10 px-1.5 py-0.5 text-[10px] font-bold text-[#003B7D]"
                            >
                                {{ activeColumnCount }}/{{ columnOptions.length }}
                            </span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-60 space-y-1 p-2">
                        <DropdownMenuLabel
                            class="flex items-center justify-between px-1 py-1 text-xs font-bold text-gray-900 dark:text-white"
                        >
                            <span>Table Columns</span>
                            <button
                                type="button"
                                @click="resetColumns"
                                class="cursor-pointer text-[11px] font-semibold text-[#003B7D] hover:underline"
                            >
                                Reset All
                            </button>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator class="my-1" />
                        <div
                            v-for="[key, label] in columnOptions"
                            :key="key"
                            @click.stop="toggleColumn(key)"
                            class="flex cursor-pointer items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-800 transition-colors select-none hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                        >
                            <span>{{ label }}</span>
                            <input
                                type="checkbox"
                                :checked="visibleColumns[key]"
                                @change="toggleColumn(key)"
                                @click.stop
                                class="h-4 w-4 cursor-pointer rounded border-gray-300 text-[#003B7D] focus:ring-[#003B7D]"
                            />
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] text-left text-xs">
                    <thead
                        class="border-b border-gray-200 bg-gray-50 text-gray-600 uppercase dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400"
                    >
                        <tr>
                            <th
                                v-if="visibleColumns.info"
                                class="px-4 py-3 font-semibold"
                            >
                                Accessory Name & Category
                            </th>
                            <th
                                v-if="visibleColumns.sale_price"
                                class="px-4 py-3 font-semibold"
                            >
                                Sale Price
                            </th>
                            <th
                                v-if="visibleColumns.cost_price"
                                class="px-4 py-3 font-semibold"
                            >
                                Cost Price
                            </th>
                            <th
                                v-if="visibleColumns.stock"
                                class="px-4 py-3 text-center font-semibold"
                            >
                                Stock Level
                            </th>
                            <th
                                v-if="visibleColumns.actions"
                                class="px-4 py-3 text-right font-semibold"
                            >
                                Actions
                            </th>
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
                                No accessories found matching your filter
                                criteria.
                            </td>
                        </tr>

                        <tr
                            v-for="product in productList"
                            :key="product.id"
                            class="transition-colors hover:bg-gray-50/50 dark:hover:bg-gray-800/30"
                        >
                            <td v-if="visibleColumns.info" class="px-4 py-3">
                                <div
                                    class="font-bold text-gray-900 dark:text-white"
                                >
                                    {{ product.name }}
                                </div>
                                <div
                                    class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400"
                                >
                                    <span class="font-bold text-[#003B7D]">{{
                                        product.brand
                                    }}</span>
                                    &bull; {{ product.category }}
                                </div>
                            </td>

                            <td
                                v-if="visibleColumns.sale_price"
                                class="px-4 py-3 font-bold text-gray-900 dark:text-white"
                            >
                                {{ formatCurrency(product.sale_price) }}
                            </td>

                            <td
                                v-if="visibleColumns.cost_price"
                                class="px-4 py-3 text-gray-600 dark:text-gray-300"
                            >
                                {{ formatCurrency(product.cost_price) }}
                            </td>

                            <td
                                v-if="visibleColumns.stock"
                                class="px-4 py-3 text-center"
                            >
                                <span
                                    class="rounded-full px-2 py-0.5 text-[9px] font-bold uppercase"
                                    :class="
                                        product.stock_quantity <= 0
                                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400'
                                            : product.stock_quantity <=
                                                product.alert_quantity
                                              ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400'
                                              : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                    "
                                >
                                    {{
                                        product.stock_quantity <= 0
                                            ? 'Out of Stock'
                                            : product.stock_quantity <=
                                                product.alert_quantity
                                              ? 'Low Stock'
                                              : 'In Stock'
                                    }}
                                </span>
                                <div
                                    class="mt-1 text-xs font-bold text-gray-900 dark:text-white"
                                >
                                    {{ product.stock_quantity }} Pcs
                                </div>
                            </td>

                            <td
                                v-if="visibleColumns.actions"
                                class="px-4 py-3 text-right"
                            >
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <button
                                            type="button"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-2xs transition-all hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 focus:ring-2 focus:ring-[#003B7D]/20 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                                            title="Accessory Actions"
                                        >
                                            <MoreVertical class="h-4 w-4" />
                                            <span class="sr-only"
                                                >Accessory Actions</span
                                            >
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        class="w-52 rounded-xl border border-gray-200/80 bg-white/95 p-1.5 shadow-xl backdrop-blur-md dark:border-gray-800 dark:bg-gray-900/95"
                                    >
                                        <DropdownMenuItem
                                            @click="openQuickEditModal(product)"
                                            class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white"
                                        >
                                            <SlidersHorizontal
                                                class="h-4 w-4 shrink-0 text-amber-500 dark:text-amber-400"
                                            />
                                            <span
                                                >Quick Edit Price & Stock</span
                                            >
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            @click="
                                                openEditProductModal(product)
                                            "
                                            class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white"
                                        >
                                            <Edit3
                                                class="h-4 w-4 shrink-0 text-indigo-500 dark:text-indigo-400"
                                            />
                                            <span>Edit Accessory Details</span>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator class="my-1" />
                                        <DropdownMenuItem
                                            @click="deleteProduct(product)"
                                            class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"
                                        >
                                            <Trash2
                                                class="h-4 w-4 shrink-0 text-rose-600 dark:text-rose-400"
                                            />
                                            <span>Delete Accessory</span>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-gray-200 bg-gray-50/50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-gray-900/50"
            >
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <span class="font-semibold text-gray-700 dark:text-gray-300"
                        >Items per page:</span
                    >
                    <select
                        v-model="perPage"
                        @change="changePerPage()"
                        class="h-8 rounded-lg border border-gray-200 bg-white px-2.5 text-xs font-bold text-gray-800 shadow-2xs focus:ring-2 focus:ring-[#003B7D] focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    >
                        <option :value="10">10 per page</option>
                        <option :value="15">15 per page (default)</option>
                        <option :value="25">25 per page</option>
                        <option :value="50">50 per page</option>
                        <option :value="100">100 per page</option>
                        <option :value="250">250 per page</option>
                        <option :value="500">500 per page (All)</option>
                    </select>
                    <span class="text-gray-400">&bull;</span>
                    <span>
                        Total
                        {{ props.products?.total ?? productList.length }} items
                    </span>
                </div>

                <div
                    v-if="
                        props.products?.links && props.products.links.length > 3
                    "
                    class="flex items-center gap-1"
                >
                    <template
                        v-for="(link, index) in props.products.links"
                        :key="index"
                    >
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
                            :class="
                                link.active
                                    ? 'bg-[#003B7D] text-white'
                                    : 'text-gray-700 dark:text-gray-300'
                            "
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <Dialog v-model:open="isProductModalOpen">
            <DialogContent class="max-h-[90vh] max-w-xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">
                        {{
                            editingProduct
                                ? 'Edit Accessory'
                                : 'Add Accessories'
                        }}
                    </DialogTitle>
                    <DialogDescription class="text-xs text-gray-500">
                        Create an accessory with standard quantity stock.
                    </DialogDescription>
                </DialogHeader>

                <form
                    class="space-y-3 py-2 text-xs"
                    @submit.prevent="submitProductForm"
                >
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="accessory-name" class="font-medium"
                                >Accessory Name *</Label
                            >
                            <Input
                                id="accessory-name"
                                v-model="productForm.name"
                                required
                                placeholder="e.g. Fast Charger 20W"
                                class="h-9 text-xs"
                            />
                        </div>
                        <div class="space-y-1">
                            <Label for="accessory-brand" class="font-medium"
                                >Brand *</Label
                            >
                            <Input
                                id="accessory-brand"
                                v-model="productForm.brand"
                                required
                                placeholder="e.g. Anker"
                                class="h-9 text-xs"
                            />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <Label for="accessory-category" class="font-medium"
                            >Category *</Label
                        >
                        <Input
                            id="accessory-category"
                            v-model="productForm.category"
                            required
                            placeholder="e.g. Chargers"
                            class="h-9 text-xs"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label
                                for="accessory-sale-price"
                                class="font-medium"
                                >Sale Price (PKR) * <span class="text-[10px] text-slate-400 font-normal">(Max: 10 Lakh)</span></Label
                            >
                            <Input
                                id="accessory-sale-price"
                                v-model="productForm.sale_price"
                                type="number"
                                step="0.01"
                                min="0"
                                max="1000000"
                                required
                                placeholder="0"
                                class="h-9 text-xs font-bold"
                            />
                            <p v-if="productForm.errors.sale_price" class="text-[11px] font-bold text-red-500 mt-1">
                                {{ productForm.errors.sale_price }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <Label for="accessory-alert-qty" class="font-medium"
                                >Low Stock Alert Qty *</Label
                            >
                            <Input
                                id="accessory-alert-qty"
                                v-model.number="productForm.alert_quantity"
                                type="number"
                                min="0"
                                required
                                placeholder="5"
                                class="h-9 text-xs"
                            />
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-3 rounded-lg border border-gray-200 bg-gray-50 p-2.5 dark:border-gray-800 dark:bg-gray-800/40"
                    >
                        <div class="space-y-1">
                            <Label
                                for="accessory-cost-price"
                                class="font-medium"
                                >Cost Price (PKR) <span class="text-[10px] text-slate-400 font-normal">(Max: 10 Lakh)</span></Label
                            >
                            <Input
                                id="accessory-cost-price"
                                v-model="productForm.cost_price"
                                type="number"
                                step="0.01"
                                min="0"
                                max="1000000"
                                placeholder="0"
                                class="h-9 bg-white text-xs dark:bg-gray-900"
                            />
                            <p v-if="productForm.errors.cost_price" class="text-[11px] font-bold text-red-500 mt-1">
                                {{ productForm.errors.cost_price }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <Label for="accessory-stock-qty" class="font-medium"
                                >Initial Stock Qty</Label
                            >
                            <Input
                                id="accessory-stock-qty"
                                v-model.number="productForm.stock_quantity"
                                type="number"
                                min="0"
                                placeholder="0"
                                class="h-9 bg-white text-xs dark:bg-gray-900"
                            />
                        </div>
                    </div>

                    <DialogFooter class="pt-3">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="isProductModalOpen = false"
                            class="text-xs"
                        >
                            Cancel
                        </Button>
                        <Button
                            size="sm"
                            class="bg-[#003B7D] text-xs font-semibold text-white hover:bg-[#002b5c]"
                            :disabled="productForm.processing"
                        >
                            {{
                                editingProduct
                                    ? 'Save Changes'
                                    : 'Create Accessory'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="isQuickEditModalOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">
                        Quick Edit &bull; {{ quickEditTarget?.name }}
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Adjust sale price, cost price, and stock directly.
                    </DialogDescription>
                </DialogHeader>

                <form
                    @submit.prevent="submitQuickEditForm"
                    class="space-y-3 py-2 text-xs"
                >
                    <div class="space-y-1">
                        <Label class="font-medium">Sale Price (PKR) * <span class="text-[10px] text-slate-400 font-normal">(Max: 10 Lakh)</span></Label>
                        <Input
                            v-model="quickEditForm.sale_price"
                            type="number"
                            step="0.01"
                            min="0"
                            max="1000000"
                            class="h-9 text-xs font-bold"
                            required
                        />
                        <p v-if="quickEditForm.errors.sale_price" class="text-[11px] font-bold text-red-500 mt-1">
                            {{ quickEditForm.errors.sale_price }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <Label class="font-medium">Cost Price (PKR) <span class="text-[10px] text-slate-400 font-normal">(Max: 10 Lakh)</span></Label>
                        <Input
                            v-model="quickEditForm.cost_price"
                            type="number"
                            step="0.01"
                            min="0"
                            max="1000000"
                            class="h-9 text-xs"
                        />
                        <p v-if="quickEditForm.errors.cost_price" class="text-[11px] font-bold text-red-500 mt-1">
                            {{ quickEditForm.errors.cost_price }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <Label class="font-medium">Stock Quantity (Pcs)</Label>
                        <Input
                            v-model.number="quickEditForm.stock_quantity"
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
                            Update Accessory
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
