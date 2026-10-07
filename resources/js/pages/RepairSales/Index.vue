<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { DollarSign, Plus, Receipt, Search, Trash2, Wrench } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
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
import repairSalesRoutes from '@/routes/repair-sales';
import type { Team } from '@/types';
import { toast } from 'vue-sonner';

interface RepairSaleItem {
    id: number;
    item_name: string;
    quantity: number | string;
    cost_price: number | string;
    sell_price: number | string;
    total_amount: number | string;
    profit: number;
    payment_method: string;
    user?: { id: number; name: string } | null;
    created_at: string;
}

interface PaymentMethodOption {
    value: string;
    label: string;
}

const props = defineProps<{
    repairSales: {
        data: RepairSaleItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    paymentMethods: PaymentMethodOption[];
    filters: {
        search: string;
        per_page?: number;
    };
    summary: {
        today_total: number;
        today_count: number;
        today_profit: number;
        month_total: number;
        month_profit: number;
        all_time_total: number;
    };
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
                title: 'Repair Sales',
                href: layoutProps.currentTeam
                    ? repairSalesRoutes.index(layoutProps.currentTeam.slug).url
                    : '/repair-sales',
            },
        ],
    }),
});

const { confirm } = useConfirm();

// Search & Pagination
const search = ref(props.filters.search || '');
const perPage = ref(props.filters.per_page || 15);
const PER_PAGE_STORAGE_KEY = 'faizan_mobile_repair_sales_per_page_v1';
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

onMounted(() => {
    try {
        const savedPerPage = localStorage.getItem(PER_PAGE_STORAGE_KEY);
        if (savedPerPage && Number(savedPerPage) !== perPage.value) {
            perPage.value = Number(savedPerPage);
            applyFilters();
        }
    } catch (e) {
        console.error(e);
    }
});

const applyFilters = () => {
    try {
        localStorage.setItem(PER_PAGE_STORAGE_KEY, String(perPage.value));
    } catch (e) {
        console.error(e);
    }
    router.get(
        repairSalesRoutes.index(currentTeamSlug.value).url,
        {
            search: search.value || undefined,
            per_page: perPage.value,
        },
        { preserveState: true, replace: true },
    );
};

watch(perPage, () => {
    applyFilters();
});

watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 350);
});

// Sell Repair Item Form
const form = useForm({
    item_name: '',
    quantity: 1,
    cost_price: '',
    sell_price: '',
    payment_method: 'cash',
});

const formQty = computed(() => Number(form.quantity) || 0);
const formCostTotal = computed(
    () => formQty.value * (Number(form.cost_price) || 0),
);
const formSellTotal = computed(
    () => formQty.value * (Number(form.sell_price) || 0),
);
const formProfit = computed(() => formSellTotal.value - formCostTotal.value);

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.quantity = 1;
    form.cost_price = '';
    form.sell_price = '';
    form.payment_method = 'cash';
};

const submitSale = () => {
    form.clearErrors();

    if (Number(form.cost_price) > 1000000) {
        form.setError(
            'cost_price',
            'لاگت قیمت 10 لاکھ (Rs 1,000,000) سے زیادہ نہیں ہو سکتی / Cost price cannot exceed Rs 1,000,000.',
        );
        toast.error('قیمت کی حد سے تجاوز', {
            description:
                'لاگت کی قیمت زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔',
        });
        return;
    }
    if (Number(form.sell_price) > 1000000) {
        form.setError(
            'sell_price',
            'فروخت قیمت 10 لاکھ (Rs 1,000,000) سے زیادہ نہیں ہو سکتی / Sell price cannot exceed Rs 1,000,000.',
        );
        toast.error('قیمت کی حد سے تجاوز', {
            description:
                'فروخت کی قیمت زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔',
        });
        return;
    }

    form.post(repairSalesRoutes.store(currentTeamSlug.value).url, {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
        },
    });
};

const deleteSale = async (sale: RepairSaleItem) => {
    const ok = await confirm({
        title: 'Delete Repair Sale',
        message: `Are you sure you want to delete "${sale.item_name}"?`,
        confirmText: 'Yes, Delete',
        cancelText: 'Cancel',
        variant: 'destructive',
    });
    if (ok) {
        router.delete(
            repairSalesRoutes.destroy([currentTeamSlug.value, sale.id]).url,
            { preserveState: true },
        );
    }
};

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
    <Head title="Repair Sales" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-bold tracking-tight text-gray-900"
                >
                    <Wrench class="h-7 w-7 text-[#003B7D]" />
                    Repair Sales
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Quick counter for selling repair jobs and services.
                </p>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">Today Sales</span>
                    <DollarSign class="h-5 w-5 text-[#003B7D]" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-gray-900">
                    {{ formatCurrency(summary.today_total) }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    {{ summary.today_count }} item(s) sold &middot; Profit
                    {{ formatCurrency(summary.today_profit) }}
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">This Month</span>
                    <Receipt class="h-5 w-5 text-sky-600 dark:text-sky-400" />
                </div>
                <div
                    class="tnum mt-2 text-2xl font-bold text-sky-600 dark:text-sky-400"
                >
                    {{ formatCurrency(summary.month_total) }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Monthly repair income &middot; Profit
                    {{ formatCurrency(summary.month_profit) }}
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">All Time</span>
                    <Wrench class="h-5 w-5 text-[#003B7D]" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-[#003B7D]">
                    {{ formatCurrency(summary.all_time_total) }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Total repair revenue
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Add Repair Sale Form -->
            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl lg:col-span-1"
            >
                <h2
                    class="flex items-center gap-2 text-base font-bold text-gray-900"
                >
                    <Plus class="h-4 w-4 text-[#003B7D]" />
                    Sell a Repair Item
                </h2>

                <form
                    @submit.prevent="submitSale"
                    class="mt-4 space-y-4 text-xs"
                >
                    <div class="space-y-1">
                        <Label for="item_name">Repairing Item Name *</Label>
                        <Input
                            id="item_name"
                            v-model="form.item_name"
                            placeholder="e.g. Screen Replacement"
                        />
                        <span
                            v-if="form.errors.item_name"
                            class="text-xs text-rose-600"
                            >{{ form.errors.item_name }}</span
                        >
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="quantity">Quantity</Label>
                            <Input
                                id="quantity"
                                v-model="form.quantity"
                                type="number"
                                step="0.01"
                                min="0.01"
                            />
                            <span
                                v-if="form.errors.quantity"
                                class="text-xs text-rose-600"
                                >{{ form.errors.quantity }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="cost_price"
                                >Cost Price (PKR) *
                                <span
                                    class="text-[10px] font-normal text-slate-400"
                                    >(Max: 10 Lakh)</span
                                ></Label
                            >
                            <Input
                                id="cost_price"
                                v-model="form.cost_price"
                                type="number"
                                step="0.01"
                                min="0"
                                max="1000000"
                                placeholder="0.00"
                            />
                            <span
                                v-if="form.errors.cost_price"
                                class="mt-1 block text-xs font-bold text-rose-600"
                                >{{ form.errors.cost_price }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="sell_price"
                                >Sell Price (PKR) *
                                <span
                                    class="text-[10px] font-normal text-slate-400"
                                    >(Max: 10 Lakh)</span
                                ></Label
                            >
                            <Input
                                id="sell_price"
                                v-model="form.sell_price"
                                type="number"
                                step="0.01"
                                min="0"
                                max="1000000"
                                placeholder="0.00"
                                class="font-bold"
                            />
                            <span
                                v-if="form.errors.sell_price"
                                class="mt-1 block text-xs font-bold text-rose-600"
                                >{{ form.errors.sell_price }}</span
                            >
                        </div>
                    </div>

                    <div class="space-y-1">
                        <Label>Payment Method *</Label>
                        <Select v-model="form.payment_method">
                            <SelectTrigger>
                                <SelectValue placeholder="Select method..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="method in paymentMethods"
                                    :key="method.value"
                                    :value="method.value"
                                >
                                    {{ method.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <span
                            v-if="form.errors.payment_method"
                            class="text-xs text-rose-600"
                            >{{ form.errors.payment_method }}</span
                        >
                    </div>

                    <div
                        class="space-y-2 rounded-xl border border-[#003B7D]/15 bg-[#003B7D]/5 px-4 py-3"
                    >
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-slate-600"
                                >Total Cost</span
                            >
                            <span
                                class="tnum font-bold text-amber-700 dark:text-amber-400"
                                >{{ formatCurrency(formCostTotal) }}</span
                            >
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-slate-600"
                                >Total Sale</span
                            >
                            <span
                                class="tnum text-lg font-bold text-[#003B7D]"
                                >{{ formatCurrency(formSellTotal) }}</span
                            >
                        </div>
                        <div
                            class="flex items-center justify-between border-t border-[#003B7D]/15 pt-2 text-xs"
                        >
                            <span class="font-semibold text-slate-600"
                                >Profit</span
                            >
                            <span
                                class="tnum font-bold"
                                :class="
                                    formProfit >= 0
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-rose-600'
                                "
                                >{{ formatCurrency(formProfit) }}</span
                            >
                        </div>
                    </div>

                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full gap-2 bg-[#003B7D] font-bold text-white shadow-sm hover:bg-[#002b5c]"
                    >
                        <Plus class="h-4 w-4" />
                        {{ form.processing ? 'Saving...' : 'Sell Item' }}
                    </Button>
                </form>
            </div>

            <!-- Repair Sales List -->
            <div class="lg:col-span-2">
                <div
                    class="mb-4 flex flex-col gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl md:flex-row md:items-center md:justify-between"
                >
                    <div class="relative flex-1">
                        <Search
                            class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2"
                        />
                        <Input
                            v-model="search"
                            placeholder="Search by item name..."
                            class="pl-9"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-slate-500"
                            >Show:</span
                        >
                        <select
                            v-model="perPage"
                            @change="applyFilters"
                            class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-xs font-medium text-slate-700 shadow-2xs focus:border-[#003B7D] focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
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

                <div
                    class="bg-card overflow-hidden rounded-xl border shadow-sm"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead
                                class="bg-muted/50 text-muted-foreground font-semibold uppercase"
                            >
                                <tr>
                                    <th class="px-4 py-3">Item</th>
                                    <th class="px-4 py-3">Qty</th>
                                    <th class="px-4 py-3">Cost</th>
                                    <th class="px-4 py-3">Sell</th>
                                    <th class="px-4 py-3">Total</th>
                                    <th class="px-4 py-3">Profit</th>
                                    <th class="px-4 py-3">Payment</th>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-border divide-y">
                                <tr v-if="repairSales.data.length === 0">
                                    <td
                                        colspan="9"
                                        class="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        No repair sales recorded yet.
                                    </td>
                                </tr>

                                <tr
                                    v-for="sale in repairSales.data"
                                    :key="sale.id"
                                    class="transition-colors hover:bg-gray-50"
                                >
                                    <td
                                        class="text-foreground max-w-xs px-4 py-3 font-semibold"
                                    >
                                        <div class="line-clamp-2">
                                            {{ sale.item_name }}
                                        </div>
                                        <div
                                            v-if="sale.user"
                                            class="text-muted-foreground text-[11px] font-normal"
                                        >
                                            {{ sale.user.name }}
                                        </div>
                                    </td>

                                    <td class="tnum px-4 py-3">
                                        {{ Number(sale.quantity) }}
                                    </td>

                                    <td
                                        class="tnum px-4 py-3 text-amber-700 dark:text-amber-400"
                                    >
                                        {{ formatCurrency(sale.cost_price) }}
                                    </td>

                                    <td class="tnum px-4 py-3">
                                        {{ formatCurrency(sale.sell_price) }}
                                    </td>

                                    <td
                                        class="tnum px-4 py-3 font-bold text-[#003B7D]"
                                    >
                                        {{ formatCurrency(sale.total_amount) }}
                                    </td>

                                    <td
                                        class="tnum px-4 py-3 font-bold"
                                        :class="
                                            Number(sale.profit) >= 0
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-rose-600'
                                        "
                                    >
                                        {{ formatCurrency(sale.profit) }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-md border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] font-semibold uppercase dark:border-slate-800 dark:bg-slate-900"
                                        >
                                            {{ sale.payment_method }}
                                        </span>
                                    </td>

                                    <td class="text-muted-foreground px-4 py-3">
                                        {{
                                            new Date(
                                                sale.created_at,
                                            ).toLocaleString('en-PK')
                                        }}
                                    </td>

                                    <td class="px-4 py-3 text-right">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            @click="deleteSale(sale)"
                                            title="Delete Sale"
                                            class="h-8 w-8 p-0 text-rose-600 hover:bg-rose-50 hover:text-rose-600 dark:text-rose-400 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Bar -->
                    <div
                        v-if="repairSales.links.length > 3"
                        class="bg-muted/20 flex items-center justify-between border-t px-4 py-3"
                    >
                        <div class="text-muted-foreground text-xs">
                            Page
                            <span class="font-semibold">{{
                                repairSales.current_page
                            }}</span>
                            of
                            <span class="font-semibold">{{
                                repairSales.last_page
                            }}</span>
                            ({{ repairSales.total }} sales)
                        </div>
                        <div class="flex gap-1">
                            <template
                                v-for="(link, i) in repairSales.links"
                                :key="i"
                            >
                                <Button
                                    v-if="link.url"
                                    size="sm"
                                    :variant="
                                        link.active ? 'default' : 'outline'
                                    "
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
            </div>
        </div>
    </div>
</template>
