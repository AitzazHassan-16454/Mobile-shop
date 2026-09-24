<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    BadgePercent,
    Calendar,
    Check,
    CheckCircle2,
    DollarSign,
    Edit3,
    Hash,
    Plus,
    RefreshCw,
    Search,
    SlidersHorizontal,
    Tag,
    Trash2,
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
const page = usePage();

interface DiscountItem {
    id: number;
    name: string;
    code?: string | null;
    type: 'percentage' | 'fixed';
    value: number | string;
    min_purchase_amount?: number | string | null;
    max_discount_amount?: number | string | null;
    start_date?: string | null;
    end_date?: string | null;
    usage_limit?: number | null;
    used_count: number;
    is_active: boolean;
    notes?: string | null;
    created_at?: string;
}

interface SummaryStats {
    total_discounts: number;
    active_discounts: number;
    total_redemptions: number;
    max_offer_value: number;
}

interface Filters {
    search?: string;
    type?: string;
    status?: string;
    per_page?: number;
}

const props = defineProps<{
    discounts: {
        data: DiscountItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    filters?: Filters;
    summary?: SummaryStats;
}>();

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam ? '/dashboard' : '/',
            },
            {
                title: 'Discounts & Offers',
                href: layoutProps.currentTeam
                    ? `/${layoutProps.currentTeam.slug}/discounts`
                    : '/discounts',
            },
        ],
    }),
});

const currentTeamSlug = computed(
    () => (page.props.currentTeam as Team | undefined)?.slug || 'default',
);

function getTeamUrl(path: string) {
    return currentTeamSlug.value ? `/${currentTeamSlug.value}${path}` : path;
}

// Table Column Customizer State
const defaultVisibleColumns = {
    rule: true,
    type_value: true,
    min_purchase: true,
    validity: true,
    redemptions: true,
    status: true,
    actions: true,
};

const visibleColumns = ref({ ...defaultVisibleColumns });

const discountColumnLabels: Record<keyof typeof defaultVisibleColumns, string> =
    {
        rule: 'Discount Rule / Code',
        type_value: 'Type & Value',
        min_purchase: 'Min Purchase / Cap',
        validity: 'Validity Period',
        redemptions: 'Redemptions / Limit',
        status: 'Status',
        actions: 'Actions',
    };

const STORAGE_KEY = 'faizan_mobile_discounts_table_columns_v1';

onMounted(() => {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            visibleColumns.value = {
                ...defaultVisibleColumns,
                ...JSON.parse(saved),
            };
        }
    } catch (e) {
        console.error(e);
    }
});

const toggleDiscountColumn = (key: string) => {
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

// Search, Filters & Pagination State
const search = ref(props.filters?.search || '');
const selectedType = ref(props.filters?.type || 'all');
const selectedStatus = ref(props.filters?.status || 'all');
const perPage = ref<number>(Number(props.filters?.per_page) || 15);

const PER_PAGE_STORAGE_KEY = 'faizan_mobile_discounts_per_page_v1';

let searchDebounce: ReturnType<typeof setTimeout> | null = null;

const applyFilters = () => {
    router.get(
        getTeamUrl('/discounts'),
        {
            search: search.value || undefined,
            type: selectedType.value !== 'all' ? selectedType.value : undefined,
            status:
                selectedStatus.value !== 'all'
                    ? selectedStatus.value
                    : undefined,
            per_page: perPage.value !== 15 ? perPage.value : undefined,
        },
        { preserveState: true, replace: true },
    );
};

const changePerPage = (val?: number) => {
    if (val) perPage.value = val;
    try {
        localStorage.setItem(PER_PAGE_STORAGE_KEY, perPage.value.toString());
    } catch (e) {
        console.error(e);
    }
    applyFilters();
};

watch(search, () => {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        applyFilters();
    }, 350);
});

watch([selectedType, selectedStatus], () => {
    applyFilters();
});

// Create & Edit Modals State
const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const editingDiscountId = ref<number | null>(null);

const form = useForm({
    name: '',
    code: '',
    type: 'percentage',
    value: '',
    min_purchase_amount: '',
    max_discount_amount: '',
    start_date: '',
    end_date: '',
    usage_limit: '',
    is_active: true,
    notes: '',
});

function openCreateModal() {
    form.reset();
    form.clearErrors();
    form.type = 'percentage';
    form.is_active = true;
    isCreateDialogOpen.value = true;
}

function handleCreateDiscount() {
    form.post(getTeamUrl('/discounts'), {
        onSuccess: () => {
            isCreateDialogOpen.value = false;
            form.reset();
        },
    });
}

function openEditModal(item: DiscountItem) {
    editingDiscountId.value = item.id;
    form.name = item.name;
    form.code = item.code || '';
    form.type = item.type;
    form.value = String(item.value);
    form.min_purchase_amount = item.min_purchase_amount
        ? String(item.min_purchase_amount)
        : '';
    form.max_discount_amount = item.max_discount_amount
        ? String(item.max_discount_amount)
        : '';
    form.start_date = item.start_date || '';
    form.end_date = item.end_date || '';
    form.usage_limit = item.usage_limit ? String(item.usage_limit) : '';
    form.is_active = item.is_active;
    form.notes = item.notes || '';
    form.clearErrors();
    isEditDialogOpen.value = true;
}

function handleUpdateDiscount() {
    if (!editingDiscountId.value) return;

    form.put(getTeamUrl(`/discounts/${editingDiscountId.value}`), {
        onSuccess: () => {
            isEditDialogOpen.value = false;
            editingDiscountId.value = null;
            form.reset();
        },
    });
}

function toggleStatus(item: DiscountItem) {
    router.patch(
        getTeamUrl(`/discounts/${item.id}/toggle`),
        {},
        { preserveState: true },
    );
}

async function handleDeleteDiscount(item: DiscountItem) {
    const isConfirmed = await confirm({
        title: 'Delete Discount Rule',
        message: `Are you sure you want to delete "${item.name}"? This action cannot be undone.`,
        confirmText: 'Delete Rule',
        cancelText: 'Cancel',
        variant: 'destructive',
    });

    if (isConfirmed) {
        router.delete(getTeamUrl(`/discounts/${item.id}`));
    }
}

const formatCurrency = (val: number | string) => {
    const num = Number(val) || 0;
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: 0,
    }).format(num);
};
</script>

<template>
    <div class="space-y-6 p-4 sm:p-6">
        <Head title="Discounts & Special Offers" />

        <!-- Header Banner & Action Button -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-foreground flex items-center gap-2 text-2xl font-bold tracking-tight sm:text-3xl"
                >
                    <BadgePercent class="text-primary h-8 w-8" />
                    Discounts & Promotional Offers
                </h1>
                <p class="text-muted-foreground text-sm">
                    Create percentage discounts, flat bill rebates, customer
                    coupons, and seasonal promotion rules.
                </p>
            </div>
            <Button
                @click="openCreateModal"
                class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-2 font-bold shadow-md"
            >
                <Plus class="h-4 w-4" />
                Create Discount Rule
            </Button>
        </div>

        <!-- Summary Stat Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Total Discount Rules -->
            <div class="glass-card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p
                            class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                        >
                            Total Rules
                        </p>
                        <h3
                            class="text-foreground mt-1 text-2xl font-bold tracking-tight"
                        >
                            {{ summary?.total_discounts || 0 }}
                        </h3>
                    </div>
                    <div
                        class="bg-primary/10 text-primary flex h-11 w-11 items-center justify-center rounded-xl"
                    >
                        <BadgePercent class="h-6 w-6" />
                    </div>
                </div>
            </div>

            <!-- Active Discounts -->
            <div class="glass-card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p
                            class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                        >
                            Active Coupons
                        </p>
                        <h3
                            class="mt-1 text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400"
                        >
                            {{ summary?.active_discounts || 0 }}
                        </h3>
                    </div>
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    >
                        <CheckCircle2 class="h-6 w-6" />
                    </div>
                </div>
            </div>

            <!-- Total Redemptions -->
            <div class="glass-card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p
                            class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                        >
                            Times Applied
                        </p>
                        <h3
                            class="mt-1 text-2xl font-bold tracking-tight text-blue-600 dark:text-blue-400"
                        >
                            {{ summary?.total_redemptions || 0 }}
                        </h3>
                    </div>
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400"
                    >
                        <Tag class="h-6 w-6" />
                    </div>
                </div>
            </div>

            <!-- Max Offer Value -->
            <div class="glass-card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p
                            class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                        >
                            Max Flat Discount
                        </p>
                        <h3
                            class="mt-1 text-2xl font-bold tracking-tight text-purple-600 dark:text-purple-400"
                        >
                            {{ formatCurrency(summary?.max_offer_value || 0) }}
                        </h3>
                    </div>
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400"
                    >
                        <DollarSign class="h-6 w-6" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Search & Filter Bar -->
        <div
            class="border-border/60 bg-card flex flex-col gap-4 rounded-xl border p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <!-- Search Input -->
                <div class="relative min-w-[240px] flex-1">
                    <Search
                        class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2"
                    />
                    <Input
                        v-model="search"
                        type="text"
                        placeholder="Search discount by name, promo code, or notes..."
                        class="pl-9"
                    />
                </div>

                <!-- Type Selector -->
                <div class="w-44">
                    <Select v-model="selectedType">
                        <SelectTrigger class="h-9 text-xs">
                            <SelectValue placeholder="All Discount Types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Types</SelectItem>
                            <SelectItem value="percentage"
                                >Percentage (%)</SelectItem
                            >
                            <SelectItem value="fixed"
                                >Flat Amount (PKR)</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <!-- Status Tabs & Columns Dropdown -->
            <div
                class="border-border/40 flex flex-wrap items-center gap-2 border-t pt-3 sm:border-t-0 sm:pt-0"
            >
                <button
                    type="button"
                    @click="selectedStatus = 'all'"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                        selectedStatus === 'all'
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                >
                    All Rules
                </button>
                <button
                    type="button"
                    @click="selectedStatus = 'active'"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                        selectedStatus === 'active'
                            ? 'bg-emerald-600 text-white dark:bg-emerald-500'
                            : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                >
                    Active
                </button>
                <button
                    type="button"
                    @click="selectedStatus = 'inactive'"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                        selectedStatus === 'inactive'
                            ? 'bg-rose-600 text-white dark:bg-rose-500'
                            : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                >
                    Inactive
                </button>

                <!-- Table Columns Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 gap-1.5 text-xs"
                        >
                            <SlidersHorizontal
                                class="text-primary h-3.5 w-3.5"
                            />
                            <span>Columns</span>
                            <span
                                class="bg-primary/10 text-primary ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                            >
                                {{ activeColumnCount }}/7
                            </span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56 space-y-1 p-2">
                        <DropdownMenuLabel
                            class="flex items-center justify-between px-1 py-1 text-xs font-bold"
                        >
                            <span>Table Columns</span>
                            <button
                                type="button"
                                @click="resetColumns"
                                class="text-primary cursor-pointer text-[11px] font-semibold hover:underline"
                            >
                                Reset All
                            </button>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator class="my-1" />
                        <div
                            v-for="(label, key) in discountColumnLabels"
                            :key="key"
                            @click.stop="toggleDiscountColumn(key)"
                            class="hover:bg-muted flex cursor-pointer items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors select-none"
                        >
                            <span>{{ label }}</span>
                            <input
                                type="checkbox"
                                :checked="
                                    visibleColumns[
                                        key as keyof typeof visibleColumns
                                    ]
                                "
                                @change="toggleDiscountColumn(key)"
                                @click.stop
                                class="text-primary focus:ring-primary h-4 w-4 cursor-pointer rounded border-gray-300"
                            />
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Data Table -->
        <div
            class="border-border/60 bg-card overflow-hidden rounded-xl border shadow-sm"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-border/60 bg-muted/40 text-muted-foreground border-b text-xs font-semibold tracking-wider uppercase"
                    >
                        <tr>
                            <th v-if="visibleColumns.rule" class="px-6 py-3.5">
                                Rule / Promo Code
                            </th>
                            <th
                                v-if="visibleColumns.type_value"
                                class="px-6 py-3.5"
                            >
                                Discount Rate / Amount
                            </th>
                            <th
                                v-if="visibleColumns.min_purchase"
                                class="px-6 py-3.5"
                            >
                                Min Bill / Cap
                            </th>
                            <th
                                v-if="visibleColumns.validity"
                                class="px-6 py-3.5"
                            >
                                Validity Dates
                            </th>
                            <th
                                v-if="visibleColumns.redemptions"
                                class="px-6 py-3.5"
                            >
                                Usage / Redemptions
                            </th>
                            <th
                                v-if="visibleColumns.status"
                                class="px-6 py-3.5"
                            >
                                Status
                            </th>
                            <th
                                v-if="visibleColumns.actions"
                                class="px-6 py-3.5 text-right"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-border/60 divide-y">
                        <tr
                            v-for="item in discounts.data"
                            :key="item.id"
                            class="hover:bg-muted/30 transition-colors"
                        >
                            <!-- Rule & Promo Code -->
                            <td v-if="visibleColumns.rule" class="px-6 py-4">
                                <div
                                    class="text-foreground flex items-center gap-2 text-sm font-bold"
                                >
                                    <span>{{ item.name }}</span>
                                    <Badge
                                        v-if="item.code"
                                        class="bg-primary/10 text-primary hover:bg-primary/20 border-primary/20 font-mono"
                                    >
                                        {{ item.code }}
                                    </Badge>
                                </div>
                                <div
                                    v-if="item.notes"
                                    class="text-muted-foreground mt-0.5 max-w-xs truncate text-xs"
                                >
                                    {{ item.notes }}
                                </div>
                            </td>

                            <!-- Type & Value -->
                            <td
                                v-if="visibleColumns.type_value"
                                class="px-6 py-4"
                            >
                                <span
                                    class="inline-flex items-center gap-1 text-base font-bold"
                                    :class="
                                        item.type === 'percentage'
                                            ? 'text-blue-600 dark:text-blue-400'
                                            : 'text-emerald-600 dark:text-emerald-400'
                                    "
                                >
                                    {{
                                        item.type === 'percentage'
                                            ? `${item.value}% OFF`
                                            : formatCurrency(item.value)
                                    }}
                                </span>
                            </td>

                            <!-- Min Purchase & Cap -->
                            <td
                                v-if="visibleColumns.min_purchase"
                                class="px-6 py-4 text-xs"
                            >
                                <div
                                    v-if="item.min_purchase_amount"
                                    class="text-foreground font-semibold"
                                >
                                    Min Bill:
                                    {{
                                        formatCurrency(item.min_purchase_amount)
                                    }}
                                </div>
                                <div
                                    v-else
                                    class="text-muted-foreground italic"
                                >
                                    No min purchase
                                </div>

                                <div
                                    v-if="item.max_discount_amount"
                                    class="text-muted-foreground"
                                >
                                    Max Cap:
                                    {{
                                        formatCurrency(item.max_discount_amount)
                                    }}
                                </div>
                            </td>

                            <!-- Validity Period -->
                            <td
                                v-if="visibleColumns.validity"
                                class="text-muted-foreground px-6 py-4 text-xs"
                            >
                                <div
                                    v-if="item.start_date || item.end_date"
                                    class="flex flex-col gap-0.5"
                                >
                                    <span
                                        >From:
                                        {{ item.start_date || 'Start' }}</span
                                    >
                                    <span
                                        >To:
                                        {{ item.end_date || 'No Expiry' }}</span
                                    >
                                </div>
                                <span
                                    v-else
                                    class="font-medium text-emerald-600 dark:text-emerald-400"
                                    >Always Active</span
                                >
                            </td>

                            <!-- Usage Redemptions -->
                            <td
                                v-if="visibleColumns.redemptions"
                                class="px-6 py-4 text-xs font-semibold"
                            >
                                <div class="text-foreground">
                                    {{ item.used_count }}
                                    {{
                                        item.used_count === 1 ? 'used' : 'used'
                                    }}
                                </div>
                                <div
                                    v-if="item.usage_limit"
                                    class="text-muted-foreground text-[11px]"
                                >
                                    Limit: {{ item.usage_limit }} max
                                </div>
                            </td>

                            <!-- Status -->
                            <td v-if="visibleColumns.status" class="px-6 py-4">
                                <button
                                    type="button"
                                    @click="toggleStatus(item)"
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium transition-colors"
                                    :class="
                                        item.is_active
                                            ? 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20 dark:text-emerald-400'
                                            : 'bg-rose-500/10 text-rose-600 hover:bg-rose-500/20 dark:text-rose-400'
                                    "
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="
                                            item.is_active
                                                ? 'bg-emerald-500'
                                                : 'bg-rose-500'
                                        "
                                    ></span>
                                    {{ item.is_active ? 'Active' : 'Inactive' }}
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
                                        variant="outline"
                                        size="sm"
                                        @click="openEditModal(item)"
                                        class="h-8 w-8 p-0"
                                        title="Edit Discount Rule"
                                    >
                                        <Edit3 class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="handleDeleteDiscount(item)"
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive h-8 w-8 p-0"
                                        title="Delete Rule"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="discounts.data.length === 0">
                            <td
                                :colspan="activeColumnCount"
                                class="text-muted-foreground px-6 py-12 text-center"
                            >
                                <div
                                    class="flex flex-col items-center justify-center space-y-3"
                                >
                                    <div
                                        class="bg-muted flex h-12 w-12 items-center justify-center rounded-full"
                                    >
                                        <BadgePercent
                                            class="text-muted-foreground h-6 w-6"
                                        />
                                    </div>
                                    <p class="text-base font-medium">
                                        No discount rules found
                                    </p>
                                    <p class="text-sm">
                                        Create promo codes or percentage
                                        discounts for bill billing.
                                    </p>
                                    <Button
                                        @click="openCreateModal"
                                        size="sm"
                                        class="mt-2"
                                    >
                                        <Plus class="mr-1.5 h-4 w-4" />
                                        Create New Rule
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Editable Items Per Page Pagination Footer -->
            <div
                class="border-border/60 bg-muted/20 flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div
                    class="text-muted-foreground flex items-center gap-2 text-xs"
                >
                    <span class="text-foreground font-semibold"
                        >Items per page:</span
                    >
                    <select
                        v-model="perPage"
                        @change="changePerPage()"
                        class="border-border/60 bg-background text-foreground focus:ring-primary h-8 cursor-pointer rounded-lg border px-2.5 text-xs font-bold focus:ring-2 focus:outline-none"
                    >
                        <option :value="10">10 per page</option>
                        <option :value="15">15 per page (default)</option>
                        <option :value="25">25 per page</option>
                        <option :value="50">50 per page</option>
                        <option :value="100">100 per page</option>
                        <option :value="250">250 per page</option>
                        <option :value="500">500 per page (All)</option>
                    </select>
                    <span class="ml-1">&bull;</span>
                    <span>Total {{ discounts.total }} discount rules</span>
                </div>

                <div
                    v-if="discounts.links && discounts.links.length > 3"
                    class="flex items-center gap-1"
                >
                    <template v-for="(link, i) in discounts.links" :key="i">
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
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- Create Discount Modal -->
        <Dialog
            :open="isCreateDialogOpen"
            @update:open="isCreateDialogOpen = $event"
        >
            <DialogContent class="sm:max-w-[520px]">
                <DialogHeader>
                    <DialogTitle>Create Discount Rule</DialogTitle>
                    <DialogDescription>
                        Set up promo codes, percentage discounts, or flat amount
                        reductions.
                    </DialogDescription>
                </DialogHeader>

                <form
                    @submit.prevent="handleCreateDiscount"
                    class="space-y-4 py-2 text-xs"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Rule Name -->
                        <div class="space-y-1.5">
                            <Label for="create_name"
                                >Rule Name
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="create_name"
                                v-model="form.name"
                                placeholder="e.g. Eid Discount, Staff Rate"
                                :class="{
                                    'border-destructive': form.errors.name,
                                }"
                            />
                            <p
                                v-if="form.errors.name"
                                class="text-destructive text-xs"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Coupon Code -->
                        <div class="space-y-1.5">
                            <Label for="create_code"
                                >Promo Code (Optional)</Label
                            >
                            <Input
                                id="create_code"
                                v-model="form.code"
                                placeholder="e.g. EID2026, SUMMER10"
                                class="font-mono uppercase"
                                :class="{
                                    'border-destructive': form.errors.code,
                                }"
                            />
                            <p
                                v-if="form.errors.code"
                                class="text-destructive text-xs"
                            >
                                {{ form.errors.code }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Type -->
                        <div class="space-y-1.5">
                            <Label
                                >Discount Type
                                <span class="text-destructive">*</span></Label
                            >
                            <Select v-model="form.type">
                                <SelectTrigger
                                    ><SelectValue placeholder="Type"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="percentage"
                                        >Percentage (%)</SelectItem
                                    >
                                    <SelectItem value="fixed"
                                        >Flat Amount (PKR)</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Value -->
                        <div class="space-y-1.5">
                            <Label for="create_val"
                                >Discount Value
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="create_val"
                                v-model="form.value"
                                type="number"
                                step="0.01"
                                :placeholder="
                                    form.type === 'percentage'
                                        ? 'e.g. 10 for 10%'
                                        : 'e.g. 500 for Rs. 500'
                                "
                                :class="{
                                    'border-destructive': form.errors.value,
                                }"
                            />
                            <p
                                v-if="form.errors.value"
                                class="text-destructive text-xs"
                            >
                                {{ form.errors.value }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Min Purchase -->
                        <div class="space-y-1.5">
                            <Label for="create_min"
                                >Min Bill Amount (Optional)</Label
                            >
                            <Input
                                id="create_min"
                                v-model="form.min_purchase_amount"
                                type="number"
                                placeholder="0.00"
                            />
                        </div>

                        <!-- Max Cap (for percentage) -->
                        <div class="space-y-1.5">
                            <Label for="create_max"
                                >Max Discount Cap (Optional)</Label
                            >
                            <Input
                                id="create_max"
                                v-model="form.max_discount_amount"
                                type="number"
                                placeholder="Optional limit (e.g. 2000)"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Start Date -->
                        <div class="space-y-1.5">
                            <Label for="create_start"
                                >Start Date (Optional)</Label
                            >
                            <Input
                                id="create_start"
                                v-model="form.start_date"
                                type="date"
                            />
                        </div>

                        <!-- End Date -->
                        <div class="space-y-1.5">
                            <Label for="create_end">End Date (Optional)</Label>
                            <Input
                                id="create_end"
                                v-model="form.end_date"
                                type="date"
                            />
                        </div>
                    </div>

                    <!-- Usage Limit -->
                    <div class="space-y-1.5">
                        <Label for="create_limit"
                            >Usage Limit Count (Optional)</Label
                        >
                        <Input
                            id="create_limit"
                            v-model="form.usage_limit"
                            type="number"
                            placeholder="Leave empty for unlimited usage"
                        />
                    </div>

                    <!-- Is Active -->
                    <div
                        class="border-border/60 bg-muted/20 flex items-center justify-between rounded-lg border p-3"
                    >
                        <div class="space-y-0.5">
                            <Label class="text-sm font-medium"
                                >Activate Discount Immediately</Label
                            >
                            <p class="text-muted-foreground text-xs">
                                Active discounts can be selected during billing.
                            </p>
                        </div>
                        <input
                            type="checkbox"
                            v-model="form.is_active"
                            class="text-primary focus:ring-primary h-4 w-4 cursor-pointer rounded border-gray-300"
                        />
                    </div>

                    <!-- Notes -->
                    <div class="space-y-1.5">
                        <Label for="create_notes"
                            >Notes / Internal Description</Label
                        >
                        <Input
                            id="create_notes"
                            v-model="form.notes"
                            placeholder="Optional notes for staff"
                        />
                    </div>

                    <DialogFooter class="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isCreateDialogOpen = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : 'Create Rule' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit Discount Modal -->
        <Dialog
            :open="isEditDialogOpen"
            @update:open="isEditDialogOpen = $event"
        >
            <DialogContent class="sm:max-w-[520px]">
                <DialogHeader>
                    <DialogTitle>Edit Discount Rule</DialogTitle>
                    <DialogDescription
                        >Update promo parameters and discount
                        limits.</DialogDescription
                    >
                </DialogHeader>

                <form
                    @submit.prevent="handleUpdateDiscount"
                    class="space-y-4 py-2 text-xs"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Rule Name -->
                        <div class="space-y-1.5">
                            <Label for="edit_name"
                                >Rule Name
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="edit_name"
                                v-model="form.name"
                                placeholder="e.g. Eid Discount"
                                :class="{
                                    'border-destructive': form.errors.name,
                                }"
                            />
                        </div>

                        <!-- Coupon Code -->
                        <div class="space-y-1.5">
                            <Label for="edit_code">Promo Code (Optional)</Label>
                            <Input
                                id="edit_code"
                                v-model="form.code"
                                placeholder="e.g. EID2026"
                                class="font-mono uppercase"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Type -->
                        <div class="space-y-1.5">
                            <Label
                                >Discount Type
                                <span class="text-destructive">*</span></Label
                            >
                            <Select v-model="form.type">
                                <SelectTrigger
                                    ><SelectValue placeholder="Type"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="percentage"
                                        >Percentage (%)</SelectItem
                                    >
                                    <SelectItem value="fixed"
                                        >Flat Amount (PKR)</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Value -->
                        <div class="space-y-1.5">
                            <Label for="edit_val"
                                >Discount Value
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="edit_val"
                                v-model="form.value"
                                type="number"
                                step="0.01"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="edit_min">Min Bill Amount</Label>
                            <Input
                                id="edit_min"
                                v-model="form.min_purchase_amount"
                                type="number"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="edit_max">Max Discount Cap</Label>
                            <Input
                                id="edit_max"
                                v-model="form.max_discount_amount"
                                type="number"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="edit_start">Start Date</Label>
                            <Input
                                id="edit_start"
                                v-model="form.start_date"
                                type="date"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="edit_end">End Date</Label>
                            <Input
                                id="edit_end"
                                v-model="form.end_date"
                                type="date"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="edit_limit">Usage Limit Count</Label>
                        <Input
                            id="edit_limit"
                            v-model="form.usage_limit"
                            type="number"
                        />
                    </div>

                    <div
                        class="border-border/60 bg-muted/20 flex items-center justify-between rounded-lg border p-3"
                    >
                        <div class="space-y-0.5">
                            <Label class="text-sm font-medium"
                                >Active Rule</Label
                            >
                            <p class="text-muted-foreground text-xs">
                                Active rules are enabled for checkout.
                            </p>
                        </div>
                        <input
                            type="checkbox"
                            v-model="form.is_active"
                            class="text-primary focus:ring-primary h-4 w-4 cursor-pointer rounded border-gray-300"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="edit_notes">Notes</Label>
                        <Input
                            id="edit_notes"
                            v-model="form.notes"
                            placeholder="Optional notes"
                        />
                    </div>

                    <DialogFooter class="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isEditDialogOpen = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="form.processing">
                            {{
                                form.processing ? 'Updating...' : 'Update Rule'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
