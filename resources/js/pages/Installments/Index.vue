<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarClock,
    CircleDollarSign,
    Plus,
    SlidersHorizontal,
    Wallet,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import installments from '@/routes/installments';
import type { Team } from '@/types';

// Table Column Customizer State
const defaultVisibleColumns = {
    customer: true,
    plan: true,
    next_due: true,
    status: true,
    collection: true,
};

const visibleColumns = ref({ ...defaultVisibleColumns });

const installmentColumnLabels: Record<
    keyof typeof defaultVisibleColumns,
    string
> = {
    customer: 'Customer',
    plan: 'Plan Details',
    next_due: 'Next Due Date',
    status: 'Status',
    collection: 'Collection / Actions',
};

const STORAGE_KEY = 'faizan_mobile_installments_table_columns_v1';
const PER_PAGE_STORAGE_KEY = 'faizan_mobile_installments_per_page_v1';

onMounted(() => {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            visibleColumns.value = {
                ...defaultVisibleColumns,
                ...JSON.parse(saved),
            };
        }
        const savedPerPage = localStorage.getItem(PER_PAGE_STORAGE_KEY);
        if (savedPerPage && Number(savedPerPage) !== perPage.value) {
            perPage.value = Number(savedPerPage);
        }
    } catch (e) {
        console.error(e);
    }
});

const toggleInstallmentColumn = (key: string) => {
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

interface Plan {
    id: number;
    customer: { name: string; phone: string };
    total_amount: number | string;
    down_payment: number | string;
    monthly_amount: number | string;
    duration_months: number;
    paid_installments: number;
    next_due_date: string;
    status: string;
}
const props = defineProps<{
    plans: { data: Plan[] };
    customers: Array<{ id: number; name: string; phone: string }>;
    filters?: { per_page?: number };
    summary: {
        active_plans: number;
        outstanding: number;
        collected: number;
        overdue_count: number;
        due_soon_count: number;
    };
}>();
const page = usePage();
const team = computed(
    () => (page.props.currentTeam as Team | undefined)?.slug || 'default',
);
const perPage = ref(props.filters?.per_page || 15);

watch(perPage, (newPerPage) => {
    try {
        localStorage.setItem(PER_PAGE_STORAGE_KEY, String(newPerPage));
    } catch (e) {
        console.error(e);
    }
    router.get(
        installments.index(team.value).url,
        { per_page: newPerPage },
        { preserveState: true, replace: true },
    );
});

const showCreate = ref(false);
const paymentPlan = ref<number | null>(null);
const planForm = useForm({
    customer_id: '',
    total_amount: '',
    down_payment: '',
    duration_months: '6',
    next_due_date: new Date().toISOString().slice(0, 10),
    notes: '',
});
const paymentForm = useForm({
    amount: '',
    payment_method: 'cash',
    reference_id: '',
    notes: '',
});
const money = (value: number | string) =>
    `Rs. ${Number(value || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 })}`;
const createPlan = () =>
    planForm.post(installments.store(team.value).url, {
        onSuccess: () => {
            planForm.reset();
            showCreate.value = false;
        },
    });
const collectPayment = (planId: number) =>
    paymentForm.post(
        installments.payments.store({ current_team: team.value, plan: planId })
            .url,
        {
            onSuccess: () => {
                paymentForm.reset();
                paymentPlan.value = null;
            },
        },
    );

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam ? '/dashboard' : '/',
            },
            {
                title: 'Installment Plans',
                href: layoutProps.currentTeam
                    ? installments.index(layoutProps.currentTeam.slug).url
                    : '/installments',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Installment Plans" />
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <section
            class="bg-card/60 flex flex-col gap-4 rounded-2xl border border-gray-200 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="eyebrow mb-2">Customer financing</p>
                <h1
                    class="flex items-center gap-2 text-2xl font-bold text-gray-900"
                >
                    <CalendarClock class="h-7 w-7 text-[#003B7D]" />
                    Installment Plans
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Track mobile kist schedules, due dates and collections.
                </p>
            </div>
            <Button
                class="gap-2 bg-[#003B7D] font-bold text-white shadow-sm hover:bg-[#002b5c]"
                @click="showCreate = !showCreate"
                ><Plus class="h-4 w-4" /> New Plan</Button
            >
        </section>
        <section
            v-if="showCreate"
            class="rounded-2xl border border-[#003B7D]/20 bg-[#003B7D]/5 p-5 backdrop-blur-xl"
        >
            <form
                class="grid gap-4 md:grid-cols-2 lg:grid-cols-5"
                @submit.prevent="createPlan"
            >
                <div class="grid gap-2 lg:col-span-2">
                    <Label for="plan-customer">Customer</Label
                    ><select
                        id="plan-customer"
                        v-model="planForm.customer_id"
                        required
                        class="h-10 rounded-md border border-gray-200 bg-gray-50 px-3 text-sm text-gray-900"
                    >
                        <option value="" disabled>Select customer</option>
                        <option
                            v-for="customer in customers"
                            :key="customer.id"
                            :value="customer.id"
                        >
                            {{ customer.name }} - {{ customer.phone }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="plan-total">Total price</Label
                    ><Input
                        id="plan-total"
                        v-model="planForm.total_amount"
                        required
                        type="number"
                        min="1"
                        class="border-gray-200 bg-gray-50 text-gray-900"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="plan-down">Down payment</Label
                    ><Input
                        id="plan-down"
                        v-model="planForm.down_payment"
                        required
                        type="number"
                        min="0"
                        class="border-gray-200 bg-gray-50 text-gray-900"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="plan-duration">Months</Label
                    ><Input
                        id="plan-duration"
                        v-model="planForm.duration_months"
                        required
                        type="number"
                        min="1"
                        max="60"
                        class="border-gray-200 bg-gray-50 text-gray-900"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="plan-due">First due date</Label
                    ><Input
                        id="plan-due"
                        v-model="planForm.next_due_date"
                        required
                        type="date"
                        class="border-gray-200 bg-gray-50 text-gray-900"
                    />
                </div>
                <div class="flex items-end">
                    <Button
                        class="w-full bg-[#003B7D] font-semibold text-white shadow-sm hover:bg-[#002b5c]"
                        :disabled="planForm.processing"
                        >Create plan</Button
                    >
                </div>
            </form>
        </section>
        <div
            v-if="summary.overdue_count > 0 || summary.due_soon_count > 0"
            :class="
                summary.overdue_count > 0
                    ? 'border-rose-300 bg-rose-50 text-rose-700 dark:border-rose-500/40 dark:bg-rose-950/40 dark:text-rose-300'
                    : 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-950/40 dark:text-amber-300'
            "
            class="flex flex-wrap items-center gap-2 rounded-2xl border px-4 py-3 text-xs font-bold backdrop-blur-xl"
        >
            <AlertTriangle class="h-4 w-4" />
            <span v-if="summary.overdue_count > 0">
                {{ summary.overdue_count }} installment(s) overdue
                <span class="mx-1 text-slate-400">•</span>
            </span>
            <span v-if="summary.due_soon_count > 0">
                {{ summary.due_soon_count }} due within the next 30 days
            </span>
            <button
                type="button"
                class="ml-auto underline underline-offset-2 hover:opacity-80"
            >
                Review plans below
            </button>
        </div>
        <section class="grid gap-4 sm:grid-cols-3">
            <div
                class="bg-card/60 rounded-2xl border border-gray-200 p-4 backdrop-blur-xl"
            >
                <p class="text-xs tracking-wider text-slate-500 uppercase">
                    Active plans
                </p>
                <p class="tnum mt-2 text-2xl font-bold text-gray-900">
                    {{ summary.active_plans }}
                </p>
            </div>
            <div
                class="bg-card/60 rounded-2xl border border-gray-200 p-4 backdrop-blur-xl"
            >
                <p class="text-xs tracking-wider text-slate-500 uppercase">
                    Outstanding
                </p>
                <p class="tnum mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">
                    {{ money(summary.outstanding) }}
                </p>
            </div>
            <div
                class="bg-card/60 rounded-2xl border border-gray-200 p-4 backdrop-blur-xl"
            >
                <p class="text-xs tracking-wider text-slate-500 uppercase">
                    Collected
                </p>
                <p class="tnum mt-2 text-2xl font-bold text-[#003B7D]">
                    {{ money(summary.collected) }}
                </p>
            </div>
        </section>
        <section
            class="bg-card/60 overflow-hidden rounded-2xl border border-gray-200 backdrop-blur-xl"
        >
            <div
                class="flex items-center justify-between border-b border-gray-200 p-4"
            >
                <h3 class="text-base font-bold text-gray-900">
                    Installment Contracts
                </h3>
                <!-- Table Columns Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 gap-1.5 text-xs font-semibold"
                        >
                            <SlidersHorizontal
                                class="h-3.5 w-3.5 text-[#003B7D]"
                            />
                            <span>Columns</span>
                            <span
                                class="ml-1 rounded-full bg-[#003B7D]/10 px-1.5 py-0.5 text-[10px] font-bold text-[#003B7D]"
                            >
                                {{ activeColumnCount }}/5
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
                                class="cursor-pointer text-[11px] font-semibold text-[#003B7D] hover:underline"
                            >
                                Reset All
                            </button>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator class="my-1" />
                        <div
                            v-for="(label, key) in installmentColumnLabels"
                            :key="key"
                            @click.stop="toggleInstallmentColumn(key)"
                            class="flex cursor-pointer items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors select-none hover:bg-slate-100"
                        >
                            <span>{{ label }}</span>
                            <input
                                type="checkbox"
                                :checked="
                                    visibleColumns[
                                        key as keyof typeof visibleColumns
                                    ]
                                "
                                @change="toggleInstallmentColumn(key)"
                                @click.stop
                                class="h-4 w-4 cursor-pointer rounded border-slate-300 text-[#003B7D] focus:ring-[#003B7D]"
                            />
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
                        class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-xs font-medium text-slate-700 shadow-2xs focus:border-[#003B7D] focus:outline-none"
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

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-gray-200 text-xs tracking-wider text-slate-500 uppercase"
                    >
                        <tr>
                            <th
                                v-if="visibleColumns.customer"
                                class="px-5 py-4"
                            >
                                Customer
                            </th>
                            <th v-if="visibleColumns.plan" class="px-5 py-4">
                                Plan
                            </th>
                            <th
                                v-if="visibleColumns.next_due"
                                class="px-5 py-4"
                            >
                                Next due
                            </th>
                            <th v-if="visibleColumns.status" class="px-5 py-4">
                                Status
                            </th>
                            <th
                                v-if="visibleColumns.collection"
                                class="px-5 py-4 text-right"
                            >
                                Collection
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr
                            v-for="plan in plans.data"
                            :key="plan.id"
                            class="text-slate-600 hover:bg-gray-50"
                        >
                            <td
                                v-if="visibleColumns.customer"
                                class="px-5 py-4"
                            >
                                <div class="font-semibold text-gray-900">
                                    {{ plan.customer.name }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ plan.customer.phone }}
                                </div>
                            </td>
                            <td v-if="visibleColumns.plan" class="px-5 py-4">
                                <div class="tnum text-gray-900">
                                    {{ money(plan.monthly_amount) }} / month
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ plan.paid_installments }} of
                                    {{ plan.duration_months }} paid
                                </div>
                            </td>
                            <td
                                v-if="visibleColumns.next_due"
                                class="tnum px-5 py-4 text-slate-500"
                            >
                                {{ plan.next_due_date }}
                            </td>
                            <td v-if="visibleColumns.status" class="px-5 py-4">
                                <span
                                    :class="
                                        plan.status === 'active'
                                            ? 'text-amber-600 dark:text-amber-400'
                                            : 'text-[#003B7D] dark:text-sky-300'
                                    "
                                    >{{ plan.status }}</span
                                >
                            </td>
                            <td
                                v-if="visibleColumns.collection"
                                class="px-5 py-4 text-right"
                            >
                                <Button
                                    size="sm"
                                    class="border-[#003B7D]/20 bg-[#003B7D]/5 text-[#003B7D] hover:bg-[#003B7D]/10"
                                    :disabled="plan.status !== 'active'"
                                    @click="paymentPlan = plan.id"
                                    ><Wallet class="mr-1 h-4 w-4" />
                                    Collect</Button
                                >
                                <form
                                    v-if="paymentPlan === plan.id"
                                    class="mt-3 flex justify-end gap-2"
                                    @submit.prevent="collectPayment(plan.id)"
                                >
                                    <Input
                                        v-model="paymentForm.amount"
                                        required
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        class="w-28 border-gray-200 bg-gray-50 text-gray-900"
                                        placeholder="Amount"
                                    /><Button
                                        size="sm"
                                        class="bg-[#003B7D] text-white hover:bg-[#002b5c]"
                                        :disabled="paymentForm.processing"
                                        ><CircleDollarSign
                                            class="mr-1 h-4 w-4"
                                        />
                                        Save</Button
                                    >
                                </form>
                            </td>
                        </tr>
                        <tr v-if="plans.data.length === 0">
                            <td
                                colspan="5"
                                class="px-5 py-12 text-center text-slate-500"
                            >
                                No installment plans found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
