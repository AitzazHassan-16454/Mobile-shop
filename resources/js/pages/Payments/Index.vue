<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDownCircle,
    ArrowUpCircle,
    Banknote,
    CalendarDays,
    HandCoins,
    Search,
    ShoppingBag,
    Wallet,
    Wrench,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Team } from '@/types';

const page = usePage();
const currentTeamSlug = computed(
    () => (page.props.currentTeam as Team | undefined)?.slug || 'default',
);

interface PaymentRowData {
    source:
        | 'sale'
        | 'customer_payment'
        | 'installment'
        | 'supplier_payment'
        | 'expense';
    ref: string;
    description: string;
    method: string;
    party: string;
    direction: 'in' | 'out';
    amount: number;
    paid_at: string;
}

interface PartyOption {
    id: number;
    name: string;
    current_balance: number;
}

const props = defineProps<{
    payments: {
        data: PaymentRowData[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    customers: PartyOption[];
    suppliers: PartyOption[];
    filters: {
        search: string;
        source: string;
        direction: string;
        method: string;
        date_from: string;
        date_to: string;
        per_page: number;
    };
    summary: {
        received_this_month: number;
        paid_this_month: number;
        received_all_time: number;
        paid_all_time: number;
    };
}>();

const search = ref(props.filters.search || '');
const sourceFilter = ref(props.filters.source || 'all');
const directionFilter = ref(props.filters.direction || 'all');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');

const applyFilters = () => {
    router.get(
        `/${currentTeamSlug.value}/all-payments`,
        {
            search: search.value || undefined,
            source:
                sourceFilter.value === 'all' ? undefined : sourceFilter.value,
            direction:
                directionFilter.value === 'all'
                    ? undefined
                    : directionFilter.value,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const setDirection = (value: string) => {
    directionFilter.value = value;
    applyFilters();
};

const setSource = () => {
    applyFilters();
};

const clearFilters = () => {
    search.value = '';
    sourceFilter.value = 'all';
    directionFilter.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
};

const sourceStyles: Record<string, string> = {
    sale: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-900/40',
    customer_payment:
        'bg-teal-50 text-teal-700 border-teal-200 dark:bg-teal-950/60 dark:text-teal-300 dark:border-teal-900/40',
    installment:
        'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-900/40',
    supplier_payment:
        'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-900/40',
    expense:
        'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-900/40',
};

const sourceLabels: Record<string, string> = {
    sale: 'Sale',
    customer_payment: 'Customer Payment',
    installment: 'Installment',
    supplier_payment: 'Supplier Payment',
    expense: 'Shop Expense',
};

const sourceIcons: Record<string, Component> = {
    sale: ShoppingBag,
    customer_payment: HandCoins,
    installment: CalendarDays,
    supplier_payment: ArrowUpCircle,
    expense: Wrench,
};

const currency = (val: number | string) =>
    `Rs ${Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
const netThisMonth =
    props.summary.received_this_month - props.summary.paid_this_month;

// Quick record payment
const isRecordOpen = ref(false);
const recordType = ref<'customer' | 'supplier'>('customer');
const recordPartyId = ref<number | null>(null);
const recordAmount = ref<number | null>(null);
const paymentMethod = ref('cash');
const notes = ref('');
const isSaving = ref(false);

const openRecord = (type: 'customer' | 'supplier') => {
    recordType.value = type;
    recordPartyId.value = null;
    recordAmount.value = null;
    paymentMethod.value = 'cash';
    notes.value = '';
    isRecordOpen.value = true;
};

const availableParties = computed(() =>
    recordType.value === 'customer' ? props.customers : props.suppliers,
);

const selectedPartyBalance = computed(() => {
    const party = availableParties.value.find(
        (p) => p.id === recordPartyId.value,
    );
    return party ? Number(party.current_balance) : 0;
});

const submitPayment = async () => {
    if (!recordPartyId.value) {
        toast.error('Select Party', {
            description:
                'Choose a customer or supplier to record the payment for.',
        });
        return;
    }
    if (!recordAmount.value || recordAmount.value <= 0) {
        toast.error('Invalid Amount', {
            description: 'Enter an amount greater than zero.',
        });
        return;
    }

    isSaving.value = true;
    try {
        const payload =
            recordType.value === 'customer'
                ? {
                      amount: recordAmount.value,
                      payment_method: paymentMethod.value,
                      notes: notes.value,
                  }
                : {
                      amount: recordAmount.value,
                      reference_id: paymentMethod.value,
                      notes: notes.value,
                  };

        await router.post(
            `/${currentTeamSlug.value}/${recordType.value}s/${recordPartyId.value}/payments`,
            payload,
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success('Payment Recorded', {
                        description: 'Payment saved and ledger updated.',
                    });
                    isRecordOpen.value = false;
                },
                onError: (errors) => {
                    toast.error('Could Not Save', {
                        description:
                            Object.values(errors).flat().join(' ') ||
                            'Validation failed.',
                    });
                },
            },
        );
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <Head title="All Payments" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <section
            class="glass-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="eyebrow mb-1.5">Cash Book Explorer</p>
                <h1
                    class="flex items-center gap-2.5 text-2xl font-black text-slate-900"
                >
                    <Wallet class="text-primary h-7 w-7" /> All Payments
                </h1>
                <p class="mt-1 text-xs font-medium text-slate-500">
                    Every rupee in and out — sales, khata receipts,
                    installments, supplier payments and expenses.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="gap-2 text-xs font-bold"
                    @click="openRecord('customer')"
                >
                    <ArrowDownCircle class="h-4 w-4 text-emerald-600 dark:text-emerald-400" /> Receive
                </Button>
                <Button
                    type="button"
                    class="bg-primary hover:bg-primary/90 gap-2 font-bold text-white shadow-md"
                    @click="openRecord('supplier')"
                >
                    <ArrowUpCircle class="h-4 w-4" /> Pay Out
                </Button>
            </div>
        </section>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="glass-card p-4">
                <p class="eyebrow text-emerald-600 dark:text-emerald-400">Received (This Month)</p>
                <p
                    class="mt-1 text-xl font-black text-emerald-600 dark:text-emerald-400"
                >
                    {{ currency(summary.received_this_month) }}
                </p>
                <p class="mt-0.5 text-[10px] font-semibold text-slate-400">
                    khata + installment receipts
                </p>
            </div>
            <div class="glass-card p-4">
                <p class="eyebrow text-rose-500 dark:text-rose-400">Paid Out (This Month)</p>
                <p
                    class="mt-1 text-xl font-black text-rose-500 dark:text-rose-400"
                >
                    {{ currency(summary.paid_this_month) }}
                </p>
                <p class="mt-0.5 text-[10px] font-semibold text-slate-400">
                    supplier + shop expenses
                </p>
            </div>
            <div class="glass-card p-4">
                <p class="eyebrow text-primary">Net Movement</p>
                <p
                    :class="
                        netThisMonth >= 0
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-rose-500 dark:text-rose-400'
                    "
                    class="mt-1 text-xl font-black"
                >
                    {{ currency(Math.abs(netThisMonth)) }}
                </p>
                <p class="mt-0.5 text-[10px] font-semibold text-slate-400">
                    {{ netThisMonth >= 0 ? 'net received' : 'net spent' }}
                </p>
            </div>
            <div class="glass-card p-4">
                <p class="eyebrow text-slate-500">All-Time Received vs Paid</p>
                <p class="mt-1 text-xl font-black text-slate-900">
                    {{ currency(summary.received_all_time) }}
                </p>
                <p class="mt-0.5 text-[10px] font-semibold text-slate-400">
                    vs {{ currency(summary.paid_all_time) }} paid
                </p>
            </div>
        </div>

        <section
            class="glass-card flex flex-col gap-3 p-3.5 lg:flex-row lg:items-center"
        >
            <div class="relative flex-1">
                <Search
                    class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                />
                <input
                    v-model="search"
                    placeholder="Search party, reference, notes..."
                    class="focus:border-primary h-10 w-full rounded-xl border border-slate-200 bg-white/70 pr-3 pl-9 text-xs font-semibold placeholder:text-slate-400 focus:outline-none"
                    @keydown.enter="applyFilters"
                />
            </div>
            <div class="flex items-center gap-1.5 overflow-x-auto">
                <button
                    v-for="option in ['all', 'in', 'out']"
                    :key="option"
                    type="button"
                    @click="setDirection(option)"
                    :class="
                        directionFilter === option
                            ? 'bg-primary font-black text-white'
                            : 'bg-white/60 font-bold text-slate-600 hover:bg-white'
                    "
                    class="h-10 rounded-xl px-3 text-xs whitespace-nowrap capitalize transition"
                >
                    {{ option === 'all' ? 'Both' : option }}
                </button>
                <select
                    v-model="sourceFilter"
                    @change="setSource"
                    class="focus:border-primary h-10 rounded-xl border border-slate-200 bg-white/70 px-2 text-xs font-bold text-slate-600 focus:outline-none"
                >
                    <option value="all">All Sources</option>
                    <option value="sale">Sales</option>
                    <option value="customer_payment">Customer Payments</option>
                    <option value="installment">Installments</option>
                    <option value="supplier_payment">Supplier Payments</option>
                    <option value="expense">Expenses</option>
                </select>
                <input
                    v-model="dateFrom"
                    type="date"
                    @change="applyFilters"
                    class="focus:border-primary h-10 rounded-xl border border-slate-200 bg-white/70 px-2 text-xs font-bold text-slate-600 focus:outline-none"
                />
                <input
                    v-model="dateTo"
                    type="date"
                    @change="applyFilters"
                    class="focus:border-primary h-10 rounded-xl border border-slate-200 bg-white/70 px-2 text-xs font-bold text-slate-600 focus:outline-none"
                />
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    class="h-10 text-[10px] font-bold text-slate-400"
                    @click="clearFilters"
                >
                    Clear
                </Button>
            </div>
        </section>

        <section class="glass-card overflow-hidden rounded-2xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-slate-100 bg-white/60">
                        <tr
                            class="text-[10px] font-black tracking-wider text-slate-400 uppercase"
                        >
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3">Party / Ref</th>
                            <th class="px-4 py-3">Details</th>
                            <th class="px-4 py-3">Method</th>
                            <th class="px-4 py-3">Direction</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="(payment, index) in payments.data"
                            :key="`${payment.source}-${payment.ref}-${payment.paid_at}-${index}`"
                        >
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-black',
                                        sourceStyles[payment.source],
                                    ]"
                                >
                                    {{ sourceLabels[payment.source] }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-800">
                                    {{ payment.party }}
                                </div>
                                <div
                                    class="font-mono text-[10px] text-slate-400"
                                >
                                    #{{ payment.ref }}
                                </div>
                            </td>
                            <td
                                class="max-w-[200px] truncate px-4 py-3 font-semibold text-slate-500"
                            >
                                {{ payment.description }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="payment.method"
                                    class="font-bold text-slate-500 capitalize"
                                    >{{ payment.method }}</span
                                >
                                <span v-else class="text-slate-300">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="
                                        payment.direction === 'in'
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-rose-500 dark:text-rose-400'
                                    "
                                    class="inline-flex items-center gap-1 text-[10px] font-black uppercase"
                                >
                                    <ArrowDownCircle
                                        v-if="payment.direction === 'in'"
                                        class="h-3 w-3"
                                    />
                                    <ArrowUpCircle v-else class="h-3 w-3" />
                                    {{ payment.direction }}
                                </span>
                            </td>
                            <td
                                :class="
                                    payment.direction === 'in'
                                        ? 'text-emerald-600'
                                        : 'text-rose-500'
                                "
                                class="px-4 py-3 text-right font-black"
                            >
                                {{ payment.direction === 'in' ? '+' : '−'
                                }}{{ currency(payment.amount) }}
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                {{
                                    new Date(
                                        payment.paid_at,
                                    ).toLocaleDateString()
                                }}
                            </td>
                        </tr>
                        <tr v-if="payments.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center">
                                <Banknote
                                    class="mx-auto mb-2 h-8 w-8 text-slate-200"
                                />
                                <p class="text-xs font-bold text-slate-400">
                                    No payments match these filters.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <Dialog
        :open="isRecordOpen"
        @update:open="(value: boolean) => !value && (isRecordOpen = false)"
    >
        <DialogContent
            class="max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
        >
            <DialogHeader>
                <DialogTitle class="text-lg font-black text-slate-900">
                    {{
                        recordType === 'customer'
                            ? 'Record Received Payment'
                            : 'Record Supplier Payment'
                    }}
                </DialogTitle>
                <DialogDescription class="text-xs text-slate-500">
                    Posts directly to the party ledger.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-2">
                <div>
                    <label
                        class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                        >{{
                            recordType === 'customer' ? 'Customer' : 'Supplier'
                        }}
                        *</label
                    >
                    <select
                        v-model="recordPartyId"
                        class="focus:border-primary h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-semibold focus:outline-none"
                    >
                        <option :value="null" disabled>
                            Select {{ recordType }}...
                        </option>
                        <option
                            v-for="party in availableParties"
                            :key="party.id"
                            :value="party.id"
                        >
                            {{ party.name }} ({{
                                currency(party.current_balance)
                            }})
                        </option>
                    </select>
                    <p
                        v-if="recordPartyId"
                        class="mt-1 text-[10px] font-bold text-slate-400"
                    >
                        Current balance: {{ currency(selectedPartyBalance) }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                        >Amount *</label
                    >
                    <input
                        v-model="recordAmount"
                        type="number"
                        min="0"
                        step="0.01"
                        class="focus:border-primary h-10 w-full rounded-xl border border-slate-300 px-3 text-sm font-black focus:outline-none"
                    />
                </div>

                <div>
                    <label
                        class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                    >
                        {{
                            recordType === 'customer'
                                ? 'Payment Method'
                                : 'Reference'
                        }}
                    </label>
                    <select
                        v-model="paymentMethod"
                        class="focus:border-primary h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-semibold focus:outline-none"
                    >
                        <option value="cash">Cash</option>
                        <option value="jazzcash">JazzCash</option>
                        <option value="easypaisa">EasyPaisa</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>

                <div>
                    <label
                        class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                        >Notes</label
                    >
                    <input
                        v-model="notes"
                        placeholder="Optional note..."
                        class="focus:border-primary h-10 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold focus:outline-none"
                    />
                </div>
            </div>

            <DialogFooter class="pt-3">
                <Button
                    type="button"
                    variant="outline"
                    @click="isRecordOpen = false"
                    >Cancel</Button
                >
                <Button
                    type="button"
                    :disabled="isSaving"
                    class="bg-primary hover:bg-primary/90 font-bold text-white"
                    @click="submitPayment"
                >
                    <span
                        v-if="isSaving"
                        class="mr-1.5 inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"
                    ></span>
                    Save Payment
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
