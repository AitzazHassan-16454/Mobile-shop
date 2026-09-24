<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Boxes,
    CheckCircle2,
    Edit2,
    Layers,
    Plus,
    Search,
    SlidersHorizontal,
    Tags,
    Trash2,
    X,
    XCircle,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';
import type { Team } from '@/types';

const { confirm } = useConfirm();
const page = usePage();

// Interactive Table Column Customizer State
const defaultVisibleColumns = {
    name: true,
    slug: true,
    products_count: true,
    description: true,
    is_active: true,
    actions: true,
};

const visibleColumns = ref({ ...defaultVisibleColumns });

const categoryColumnLabels: Record<keyof typeof defaultVisibleColumns, string> =
    {
        name: 'Category Name',
        slug: 'Slug',
        products_count: 'Total Products',
        description: 'Description',
        is_active: 'Status',
        actions: 'Actions',
    };

const STORAGE_KEY = 'faizan_mobile_categories_table_columns_v1';
const PER_PAGE_STORAGE_KEY = 'faizan_mobile_categories_per_page_v1';

onMounted(() => {
    try {
        const savedCols = localStorage.getItem(STORAGE_KEY);
        if (savedCols) {
            visibleColumns.value = {
                ...defaultVisibleColumns,
                ...JSON.parse(savedCols),
            };
        }
        const savedPerPage = localStorage.getItem(PER_PAGE_STORAGE_KEY);
        if (savedPerPage && Number(savedPerPage) !== perPage.value) {
            perPage.value = Number(savedPerPage);
            triggerSearch();
        }
    } catch (e) {
        console.error(e);
    }
});

const toggleCategoryColumn = (key: string) => {
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

interface CategoryItem {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    products_count: number;
    created_at: string;
}

interface Props {
    categories: {
        data: CategoryItem[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search: string;
        status_filter: string;
        per_page: number;
    };
    stats: {
        total_categories: number;
        active_categories: number;
        total_products_categorized: number;
    };
}

const props = defineProps<Props>();

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam
                    ? `/${layoutProps.currentTeam.slug}/dashboard`
                    : '/',
            },
            {
                title: 'Categories',
                href: layoutProps.currentTeam
                    ? `/${layoutProps.currentTeam.slug}/categories`
                    : '/categories',
            },
        ],
    }),
});

const currentTeamSlug = computed(
    () => (page.props.currentTeam as Team | undefined)?.slug || '',
);

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status_filter || 'all');
const perPage = ref(props.filters.per_page || 15);

const triggerSearch = () => {
    try {
        localStorage.setItem(PER_PAGE_STORAGE_KEY, String(perPage.value));
    } catch (e) {
        console.error(e);
    }

    router.get(
        `/${currentTeamSlug.value}/categories`,
        {
            search: search.value,
            status_filter: statusFilter.value,
            per_page: perPage.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

watch([statusFilter, perPage], () => {
    triggerSearch();
});

let searchTimeout: ReturnType<typeof setTimeout>;
const onSearchInput = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        triggerSearch();
    }, 300);
};

// Modals
const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingCategory = ref<CategoryItem | null>(null);

const createForm = useForm({
    name: '',
    description: '',
    is_active: true,
});

const editForm = useForm({
    name: '',
    description: '',
    is_active: true,
});

const openCreateModal = () => {
    createForm.reset();
    createForm.clearErrors();
    showCreateModal.value = true;
};

const submitCreate = () => {
    createForm.post(`/${currentTeamSlug.value}/categories`, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

const openEditModal = (category: CategoryItem) => {
    editingCategory.value = category;
    editForm.name = category.name;
    editForm.description = category.description || '';
    editForm.is_active = category.is_active;
    editForm.clearErrors();
    showEditModal.value = true;
};

const submitEdit = () => {
    if (!editingCategory.value) return;
    editForm.put(
        `/${currentTeamSlug.value}/categories/${editingCategory.value.id}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                showEditModal.value = false;
                editingCategory.value = null;
            },
        },
    );
};

const toggleStatus = (category: CategoryItem) => {
    router.patch(
        `/${currentTeamSlug.value}/categories/${category.id}/toggle`,
        {},
        { preserveScroll: true },
    );
};

const deleteCategory = async (category: CategoryItem) => {
    const isConfirmed = await confirm({
        title: `Delete Category "${category.name}"?`,
        message:
            category.products_count > 0
                ? `Warning: This category currently has ${category.products_count} product(s) assigned to it. Are you sure you want to delete it?`
                : 'Are you sure you want to delete this category? This action cannot be undone.',
        confirmText: 'Delete Category',
        cancelText: 'Cancel',
        variant: 'destructive',
    });

    if (isConfirmed) {
        router.delete(`/${currentTeamSlug.value}/categories/${category.id}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Categories Management" />

    <div class="space-y-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    Product Categories
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Organize your mobile shop product catalog with custom
                    categories.
                </p>
            </div>
            <Button
                @click="openCreateModal"
                class="bg-blue-600 font-medium text-white shadow-sm hover:bg-blue-700"
            >
                <Plus class="mr-2 h-4 w-4" />
                Add New Category
            </Button>
        </div>

        <!-- Summary Stats Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >Total Categories</span
                    >
                    <div
                        class="rounded-lg bg-blue-50 p-2.5 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400"
                    >
                        <Tags class="h-5 w-5" />
                    </div>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span
                        class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                    >
                        {{ stats.total_categories }}
                    </span>
                    <span class="text-xs text-slate-500"
                        >Defined catalog groups</span
                    >
                </div>
            </div>

            <div
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >Active Categories</span
                    >
                    <div
                        class="rounded-lg bg-emerald-50 p-2.5 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400"
                    >
                        <CheckCircle2 class="h-5 w-5" />
                    </div>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span
                        class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                    >
                        {{ stats.active_categories }}
                    </span>
                    <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400"
                        >Ready for POS & Sales</span
                    >
                </div>
            </div>

            <div
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >Categorized Products</span
                    >
                    <div
                        class="rounded-lg bg-indigo-50 p-2.5 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400"
                    >
                        <Boxes class="h-5 w-5" />
                    </div>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span
                        class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                    >
                        {{ stats.total_products_categorized }}
                    </span>
                    <span class="text-xs text-slate-500"
                        >Products assigned</span
                    >
                </div>
            </div>
        </div>

        <!-- Filters & Table Customizer Bar -->
        <div
            class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <!-- Search & Filters -->
                <div
                    class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center"
                >
                    <div class="relative flex-1">
                        <Search
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                        />
                        <Input
                            v-model="search"
                            @input="onSearchInput"
                            placeholder="Search category name or description..."
                            class="pl-9 text-sm"
                        />
                    </div>
                    <select
                        v-model="statusFilter"
                        class="h-9 rounded-md border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
                    >
                        <option value="all">All Statuses</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>
                </div>

                <!-- Customizer Dropdown & Per-Page Controls -->
                <div class="flex items-center gap-3">
                    <!-- Column Customizer Dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-9 border-slate-200 text-xs font-medium dark:border-slate-800"
                            >
                                <SlidersHorizontal class="mr-2 h-3.5 w-3.5" />
                                Columns ({{ activeColumnCount }})
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56 p-2">
                            <DropdownMenuLabel
                                class="flex items-center justify-between text-xs font-semibold"
                            >
                                <span>Table Columns</span>
                                <button
                                    @click="resetColumns"
                                    class="text-[11px] font-normal text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    Reset All
                                </button>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <div class="space-y-1 py-1">
                                <label
                                    v-for="(label, key) in categoryColumnLabels"
                                    :key="key"
                                    class="flex cursor-pointer items-center justify-between rounded px-2 py-1 text-xs transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                                >
                                    <span
                                        class="text-slate-700 dark:text-slate-300"
                                        >{{ label }}</span
                                    >
                                    <input
                                        type="checkbox"
                                        :checked="
                                            visibleColumns[
                                                key as keyof typeof defaultVisibleColumns
                                            ]
                                        "
                                        @change="toggleCategoryColumn(key)"
                                        class="h-3.5 w-3.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-700"
                                    />
                                </label>
                            </div>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Per-Page Selection -->
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-slate-500"
                            >Show:</span
                        >
                        <select
                            v-model="perPage"
                            class="h-9 rounded-md border border-slate-200 bg-white px-2 text-xs font-medium text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
                        >
                            <option :value="10">10</option>
                            <option :value="15">15</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                            <option :value="250">250</option>
                            <option :value="500">500 / All</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Data Card -->
        <div
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="bg-slate-50 text-xs tracking-wider text-slate-500 uppercase dark:bg-slate-800/50 dark:text-slate-400"
                    >
                        <tr>
                            <th
                                v-if="visibleColumns.name"
                                class="px-6 py-3.5 font-semibold"
                            >
                                Category Name
                            </th>
                            <th
                                v-if="visibleColumns.slug"
                                class="px-6 py-3.5 font-semibold"
                            >
                                Slug
                            </th>
                            <th
                                v-if="visibleColumns.products_count"
                                class="px-6 py-3.5 font-semibold"
                            >
                                Products
                            </th>
                            <th
                                v-if="visibleColumns.description"
                                class="px-6 py-3.5 font-semibold"
                            >
                                Description
                            </th>
                            <th
                                v-if="visibleColumns.is_active"
                                class="px-6 py-3.5 font-semibold"
                            >
                                Status
                            </th>
                            <th
                                v-if="visibleColumns.actions"
                                class="px-6 py-3.5 text-right font-semibold"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-200 dark:divide-slate-800"
                    >
                        <tr
                            v-for="category in categories.data"
                            :key="category.id"
                            class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                        >
                            <!-- Name -->
                            <td
                                v-if="visibleColumns.name"
                                class="px-6 py-4 font-semibold text-slate-900 dark:text-slate-100"
                            >
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100/70 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400"
                                    >
                                        <Layers class="h-4 w-4" />
                                    </div>
                                    <span>{{ category.name }}</span>
                                </div>
                            </td>

                            <!-- Slug -->
                            <td
                                v-if="visibleColumns.slug"
                                class="px-6 py-4 font-mono text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ category.slug }}
                            </td>

                            <!-- Total Products -->
                            <td
                                v-if="visibleColumns.products_count"
                                class="px-6 py-4"
                            >
                                <Badge
                                    variant="outline"
                                    class="border-blue-200 bg-blue-50/50 font-semibold text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-400"
                                >
                                    {{ category.products_count }} products
                                </Badge>
                            </td>

                            <!-- Description -->
                            <td
                                v-if="visibleColumns.description"
                                class="max-w-xs truncate px-6 py-4 text-xs text-slate-600 dark:text-slate-400"
                            >
                                {{ category.description || '—' }}
                            </td>

                            <!-- Status -->
                            <td
                                v-if="visibleColumns.is_active"
                                class="px-6 py-4"
                            >
                                <button
                                    @click="toggleStatus(category)"
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium transition-transform active:scale-95"
                                    :class="
                                        category.is_active
                                            ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-950/70 dark:text-emerald-300'
                                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
                                    "
                                >
                                    <CheckCircle2
                                        v-if="category.is_active"
                                        class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400"
                                    />
                                    <XCircle
                                        v-else
                                        class="h-3.5 w-3.5 text-slate-400"
                                    />
                                    {{
                                        category.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </button>
                            </td>

                            <!-- Actions -->
                            <td
                                v-if="visibleColumns.actions"
                                class="px-6 py-4 text-right"
                            >
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="openEditModal(category)"
                                        title="Edit Category"
                                        class="h-8 w-8 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800"
                                    >
                                        <Edit2 class="h-4 w-4" />
                                    </Button>

                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="deleteCategory(category)"
                                        title="Delete Category"
                                        class="h-8 w-8 text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-950/50"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="categories.data.length === 0">
                            <td
                                :colspan="activeColumnCount"
                                class="px-6 py-12 text-center"
                            >
                                <div
                                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800"
                                >
                                    <Tags class="h-6 w-6 text-slate-400" />
                                </div>
                                <h3
                                    class="mt-3 text-sm font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    No categories found
                                </h3>
                                <p
                                    class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Try adjusting your search query or status
                                    filter.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div
                v-if="categories.total > 0"
                class="flex flex-col items-center justify-between gap-4 border-t border-slate-200 bg-slate-50/50 px-6 py-3.5 sm:flex-row dark:border-slate-800 dark:bg-slate-800/20"
            >
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    Showing
                    <span
                        class="font-medium text-slate-900 dark:text-slate-200"
                        >{{ categories.data.length }}</span
                    >
                    of
                    <span
                        class="font-medium text-slate-900 dark:text-slate-200"
                        >{{ categories.total }}</span
                    >
                    categories
                </div>

                <div class="flex items-center gap-1.5">
                    <template
                        v-for="(link, idx) in categories.links"
                        :key="idx"
                    >
                        <Button
                            v-if="link.url"
                            variant="outline"
                            size="sm"
                            :class="[
                                'h-8 px-3 text-xs',
                                link.active
                                    ? 'border-blue-600 bg-blue-600 font-semibold text-white hover:bg-blue-700'
                                    : 'border-slate-200 text-slate-700 dark:border-slate-800 dark:text-slate-300',
                            ]"
                            @click="
                                router.get(
                                    link.url,
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                    },
                                )
                            "
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="px-2 text-xs text-slate-400"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- Create Category Modal -->
        <Dialog :open="showCreateModal" @update:open="showCreateModal = $event">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle class="text-lg font-bold"
                        >Add New Category</DialogTitle
                    >
                    <DialogDescription class="text-xs">
                        Create a product category to organize smartphones,
                        accessories, or parts.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitCreate" class="space-y-4 py-2">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold"
                            >Category Name *</Label
                        >
                        <Input
                            v-model="createForm.name"
                            placeholder="e.g. Smartphones, Accessories, Chargers"
                            required
                        />
                        <p
                            v-if="createForm.errors.name"
                            class="text-xs text-rose-500"
                        >
                            {{ createForm.errors.name }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Description</Label>
                        <textarea
                            v-model="createForm.description"
                            rows="3"
                            placeholder="Brief description of this category..."
                            class="w-full rounded-md border border-slate-200 bg-white p-2 text-xs focus:border-blue-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                        ></textarea>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input
                            type="checkbox"
                            id="create_is_active"
                            v-model="createForm.is_active"
                            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-700"
                        />
                        <label
                            for="create_is_active"
                            class="cursor-pointer text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Active Category (Visible during product creation and
                            sales)
                        </label>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showCreateModal = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="createForm.processing"
                            class="bg-blue-600 text-white hover:bg-blue-700"
                        >
                            Create Category
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit Category Modal -->
        <Dialog :open="showEditModal" @update:open="showEditModal = $event">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle class="text-lg font-bold"
                        >Edit Category</DialogTitle
                    >
                    <DialogDescription class="text-xs">
                        Update category details.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitEdit" class="space-y-4 py-2">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold"
                            >Category Name *</Label
                        >
                        <Input
                            v-model="editForm.name"
                            placeholder="e.g. Smartphones, Accessories"
                            required
                        />
                        <p
                            v-if="editForm.errors.name"
                            class="text-xs text-rose-500"
                        >
                            {{ editForm.errors.name }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Description</Label>
                        <textarea
                            v-model="editForm.description"
                            rows="3"
                            placeholder="Brief description..."
                            class="w-full rounded-md border border-slate-200 bg-white p-2 text-xs focus:border-blue-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                        ></textarea>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input
                            type="checkbox"
                            id="edit_is_active"
                            v-model="editForm.is_active"
                            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-700"
                        />
                        <label
                            for="edit_is_active"
                            class="cursor-pointer text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Active Category
                        </label>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showEditModal = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="editForm.processing"
                            class="bg-blue-600 text-white hover:bg-blue-700"
                        >
                            Save Changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
