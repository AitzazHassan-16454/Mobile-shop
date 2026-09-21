<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowDownRight,
    ArrowUpRight,
    BarChart3,
    Boxes,
    Calendar,
    DollarSign,
    Download,
    Filter,
    Layers,
    Search,
    ShieldAlert,
    TrendingUp,
    Wrench,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { Team } from '@/types';

interface SummaryData {
    total_sales_count: number;
    total_revenue: number;
    net_profit: number;
    new_phone_count: number;
    used_phone_count: number;
    accessory_item_count: number;
    accessory_revenue: number;
    repair_revenue: number;
    udhaar_created: number;
    wasooli_collected: number;
}

interface ValuationData {
    new_phones: number;
    used_phones: number;
    accessories: number;
    total: number;
}

interface DeviceProfit {
    id: number;
    invoice_no: string;
    customer_name: string;
    device_name: string;
    imei: string;
    color: string;
    storage: string;
    condition: string;
    purchase_cost: number;
    sale_price: number;
    profit: number;
    margin_pct: number;
    sold_at: string;
}

interface SlowStockItem {
    type: string;
    name: string;
    detail: string;
    cost: number;
    days_in_stock: number;
    created_at: string;
}

const props = defineProps<{
    filters: {
        start_date?: string;
        end_date?: string;
    };
    summary: SummaryData;
    valuation: ValuationData;
    deviceProfits: DeviceProfit[];
    slowMovingStock: SlowStockItem[];
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Reports & Analytics', href: '/reports' },
        ],
    }),
});

const activeTab = ref<'device_profit' | 'valuation' | 'slow_stock'>(
    'device_profit',
);
const searchDeviceQuery = ref('');
const startDateInput = ref(props.filters.start_date || '');
const endDateInput = ref(props.filters.end_date || '');

const applyFilters = () => {
    router.get(
        '/reports',
        {
            start_date: startDateInput.value || undefined,
            end_date: endDateInput.value || undefined,
        },
        { preserveState: true },
    );
};

const setDatePreset = (preset: 'today' | 'week' | 'month' | 'clear') => {
    const today = new Date();
    if (preset === 'today') {
        const iso = today.toISOString().split('T')[0];
        startDateInput.value = iso;
        endDateInput.value = iso;
    } else if (preset === 'week') {
        const past = new Date(today);
        past.setDate(today.getDate() - 7);
        startDateInput.value = past.toISOString().split('T')[0];
        endDateInput.value = today.toISOString().split('T')[0];
    } else if (preset === 'month') {
        const past = new Date(today);
        past.setDate(today.getDate() - 30);
        startDateInput.value = past.toISOString().split('T')[0];
        endDateInput.value = today.toISOString().split('T')[0];
    } else {
        startDateInput.value = '';
        endDateInput.value = '';
    }
    applyFilters();
};

const filteredDeviceProfits = computed(() => {
    if (!searchDeviceQuery.value) return props.deviceProfits;
    const q = searchDeviceQuery.value.toLowerCase();
    return props.deviceProfits.filter(
        (d) =>
            d.device_name.toLowerCase().includes(q) ||
            d.imei.toLowerCase().includes(q) ||
            d.invoice_no.toLowerCase().includes(q) ||
            d.customer_name.toLowerCase().includes(q),
    );
});
</script>

<template>
    <Head title="Analytics & Profit Reports" />

    <div class="w-full space-y-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl md:flex-row md:items-center md:justify-between"
        >
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-bold text-gray-900"
                >
                    <BarChart3 class="h-7 w-7 text-[#003B7D]" />
                    Analytics & Business Reports
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    True device-wise profit tracking, stock capital valuation,
                    and slow stock alerts.
                </p>
            </div>
        </div>

        <!-- Date Filtering Bar -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl md:flex-row md:items-center md:justify-between"
        >
            <div class="flex items-center gap-2">
                <Filter class="h-4 w-4 text-[#003B7D]" />
                <span
                    class="text-xs font-semibold tracking-wider text-slate-600 uppercase"
                    >Date Filter:</span
                >
                <div class="flex items-center gap-2 text-sm">
                    <input
                        v-model="startDateInput"
                        type="date"
                        class="rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-900 placeholder-slate-500 focus:outline-none focus-visible:border-[#003B7D]/70 focus-visible:ring-2 focus-visible:ring-[#003B7D]/20"
                    />
                    <span class="text-slate-500">to</span>
                    <input
                        v-model="endDateInput"
                        type="date"
                        class="rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-900 placeholder-slate-500 focus:outline-none focus-visible:border-[#003B7D]/70 focus-visible:ring-2 focus-visible:ring-[#003B7D]/20"
                    />
                    <button
                        @click="applyFilters"
                        class="rounded-lg bg-[#003B7D] px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#002b5c]"
                    >
                        Apply
                    </button>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button
                    @click="setDatePreset('today')"
                    class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-slate-600 transition hover:bg-gray-100"
                >
                    Today
                </button>
                <button
                    @click="setDatePreset('week')"
                    class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-slate-600 transition hover:bg-gray-100"
                >
                    Last 7 Days
                </button>
                <button
                    @click="setDatePreset('month')"
                    class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-slate-600 transition hover:bg-gray-100"
                >
                    Last 30 Days
                </button>
                <button
                    @click="setDatePreset('clear')"
                    class="px-3 py-1 text-xs text-slate-500 transition hover:text-slate-600"
                >
                    All Time
                </button>
            </div>
        </div>

        <!-- KPI Summary Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                class="bg-card space-y-2 rounded-xl border border-gray-200 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)]"
            >
                <span
                    class="block text-xs font-semibold tracking-wider text-slate-500 uppercase"
                    >Total Sales Revenue</span
                >
                <span class="tnum block text-3xl font-black text-gray-900"
                    >Rs {{ summary.total_revenue.toLocaleString() }}</span
                >
                <span class="block text-xs text-slate-500"
                    >From {{ summary.total_sales_count }} sales invoices</span
                >
            </div>

            <div
                class="bg-card space-y-2 rounded-xl border border-gray-200 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)]"
            >
                <span
                    class="block text-xs font-semibold tracking-wider text-slate-500 uppercase"
                    >Net Profit</span
                >
                <span class="tnum block text-3xl font-black text-emerald-600"
                    >Rs {{ summary.net_profit.toLocaleString() }}</span
                >
                <span class="block text-xs text-slate-500"
                    >Exact purchase cost vs sale margin</span
                >
            </div>

            <div
                class="bg-card space-y-2 rounded-xl border border-gray-200 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)]"
            >
                <span
                    class="block text-xs font-semibold tracking-wider text-slate-500 uppercase"
                    >Stock Capital Investment</span
                >
                <span class="tnum block text-3xl font-black text-gray-900"
                    >Rs {{ valuation.total.toLocaleString() }}</span
                >
                <span class="block text-xs text-slate-500"
                    >Handsets + Accessories valuation</span
                >
            </div>

            <div
                class="bg-card space-y-2 rounded-xl border border-gray-200 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)]"
            >
                <span
                    class="block text-xs font-semibold tracking-wider text-slate-500 uppercase"
                    >Udhaar vs Wasooli</span
                >
                <div
                    class="flex items-center justify-between pt-1 text-sm text-gray-900"
                >
                    <span class="text-slate-500">New Udhaar:</span>
                    <span class="tnum font-bold"
                        >Rs {{ summary.udhaar_created.toLocaleString() }}</span
                    >
                </div>
                <div
                    class="flex items-center justify-between text-sm text-gray-900"
                >
                    <span class="text-slate-500">Wasooli Cash:</span>
                    <span class="tnum font-bold"
                        >Rs
                        {{ summary.wasooli_collected.toLocaleString() }}</span
                    >
                </div>
            </div>
        </div>

        <!-- Revenue Source Breakdown Cards -->
        <div
            class="bg-card grid grid-cols-2 gap-4 rounded-xl border border-gray-200 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] sm:grid-cols-4"
        >
            <div>
                <span class="block text-xs text-slate-500"
                    >New Phones Sold:</span
                >
                <span class="text-lg font-bold text-gray-900"
                    >{{ summary.new_phone_count }} Units</span
                >
            </div>
            <div>
                <span class="block text-xs text-slate-500"
                    >Used Phones Sold:</span
                >
                <span class="text-lg font-bold text-gray-900"
                    >{{ summary.used_phone_count }} Units</span
                >
            </div>
            <div>
                <span class="block text-xs text-slate-500"
                    >Accessories Revenue:</span
                >
                <span class="tnum text-lg font-bold text-[#003B7D]"
                    >Rs {{ summary.accessory_revenue.toLocaleString() }}</span
                >
            </div>
            <div>
                <span class="block text-xs text-slate-500"
                    >Repairing Revenue:</span
                >
                <span class="tnum text-lg font-bold text-sky-600"
                    >Rs {{ summary.repair_revenue.toLocaleString() }}</span
                >
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="flex items-center gap-6 border-b border-gray-200">
            <button
                @click="activeTab = 'device_profit'"
                :class="[
                    activeTab === 'device_profit'
                        ? 'border-[#003B7D]/50 font-bold text-[#003B7D]'
                        : 'border-transparent font-medium text-slate-500 hover:text-gray-900',
                    'flex items-center gap-2 border-b-2 py-3 text-sm transition',
                ]"
            >
                <TrendingUp class="h-4 w-4" />
                True Device-Wise Profit ({{ deviceProfits.length }})
            </button>
            <button
                @click="activeTab = 'valuation'"
                :class="[
                    activeTab === 'valuation'
                        ? 'border-[#003B7D]/50 font-bold text-[#003B7D]'
                        : 'border-transparent font-medium text-slate-500 hover:text-gray-900',
                    'flex items-center gap-2 border-b-2 py-3 text-sm transition',
                ]"
            >
                <Boxes class="h-4 w-4" />
                Stock Valuation Breakdown
            </button>
            <button
                @click="activeTab = 'slow_stock'"
                :class="[
                    activeTab === 'slow_stock'
                        ? 'border-[#003B7D]/50 font-bold text-[#003B7D]'
                        : 'border-transparent font-medium text-slate-500 hover:text-gray-900',
                    'flex items-center gap-2 border-b-2 py-3 text-sm transition',
                ]"
            >
                <ShieldAlert class="h-4 w-4" />
                Slow-Moving Alerts (> 30 Days) ({{ slowMovingStock.length }})
            </button>
        </div>

        <!-- Tab 1: Device-Wise Profit -->
        <div
            v-if="activeTab === 'device_profit'"
            class="bg-card space-y-4 rounded-xl border border-gray-200 p-6 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)]"
        >
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <h2 class="text-lg font-bold text-gray-900">
                    Device-Wise Exact Purchase vs Sale Profit
                </h2>
                <div class="relative w-full max-w-xs">
                    <Search
                        class="absolute top-3 left-3 h-4 w-4 text-slate-500"
                    />
                    <input
                        v-model="searchDeviceQuery"
                        type="text"
                        placeholder="Search IMEI, model, invoice..."
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pr-3 pl-9 text-xs text-gray-900 placeholder-slate-500 focus:outline-none focus-visible:border-[#003B7D]/70 focus-visible:ring-2 focus-visible:ring-[#003B7D]/20"
                    />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-xs text-slate-500 uppercase"
                        >
                            <th class="px-4 py-3">Invoice / Customer</th>
                            <th class="px-4 py-3">Device Model & IMEI</th>
                            <th class="px-4 py-3">Condition</th>
                            <th class="px-4 py-3">Purchase Cost</th>
                            <th class="px-4 py-3">Sale Price</th>
                            <th class="px-4 py-3">Net Profit</th>
                            <th class="px-4 py-3">Margin %</th>
                            <th class="px-4 py-3">Sold Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-if="filteredDeviceProfits.length === 0">
                            <td
                                colspan="8"
                                class="py-6 text-center text-slate-500 italic"
                            >
                                No sold handsets found matching criteria.
                            </td>
                        </tr>
                        <tr
                            v-for="d in filteredDeviceProfits"
                            :key="d.id"
                            class="hover:bg-gray-50"
                        >
                            <td class="px-4 py-3">
                                <span
                                    class="block font-mono text-xs font-semibold text-[#003B7D]"
                                    >{{ d.invoice_no }}</span
                                >
                                <span class="text-xs text-slate-500">{{
                                    d.customer_name
                                }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="block font-bold text-gray-900">{{
                                    d.device_name
                                }}</span>
                                <span
                                    class="block font-mono text-xs text-slate-500"
                                    >IMEI: {{ d.imei }} ({{ d.color }},
                                    {{ d.storage }})</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        d.condition === 'new'
                                            ? 'border border-[#003B7D]/20 bg-[#003B7D]/5 text-[#003B7D]'
                                            : 'border border-sky-200 bg-sky-50 text-sky-600',
                                        'rounded px-2 py-0.5 text-xs font-semibold capitalize',
                                    ]"
                                >
                                    {{ d.condition }}
                                </span>
                            </td>
                            <td
                                class="tnum px-4 py-3 font-medium text-slate-600"
                            >
                                Rs {{ d.purchase_cost.toLocaleString() }}
                            </td>
                            <td
                                class="tnum px-4 py-3 font-semibold text-gray-900"
                            >
                                Rs {{ d.sale_price.toLocaleString() }}
                            </td>
                            <td class="tnum px-4 py-3 font-bold text-[#003B7D]">
                                + Rs {{ d.profit.toLocaleString() }}
                            </td>
                            <td
                                class="tnum px-4 py-3 font-mono text-xs font-bold text-[#003B7D]"
                            >
                                {{ d.margin_pct }}%
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                {{ d.sold_at }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 2: Valuation Breakdown -->
        <div
            v-if="activeTab === 'valuation'"
            class="bg-card space-y-6 rounded-xl border border-gray-200 p-6 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)]"
        >
            <h2 class="text-lg font-bold text-gray-900">
                Shop Capital & Inventory Stock Valuation
            </h2>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div
                    class="space-y-2 rounded-2xl border border-gray-200 bg-gray-50 p-6"
                >
                    <span
                        class="block text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >New Handsets Inventory</span
                    >
                    <span class="tnum block text-2xl font-black text-gray-900"
                        >Rs {{ valuation.new_phones.toLocaleString() }}</span
                    >
                    <p class="text-xs text-slate-500">
                        Total purchase capital locked in box-pack & new devices
                        in stock.
                    </p>
                </div>

                <div
                    class="space-y-2 rounded-2xl border border-gray-200 bg-gray-50 p-6"
                >
                    <span
                        class="block text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >Used Handsets Inventory</span
                    >
                    <span class="tnum block text-2xl font-black text-gray-900"
                        >Rs {{ valuation.used_phones.toLocaleString() }}</span
                    >
                    <p class="text-xs text-slate-500">
                        Total capital locked in second-hand & traded-in devices
                        in stock.
                    </p>
                </div>

                <div
                    class="space-y-2 rounded-2xl border border-gray-200 bg-gray-50 p-6"
                >
                    <span
                        class="block text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >Accessories Stock</span
                    >
                    <span class="tnum block text-2xl font-black text-gray-900"
                        >Rs {{ valuation.accessories.toLocaleString() }}</span
                    >
                    <p class="text-xs text-slate-500">
                        Calculated as (cost_price × stock_quantity) across
                        standard items.
                    </p>
                </div>
            </div>

            <div
                class="flex items-center justify-between rounded-2xl bg-[#003B7D] p-6 shadow-lg shadow-[#003B7D]/20"
            >
                <div>
                    <span
                        class="block text-xs font-semibold tracking-wider text-blue-200 uppercase"
                        >Combined Total Shop Stock Valuation</span
                    >
                    <span class="tnum mt-1 block text-3xl font-black text-white"
                        >Rs {{ valuation.total.toLocaleString() }}</span
                    >
                </div>
                <Boxes class="h-12 w-12 text-white/40" />
            </div>
        </div>

        <!-- Tab 3: Slow-Moving Stock Alert -->
        <div
            v-if="activeTab === 'slow_stock'"
            class="bg-card space-y-4 rounded-xl border border-gray-200 p-6 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)]"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="flex items-center gap-2 text-lg font-bold text-gray-900"
                    >
                        <ShieldAlert class="h-5 w-5 text-amber-600" />
                        Slow-Moving Stock Alerts (> 30 Days in Stock)
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Items sitting in inventory without sale for over 30
                        days. Consider offering discounts or promotion.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-xs text-slate-500 uppercase"
                        >
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Item Name</th>
                            <th class="px-4 py-3">Details</th>
                            <th class="px-4 py-3">Capital Tied Up</th>
                            <th class="px-4 py-3">Days in Stock</th>
                            <th class="px-4 py-3">Added Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-if="slowMovingStock.length === 0">
                            <td
                                colspan="6"
                                class="py-6 text-center text-slate-500 italic"
                            >
                                Great news! No slow-moving stock older than 30
                                days.
                            </td>
                        </tr>
                        <tr
                            v-for="(item, idx) in slowMovingStock"
                            :key="idx"
                            class="hover:bg-gray-50"
                        >
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        item.type === 'Handset'
                                            ? 'border border-sky-200 bg-sky-50 text-sky-600'
                                            : 'border border-[#003B7D]/20 bg-[#003B7D]/5 text-[#003B7D]',
                                        'rounded px-2 py-0.5 text-xs font-semibold',
                                    ]"
                                >
                                    {{ item.type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-bold text-gray-900">
                                {{ item.name }}
                            </td>
                            <td
                                class="px-4 py-3 font-mono text-xs text-slate-500"
                            >
                                {{ item.detail }}
                            </td>
                            <td class="tnum px-4 py-3 font-bold text-rose-600">
                                Rs {{ item.cost.toLocaleString() }}
                            </td>
                            <td class="tnum px-4 py-3 font-bold text-amber-600">
                                {{ item.days_in_stock }} Days
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                {{ item.created_at }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
