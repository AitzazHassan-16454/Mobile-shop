<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    BarChart3,
    Bell,
    Boxes,
    CalendarDays,
    Clock3,
    Coins,
    CreditCard,
    Database,
    HandCoins,
    Landmark,
    LayoutGrid,
    NotebookText,
    PackageX,
    Percent,
    PiggyBank,
    Receipt,
    ShieldCheck,
    ShoppingCart,
    Smartphone,
    Store,
    TrendingUp,
    Users,
    Wallet,
    Wrench,
    Zap,
} from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import { dashboard } from '@/routes';
import DonutChart from '@/components/dashboard/DonutChart.vue';
import LineChart from '@/components/dashboard/LineChart.vue';
import RangeFilter from '@/components/dashboard/RangeFilter.vue';
import StatsCard from '@/components/dashboard/StatsCard.vue';
import type { StatsTone } from '@/components/dashboard/StatsCard.vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import backup from '@/routes/backup';
import customers from '@/routes/customers';
import inventory from '@/routes/inventory';
import pos from '@/routes/pos';
import repairs from '@/routes/repairs';
import reports from '@/routes/reports';
import shifts from '@/routes/shifts';
import usedPhones from '@/routes/used-phones';
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
const dashboardUrl = computed(() => dashboard.url(currentTeamSlug.value));

const earningsCards = computed(() => {
    const stats = props.posStats ?? ({} as PosStats);

    return [
        {
            label: 'Total Sales',
            value: stats.total_sale,
            tone: 'violet',
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

const duesCards = computed(() => {
    const stats = props.posStats ?? ({} as PosStats);

    return [
        {
            label: 'Customer Khata Due',
            value: stats.sale_due,
            tone: 'rose',
            icon: Users,
            sublabel: 'Open installments + khata',
        },
        {
            label: 'Suppliers Payables',
            value: stats.purchase_due,
            tone: 'amber',
            icon: Store,
            sublabel: 'Stock purchase dues',
        },
        {
            label: 'Total Outstanding',
            value: stats.total_due,
            tone: 'rose',
            icon: HandCoins,
            sublabel: 'Khata + payables',
        },
        {
            label: 'Total Advance',
            value: stats.total_advance,
            tone: 'emerald',
            icon: PiggyBank,
            sublabel: 'Credit held by customers',
        },
        {
            label: 'Udhaar Created',
            value: stats.udhaar_created,
            tone: 'rose',
            icon: CreditCard,
            sublabel: 'New khata in range',
        },
        {
            label: 'Wasooli Collected',
            value: stats.wasooli_collected,
            tone: 'emerald',
            icon: HandCoins,
            sublabel: 'Khata recoveries',
        },
        {
            label: 'Used Phone Buying',
            value: stats.used_phone_buying,
            tone: 'amber',
            icon: ShieldCheck,
            sublabel: 'Stock invested',
        },
    ] as {
        label: string;
        value: number;
        tone: Tone;
        icon: Component;
        sublabel: string;
    }[];
});

const paymentMethodMeta: Record<string, { icon: Component; tone: Tone }> = {
    cash: { icon: Banknote, tone: 'emerald' },
    jazzcash: { icon: Smartphone, tone: 'rose' },
    easypaisa: { icon: Zap, tone: 'fuchsia' },
    bank: { icon: Landmark, tone: 'sky' },
    card: { icon: CreditCard, tone: 'violet' },
    udhaar: { icon: NotebookText, tone: 'amber' },
};

const paymentTotal = computed(() =>
    (props.paymentMethods ?? []).reduce(
        (sum, method) => sum + method.amount,
        0,
    ),
);

const expenseMax = computed(() =>
    Math.max(
        ...(props.expenseCategories ?? []).map((category) => category.amount),
        1,
    ),
);

const chartSeries = computed(() => {
    const graph = props.graph ?? {
        labels: [],
        sales: [],
        payments: [],
        profit: [],
        expenses: [],
    };

    return [
        { name: 'Sales', color: '#8b5cf6', data: graph.sales },
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

const alertMeta: Record<
    string,
    { icon: Component; chip: string; iconChip: string }
> = {
    low_stock: {
        icon: PackageX,
        chip: 'bg-amber-50 text-amber-600 border-amber-200',
        iconChip: 'bg-amber-100 text-amber-600 ring-amber-200',
    },
    repairs: {
        icon: Wrench,
        chip: 'bg-sky-50 text-sky-600 border-sky-200',
        iconChip: 'bg-sky-100 text-sky-600 ring-sky-200',
    },
    installments: {
        icon: CalendarDays,
        chip: 'bg-rose-50 text-rose-600 border-rose-200',
        iconChip: 'bg-rose-100 text-rose-600 ring-rose-200',
    },
};

function reminderStatusChip(status: ReminderItem['status']): string {
    const map: Record<ReminderItem['status'], string> = {
        overdue: 'bg-rose-50 text-rose-600 border-rose-200',
        today: 'bg-amber-50 text-amber-600 border-amber-200',
        upcoming: 'bg-sky-50 text-sky-600 border-sky-200',
    };

    return map[status];
}

const reminders = computed(
    () => props.reminders ?? { overdue: 0, today: 0, upcoming: 0, list: [] },
);
const metrics = computed(() => props.metrics);

function formatDate(date: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'short',
    }).format(new Date(`${date}T00:00:00`));
}
</script>

<template>
    <Head title="Faizan Mobile Dashboard" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <div class="mx-0 w-full max-w-none space-y-4 p-4 md:p-6">
        <!-- Header -->
        <div
            class="bg-card flex flex-col gap-4 rounded-2xl border border-gray-200 p-5 shadow-[0_1px_2px_rgba(2,43,90,0.06)] md:flex-row md:items-center md:justify-between"
        >
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-black tracking-tight text-gray-900"
                >
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#003b7d] text-white"
                    >
                        <LayoutGrid class="h-5 w-5" />
                    </span>
                    <span>Faizan Mobile Dashboard</span>
                </h1>
                <p class="mt-1.5 text-sm text-slate-500">
                    Live store performance, cash movement, inventory health, and
                    service operations in one place.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a
                    :href="backupDownloadUrl"
                    class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2 text-xs font-bold text-slate-600 transition hover:border-[#003b7d]/30 hover:bg-[#003b7d]/5"
                >
                    <Database class="h-4 w-4 text-[#003b7d]" />
                    Local Backup
                </a>
                <Link
                    :href="pos.index(currentTeamSlug).url"
                    class="inline-flex items-center gap-2 rounded-xl bg-[#003b7d] px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[#0f4c81]"
                >
                    <ShoppingCart class="h-4 w-4" />
                    Open POS
                </Link>
            </div>
        </div>

        <!-- Shift banner -->
        <div
            v-if="metrics"
            :class="[
                metrics.hasActiveShift
                    ? 'border-violet-200 bg-violet-50 text-violet-700'
                    : 'border-amber-200 bg-amber-50 text-amber-700',
                'flex flex-col gap-2.5 rounded-2xl border p-3 shadow-sm backdrop-blur md:flex-row md:items-center md:justify-between',
            ]"
        >
            <div class="flex items-start gap-3">
                <div
                    class="rounded-xl bg-gray-100 p-2 ring-1 ring-gray-200 ring-inset"
                >
                    <Receipt class="h-5 w-5 text-violet-600" />
                </div>
                <div>
                    <div class="text-sm font-black">
                        {{
                            metrics.hasActiveShift
                                ? 'Register shift is active and tracking sales.'
                                : 'No active shift has been opened yet.'
                        }}
                    </div>
                    <div class="mt-0.5 text-xs opacity-80">
                        {{
                            metrics.hasActiveShift
                                ? `Opened at ${metrics.activeShiftOpenedAt}. Cash drawer and expenses are syncing live.`
                                : 'Open the cashier drawer before processing sales to keep reconciliation accurate.'
                        }}
                    </div>
                </div>
            </div>
            <Link
                :href="shifts.index(currentTeamSlug).url"
                class="inline-flex items-center justify-center rounded-xl border border-current/20 bg-gray-50 px-3 py-2 text-xs font-bold transition hover:bg-gray-100"
            >
                {{ metrics.hasActiveShift ? 'Manage Shift' : 'Open Shift' }}
            </Link>
        </div>

        <!-- Range filter -->
        <div
            class="bg-card rounded-3xl border border-gray-200 p-4 shadow-[0_1px_2px_rgba(2,43,90,0.06)]"
        >
            <div class="mb-3 flex items-center gap-2">
                <span class="eyebrow">
                    <CalendarDays class="h-3.5 w-3.5" />
                    Reporting Period
                </span>
            </div>
            <RangeFilter
                :team-slug="currentTeamSlug"
                :preset="filters?.preset ?? 'this_month'"
                :start="filters?.start ?? null"
                :end="filters?.end ?? null"
            />
        </div>

        <!-- POS performance -->
        <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black tracking-tight text-gray-900">
                        Store Earnings
                    </h2>
                    <p class="text-xs text-slate-500">
                        Revenue, profit and cash movement for the selected
                        period
                    </p>
                </div>
                <Link
                    :href="reports.index(currentTeamSlug).url"
                    class="inline-flex items-center gap-1 text-xs font-bold text-violet-600 hover:text-violet-600"
                >
                    <BarChart3 class="h-3.5 w-3.5" />
                    Detailed reports
                </Link>
            </div>
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
        </div>

        <!-- Dues & advances -->
        <div class="space-y-3">
            <div>
                <h2 class="text-lg font-black tracking-tight text-gray-900">
                    Dues & Advances
                </h2>
                <p class="text-xs text-slate-500">
                    Money owed to and by the store
                </p>
            </div>
            <div
                class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4"
            >
                <StatsCard
                    v-for="card in duesCards"
                    :key="card.label"
                    :label="card.label"
                    :value="card.value"
                    :sublabel="card.sublabel"
                    :tone="card.tone"
                    :icon="card.icon"
                />
            </div>
        </div>

        <!-- Payment method split -->
        <div
            class="bg-card rounded-3xl border border-gray-200 p-4 shadow-[0_1px_2px_rgba(2,43,90,0.06)]"
        >
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black tracking-tight text-gray-900">
                        Payment Method Stats
                    </h2>
                    <p class="text-xs text-slate-500">
                        How customers paid during the selected period
                    </p>
                </div>
                <span
                    class="tnum rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-bold text-slate-600"
                >
                    Collected
                    <span class="text-violet-600"
                        >Rs {{ paymentTotal.toLocaleString() }}</span
                    >
                </span>
            </div>
            <div
                class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
            >
                <div
                    v-for="method in paymentMethods"
                    :key="method.method"
                    class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-3.5"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-600 ring-1 ring-violet-200 ring-inset"
                    >
                        <component
                            :is="
                                paymentMethodMeta[method.method]?.icon ??
                                Banknote
                            "
                            class="h-5 w-5"
                        />
                    </div>
                    <div class="min-w-0">
                        <p
                            class="truncate text-[11px] font-bold tracking-[0.12em] text-slate-500 uppercase"
                        >
                            {{ method.label }}
                        </p>
                        <p
                            class="tnum truncate text-sm font-black text-gray-900"
                        >
                            Rs {{ method.amount.toLocaleString() }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expenses -->
        <div
            class="bg-card rounded-3xl border border-gray-200 p-4 shadow-[0_1px_2px_rgba(2,43,90,0.06)]"
        >
            <div class="mb-4">
                <h2 class="text-lg font-black tracking-tight text-gray-900">
                    Expenses
                </h2>
                <p class="text-xs text-slate-500">
                    Shop expenses grouped by category for the selected period
                </p>
            </div>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <div class="space-y-3 lg:col-span-7">
                    <div
                        v-for="category in expenseCategories"
                        :key="category.name"
                        class="rounded-2xl border border-gray-200 bg-gray-50 p-3.5"
                    >
                        <div
                            class="mb-2 flex items-center justify-between gap-3"
                        >
                            <span
                                class="truncate text-sm font-bold text-gray-900 capitalize"
                                >{{ category.name }}</span
                            >
                            <span
                                class="tnum shrink-0 text-sm font-black text-violet-600"
                                >Rs {{ category.amount.toLocaleString() }}</span
                            >
                        </div>
                        <div
                            class="h-2 overflow-hidden rounded-full bg-gray-100"
                        >
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-[#003b7d] to-[#5b8def]"
                                :style="{
                                    width: `${(category.amount / expenseMax) * 100}%`,
                                }"
                            />
                        </div>
                    </div>
                </div>
                <div
                    class="flex items-center rounded-2xl border border-gray-200 bg-gray-50 p-4 lg:col-span-5"
                >
                    <DonutChart :items="expenseCategories ?? []" />
                </div>
            </div>
        </div>

        <!-- Graphs -->
        <div
            class="bg-card rounded-3xl border border-gray-200 p-4 shadow-[0_1px_2px_rgba(2,43,90,0.06)]"
        >
            <div class="mb-4">
                <h2 class="text-lg font-black tracking-tight text-gray-900">
                    Financial Overview
                </h2>
                <p class="text-xs text-slate-500">
                    Sales, collections, profit and expenses trend across the
                    period
                </p>
            </div>
            <LineChart
                :labels="graph?.labels ?? []"
                :series="chartSeries"
                :format-label="formatGraphLabel"
                :format-value="
                    (value) => `Rs ${Math.round(value).toLocaleString()}`
                "
            />
        </div>

        <!-- Reminders + Alerts -->
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <div
                class="bg-card rounded-3xl border border-gray-200 p-4 shadow-[0_1px_2px_rgba(2,43,90,0.06)]"
            >
                <div class="mb-3 flex items-center gap-2">
                    <span class="eyebrow">
                        <Clock3 class="h-3.5 w-3.5" />
                        Installment Reminders
                    </span>
                </div>
                <div class="mb-4 grid grid-cols-3 gap-3">
                    <div
                        class="rounded-2xl border border-rose-200 bg-rose-50 p-3 text-center"
                    >
                        <p class="tnum text-2xl font-black text-rose-600">
                            {{ reminders.overdue }}
                        </p>
                        <p
                            class="text-[10px] font-bold tracking-[0.14em] text-rose-600/70 uppercase"
                        >
                            Overdue
                        </p>
                    </div>
                    <div
                        class="rounded-2xl border border-amber-200 bg-amber-50 p-3 text-center"
                    >
                        <p class="tnum text-2xl font-black text-amber-600">
                            {{ reminders.today }}
                        </p>
                        <p
                            class="text-[10px] font-bold tracking-[0.14em] text-amber-600/70 uppercase"
                        >
                            Due today
                        </p>
                    </div>
                    <div
                        class="rounded-2xl border border-sky-200 bg-sky-50 p-3 text-center"
                    >
                        <p class="tnum text-2xl font-black text-sky-600">
                            {{ reminders.upcoming }}
                        </p>
                        <p
                            class="text-[10px] font-bold tracking-[0.14em] text-sky-600/70 uppercase"
                        >
                            Next 7 days
                        </p>
                    </div>
                </div>
                <div class="space-y-2">
                    <div
                        v-for="item in reminders.list"
                        :key="item.id"
                        class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-3"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-gray-900">
                                {{ item.customer }}
                            </p>
                            <p class="text-[11px] text-slate-500">
                                Due {{ formatDate(item.due_date) }}
                            </p>
                        </div>
                        <span
                            :class="[
                                'rounded-md border px-2 py-0.5 text-[10px] font-black tracking-[0.12em] uppercase',
                                reminderStatusChip(item.status),
                            ]"
                        >
                            {{ item.status }}
                        </span>
                        <span
                            class="tnum shrink-0 text-sm font-black text-violet-600"
                            >Rs {{ item.amount.toLocaleString() }}</span
                        >
                    </div>
                    <p
                        v-if="reminders.list.length === 0"
                        class="py-4 text-center text-sm text-slate-500 italic"
                    >
                        No upcoming installments — everything is on track.
                    </p>
                </div>
                <Link
                    :href="customers.index(currentTeamSlug).url"
                    class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-violet-600 hover:text-violet-600"
                >
                    <Users class="h-3.5 w-3.5" />
                    Review customer khata
                </Link>
            </div>

            <div
                class="bg-card rounded-3xl border border-gray-200 p-4 shadow-[0_1px_2px_rgba(2,43,90,0.06)]"
            >
                <div class="mb-3 flex items-center justify-between gap-3">
                    <span class="eyebrow">
                        <Bell class="h-3.5 w-3.5" />
                        Alerts
                    </span>
                    <span
                        v-if="alerts?.length"
                        class="rounded-md bg-violet-100 px-2 py-0.5 text-[10px] font-black tracking-[0.12em] text-violet-600 uppercase ring-1 ring-violet-200 ring-inset"
                    >
                        {{ alerts.length }} open
                    </span>
                </div>
                <div class="space-y-2">
                    <div
                        v-for="(alert, index) in alerts"
                        :key="index"
                        class="flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-3"
                    >
                        <div
                            :class="[
                                'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset',
                                alertMeta[alert.type]?.iconChip ??
                                    'bg-violet-100 text-violet-600 ring-violet-200',
                            ]"
                        >
                            <component
                                :is="alertMeta[alert.type]?.icon ?? Bell"
                                class="h-4 w-4"
                            />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-900">
                                {{ alert.title }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ alert.message }}
                            </p>
                        </div>
                    </div>
                    <p
                        v-if="!alerts?.length"
                        class="py-4 text-center text-sm text-slate-500 italic"
                    >
                        No alerts. Your store is running smoothly.
                    </p>
                </div>
            </div>
        </div>

        <!-- Inventory at a glance -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                class="bg-card rounded-2xl border border-gray-200 p-4 shadow-[0_1px_2px_rgba(2,43,90,0.06)]"
            >
                <div class="mb-3 flex items-center justify-between">
                    <span class="eyebrow text-slate-500">Phones In Stock</span>
                    <div
                        class="rounded-lg bg-sky-100 p-2 text-sky-600 ring-1 ring-sky-200 ring-inset"
                    >
                        <Smartphone class="h-4 w-4" />
                    </div>
                </div>
                <div
                    class="tnum text-3xl font-black tracking-tight text-gray-900"
                >
                    {{ metrics?.inStockPhones ?? 0 }}
                </div>
                <div class="mt-1.5 text-xs text-slate-500">
                    Serialized IMEI inventory
                </div>
            </div>

            <div
                class="bg-card rounded-2xl border border-gray-200 p-4 shadow-[0_1px_2px_rgba(2,43,90,0.06)]"
            >
                <div class="mb-3 flex items-center justify-between">
                    <span class="eyebrow text-slate-500">Accessories</span>
                    <div
                        class="rounded-lg bg-gray-100 p-2 text-slate-600 ring-1 ring-gray-200 ring-inset"
                    >
                        <Boxes class="h-4 w-4" />
                    </div>
                </div>
                <div
                    class="tnum text-3xl font-black tracking-tight text-gray-900"
                >
                    {{ metrics?.totalAccessories ?? 0 }}
                </div>
                <div class="mt-1.5 text-xs text-slate-500">
                    Open stock across catalog
                </div>
            </div>

            <div
                class="bg-card rounded-2xl border border-gray-200 p-4 shadow-[0_1px_2px_rgba(2,43,90,0.06)]"
            >
                <div class="mb-3 flex items-center justify-between">
                    <span class="eyebrow text-slate-500">Stock Valuation</span>
                    <div
                        class="rounded-lg bg-violet-100 p-2 text-violet-600 ring-1 ring-violet-200 ring-inset"
                    >
                        <Database class="h-4 w-4" />
                    </div>
                </div>
                <div
                    class="tnum text-3xl font-black tracking-tight text-violet-600"
                >
                    Rs {{ (metrics?.totalValuation ?? 0).toLocaleString() }}
                </div>
                <div class="mt-1.5 text-xs text-slate-500">
                    Phones + accessories at cost
                </div>
            </div>

            <div
                class="bg-card rounded-2xl border border-gray-200 p-4 shadow-[0_1px_2px_rgba(2,43,90,0.06)]"
            >
                <div class="mb-3 flex items-center justify-between">
                    <span class="eyebrow text-slate-500">Repairs Pending</span>
                    <div
                        class="rounded-lg bg-amber-100 p-2 text-amber-600 ring-1 ring-amber-200 ring-inset"
                    >
                        <Wrench class="h-4 w-4" />
                    </div>
                </div>
                <div
                    class="tnum text-3xl font-black tracking-tight text-gray-900"
                >
                    {{ metrics?.pendingRepairs ?? 0 }}
                </div>
                <div class="mt-1.5 text-xs text-slate-500">
                    Jobs in the service pipeline
                </div>
            </div>
        </div>

        <!-- Quick navigation -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
            <Link
                :href="pos.index(currentTeamSlug).url"
                class="group bg-card rounded-2xl border border-gray-200 p-3 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-[0_18px_40px_rgba(139,92,246,0.15)]"
            >
                <div
                    class="mx-auto mb-2.5 flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-600 ring-1 ring-violet-200 ring-inset"
                >
                    <ShoppingCart
                        class="h-5 w-5 transition group-hover:scale-110"
                    />
                </div>
                <div class="text-sm font-bold text-gray-900">POS</div>
                <div
                    class="mt-0.5 text-[10px] tracking-[0.16em] text-slate-500 uppercase"
                >
                    Checkout
                </div>
            </Link>

            <Link
                :href="usedPhones.index(currentTeamSlug).url"
                class="group bg-card rounded-2xl border border-gray-200 p-3 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-[0_18px_40px_rgba(139,92,246,0.15)]"
            >
                <div
                    class="mx-auto mb-2.5 flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-600 ring-1 ring-violet-200 ring-inset"
                >
                    <ShieldCheck
                        class="h-5 w-5 transition group-hover:scale-110"
                    />
                </div>
                <div class="text-sm font-bold text-gray-900">Used Phones</div>
                <div
                    class="mt-0.5 text-[10px] tracking-[0.16em] text-slate-500 uppercase"
                >
                    Buying
                </div>
            </Link>

            <Link
                :href="repairs.index(currentTeamSlug).url"
                class="group bg-card rounded-2xl border border-gray-200 p-3 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-amber-400/40 hover:shadow-[0_18px_40px_rgba(251,191,36,0.12)]"
            >
                <div
                    class="mx-auto mb-2.5 flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 ring-1 ring-amber-200 ring-inset"
                >
                    <Wrench class="h-5 w-5 transition group-hover:scale-110" />
                </div>
                <div class="text-sm font-bold text-gray-900">Repair Lab</div>
                <div
                    class="mt-0.5 text-[10px] tracking-[0.16em] text-slate-500 uppercase"
                >
                    Jobs
                </div>
            </Link>

            <Link
                :href="customers.index(currentTeamSlug).url"
                class="group bg-card rounded-2xl border border-gray-200 p-3 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-sky-400/40 hover:shadow-[0_18px_40px_rgba(14,165,233,0.15)]"
            >
                <div
                    class="mx-auto mb-2.5 flex h-11 w-11 items-center justify-center rounded-2xl bg-sky-100 text-sky-600 ring-1 ring-sky-200 ring-inset"
                >
                    <Users class="h-5 w-5 transition group-hover:scale-110" />
                </div>
                <div class="text-sm font-bold text-gray-900">Customers</div>
                <div
                    class="mt-0.5 text-[10px] tracking-[0.16em] text-slate-500 uppercase"
                >
                    Khata
                </div>
            </Link>

            <Link
                :href="reports.index(currentTeamSlug).url"
                class="group bg-card rounded-2xl border border-gray-200 p-3 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-[0_18px_40px_rgba(139,92,246,0.15)]"
            >
                <div
                    class="mx-auto mb-2.5 flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-600 ring-1 ring-violet-200 ring-inset"
                >
                    <BarChart3
                        class="h-5 w-5 transition group-hover:scale-110"
                    />
                </div>
                <div class="text-sm font-bold text-gray-900">Analytics</div>
                <div
                    class="mt-0.5 text-[10px] tracking-[0.16em] text-slate-500 uppercase"
                >
                    Reports
                </div>
            </Link>

            <Link
                :href="inventory.index(currentTeamSlug).url"
                class="group bg-card rounded-2xl border border-gray-200 p-3 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-gray-200 hover:shadow-[0_1px_3px_rgba(2,43,90,0.08)]"
            >
                <div
                    class="mx-auto mb-2.5 flex h-11 w-11 items-center justify-center rounded-2xl bg-gray-100 text-slate-600 ring-1 ring-gray-200 ring-inset"
                >
                    <Boxes class="h-5 w-5 transition group-hover:scale-110" />
                </div>
                <div class="text-sm font-bold text-gray-900">Inventory</div>
                <div
                    class="mt-0.5 text-[10px] tracking-[0.16em] text-slate-500 uppercase"
                >
                    Catalog
                </div>
            </Link>
        </div>

        <!-- Recent sales -->
        <div
            class="bg-card rounded-3xl border border-gray-200 p-4 shadow-[0_1px_2px_rgba(2,43,90,0.06)]"
        >
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black tracking-tight text-gray-900">
                        Recent Sales
                    </h2>
                    <p class="text-xs text-slate-500">
                        Latest transactions across the store
                    </p>
                </div>
                <Link
                    :href="reports.index(currentTeamSlug).url"
                    class="text-xs font-bold text-violet-600 hover:text-violet-600"
                >
                    View all reports →
                </Link>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-[11px] font-bold tracking-[0.12em] text-slate-500 uppercase"
                        >
                            <th class="px-4 py-2.5">Invoice</th>
                            <th class="px-4 py-2.5">Customer</th>
                            <th class="px-4 py-2.5">Payment</th>
                            <th class="px-4 py-2.5">Net Amount</th>
                            <th class="px-4 py-2.5">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-if="!recentSales || recentSales.length === 0">
                            <td
                                colspan="5"
                                class="py-6 text-center text-sm text-slate-500 italic"
                            >
                                No sales were recorded yet.
                            </td>
                        </tr>
                        <tr
                            v-for="sale in recentSales"
                            :key="sale.id"
                            class="hover:bg-gray-50"
                        >
                            <td
                                class="tnum px-4 py-2.5 font-black text-violet-600"
                            >
                                {{ sale.invoice_no }}
                            </td>
                            <td class="px-4 py-2.5 font-semibold text-gray-900">
                                {{ sale.customer }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-[11px] font-bold tracking-[0.12em] text-slate-500 uppercase"
                            >
                                {{ sale.payment_method }}
                            </td>
                            <td
                                class="tnum px-4 py-2.5 font-black text-violet-600"
                            >
                                Rs {{ sale.net_amount.toLocaleString() }}
                            </td>
                            <td class="tnum px-4 py-2.5 text-xs text-slate-500">
                                {{ sale.created_at }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
