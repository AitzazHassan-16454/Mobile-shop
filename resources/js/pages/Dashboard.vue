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

import type { IconTone, ValueColor } from '@/components/dashboard/StatsCard.vue';

interface MetricCard {
    label: string;
    value: number;
    sublabel?: string;
    iconTone: IconTone;
    valueColor: ValueColor;
}

const statsCards = computed<MetricCard[]>(() => {
    const stats = props.posStats ?? ({} as PosStats);

    const purchase = stats.used_phone_buying ?? 0;
    const purchaseDue = stats.purchase_due ?? 0;
    const purchasePayment = Math.max(0, purchase - purchaseDue);

    return [
        {
            label: 'Total Purchase',
            value: purchase,
            iconTone: 'blue',
            valueColor: 'blue',
        },
        {
            label: 'Total Purchase Returned',
            value: 0,
            iconTone: 'blue',
            valueColor: 'blue',
        },
        {
            label: 'Total Purchase Payment',
            value: purchasePayment,
            iconTone: 'green',
            valueColor: 'green',
        },
        {
            label: 'Total Sale',
            value: stats.total_sale ?? 0,
            iconTone: 'blue',
            valueColor: 'blue',
        },
        {
            label: 'Total Sale Returned',
            value: 0,
            iconTone: 'blue',
            valueColor: 'blue',
        },
        {
            label: 'Total Expense',
            value: stats.total_expense ?? 0,
            iconTone: 'blue',
            valueColor: 'blue',
        },
        {
            label: 'Gross Profit',
            value: stats.gross_profit ?? 0,
            sublabel: 'Sale - Purchase - Discount',
            iconTone: 'green',
            valueColor: 'green',
        },
        {
            label: 'Net Profit',
            value: stats.net_profit ?? 0,
            sublabel: 'Gross Profit - Expenses',
            iconTone: 'green',
            valueColor: 'green',
        },
        {
            label: 'Total Payment Received',
            value: stats.payment_received ?? 0,
            iconTone: 'green',
            valueColor: 'green',
        },
        {
            label: 'Total Purchase Due',
            value: purchaseDue,
            iconTone: 'red',
            valueColor: 'red',
        },
        {
            label: 'Total Sale Due',
            value: stats.sale_due ?? 0,
            iconTone: 'red',
            valueColor: 'red',
        },
        {
            label: 'Opening Balance Dues',
            value: 0,
            iconTone: 'red',
            valueColor: 'red',
        },
        {
            label: 'Total Due',
            value: stats.total_due ?? 0,
            iconTone: 'red',
            valueColor: 'red',
        },
        {
            label: 'Total Advance',
            value: stats.total_advance ?? 0,
            iconTone: 'green',
            valueColor: 'green',
        },
        {
            label: 'Total Discount',
            value: stats.total_discount ?? 0,
            iconTone: 'red',
            valueColor: 'red',
        },
    ];
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
        { name: 'Payments', color: '#0284c7', data: graph.payments },
        { name: 'Profit', color: '#059669', data: graph.profit },
        { name: 'Expenses', color: '#e11d48', data: graph.expenses },
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
    <Head title="Horizon Studio Dashboard" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <div class="mx-0 w-full max-w-none space-y-5 p-4 md:p-6">
        <!-- Standalone Dashboard Title Header with normal spacing -->
        <div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">
                Dashboard
            </h1>
        </div>

        <!-- 15 Stats Cards Section with RangeFilter right above the boxes -->
        <section class="space-y-3">
            <div class="flex items-center justify-end">
                <!-- Date Range Filter Select Box (Reporting Period) -->
                <RangeFilter
                    :team-slug="currentTeamSlug"
                    :preset="filters?.preset ?? 'this_month'"
                    :start="filters?.start ?? null"
                    :end="filters?.end ?? null"
                />
            </div>

            <div
                class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4"
            >
                <StatsCard
                    v-for="card in statsCards"
                    :key="card.label"
                    :label="card.label"
                    :value="card.value"
                    :sublabel="card.sublabel"
                    :icon-tone="card.iconTone"
                    :value-color="card.valueColor"
                />
            </div>
        </section>

        <!-- Financial Overview Chart -->
        <section
            class="glass-card rounded-3xl p-5 sm:p-7 border border-slate-200/80 bg-white/95 shadow-[0_16px_40px_rgba(0,35,90,0.06)] backdrop-blur-xl"
        >
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
