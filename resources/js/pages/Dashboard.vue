<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    BarChart3,
    Coins,
    Database,
    LayoutGrid,
    Percent,
    Receipt,
    ShoppingCart,
    TrendingUp,
    Wallet,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import LineChart from '@/components/dashboard/LineChart.vue';
import RangeFilter from '@/components/dashboard/RangeFilter.vue';
import StatsCard from '@/components/dashboard/StatsCard.vue';
import type { StatsTone } from '@/components/dashboard/StatsCard.vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import backup from '@/routes/backup';
import pos from '@/routes/pos';
import reports from '@/routes/reports';
import type { DashboardInvitation } from '@/types';

interface DashboardMetrics {
    todayRevenue: number;
    todaySalesCount: number;
    inStockPhones: number;
    totalAccessories: number;
    pendingRepairs: number;
    totalCustomerDebt: number;
    totalValuation: number;
    hasActiveShift: boolean;
    activeShiftOpenedAt?: string;
}

interface PosStats {
    total_sale: number;
    sales_count: number;
    total_expense: number;
    gross_profit: number;
    net_profit: number;
    payment_received: number;
    total_discount: number;
    total_advance: number;
    udhaar_created: number;
    wasooli_collected: number;
    sale_due: number;
    purchase_due: number;
    total_due: number;
    used_phone_buying: number;
    repair_revenue: number;
    repair_profit: number;
}

interface PaymentMethodStat {
    method: string;
    label: string;
    amount: number;
}

interface ExpenseCategory {
    name: string;
    amount: number;
}

interface GraphData {
    labels: string[];
    sales: number[];
    payments: number[];
    profit: number[];
    expenses: number[];
}

interface ReminderItem {
    id: number;
    customer: string;
    amount: number;
    due_date: string;
    status: 'overdue' | 'today' | 'upcoming';
}

interface DashboardReminders {
    overdue: number;
    today: number;
    upcoming: number;
    list: ReminderItem[];
}

interface DashboardAlert {
    type: 'low_stock' | 'repairs' | 'installments' | string;
    title: string;
    message: string;
}

interface RecentSaleItem {
    id: number;
    invoice_no: string;
    customer: string;
    net_amount: number;
    payment_method: string;
    created_at: string;
}

type Tone = StatsTone;

const props = defineProps<{
    pendingInvitations?: DashboardInvitation[];
    metrics?: DashboardMetrics;
    filters?: { preset: string; start: string | null; end: string | null };
    posStats?: PosStats;
    paymentMethods?: PaymentMethodStat[];
    expenseCategories?: ExpenseCategory[];
    graph?: GraphData;
    reminders?: DashboardReminders;
    alerts?: DashboardAlert[];
    recentSales?: RecentSaleItem[];
}>();

const page = usePage();
const currentTeamSlug = computed(
    () =>
        (page.props.currentTeam as { slug: string } | undefined)?.slug ??
        'default',
);

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
    }),
});

const backupDownloadUrl = computed(
    () => backup.download(currentTeamSlug.value).url,
);

const earningsCards = computed(() => {
    const stats = props.posStats ?? ({} as PosStats);

    return [
        {
            label: 'Total Sales',
            value: stats.total_sale,
            tone: 'brand',
            icon: ShoppingCart,
            sublabel: `Rs ${(stats.total_sale / Math.max(stats.sales_count, 1)).toLocaleString(undefined, { maximumFractionDigits: 0 })} per sale`,
        },
        {
            label: 'Sales Count',
            value: stats.sales_count,
            tone: 'slate',
            icon: Receipt,
            sublabel: 'Invoices in range',
        },
        {
            label: 'Payment Received',
            value: stats.payment_received,
            tone: 'sky',
            icon: Banknote,
            sublabel: 'Collected from sales',
        },
        {
            label: 'Gross Profit',
            value: stats.gross_profit,
            tone: 'emerald',
            icon: TrendingUp,
            sublabel: 'After product cost',
        },
        {
            label: 'Net Profit',
            value: stats.net_profit,
            tone: 'fuchsia',
            icon: Wallet,
            sublabel: 'After expenses + repairs',
        },
        {
            label: 'Total Expense',
            value: stats.total_expense,
            tone: 'rose',
            icon: Coins,
            sublabel: 'Shop running costs',
        },
        {
            label: 'Total Discount',
            value: stats.total_discount,
            tone: 'amber',
            icon: Percent,
            sublabel: 'Given on bills',
        },
        {
            label: 'Repair Revenue',
            value: stats.repair_revenue,
            tone: 'sky',
            icon: Wrench,
            sublabel: `Rs ${stats.repair_profit.toLocaleString()} profit`,
        },
    ] as {
        label: string;
        value: number;
        tone: Tone;
        icon: Component;
        sublabel: string;
    }[];
});

const chartSeries = computed(() => {
    const graph = props.graph ?? {
        labels: [],
        sales: [],
        payments: [],
        profit: [],
        expenses: [],
    };

    return [
        { name: 'Sales', color: '#003B7D', data: graph.sales },
        { name: 'Payments', color: '#22d3ee', data: graph.payments },
        { name: 'Profit', color: '#34d399', data: graph.profit },
        { name: 'Expenses', color: '#f43f5e', data: graph.expenses },
    ];
});

function formatGraphLabel(label: string): string {
    const yearMonth = label.match(/^(\d{4})-(\d{2})$/);
    if (yearMonth) {
        return new Intl.DateTimeFormat('en-GB', {
            month: 'short',
            year: '2-digit',
        }).format(new Date(Number(yearMonth[1]), Number(yearMonth[2]) - 1, 1));
    }

    const day = label.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (day) {
        return new Intl.DateTimeFormat('en-GB', {
            day: 'numeric',
            month: 'short',
        }).format(new Date(Number(day[1]), Number(day[2]) - 1, Number(day[3])));
    }

    return label;
}
</script>

<template>
    <Head title="Faizan Mobile Dashboard" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <div class="mx-0 w-full max-w-none space-y-5 p-4 md:p-6">
        <!-- Page header -->
        <div
            class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <h1
                    class="flex items-center gap-3 text-2xl font-black tracking-tight text-slate-900"
                >
                    <span
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-[#003B7D] to-[#002752] text-white shadow-[0_10px_25px_rgba(0,59,125,0.35),inset_0_1px_1.5px_rgba(255,255,255,0.4)] ring-1 ring-white/20"
                    >
                        <LayoutGrid class="h-5 w-5" />
                    </span>
                    <span>Store Dashboard</span>
                </h1>
                <p class="mt-1.5 text-sm text-slate-500">
                    Shop earnings and financial performance for the selected
                    period
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a
                    :href="backupDownloadUrl"
                    class="glass-pill inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 text-xs font-bold text-slate-700 shadow-xs transition hover:-translate-y-0.5 hover:bg-white/90 hover:shadow-md"
                >
                    <Database class="h-4 w-4 text-[#003B7D]" />
                    Local Backup
                </a>
                <Link
                    :href="pos.index(currentTeamSlug).url"
                    class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-[#003B7D] to-[#0051a8] px-5 py-2.5 text-sm font-black text-white shadow-[0_10px_25px_rgba(0,59,125,0.35),inset_0_1px_1.5px_rgba(255,255,255,0.35)] backdrop-blur-xl transition hover:-translate-y-0.5 hover:brightness-105"
                >
                    <ShoppingCart class="h-4 w-4" />
                    Open POS
                </Link>
            </div>
        </div>

        <!-- Reporting period -->
        <RangeFilter
            :team-slug="currentTeamSlug"
            :preset="filters?.preset ?? 'this_month'"
            :start="filters?.start ?? null"
            :end="filters?.end ?? null"
        />

        <!-- Store earnings -->
        <section>
            <header class="mb-3 flex items-end justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#003B7D]/18 to-[#003B7D]/5 text-[#003B7D] shadow-[inset_0_1px_1.5px_rgba(255,255,255,0.7),0_4px_12px_rgba(0,0,0,0.04)] ring-1 ring-[#003B7D]/20 ring-inset"
                    >
                        <TrendingUp class="h-5 w-5" />
                    </div>
                    <div>
                        <h2
                            class="text-lg font-black tracking-tight text-slate-900"
                        >
                            Store Earnings
                        </h2>
                        <p class="text-xs text-slate-500">
                            Revenue, profit and cash movement for the selected
                            period
                        </p>
                    </div>
                </div>
                <Link
                    :href="reports.index(currentTeamSlug).url"
                    class="glass-pill inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold text-[#003B7D] transition hover:bg-white hover:shadow-xs"
                >
                    <BarChart3 class="h-3.5 w-3.5" />
                    Detailed reports
                </Link>
            </header>
            <div
                class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4"
            >
                <StatsCard
                    v-for="card in earningsCards"
                    :key="card.label"
                    :label="card.label"
                    :value="card.value"
                    :sublabel="card.sublabel"
                    :tone="card.tone"
                    :icon="card.icon"
                />
            </div>
        </section>

        <!-- Financial overview -->
        <section class="glass-card rounded-3xl p-4 sm:p-5">
            <header class="mb-4 flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#003B7D]/18 to-[#003B7D]/5 text-[#003B7D] shadow-[inset_0_1px_1.5px_rgba(255,255,255,0.7),0_4px_12px_rgba(0,0,0,0.04)] ring-1 ring-[#003B7D]/20 ring-inset"
                >
                    <BarChart3 class="h-5 w-5" />
                </div>
                <div>
                    <h2
                        class="text-lg font-black tracking-tight text-slate-900"
                    >
                        Financial Overview
                    </h2>
                    <p class="text-xs text-slate-500">
                        Sales, collections, profit and expenses trend across the
                        period
                    </p>
                </div>
            </header>
            <LineChart
                :labels="graph?.labels ?? []"
                :series="chartSeries"
                :format-label="formatGraphLabel"
                :format-value="
                    (value) => `Rs ${Math.round(value).toLocaleString()}`
                "
            />
        </section>
    </div>
</template>
