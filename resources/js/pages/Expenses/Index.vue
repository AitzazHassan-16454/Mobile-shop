<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    Calendar,
    Check,
    Coffee,
    DollarSign,
    Edit3,
    Filter,
    Plus,
    Receipt,
    RefreshCw,
    Search,
    Tag,
    Trash2,
    TrendingDown,
    UserCheck,
    Wallet,
    X,
    SlidersHorizontal,
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

interface User {
    id: number;
    name: string;
}

interface RegisterShift {
    id: number;
    status: string;
    created_at: string;
}

interface ShopExpense {
    id: number;
    register_shift_id?: number | null;
    user_id: number;
    category: string;
    amount: string | number;
    notes?: string | null;
    created_at: string;
    updated_at: string;
    user?: User | null;
    shift?: RegisterShift | null;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
    expenses: PaginatedData<ShopExpense>;
    categories: string[];
    activeShift?: RegisterShift | null;
    filters: {
        search: string;
        category_filter: string;
        date_filter: string;
        date_from?: string;
        date_to?: string;
    };
    stats: {
        today_total: number;
        today_count: number;
        month_total: number;
        month_count: number;
        all_time_total: number;
        all_time_count: number;
    };
    currentTeam?: { slug: string; name: string } | null;
}

const props = defineProps<Props>();

defineOptions({
    layout: (layoutProps: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam ? `/${layoutProps.currentTeam.slug}/dashboard` : '/dashboard',
            },
            {
                title: 'Expenses',
                href: layoutProps.currentTeam ? `/${layoutProps.currentTeam.slug}/expenses` : '/expenses',
            },
        ],
    }),
});

// Interactive Table Column Customizer State
const defaultVisibleColumns = {
    created_at: true,
    category: true,
    amount: true,
    user: true,
    notes: true,
    actions: true,
};

const visibleColumns = ref({ ...defaultVisibleColumns });

const expenseColumnLabels: Record<keyof typeof defaultVisibleColumns, string> = {
    created_at: 'Date & Time',
    category: 'Category',
    amount: 'Amount',
    user: 'Recorded By / Shift',
    notes: 'Notes / Description',
    actions: 'Actions',
};

const STORAGE_KEY = 'faizan_mobile_expenses_table_columns_v1';

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

const toggleExpenseColumn = (key: string) => {
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

// Filters State
const search = ref(props.filters.search || '');
const categoryFilter = ref(props.filters.category_filter || 'all');
const dateFilter = ref(props.filters.date_filter || 'all');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');

const applyFilters = () => {
    const routeName = props.currentTeam ? `/${props.currentTeam.slug}/expenses` : '/expenses';
    router.get(
        routeName,
        {
            search: search.value || undefined,
            category_filter: categoryFilter.value !== 'all' ? categoryFilter.value : undefined,
            date_filter: dateFilter.value !== 'all' ? dateFilter.value : undefined,
            date_from: dateFilter.value === 'custom' ? dateFrom.value : undefined,
            date_to: dateFilter.value === 'custom' ? dateTo.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

const resetFilters = () => {
    search.value = '';
    categoryFilter.value = 'all';
    dateFilter.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
};

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

// Create / Edit Modal State
const isModalOpen = ref(false);
const editingExpense = ref<ShopExpense | null>(null);
const isCustomCategory = ref(false);
const customCategoryInput = ref('');

const form = useForm({
    category: '',
    amount: '',
    notes: '',
    register_shift_id: null as number | null,
});

const openCreateModal = () => {
    editingExpense.value = null;
    isCustomCategory.value = false;
    customCategoryInput.value = '';
    form.reset();
    form.clearErrors();
    form.category = props.categories[0] || 'Miscellaneous';
    form.register_shift_id = props.activeShift?.id || null;
    isModalOpen.value = true;
};

const openEditModal = (expense: ShopExpense) => {
    editingExpense.value = expense;
    form.clearErrors();
    form.amount = String(expense.amount);
    form.notes = expense.notes || '';
    form.register_shift_id = expense.register_shift_id || null;

    if (props.categories.includes(expense.category)) {
        isCustomCategory.value = false;
        form.category = expense.category;
    } else {
        isCustomCategory.value = true;
        form.category = 'custom';
        customCategoryInput.value = expense.category;
    }
    isModalOpen.value = true;
};

const submitExpense = () => {
    const finalCategory = isCustomCategory.value ? customCategoryInput.value.trim() : form.category;
    if (!finalCategory) {
        form.setError('category', 'Category is required');
        return;
    }

    form.category = finalCategory;
    const routePrefix = props.currentTeam ? `/${props.currentTeam.slug}` : '';

    if (editingExpense.value) {
        form.put(`${routePrefix}/expenses/${editingExpense.value.id}`, {
            onSuccess: () => {
                isModalOpen.value = false;
                form.reset();
            },
        });
    } else {
        form.post(`${routePrefix}/expenses`, {
            onSuccess: () => {
                isModalOpen.value = false;
                form.reset();
            },
        });
    }
};

// Delete Confirmation
const isDeleteModalOpen = ref(false);
const deletingExpense = ref<ShopExpense | null>(null);
const deleteForm = useForm({});

const confirmDelete = (expense: ShopExpense) => {
    deletingExpense.value = expense;
    isDeleteModalOpen.value = true;
};

const handleDelete = () => {
    if (!deletingExpense.value) return;
    const routePrefix = props.currentTeam ? `/${props.currentTeam.slug}` : '';
    deleteForm.delete(`${routePrefix}/expenses/${deletingExpense.value.id}`, {
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            deletingExpense.value = null;
        },
    });
};

// Formatting helpers
const formatMoney = (val: number | string) => {
    const num = Number(val) || 0;
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: 2,
    })
        .format(num)
        .replace('PKR', 'Rs.');
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Category badge colors
const getCategoryColor = (cat: string) => {
    const lower = cat.toLowerCase();
    if (lower.includes('tea') || lower.includes('refreshment')) {
        return 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800';
    }
    if (lower.includes('util') || lower.includes('electric') || lower.includes('water')) {
        return 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800';
    }
    if (lower.includes('rent')) {
        return 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800';
    }
    if (lower.includes('salary') || lower.includes('staff')) {
        return 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800';
    }
    if (lower.includes('clean') || lower.includes('mainten')) {
        return 'bg-teal-100 text-teal-800 border-teal-200 dark:bg-teal-950/40 dark:text-teal-300 dark:border-teal-800';
    }
    if (lower.includes('repair') || lower.includes('tool')) {
        return 'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-950/40 dark:text-orange-300 dark:border-orange-800';
    }
    return 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700';
};
</script>

<template>
    <Head title="Expenses Management" />

    <div class="space-y-6 p-4 md:p-6">
        <!-- Header & Action Button -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <Receipt class="h-6 w-6 text-[#003B7D]" />
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Expenses Management
                    </h1>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Record, categorize, and track daily shop operating expenses and cash drawer payouts.
                </p>
            </div>
            <Button
                @click="openCreateModal"
                class="bg-[#003B7D] text-white hover:bg-[#002a59] gap-2 font-medium cursor-pointer"
            >
                <Plus class="h-4 w-4" />
                <span>Record New Expense</span>
            </Button>
        </div>

        <!-- Active Shift Status Banner -->
        <div
            v-if="props.activeShift"
            class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/60 p-3.5 text-xs text-emerald-900 dark:border-emerald-800/60 dark:bg-emerald-950/30 dark:text-emerald-200"
        >
            <div class="flex items-center gap-2.5">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                </span>
                <span class="font-medium">
                    Active Cash Register Shift #{{ props.activeShift.id }} is currently OPEN. New expenses will automatically deduct from this shift cash drawer.
                </span>
            </div>
        </div>
        <div
            v-else
            class="flex items-center gap-2.5 rounded-xl border border-amber-200 bg-amber-50/60 p-3.5 text-xs text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-200"
        >
            <AlertCircle class="h-4 w-4 text-amber-600 dark:text-amber-400 shrink-0" />
            <span>
                No active cash shift is open for your account. Expenses will be recorded as general shop expenses.
            </span>
        </div>

        <!-- Summary Statistics Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <!-- Today's Expenses -->
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Today's Expenses</span>
                    <div class="rounded-lg bg-rose-50 p-2 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400">
                        <TrendingDown class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ formatMoney(props.stats.today_total) }}
                    </p>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                        {{ props.stats.today_count }} {{ props.stats.today_count === 1 ? 'expense' : 'expenses' }} recorded today
                    </p>
                </div>
            </div>

            <!-- This Month's Expenses -->
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">This Month's Expenses</span>
                    <div class="rounded-lg bg-blue-50 p-2 text-[#003B7D] dark:bg-blue-950/40 dark:text-blue-400">
                        <Calendar class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ formatMoney(props.stats.month_total) }}
                    </p>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                        {{ props.stats.month_count }} {{ props.stats.month_count === 1 ? 'expense' : 'expenses' }} this month
                    </p>
                </div>
            </div>

            <!-- All-Time Expenses -->
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">All-Time Total</span>
                    <div class="rounded-lg bg-emerald-50 p-2 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                        <Wallet class="h-4 w-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ formatMoney(props.stats.all_time_total) }}
                    </p>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                        {{ props.stats.all_time_count }} total entries in database
                    </p>
                </div>
            </div>
        </div>

        <!-- Filter Controls Toolbar -->
        <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-2xs sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <!-- Search Input -->
                <div class="relative min-w-[220px] flex-1">
                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                    <Input
                        v-model="search"
                        type="text"
                        placeholder="Search category, notes, user..."
                        class="pl-9 text-xs"
                    />
                </div>

                <!-- Category Filter -->
                <div class="min-w-[170px]">
                    <select
                        v-model="categoryFilter"
                        @change="applyFilters"
                        class="h-9 w-full rounded-md border border-gray-200 bg-white px-3 text-xs font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-1 focus:ring-[#003B7D]"
                    >
                        <option value="all">All Categories</option>
                        <option v-for="cat in props.categories" :key="cat" :value="cat">
                            {{ cat }}
                        </option>
                    </select>
                </div>

                <!-- Date Range Filter -->
                <div class="min-w-[150px]">
                    <select
                        v-model="dateFilter"
                        @change="applyFilters"
                        class="h-9 w-full rounded-md border border-gray-200 bg-white px-3 text-xs font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-1 focus:ring-[#003B7D]"
                    >
                        <option value="all">All Time</option>
                        <option value="today">Today</option>
                        <option value="this_week">This Week</option>
                        <option value="this_month">This Month</option>
                        <option value="custom">Custom Date Range</option>
                    </select>
                </div>

                <!-- Custom Date Inputs -->
                <template v-if="dateFilter === 'custom'">
                    <Input v-model="dateFrom" type="date" class="h-9 text-xs" @change="applyFilters" />
                    <span class="text-xs text-gray-400">to</span>
                    <Input v-model="dateTo" type="date" class="h-9 text-xs" @change="applyFilters" />
                </template>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    @click="resetFilters"
                    class="h-9 gap-1.5 text-xs text-gray-600 dark:text-gray-300"
                >
                    <RefreshCw class="h-3.5 w-3.5" />
                    <span>Reset</span>
                </Button>

                <!-- Table Columns Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-9 gap-1.5 text-xs text-gray-700 dark:text-gray-300"
                        >
                            <SlidersHorizontal class="h-3.5 w-3.5 text-[#003B7D]" />
                            <span>Columns</span>
                            <span class="ml-1 rounded-full bg-[#003B7D]/10 px-1.5 py-0.5 text-[10px] font-bold text-[#003B7D]">
                                {{ activeColumnCount }}/6
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
                            v-for="(label, key) in expenseColumnLabels"
                            :key="key"
                            @click.stop="toggleExpenseColumn(key)"
                            class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer select-none transition-colors"
                        >
                            <span>{{ label }}</span>
                            <input
                                type="checkbox"
                                :checked="visibleColumns[key as keyof typeof visibleColumns]"
                                @change="toggleExpenseColumn(key)"
                                @click.stop
                                class="h-4 w-4 rounded border-gray-300 text-[#003B7D] focus:ring-[#003B7D] cursor-pointer"
                            />
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Expenses Table -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xs dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-gray-200 bg-gray-50/70 text-gray-600 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                        <tr>
                            <th v-if="visibleColumns.created_at" class="px-4 py-3 font-semibold">Date & Time</th>
                            <th v-if="visibleColumns.category" class="px-4 py-3 font-semibold">Category</th>
                            <th v-if="visibleColumns.amount" class="px-4 py-3 font-semibold">Amount</th>
                            <th v-if="visibleColumns.user" class="px-4 py-3 font-semibold">Recorded By / Shift</th>
                            <th v-if="visibleColumns.notes" class="px-4 py-3 font-semibold">Notes / Description</th>
                            <th v-if="visibleColumns.actions" class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        <tr
                            v-for="expense in props.expenses.data"
                            :key="expense.id"
                            class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40 transition-colors"
                        >
                            <!-- Date & Time -->
                            <td v-if="visibleColumns.created_at" class="px-4 py-3.5 text-gray-600 dark:text-gray-300 font-mono text-[11px]">
                                {{ formatDate(expense.created_at) }}
                            </td>

                            <!-- Category -->
                            <td v-if="visibleColumns.category" class="px-4 py-3.5">
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold',
                                        getCategoryColor(expense.category)
                                    ]"
                                >
                                    <Tag class="h-3 w-3" />
                                    <span>{{ expense.category }}</span>
                                </span>
                            </td>

                            <!-- Amount -->
                            <td v-if="visibleColumns.amount" class="px-4 py-3.5 font-bold text-rose-600 dark:text-rose-400 text-sm">
                                {{ formatMoney(expense.amount) }}
                            </td>

                            <!-- Recorded By & Shift -->
                            <td v-if="visibleColumns.user" class="px-4 py-3.5 text-gray-700 dark:text-gray-300">
                                <div class="flex items-center gap-1.5">
                                    <UserCheck class="h-3.5 w-3.5 text-gray-400" />
                                    <span>{{ expense.user?.name || 'Staff' }}</span>
                                    <span
                                        v-if="expense.register_shift_id"
                                        class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                                        title="Linked Shift"
                                    >
                                        Shift #{{ expense.register_shift_id }}
                                    </span>
                                </div>
                            </td>

                            <!-- Notes -->
                            <td v-if="visibleColumns.notes" class="px-4 py-3.5 text-gray-600 dark:text-gray-300 max-w-xs truncate">
                                {{ expense.notes || '-' }}
                            </td>

                            <!-- Actions -->
                            <td v-if="visibleColumns.actions" class="px-4 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="openEditModal(expense)"
                                        class="h-7 w-7 p-0 text-gray-500 hover:text-blue-600 dark:hover:text-blue-400"
                                        title="Edit Expense"
                                    >
                                        <Edit3 class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="confirmDelete(expense)"
                                        class="h-7 w-7 p-0 text-gray-500 hover:text-rose-600 dark:hover:text-rose-400"
                                        title="Delete Expense"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="props.expenses.data.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                <Receipt class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" />
                                <p class="mt-2 text-xs font-medium">No expenses found</p>
                                <p class="text-[11px] text-gray-400">
                                    Try adjusting your search query or record a new expense.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="props.expenses.links && props.expenses.links.length > 3"
                class="flex items-center justify-between border-t border-gray-200 bg-gray-50/50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900/50"
            >
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Showing <span class="font-semibold text-gray-700 dark:text-gray-300">{{ props.expenses.data.length }}</span> of <span class="font-semibold text-gray-700 dark:text-gray-300">{{ props.expenses.total }}</span> expenses
                </div>
                <div class="flex items-center gap-1">
                    <component
                        :is="link.url ? 'a' : 'span'"
                        v-for="(link, i) in props.expenses.links"
                        :key="i"
                        :href="link.url || undefined"
                        v-html="link.label"
                        :class="[
                            'px-2.5 py-1 text-xs rounded-md transition-colors',
                            link.active
                                ? 'bg-[#003B7D] text-white font-bold'
                                : link.url
                                  ? 'text-gray-700 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-800'
                                  : 'text-gray-400 cursor-not-allowed'
                        ]"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Create / Edit Expense Modal -->
    <Dialog v-model:open="isModalOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-base font-bold text-gray-900 dark:text-white">
                    <Receipt class="h-5 w-5 text-[#003B7D]" />
                    <span>{{ editingExpense ? 'Edit Expense Record' : 'Record New Expense' }}</span>
                </DialogTitle>
                <DialogDescription class="text-xs text-gray-500 dark:text-gray-400">
                    {{ editingExpense ? 'Update category, amount or notes for this expense entry.' : 'Enter details for shop expense payment or cash register payout.' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitExpense" class="space-y-4 py-2">
                <!-- Shift Link Indicator -->
                <div
                    v-if="props.activeShift && !editingExpense"
                    class="rounded-lg bg-emerald-50 p-2.5 text-xs text-emerald-800 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800"
                >
                    ✓ Linked to active shift #{{ props.activeShift.id }}
                </div>

                <!-- Category Selection -->
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                        Expense Category <span class="text-rose-500">*</span>
                    </label>
                    <select
                        v-if="!isCustomCategory"
                        v-model="form.category"
                        class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-1 focus:ring-[#003B7D]"
                    >
                        <option v-for="cat in props.categories" :key="cat" :value="cat">
                            {{ cat }}
                        </option>
                        <option value="custom">+ Add Custom Category...</option>
                    </select>
                    <div v-else class="flex items-center gap-2">
                        <Input
                            v-model="customCategoryInput"
                            type="text"
                            placeholder="Type new category name..."
                            class="text-xs flex-1"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="isCustomCategory = false; form.category = props.categories[0] || ''"
                            class="text-xs text-gray-500 hover:text-gray-800"
                        >
                            Cancel
                        </Button>
                    </div>
                    <span v-if="form.errors.category" class="text-[11px] font-medium text-rose-500">
                        {{ form.errors.category }}
                    </span>
                </div>

                <!-- Amount Input -->
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                        Expense Amount (PKR) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-xs font-bold text-gray-500">Rs.</span>
                        <Input
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            class="pl-10 text-xs font-mono font-bold"
                            required
                        />
                    </div>
                    <span v-if="form.errors.amount" class="text-[11px] font-medium text-rose-500">
                        {{ form.errors.amount }}
                    </span>
                </div>

                <!-- Notes / Description -->
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                        Notes / Description
                    </label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        placeholder="E.g. Electricity bill paid for September, Tea for guests..."
                        class="w-full rounded-md border border-gray-200 bg-white p-2.5 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-1 focus:ring-[#003B7D]"
                    ></textarea>
                    <span v-if="form.errors.notes" class="text-[11px] font-medium text-rose-500">
                        {{ form.errors.notes }}
                    </span>
                </div>

                <DialogFooter class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isModalOpen = false"
                        class="text-xs"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="bg-[#003B7D] text-white hover:bg-[#002a59] text-xs font-medium cursor-pointer"
                    >
                        <span v-if="form.processing">Saving...</span>
                        <span v-else>{{ editingExpense ? 'Update Expense' : 'Save Expense' }}</span>
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Delete Confirmation Modal -->
    <Dialog v-model:open="isDeleteModalOpen">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle class="text-base font-bold text-rose-600 dark:text-rose-400 flex items-center gap-2">
                    <Trash2 class="h-5 w-5" />
                    <span>Delete Expense Record</span>
                </DialogTitle>
                <DialogDescription class="text-xs text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete the expense entry of
                    <strong class="text-gray-900 dark:text-white">{{ deletingExpense ? formatMoney(deletingExpense.amount) : '' }}</strong>
                    ({{ deletingExpense?.category }})? This action cannot be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="pt-3">
                <Button
                    type="button"
                    variant="outline"
                    @click="isDeleteModalOpen = false"
                    class="text-xs"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="deleteForm.processing"
                    @click="handleDelete"
                    class="text-xs cursor-pointer"
                >
                    <span v-if="deleteForm.processing">Deleting...</span>
                    <span v-else>Confirm Delete</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
