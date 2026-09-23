<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    CheckCircle2,
    Clock,
    DollarSign,
    Plus,
    Printer,
    Receipt,
    SlidersHorizontal,
    Wallet,
    X,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import shifts from '@/routes/shifts';

// Table Column Customizer State
const defaultVisibleColumns = {
    id: true,
    cashier: true,
    opened: true,
    closed: true,
    float: true,
    expected: true,
    actual: true,
    discrepancy: true,
    status: true,
};

const visibleColumns = ref({ ...defaultVisibleColumns });

const shiftColumnLabels: Record<keyof typeof defaultVisibleColumns, string> = {
    id: 'Shift ID',
    cashier: 'Cashier',
    opened: 'Opened',
    closed: 'Closed',
    float: 'Float',
    expected: 'Expected Cash',
    actual: 'Actual Cash',
    discrepancy: 'Discrepancy',
    status: 'Status',
};

const STORAGE_KEY = 'faizan_mobile_shifts_table_columns_v1';

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

const toggleShiftColumn = (key: string) => {
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

interface ShiftExpense {
    id: number;
    category: string;
    amount: number;
    notes?: string;
    created_at: string;
}

interface ActiveShiftData {
    id: number;
    user: string;
    opened_at: string;
    opening_float: number;
    cash_sales: number;
    jazzcash_sales: number;
    easypaisa_sales: number;
    bank_sales: number;
    udhaar_sales: number;
    wasooli_cash: number;
    expenses_amount: number;
    expected_cash: number;
    expenses: ShiftExpense[];
}

interface PastShiftData {
    id: number;
    cashier: string;
    opened_at: string;
    closed_at?: string;
    opening_float: number;
    cash_sales: number;
    expected_cash: number;
    actual_cash?: number;
    discrepancy?: number;
    status: string;
    notes?: string;
}

const props = defineProps<{
    activeShift: ActiveShiftData | null;
    pastShifts: PastShiftData[];
}>();

const page = usePage();
const currentTeamSlug = computed(
    () =>
        (page.props.currentTeam as { slug: string } | undefined)?.slug ??
        'default',
);

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Shift & Cash Drawer', href: '/shifts' },
        ],
    }),
});

// Open Shift Form
const openShiftForm = useForm({
    opening_float: 0,
});

const submitOpenShift = () => {
    openShiftForm.post(shifts.open(currentTeamSlug.value).url, {
        onSuccess: () => openShiftForm.reset(),
    });
};

// Add Expense Form & Modal
const showExpenseModal = ref(false);
const expenseForm = useForm({
    category: 'Chaye / Khana',
    amount: '',
    notes: '',
});

const categories = [
    'Chaye / Khana',
    'Electricity / Bill',
    'Shop Maintenance',
    'Repair Parts',
    'Misc Expenses',
];

const submitExpense = () => {
    expenseForm.post(shifts.expense.store(currentTeamSlug.value).url, {
        onSuccess: () => {
            expenseForm.reset();
            showExpenseModal.value = false;
        },
    });
};

// Close Shift Form & Modal
const showCloseShiftModal = ref(false);
const closeShiftForm = useForm({
    actual_cash: '',
    notes: '',
});

const liveDiscrepancy = computed(() => {
    if (!props.activeShift || closeShiftForm.actual_cash === '') return 0;
    const actual = parseFloat(closeShiftForm.actual_cash) || 0;
    return actual - props.activeShift.expected_cash;
});

const submitCloseShift = () => {
    closeShiftForm.post(shifts.close(currentTeamSlug.value).url, {
        onSuccess: () => {
            closeShiftForm.reset();
            showCloseShiftModal.value = false;
        },
    });
};

// Print Shift Slip
const printShiftSlip = () => {
    window.print();
};
</script>

<template>
    <Head title="Shift & Cash Drawer Management" />

    <div class="w-full space-y-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl md:flex-row md:items-center md:justify-between"
        >
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-bold text-gray-900"
                >
                    <Receipt class="h-7 w-7 text-[#003B7D]" />
                    Shift & Cash Drawer Management
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Register open float, shop daily expenses, and shift closing
                    cash reconciliation.
                </p>
            </div>

            <div v-if="activeShift" class="flex items-center gap-3">
                <button
                    @click="showExpenseModal = true"
                    class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-600 transition hover:bg-amber-100"
                >
                    <Plus class="h-4 w-4" />
                    Add Shop Expense
                </button>
                <button
                    @click="showCloseShiftModal = true"
                    class="flex items-center gap-2 rounded-lg bg-[#003B7D] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                >
                    <CheckCircle2 class="h-4 w-4" />
                    Close Shift & Reconcile
                </button>
            </div>
        </div>

        <!-- Active Shift Section -->
        <div
            v-if="activeShift"
            class="space-y-6 rounded-2xl border border-gray-200 bg-gray-50 p-6 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
        >
            <div
                class="flex items-center justify-between border-b border-gray-200 pb-4"
            >
                <div class="flex items-center gap-3">
                    <span class="relative flex h-3 w-3">
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#003B7D]/60 opacity-75"
                        ></span>
                        <span
                            class="relative inline-flex h-3 w-3 rounded-full bg-[#003B7D]"
                        ></span>
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">
                            Active Shift in Progress
                        </h2>
                        <p class="text-xs text-slate-500">
                            Cashier:
                            <span class="font-medium text-slate-600">{{
                                activeShift.user
                            }}</span>
                            • Opened at {{ activeShift.opened_at }}
                        </p>
                    </div>
                </div>
                <button
                    @click="printShiftSlip"
                    class="flex items-center gap-1.5 rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-gray-100 print:hidden"
                >
                    <Printer class="h-3.5 w-3.5" />
                    Print Summary Slip
                </button>
            </div>

            <!-- Financial Metrics Cards -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <span
                        class="block text-xs font-medium tracking-wider text-slate-500 uppercase"
                        >Opening Float</span
                    >
                    <span
                        class="tnum mt-1 block text-xl font-bold text-gray-900"
                        >Rs
                        {{ activeShift.opening_float.toLocaleString() }}</span
                    >
                </div>

                <div
                    class="rounded-xl border border-[#003B7D]/20 bg-[#003B7D]/5 p-4"
                >
                    <span
                        class="block text-xs font-medium tracking-wider text-[#003B7D] uppercase"
                        >Cash Sales</span
                    >
                    <span
                        class="tnum mt-1 block text-xl font-bold text-[#003B7D]"
                        >+ Rs
                        {{ activeShift.cash_sales.toLocaleString() }}</span
                    >
                </div>

                <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
                    <span
                        class="block text-xs font-medium tracking-wider text-sky-600 uppercase"
                        >Wasooli (Cash In)</span
                    >
                    <span class="tnum mt-1 block text-xl font-bold text-sky-600"
                        >+ Rs
                        {{ activeShift.wasooli_cash.toLocaleString() }}</span
                    >
                </div>

                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                    <span
                        class="block text-xs font-medium tracking-wider text-rose-600 uppercase"
                        >Shop Expenses</span
                    >
                    <span
                        class="tnum mt-1 block text-xl font-bold text-rose-600"
                        >- Rs
                        {{ activeShift.expenses_amount.toLocaleString() }}</span
                    >
                </div>

                <div
                    class="col-span-2 rounded-xl border border-[#003B7D] bg-[#003B7D] p-4 shadow-sm"
                >
                    <span
                        class="block text-xs font-semibold tracking-wider text-blue-200 uppercase"
                        >Expected Cash in Drawer</span
                    >
                    <span class="tnum mt-1 block text-2xl font-black text-white"
                        >Rs
                        {{ activeShift.expected_cash.toLocaleString() }}</span
                    >
                </div>
            </div>

            <!-- Non-Cash Sales Summary -->
            <div
                class="grid grid-cols-2 gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 sm:grid-cols-4"
            >
                <div>
                    <span class="text-xs text-slate-500">JazzCash Sales:</span>
                    <span class="tnum ml-1 text-sm font-semibold text-slate-600"
                        >Rs
                        {{ activeShift.jazzcash_sales.toLocaleString() }}</span
                    >
                </div>
                <div>
                    <span class="text-xs text-slate-500">EasyPaisa Sales:</span>
                    <span class="tnum ml-1 text-sm font-semibold text-slate-600"
                        >Rs
                        {{ activeShift.easypaisa_sales.toLocaleString() }}</span
                    >
                </div>
                <div>
                    <span class="text-xs text-slate-500"
                        >Bank / Card Sales:</span
                    >
                    <span class="tnum ml-1 text-sm font-semibold text-slate-600"
                        >Rs {{ activeShift.bank_sales.toLocaleString() }}</span
                    >
                </div>
                <div>
                    <span class="text-xs text-slate-500"
                        >Udhaar (Khata) Sales:</span
                    >
                    <span class="tnum ml-1 text-sm font-semibold text-amber-600"
                        >Rs
                        {{ activeShift.udhaar_sales.toLocaleString() }}</span
                    >
                </div>
            </div>

            <!-- Active Shift Expenses Log -->
            <div>
                <h3 class="mb-3 text-sm font-semibold text-gray-900">
                    Shift Shop Expenses
                </h3>
                <div
                    v-if="activeShift.expenses.length === 0"
                    class="py-2 text-sm text-slate-500 italic"
                >
                    No shop expenses recorded for this shift yet.
                </div>
                <div v-else class="divide-y divide-gray-200">
                    <div
                        v-for="exp in activeShift.expenses"
                        :key="exp.id"
                        class="flex items-center justify-between py-2.5 text-sm"
                    >
                        <div>
                            <span class="font-medium text-gray-900">{{
                                exp.category
                            }}</span>
                            <span
                                v-if="exp.notes"
                                class="ml-2 text-xs text-slate-500"
                                >({{ exp.notes }})</span
                            >
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="tnum font-bold text-rose-600"
                                >- Rs {{ exp.amount.toLocaleString() }}</span
                            >
                            <span class="text-xs text-slate-500">{{
                                exp.created_at
                            }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Active Shift / Open Shift Section -->
        <div
            v-else
            class="space-y-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)]"
        >
            <div class="flex items-start gap-4">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#003B7D]/10 text-[#003B7D] ring-1 ring-[#003B7D]/20 ring-inset"
                >
                    <Wallet class="h-6 w-6" />
                </div>
                <div>
                    <h2 class="text-lg font-black tracking-tight text-gray-900">
                        No Register Shift Currently Open
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Start a new shift by entering the opening float cash
                        available in the shop register drawer.
                    </p>
                </div>
            </div>

            <form
                @submit.prevent="submitOpenShift"
                class="max-w-md space-y-4 pt-2"
            >
                <div>
                    <label
                        class="mb-2 block text-[10px] font-black tracking-[0.18em] text-[#003B7D] uppercase"
                    >
                        Opening Float Cash (Rs)
                    </label>
                    <div class="relative">
                        <span
                            class="tnum absolute top-3 left-3 text-sm font-bold text-slate-500"
                            >Rs</span
                        >
                        <input
                            v-model="openShiftForm.opening_float"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            placeholder="5000"
                            class="tnum w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pr-4 pl-10 text-lg font-semibold text-gray-900 placeholder-slate-500 focus:outline-none focus-visible:border-[#003B7D]/70 focus-visible:ring-2 focus-visible:ring-[#003B7D]/20"
                        />
                    </div>
                    <p
                        v-if="openShiftForm.errors.opening_float"
                        class="mt-1 text-xs text-rose-600"
                    >
                        {{ openShiftForm.errors.opening_float }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="openShiftForm.processing"
                    class="w-full rounded-xl bg-[#003B7D] py-3 text-center font-semibold text-white shadow-sm transition hover:opacity-90 disabled:opacity-50"
                >
                    Start Shift & Open Register
                </button>
            </form>
        </div>

        <!-- Past Shifts History -->
        <div
            class="bg-card space-y-4 rounded-2xl border border-gray-200 p-6 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)]"
        >
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-lg font-bold text-gray-900">
                    <Clock class="h-5 w-5 text-[#003B7D]" />
                    Recent Shifts History
                </h2>

                <!-- Table Columns Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 gap-1.5 text-xs font-semibold"
                        >
                            <SlidersHorizontal class="h-3.5 w-3.5 text-[#003B7D]" />
                            <span>Columns</span>
                            <span class="ml-1 rounded-full bg-[#003B7D]/10 px-1.5 py-0.5 text-[10px] font-bold text-[#003B7D]">
                                {{ activeColumnCount }}/9
                            </span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56 p-2 space-y-1">
                        <DropdownMenuLabel class="flex items-center justify-between text-xs font-bold px-1 py-1">
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
                            v-for="(label, key) in shiftColumnLabels"
                            :key="key"
                            @click.stop="toggleShiftColumn(key)"
                            class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-xs font-medium hover:bg-slate-100 cursor-pointer select-none transition-colors"
                        >
                            <span>{{ label }}</span>
                            <input
                                type="checkbox"
                                :checked="visibleColumns[key as keyof typeof visibleColumns]"
                                @change="toggleShiftColumn(key)"
                                @click.stop
                                class="h-4 w-4 rounded border-slate-300 text-[#003B7D] focus:ring-[#003B7D] cursor-pointer"
                            />
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-xs text-slate-500 uppercase"
                        >
                            <th v-if="visibleColumns.id" class="px-4 py-3">Shift ID</th>
                            <th v-if="visibleColumns.cashier" class="px-4 py-3">Cashier</th>
                            <th v-if="visibleColumns.opened" class="px-4 py-3">Opened</th>
                            <th v-if="visibleColumns.closed" class="px-4 py-3">Closed</th>
                            <th v-if="visibleColumns.float" class="px-4 py-3">Float</th>
                            <th v-if="visibleColumns.expected" class="px-4 py-3">Expected Cash</th>
                            <th v-if="visibleColumns.actual" class="px-4 py-3">Actual Cash</th>
                            <th v-if="visibleColumns.discrepancy" class="px-4 py-3">Discrepancy</th>
                            <th v-if="visibleColumns.status" class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-if="pastShifts.length === 0">
                            <td
                                colspan="9"
                                class="py-6 text-center text-slate-500 italic"
                            >
                                No previous shifts recorded yet.
                            </td>
                        </tr>
                        <tr
                            v-for="shift in pastShifts"
                            :key="shift.id"
                            class="hover:bg-gray-50"
                        >
                            <td
                                v-if="visibleColumns.id"
                                class="px-4 py-3 font-mono text-xs font-semibold text-slate-500"
                            >
                                #SHIFT-{{ shift.id }}
                            </td>
                            <td v-if="visibleColumns.cashier" class="px-4 py-3 font-medium text-gray-900">
                                {{ shift.cashier }}
                            </td>
                            <td v-if="visibleColumns.opened" class="px-4 py-3 text-xs text-slate-500">
                                {{ shift.opened_at }}
                            </td>
                            <td v-if="visibleColumns.closed" class="px-4 py-3 text-xs text-slate-500">
                                {{ shift.closed_at ?? 'Active' }}
                            </td>
                            <td
                                v-if="visibleColumns.float"
                                class="tnum px-4 py-3 font-medium text-slate-600"
                            >
                                Rs {{ shift.opening_float.toLocaleString() }}
                            </td>
                            <td
                                v-if="visibleColumns.expected"
                                class="tnum px-4 py-3 font-semibold text-[#003B7D]"
                            >
                                Rs {{ shift.expected_cash.toLocaleString() }}
                            </td>
                            <td v-if="visibleColumns.actual" class="tnum px-4 py-3 font-bold text-gray-900">
                                {{
                                    shift.actual_cash != null
                                        ? 'Rs ' +
                                          shift.actual_cash.toLocaleString()
                                        : '-'
                                }}
                            </td>
                            <td v-if="visibleColumns.discrepancy" class="px-4 py-3 text-xs font-bold">
                                <span
                                    v-if="shift.discrepancy != null"
                                    :class="[
                                        shift.discrepancy < 0
                                            ? 'rounded border border-rose-200 bg-rose-50 px-2 py-0.5 text-rose-600'
                                            : shift.discrepancy > 0
                                              ? 'rounded border border-[#003B7D]/20 bg-[#003B7D]/5 px-2 py-0.5 text-[#003B7D]'
                                              : 'text-slate-500',
                                    ]"
                                >
                                    {{
                                        shift.discrepancy > 0
                                            ? '+Rs ' +
                                              shift.discrepancy.toLocaleString()
                                            : 'Rs ' +
                                              shift.discrepancy.toLocaleString()
                                    }}
                                </span>
                                <span v-else>-</span>
                            </td>
                            <td v-if="visibleColumns.status" class="px-4 py-3">
                                <span
                                    :class="[
                                        shift.status === 'open'
                                            ? 'border border-[#003B7D]/20 bg-[#003B7D]/5 text-[#003B7D]'
                                            : 'border border-gray-200 bg-gray-50 text-slate-600',
                                        'rounded-full px-2 py-0.5 text-xs font-semibold capitalize',
                                    ]"
                                >
                                    {{ shift.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div
        v-if="showExpenseModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
    >
        <div
            class="bg-card w-full max-w-md space-y-4 rounded-2xl border border-gray-200 p-6 shadow-[0_1px_3px_rgba(2,43,90,0.10)]"
        >
            <div
                class="flex items-center justify-between border-b border-gray-200 pb-3"
            >
                <h3 class="text-lg font-bold text-gray-900">
                    Record Shop Expense
                </h3>
                <button
                    @click="showExpenseModal = false"
                    class="text-slate-500 hover:text-slate-600"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <form @submit.prevent="submitExpense" class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600"
                        >Expense Category</label
                    >
                    <select
                        v-model="expenseForm.category"
                        required
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 placeholder-slate-500 focus:outline-none focus-visible:border-[#003B7D]/70 focus-visible:ring-2 focus-visible:ring-[#003B7D]/20"
                    >
                        <option
                            v-for="cat in categories"
                            :key="cat"
                            :value="cat"
                        >
                            {{ cat }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600"
                        >Amount (Rs)</label
                    >
                    <input
                        v-model="expenseForm.amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        required
                        placeholder="e.g. 500"
                        class="tnum w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-900 placeholder-slate-500 focus:outline-none focus-visible:border-[#003B7D]/70 focus-visible:ring-2 focus-visible:ring-[#003B7D]/20"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600"
                        >Notes / Description (Optional)</label
                    >
                    <textarea
                        v-model="expenseForm.notes"
                        rows="2"
                        placeholder="e.g. Tea & lunch for staff"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 placeholder-slate-500 focus:outline-none focus-visible:border-[#003B7D]/70 focus-visible:ring-2 focus-visible:ring-[#003B7D]/20"
                    ></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="showExpenseModal = false"
                        class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-gray-100"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="expenseForm.processing"
                        class="rounded-lg bg-[#003B7D] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#002b5c] disabled:opacity-50"
                    >
                        Record Expense
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Close Shift Modal -->
    <div
        v-if="showCloseShiftModal && activeShift"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
    >
        <div
            class="bg-card w-full max-w-lg space-y-4 rounded-2xl border border-gray-200 p-6 shadow-[0_1px_3px_rgba(2,43,90,0.10)]"
        >
            <div
                class="flex items-center justify-between border-b border-gray-200 pb-3"
            >
                <h3 class="text-lg font-bold text-gray-900">
                    Shift Close & Cash Reconciliation
                </h3>
                <button
                    @click="showCloseShiftModal = false"
                    class="text-slate-500 hover:text-slate-600"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div
                class="space-y-2 rounded-xl border border-gray-200 bg-gray-50 p-4"
            >
                <div class="flex justify-between text-xs text-slate-500">
                    <span>Opening Float:</span>
                    <span class="tnum font-medium text-gray-900"
                        >Rs
                        {{ activeShift.opening_float.toLocaleString() }}</span
                    >
                </div>
                <div class="flex justify-between text-xs text-slate-500">
                    <span>Net Cash Sales + Wasooli:</span>
                    <span class="tnum font-medium text-[#003B7D]"
                        >+ Rs
                        {{
                            (
                                activeShift.cash_sales +
                                activeShift.wasooli_cash
                            ).toLocaleString()
                        }}</span
                    >
                </div>
                <div class="flex justify-between text-xs text-slate-500">
                    <span>Shop Expenses:</span>
                    <span class="tnum font-medium text-rose-600"
                        >- Rs
                        {{ activeShift.expenses_amount.toLocaleString() }}</span
                    >
                </div>
                <div
                    class="flex justify-between border-t border-[#003B7D]/20 pt-2 text-sm font-bold text-blue-100"
                >
                    <span>Expected Cash in Register:</span>
                    <span class="tnum text-lg"
                        >Rs
                        {{ activeShift.expected_cash.toLocaleString() }}</span
                    >
                </div>
            </div>

            <form @submit.prevent="submitCloseShift" class="space-y-4">
                <div>
                    <label
                        class="mb-1 block text-xs font-semibold text-slate-600"
                    >
                        Physical Counted Cash in Drawer (Rs)
                    </label>
                    <input
                        v-model="closeShiftForm.actual_cash"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                        placeholder="Enter counted physical cash..."
                        class="tnum w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-lg font-bold text-gray-900 placeholder-slate-500 focus:outline-none focus-visible:border-[#003B7D]/70 focus-visible:ring-2 focus-visible:ring-[#003B7D]/20"
                    />
                </div>

                <!-- Discrepancy Indicator -->
                <div
                    v-if="closeShiftForm.actual_cash !== ''"
                    class="flex items-center justify-between rounded-xl p-3 text-sm font-semibold"
                    :class="[
                        liveDiscrepancy < 0
                            ? 'border border-rose-200 bg-rose-50 text-rose-600'
                            : liveDiscrepancy > 0
                              ? 'border border-[#003B7D]/20 bg-[#003B7D]/5 text-[#003B7D]'
                              : 'border border-gray-200 bg-gray-50 text-slate-600',
                    ]"
                >
                    <span>Cash Discrepancy / Shortage:</span>
                    <span class="tnum text-base font-black">
                        {{
                            liveDiscrepancy > 0
                                ? '+ Rs ' + liveDiscrepancy.toLocaleString()
                                : 'Rs ' + liveDiscrepancy.toLocaleString()
                        }}
                    </span>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600"
                        >Notes / Discrepancy Reason (Optional)</label
                    >
                    <textarea
                        v-model="closeShiftForm.notes"
                        rows="2"
                        placeholder="Add reason for cash shortage or discrepancy..."
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 placeholder-slate-500 focus:outline-none focus-visible:border-[#003B7D]/70 focus-visible:ring-2 focus-visible:ring-[#003B7D]/20"
                    ></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="showCloseShiftModal = false"
                        class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-gray-100"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="closeShiftForm.processing"
                        class="rounded-lg bg-[#003B7D] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:opacity-90 disabled:opacity-50"
                    >
                        Confirm & Close Shift
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Printable Shift Summary Slip (80mm Thermal) -->
    <div
        v-if="activeShift"
        class="hidden bg-white p-4 font-mono text-xs text-black print:block"
    >
        <div class="mb-2 border-b pb-2 text-center">
            <h2 class="text-base font-bold">Horizon Studio</h2>
            <p class="text-xs">REGISTER SHIFT SUMMARY SLIP</p>
            <p class="text-[10px]">
                Shift ID: #SHIFT-{{ activeShift.id }} | Cashier:
                {{ activeShift.user }}
            </p>
            <p class="text-[10px]">Opened: {{ activeShift.opened_at }}</p>
        </div>

        <div class="mb-3 space-y-1 text-xs">
            <div class="flex justify-between">
                <span>Opening Float:</span
                ><span
                    >Rs {{ activeShift.opening_float.toLocaleString() }}</span
                >
            </div>
            <div class="flex justify-between">
                <span>Cash Sales:</span
                ><span>Rs {{ activeShift.cash_sales.toLocaleString() }}</span>
            </div>
            <div class="flex justify-between">
                <span>Wasooli Cash:</span
                ><span>Rs {{ activeShift.wasooli_cash.toLocaleString() }}</span>
            </div>
            <div class="flex justify-between">
                <span>Expenses:</span
                ><span
                    >- Rs
                    {{ activeShift.expenses_amount.toLocaleString() }}</span
                >
            </div>
            <div
                class="flex justify-between border-t border-black pt-1 font-bold"
            >
                <span>EXPECTED CASH:</span
                ><span
                    >Rs {{ activeShift.expected_cash.toLocaleString() }}</span
                >
            </div>
        </div>

        <div
            class="space-y-1 border-t border-dashed border-black pt-2 text-[10px]"
        >
            <div class="flex justify-between">
                <span>JazzCash:</span
                ><span
                    >Rs {{ activeShift.jazzcash_sales.toLocaleString() }}</span
                >
            </div>
            <div class="flex justify-between">
                <span>EasyPaisa:</span
                ><span
                    >Rs {{ activeShift.easypaisa_sales.toLocaleString() }}</span
                >
            </div>
            <div class="flex justify-between">
                <span>Bank/Card:</span
                ><span>Rs {{ activeShift.bank_sales.toLocaleString() }}</span>
            </div>
            <div class="flex justify-between">
                <span>Udhaar Sales:</span
                ><span>Rs {{ activeShift.udhaar_sales.toLocaleString() }}</span>
            </div>
        </div>

        <div class="mt-6 flex justify-between border-t pt-4 text-[10px]">
            <div>
                <p class="mt-6 w-24 border-t border-black text-center">
                    Cashier Sign
                </p>
            </div>
            <div>
                <p class="mt-6 w-24 border-t border-black text-center">
                    Manager Sign
                </p>
            </div>
        </div>
    </div>
</template>
