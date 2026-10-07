<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowDownLeft,
    ArrowUpRight,
    Check,
    CheckCircle,
    Download,
    Edit3,
    FileSpreadsheet,
    History,
    MoreVertical,
    Plus,
    Printer,
    RotateCcw,
    Search,
    SlidersHorizontal,
    Trash2,
    Users,
    Wallet,
    X,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
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
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
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
import ImportDialog from '@/components/ImportDialog.vue';
import StatementPrint from '@/components/StatementPrint.vue';
import customerRoutes from '@/routes/customers';
import customerStatementRoutes from '@/routes/customers/statement';
import type { Team } from '@/types';

const { confirm } = useConfirm();

interface StatementEntry {
    Date: string;
    Type: string;
    Reference: string;
    Notes: string;
    Debit: number | string;
    Credit: number | string;
    Balance: number | string;
}

interface PrintStatement {
    id: number;
    name: string;
    phone?: string | null;
    address?: string | null;
    current_balance: number | string;
    entries: StatementEntry[];
}

interface LedgerItem {
    id: number;
    customer_id: number;
    user_id?: number | null;
    user?: { id: number; name: string } | null;
    type: string;
    amount: number | string;
    payment_method?: string | null;
    balance_after: number | string;
    reference_id?: string | null;
    notes?: string | null;
    created_at: string;
}

interface CustomerItem {
    id: number;
    name: string;
    phone: string;
    address?: string | null;
    current_balance: number | string;
    created_at: string;
    ledgers?: LedgerItem[];
}

const props = defineProps<{
    customers: {
        data: CustomerItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search: string;
        balance_filter: string;
        per_page?: number;
    };
    summary: {
        total_customers: number;
        total_receivables: number;
        total_advances: number;
    };
    shopInfo?: {
        name: string;
        phone: string;
        address: string;
    };
}>();

const page = usePage();
const currentTeamSlug = computed(
    () => (page.props.currentTeam as Team | undefined)?.slug || 'default',
);

const isImportDialogOpen = ref(false);
const importTemplateUrl = computed(
    () => `/${currentTeamSlug.value}/customers/import/template`,
);
const importActionUrl = computed(
    () => `/${currentTeamSlug.value}/customers/import`,
);

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam ? '/dashboard' : '/',
            },
            {
                title: 'Customers',
                href: layoutProps.currentTeam
                    ? customerRoutes.index(layoutProps.currentTeam.slug).url
                    : '/customers',
            },
        ],
    }),
});

// Search & Filter
const search = ref(props.filters.search || '');
const selectedBalanceFilter = ref(props.filters.balance_filter || 'all');
const perPage = ref<number>(Number(props.filters.per_page) || 15);

const PER_PAGE_STORAGE_KEY = 'faizan_mobile_customers_per_page_v1';

const changePerPage = (val?: number) => {
    if (val) perPage.value = val;
    try {
        localStorage.setItem(PER_PAGE_STORAGE_KEY, perPage.value.toString());
    } catch (e) {
        console.error(e);
    }
    applyFilters();
};

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const applyFilters = () => {
    router.get(
        customerRoutes.index(currentTeamSlug.value).url,
        {
            search: search.value || undefined,
            balance_filter:
                selectedBalanceFilter.value !== 'all'
                    ? selectedBalanceFilter.value
                    : undefined,
            per_page: perPage.value !== 15 ? perPage.value : undefined,
        },
        { preserveState: true, replace: true },
    );
};

watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 350);
});

watch(selectedBalanceFilter, () => {
    applyFilters();
});

// Column Visibility Controls
const DEFAULT_VISIBLE_COLUMNS = {
    name: true,
    phone: true,
    address: true,
    balance: true,
    actions: true,
};

const visibleColumns = ref({ ...DEFAULT_VISIBLE_COLUMNS });

if (typeof window !== 'undefined') {
    try {
        const saved = localStorage.getItem('customers_visible_columns');
        if (saved) {
            visibleColumns.value = {
                ...DEFAULT_VISIBLE_COLUMNS,
                ...JSON.parse(saved),
            };
        }
    } catch {
        // ignore parse error
    }
}

watch(
    visibleColumns,
    (val) => {
        try {
            localStorage.setItem(
                'customers_visible_columns',
                JSON.stringify(val),
            );
        } catch {
            // ignore storage error
        }
    },
    { deep: true },
);

const activeColumnCount = computed(
    () => Object.values(visibleColumns.value).filter(Boolean).length,
);

const customerColumnLabels: Record<string, string> = {
    name: 'Customer Name',
    phone: 'Phone Number',
    address: 'Address',
    balance: 'Current Balance',
    actions: 'Actions',
};

const toggleCustomerColumn = (key: string) => {
    if (key in visibleColumns.value) {
        visibleColumns.value[key as keyof typeof DEFAULT_VISIBLE_COLUMNS] =
            !visibleColumns.value[key as keyof typeof DEFAULT_VISIBLE_COLUMNS];
    }
};

const resetColumns = () => {
    visibleColumns.value = { ...DEFAULT_VISIBLE_COLUMNS };
};

// Add / Edit Modal
const isFormModalOpen = ref(false);
const editingCustomer = ref<CustomerItem | null>(null);

const form = useForm({
    name: '',
    phone: '',
    address: '',
    initial_balance: '',
});

const openAddModal = () => {
    editingCustomer.value = null;
    form.reset();
    form.clearErrors();
    form.initial_balance = '';
    isFormModalOpen.value = true;
};

const openEditModal = (customer: CustomerItem) => {
    editingCustomer.value = customer;
    form.clearErrors();
    form.name = customer.name;
    form.phone = customer.phone;
    form.address = customer.address || '';
    form.initial_balance = '';
    isFormModalOpen.value = true;
};

const submitForm = () => {
    if (editingCustomer.value) {
        form.put(
            customerRoutes.update([
                currentTeamSlug.value,
                editingCustomer.value.id,
            ]).url,
            {
                onSuccess: () => {
                    isFormModalOpen.value = false;
                },
            },
        );
    } else {
        form.post(customerRoutes.store(currentTeamSlug.value).url, {
            onSuccess: () => {
                isFormModalOpen.value = false;
            },
        });
    }
};

// Inline Customer Cell Editing
const inlineEditingCustomerId = ref<number | null>(null);
const inlineCustomerForm = useForm({
    name: '',
    phone: '',
    address: '',
});

const startInlineCustomerEdit = (customer: CustomerItem) => {
    inlineEditingCustomerId.value = customer.id;
    inlineCustomerForm.clearErrors();
    inlineCustomerForm.name = customer.name;
    inlineCustomerForm.phone = customer.phone;
    inlineCustomerForm.address = customer.address || '';
};

const cancelInlineCustomerEdit = () => {
    inlineEditingCustomerId.value = null;
};

const saveInlineCustomerEdit = () => {
    if (!inlineEditingCustomerId.value) return;
    inlineCustomerForm.put(
        customerRoutes.update([
            currentTeamSlug.value,
            inlineEditingCustomerId.value,
        ]).url,
        {
            onSuccess: () => {
                inlineEditingCustomerId.value = null;
            },
        },
    );
};

// Payment (Wasooli) Modal
const isPaymentModalOpen = ref(false);
const paymentCustomer = ref<CustomerItem | null>(null);

const paymentForm = useForm({
    amount: '',
    payment_method: 'cash',
    notes: '',
});

const paymentCustomerDebt = computed(() => {
    return Math.max(0, Number(paymentCustomer.value?.current_balance || 0));
});

const paymentEnteredAmount = computed(() => Number(paymentForm.amount) || 0);

const paymentRemainingDue = computed(() => {
    return Math.max(0, paymentCustomerDebt.value - paymentEnteredAmount.value);
});

const paymentOverpaymentAdvance = computed(() => {
    return Math.max(0, paymentEnteredAmount.value - paymentCustomerDebt.value);
});

const openPaymentModal = (customer: CustomerItem) => {
    paymentCustomer.value = customer;
    paymentForm.reset();
    paymentForm.clearErrors();
    const currentDebt = Math.max(0, Number(customer.current_balance));
    paymentForm.amount = currentDebt > 0 ? String(currentDebt) : '';
    paymentForm.payment_method = 'cash';
    paymentForm.notes = '';
    isPaymentModalOpen.value = true;
};

const setQuickPaymentAmount = (amt: number) => {
    paymentForm.amount = String(amt);
};

const submitPayment = () => {
    if (!paymentCustomer.value) return;

    paymentForm.post(
        customerRoutes.payments.store([
            currentTeamSlug.value,
            paymentCustomer.value.id,
        ]).url,
        {
            onSuccess: () => {
                isPaymentModalOpen.value = false;
                toast.success('Due payment recorded successfully.');
            },
            onError: (errors) => {
                const msg =
                    Object.values(errors).flat().join(' ') ||
                    'Could not record payment.';
                toast.error('Payment Failed', { description: msg });
            },
        },
    );
};

// Advance Deposit Modal
const isAdvanceModalOpen = ref(false);
const advanceCustomer = ref<CustomerItem | null>(null);

const advanceForm = useForm({
    amount: '',
    payment_method: 'cash',
    notes: '',
});

const advanceCustomerCurrentAdvance = computed(() => {
    if (!advanceCustomer.value) return 0;
    const b = Number(advanceCustomer.value.current_balance) || 0;
    return b < 0 ? Math.abs(b) : 0;
});

const advanceEnteredAmount = computed(() => Number(advanceForm.amount) || 0);

const newProjectedAdvance = computed(() => {
    return advanceCustomerCurrentAdvance.value + advanceEnteredAmount.value;
});

const openAdvanceModal = (customer: CustomerItem) => {
    advanceCustomer.value = customer;
    advanceForm.reset();
    advanceForm.clearErrors();
    advanceForm.amount = '';
    advanceForm.payment_method = 'cash';
    advanceForm.notes = '';
    isAdvanceModalOpen.value = true;
};

const submitAdvance = () => {
    if (!advanceCustomer.value) return;

    advanceForm.post(
        customerRoutes.advance.store([
            currentTeamSlug.value,
            advanceCustomer.value.id,
        ]).url,
        {
            onSuccess: () => {
                isAdvanceModalOpen.value = false;
                toast.success('Customer advance recorded successfully.');
            },
            onError: (errors) => {
                const msg =
                    Object.values(errors).flat().join(' ') ||
                    'Could not record advance.';
                toast.error('Advance Failed', { description: msg });
            },
        },
    );
};

// Return Advance / Refund Advance Modal
const isRefundAdvanceModalOpen = ref(false);
const refundAdvanceCustomer = ref<CustomerItem | null>(null);

const refundAdvanceForm = useForm({
    amount: '',
    payment_method: 'cash',
    notes: '',
});

const customerAvailableAdvance = computed(() => {
    if (!refundAdvanceCustomer.value) return 0;
    const bal = Number(refundAdvanceCustomer.value.current_balance) || 0;
    return bal < 0 ? Math.abs(bal) : 0;
});

const refundAdvanceEnteredAmount = computed(
    () => Number(refundAdvanceForm.amount) || 0,
);

const refundRemainingAdvance = computed(() => {
    return Math.max(
        0,
        customerAvailableAdvance.value - refundAdvanceEnteredAmount.value,
    );
});

const openRefundAdvanceModal = (customer: CustomerItem) => {
    refundAdvanceCustomer.value = customer;
    refundAdvanceForm.reset();
    refundAdvanceForm.clearErrors();
    const avail =
        Number(customer.current_balance) < 0
            ? Math.abs(Number(customer.current_balance))
            : 0;
    refundAdvanceForm.amount = avail > 0 ? String(avail) : '';
    refundAdvanceForm.payment_method = 'cash';
    refundAdvanceForm.notes = '';
    isRefundAdvanceModalOpen.value = true;
};

const setFullAdvanceRefund = () => {
    refundAdvanceForm.amount = String(customerAvailableAdvance.value);
};

const submitRefundAdvance = () => {
    if (!refundAdvanceCustomer.value) return;

    const reqAmt = Number(refundAdvanceForm.amount) || 0;
    if (reqAmt <= 0) {
        refundAdvanceForm.setError(
            'amount',
            'Refund amount must be greater than 0.',
        );
        return;
    }
    if (reqAmt > customerAvailableAdvance.value) {
        refundAdvanceForm.setError(
            'amount',
            `Refund amount cannot exceed available advance of Rs. ${customerAvailableAdvance.value.toLocaleString()}`,
        );
        return;
    }

    refundAdvanceForm.post(
        customerRoutes.advance.refund([
            currentTeamSlug.value,
            refundAdvanceCustomer.value.id,
        ]).url,
        {
            onSuccess: () => {
                isRefundAdvanceModalOpen.value = false;
                toast.success('Advance refund processed successfully.');
            },
            onError: (errors) => {
                const msg =
                    Object.values(errors).flat().join(' ') ||
                    'Could not process advance refund.';
                toast.error('Refund Failed', { description: msg });
            },
        },
    );
};

// Ledger History Modal
const isHistoryModalOpen = ref(false);
const historyCustomer = ref<CustomerItem | null>(null);
const ledgerSearchQuery = ref('');
const ledgerTypeFilter = ref('all');

const openHistoryModal = (customer: CustomerItem) => {
    historyCustomer.value = customer;
    ledgerSearchQuery.value = '';
    ledgerTypeFilter.value = 'all';
    isHistoryModalOpen.value = true;
};

const historyTotalDebits = computed(() => {
    if (!historyCustomer.value?.ledgers) return 0;
    return historyCustomer.value.ledgers.reduce((sum, item) => {
        if (item.type === 'sale' || item.type === 'advance_return') {
            return sum + (Number(item.amount) || 0);
        }
        return sum;
    }, 0);
});

const historyTotalCredits = computed(() => {
    if (!historyCustomer.value?.ledgers) return 0;
    return historyCustomer.value.ledgers.reduce((sum, item) => {
        if (
            item.type === 'payment' ||
            item.type === 'advance' ||
            item.type === 'return'
        ) {
            return sum + (Number(item.amount) || 0);
        }
        return sum;
    }, 0);
});

const filteredLedgers = computed(() => {
    if (!historyCustomer.value?.ledgers) return [];
    return historyCustomer.value.ledgers.filter((item) => {
        if (
            ledgerTypeFilter.value !== 'all' &&
            item.type !== ledgerTypeFilter.value
        ) {
            return false;
        }
        if (ledgerSearchQuery.value.trim() !== '') {
            const q = ledgerSearchQuery.value.toLowerCase();
            const refMatch = item.reference_id?.toLowerCase().includes(q);
            const notesMatch = item.notes?.toLowerCase().includes(q);
            const methodMatch = item.payment_method?.toLowerCase().includes(q);
            const userMatch = item.user?.name.toLowerCase().includes(q);
            if (!refMatch && !notesMatch && !methodMatch && !userMatch) {
                return false;
            }
        }
        return true;
    });
});

const ledgerPage = ref(1);
const ledgerPerPage = ref(15);
const totalLedgerPages = computed(() => {
    return Math.ceil(filteredLedgers.value.length / ledgerPerPage.value) || 1;
});
const paginatedLedgers = computed(() => {
    const start = (ledgerPage.value - 1) * ledgerPerPage.value;
    return filteredLedgers.value.slice(start, start + ledgerPerPage.value);
});
watch([ledgerTypeFilter, ledgerSearchQuery, historyCustomer], () => {
    ledgerPage.value = 1;
});

// Delete Customer
const deleteCustomer = async (customer: CustomerItem) => {
    const ok = await confirm({
        title: 'Delete Customer',
        message: `Are you sure you want to delete "${customer.name}"?`,
        confirmText: 'Yes, Delete',
        cancelText: 'Cancel',
        variant: 'destructive',
    });
    if (ok) {
        router.delete(
            customerRoutes.destroy([currentTeamSlug.value, customer.id]).url,
        );
    }
};

// Format currency
const money = (val: number | string) => {
    const num = Number(val) || 0;
    return (
        'Rs. ' +
        Math.round(num).toLocaleString('en-PK', { maximumFractionDigits: 0 })
    );
};

// Khata statement print + export
const printStatementData = ref<PrintStatement | null>(null);
const printStatementLoading = ref(false);

const printStatement = async (customer: CustomerItem) => {
    printStatementLoading.value = true;
    try {
        const response = await fetch(
            customerRoutes.statement([currentTeamSlug.value, customer.id]).url,
        );
        const data = await response.json();
        printStatementData.value = {
            id: data.entity.id,
            name: data.entity.name,
            phone: data.entity.phone,
            address: data.entity.address,
            current_balance: data.entity.current_balance,
            entries: data.entries,
        };
        await nextTick();
        window.print();
    } finally {
        printStatementLoading.value = false;
    }
};

const statementExportUrl = (customer: CustomerItem, format: 'csv' | 'xlsx') => {
    return customerStatementRoutes.export(
        [currentTeamSlug.value, customer.id],
        { query: { format } },
    ).url;
};
</script>

<template>
    <Head title="Customers" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <!-- Top Header -->
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-xl font-bold tracking-tight text-gray-900 sm:text-2xl dark:text-white"
                >
                    Customers
                </h1>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Customer Khata accounts and balances
                </p>
            </div>

            <div>
                <Button
                    @click="openAddModal"
                    class="gap-1.5 bg-[#003B7D] text-xs font-semibold text-white hover:bg-[#002b5c]"
                >
                    <Plus class="h-4 w-4" /> Add Customer
                </Button>
                <Button
                    @click="isImportDialogOpen = true"
                    variant="outline"
                    class="gap-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    <FileSpreadsheet class="h-4 w-4 text-[#003B7D]" /> Import /
                    Export
                </Button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div
                class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex items-center justify-between text-gray-500">
                    <span class="text-xs font-semibold">Total Customers</span>
                    <Users class="h-4 w-4 text-[#003B7D]" />
                </div>
                <div
                    class="mt-2 text-2xl font-bold text-gray-900 dark:text-white"
                >
                    {{ summary.total_customers }}
                </div>
            </div>

            <div
                class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 shadow-sm dark:border-amber-900/30 dark:bg-amber-950/20"
            >
                <div
                    class="flex items-center justify-between text-amber-800 dark:text-amber-300"
                >
                    <span class="text-xs font-semibold"
                        >Total Udhaar (Receivables)</span
                    >
                    <ArrowDownLeft
                        class="h-4 w-4 text-amber-600 dark:text-amber-400"
                    />
                </div>
                <div
                    class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400"
                >
                    {{ money(summary.total_receivables) }}
                </div>
            </div>

            <div
                class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 shadow-sm dark:border-emerald-900/30 dark:bg-emerald-950/20"
            >
                <div
                    class="flex items-center justify-between text-emerald-800 dark:text-emerald-300"
                >
                    <span class="text-xs font-semibold"
                        >Total Advance Deposits</span
                    >
                    <ArrowUpRight
                        class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                    />
                </div>
                <div
                    class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400"
                >
                    {{ money(summary.total_advances) }}
                </div>
            </div>
        </div>

        <!-- Search & Filter Bar -->
        <div
            class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="relative max-w-sm flex-1">
                <Search
                    class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2"
                />
                <Input
                    v-model="search"
                    placeholder="Search by name, phone or address..."
                    class="h-9 pl-9 text-xs"
                />
            </div>

            <div class="flex items-center gap-2">
                <div class="w-44">
                    <Select v-model="selectedBalanceFilter">
                        <SelectTrigger class="h-9 text-xs">
                            <SelectValue placeholder="All Balances" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Balances</SelectItem>
                            <SelectItem value="has_debt"
                                >Udhaar Only</SelectItem
                            >
                            <SelectItem value="advance"
                                >Advance Only</SelectItem
                            >
                            <SelectItem value="zero">Zero Balance</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Column Customizer Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-9 gap-1.5 text-xs text-gray-700 dark:text-gray-300"
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
                    <DropdownMenuContent align="end" class="w-60 space-y-1 p-2">
                        <DropdownMenuLabel
                            class="flex items-center justify-between px-1 py-1 text-xs font-bold text-gray-900 dark:text-white"
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
                            v-for="(label, key) in customerColumnLabels"
                            :key="key"
                            @click.stop="toggleCustomerColumn(key)"
                            class="flex cursor-pointer items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-800 transition-colors select-none hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                        >
                            <span>{{ label }}</span>
                            <input
                                type="checkbox"
                                :checked="
                                    visibleColumns[
                                        key as keyof typeof visibleColumns
                                    ]
                                "
                                @change="toggleCustomerColumn(key)"
                                @click.stop
                                class="h-4 w-4 cursor-pointer rounded border-gray-300 text-[#003B7D] focus:ring-[#003B7D]"
                            />
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Customers Table -->
        <div
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead
                        class="border-b border-gray-200 bg-gray-50 text-gray-600 uppercase dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400"
                    >
                        <tr>
                            <th
                                v-if="visibleColumns.name"
                                class="px-4 py-3 font-semibold"
                            >
                                Name
                            </th>
                            <th
                                v-if="visibleColumns.phone"
                                class="px-4 py-3 font-semibold"
                            >
                                Phone
                            </th>
                            <th
                                v-if="visibleColumns.address"
                                class="px-4 py-3 font-semibold"
                            >
                                Address
                            </th>
                            <th
                                v-if="visibleColumns.balance"
                                class="px-4 py-3 font-semibold"
                            >
                                Balance
                            </th>
                            <th
                                v-if="visibleColumns.actions"
                                class="px-4 py-3 text-right font-semibold"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr v-if="customers.data.length === 0">
                            <td
                                :colspan="activeColumnCount"
                                class="px-4 py-8 text-center text-gray-500"
                            >
                                No customers found.
                            </td>
                        </tr>

                        <tr
                            v-for="customer in customers.data"
                            :key="customer.id"
                            class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30"
                        >
                            <!-- Name -->
                            <td
                                v-if="visibleColumns.name"
                                class="px-4 py-3 font-semibold text-gray-900 dark:text-white"
                            >
                                <div
                                    v-if="
                                        inlineEditingCustomerId === customer.id
                                    "
                                >
                                    <Input
                                        v-model="inlineCustomerForm.name"
                                        type="text"
                                        class="h-7 text-xs font-semibold"
                                        @keydown.enter.prevent="
                                            saveInlineCustomerEdit
                                        "
                                        @keydown.escape.prevent="
                                            cancelInlineCustomerEdit
                                        "
                                        autofocus
                                    />
                                </div>
                                <div
                                    v-else
                                    class="group flex cursor-pointer items-center gap-1.5"
                                    @click="startInlineCustomerEdit(customer)"
                                    title="Click to edit customer details inline"
                                >
                                    <span>{{ customer.name }}</span>
                                    <button
                                        class="text-gray-400 opacity-0 transition-opacity group-hover:opacity-100 hover:text-[#003B7D]"
                                        title="Edit Customer"
                                    >
                                        <Edit3 class="h-3 w-3" />
                                    </button>
                                </div>
                            </td>

                            <!-- Phone -->
                            <td
                                v-if="visibleColumns.phone"
                                class="px-4 py-3 font-mono text-gray-600 dark:text-gray-300"
                            >
                                <div
                                    v-if="
                                        inlineEditingCustomerId === customer.id
                                    "
                                >
                                    <Input
                                        v-model="inlineCustomerForm.phone"
                                        type="text"
                                        class="h-7 font-mono text-xs"
                                        @keydown.enter.prevent="
                                            saveInlineCustomerEdit
                                        "
                                        @keydown.escape.prevent="
                                            cancelInlineCustomerEdit
                                        "
                                    />
                                </div>
                                <span v-else>{{ customer.phone }}</span>
                            </td>

                            <!-- Address -->
                            <td
                                v-if="visibleColumns.address"
                                class="px-4 py-3 text-gray-500 dark:text-gray-400"
                            >
                                <div
                                    v-if="
                                        inlineEditingCustomerId === customer.id
                                    "
                                    class="flex items-center gap-1"
                                >
                                    <Input
                                        v-model="inlineCustomerForm.address"
                                        type="text"
                                        class="h-7 text-xs"
                                        placeholder="Address"
                                        @keydown.enter.prevent="
                                            saveInlineCustomerEdit
                                        "
                                        @keydown.escape.prevent="
                                            cancelInlineCustomerEdit
                                        "
                                    />
                                    <button
                                        @click="saveInlineCustomerEdit"
                                        :disabled="
                                            inlineCustomerForm.processing
                                        "
                                        class="flex h-6 w-6 items-center justify-center rounded bg-emerald-600 text-white hover:bg-emerald-700"
                                        title="Save Customer"
                                    >
                                        <Check class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        @click="cancelInlineCustomerEdit"
                                        class="flex h-6 w-6 items-center justify-center rounded bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-800 dark:text-gray-300"
                                        title="Cancel"
                                    >
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                                <span v-else>{{
                                    customer.address || '-'
                                }}</span>
                            </td>

                            <td v-if="visibleColumns.balance" class="px-4 py-3">
                                <span
                                    :class="[
                                        'font-bold',
                                        Number(customer.current_balance) > 0
                                            ? 'text-amber-600 dark:text-amber-400'
                                            : Number(customer.current_balance) <
                                                0
                                              ? 'text-emerald-600 dark:text-emerald-400'
                                              : 'text-gray-500 dark:text-gray-400',
                                    ]"
                                >
                                    {{
                                        money(
                                            Math.abs(
                                                Number(
                                                    customer.current_balance,
                                                ),
                                            ),
                                        )
                                    }}
                                </span>
                                <span
                                    v-if="Number(customer.current_balance) > 0"
                                    class="ml-1 text-[10px] text-amber-700 dark:text-amber-400"
                                    >(Udhaar)</span
                                >
                                <span
                                    v-else-if="
                                        Number(customer.current_balance) < 0
                                    "
                                    class="ml-1 text-[10px] text-emerald-700 dark:text-emerald-400"
                                    >(Advance)</span
                                >
                            </td>

                            <td
                                v-if="visibleColumns.actions"
                                class="px-4 py-3 text-right"
                            >
                                <div class="flex items-center justify-end">
                                    <!-- Quick Action Button -->
                                    <button
                                        v-if="
                                            Number(customer.current_balance) > 0
                                        "
                                        type="button"
                                        @click="openPaymentModal(customer)"
                                        class="mr-1.5 inline-flex items-center gap-1 rounded-lg border border-emerald-300 bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                        title="Receive Due Payment (Wasooli)"
                                    >
                                        <Wallet class="h-3.5 w-3.5" />
                                        <span>Wasooli</span>
                                    </button>
                                    <button
                                        v-else-if="
                                            Number(customer.current_balance) < 0
                                        "
                                        type="button"
                                        @click="
                                            openRefundAdvanceModal(customer)
                                        "
                                        class="mr-1.5 inline-flex items-center gap-1 rounded-lg border border-amber-300 bg-amber-50 px-2 py-1 text-[11px] font-bold text-amber-700 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300"
                                        title="Refund Customer Advance"
                                    >
                                        <RotateCcw class="h-3.5 w-3.5" />
                                        <span>Refund Adv</span>
                                    </button>

                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-2xs transition-all hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 focus:ring-2 focus:ring-[#003B7D]/20 focus:outline-none data-[state=open]:border-gray-300 data-[state=open]:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white dark:data-[state=open]:bg-gray-700"
                                                title="Customer Actions"
                                            >
                                                <MoreVertical class="h-4 w-4" />
                                                <span class="sr-only"
                                                    >Customer Actions</span
                                                >
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="end"
                                            class="w-56 rounded-xl border border-gray-200/80 bg-white/95 p-1.5 shadow-xl backdrop-blur-md dark:border-gray-800 dark:bg-gray-900/95"
                                        >
                                            <DropdownMenuItem
                                                @click="
                                                    openPaymentModal(customer)
                                                "
                                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white"
                                            >
                                                <Wallet
                                                    class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                                />
                                                <span
                                                    >Receive Payment
                                                    (Wasooli)</span
                                                >
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="
                                                    openAdvanceModal(customer)
                                                "
                                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white"
                                            >
                                                <Plus
                                                    class="h-4 w-4 shrink-0 text-sky-600 dark:text-sky-400"
                                                />
                                                <span
                                                    >Deposit Advance
                                                    (Peshgi)</span
                                                >
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="
                                                    openRefundAdvanceModal(
                                                        customer,
                                                    )
                                                "
                                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-amber-700 hover:bg-amber-50 hover:text-amber-800 dark:text-amber-300 dark:hover:bg-amber-950/40"
                                            >
                                                <RotateCcw
                                                    class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400"
                                                />
                                                <span
                                                    >Return / Refund
                                                    Advance</span
                                                >
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator
                                                class="my-1 bg-gray-100 dark:bg-gray-800"
                                            />
                                            <DropdownMenuItem
                                                @click="
                                                    openHistoryModal(customer)
                                                "
                                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white"
                                            >
                                                <History
                                                    class="h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400"
                                                />
                                                <span>Ledger History</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="
                                                    printStatement(customer)
                                                "
                                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white"
                                            >
                                                <Printer
                                                    class="h-4 w-4 shrink-0 text-slate-600 dark:text-slate-400"
                                                />
                                                <span>Print Statement</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="openEditModal(customer)"
                                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white"
                                            >
                                                <Edit3
                                                    class="h-4 w-4 shrink-0 text-indigo-500 dark:text-indigo-400"
                                                />
                                                <span>Edit Details</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator
                                                class="my-1 bg-gray-100 dark:bg-gray-800"
                                            />
                                            <DropdownMenuItem
                                                @click="
                                                    deleteCustomer(customer)
                                                "
                                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"
                                            >
                                                <Trash2
                                                    class="h-4 w-4 shrink-0 text-rose-600 dark:text-rose-400"
                                                />
                                                <span>Delete Customer</span>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer with Editable Items Per Page -->
            <div
                class="flex flex-col gap-3 border-t border-gray-200 bg-gray-50/50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-gray-900/50"
            >
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <span class="font-semibold text-gray-700 dark:text-gray-300"
                        >Items per page:</span
                    >
                    <select
                        v-model="perPage"
                        @change="changePerPage()"
                        class="h-8 rounded-lg border border-gray-200 bg-white px-2.5 text-xs font-bold text-gray-800 shadow-2xs focus:ring-2 focus:ring-[#003B7D] focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    >
                        <option :value="10">10 per page</option>
                        <option :value="15">15 per page (default)</option>
                        <option :value="25">25 per page</option>
                        <option :value="50">50 per page</option>
                        <option :value="100">100 per page</option>
                        <option :value="250">250 per page</option>
                        <option :value="500">500 per page (All)</option>
                    </select>
                    <span class="ml-1 text-gray-400">&bull;</span>
                    <span>Total {{ customers.total }} customers</span>
                </div>

                <div
                    v-if="customers.links && customers.links.length > 3"
                    class="flex items-center gap-1"
                >
                    <template v-for="(link, i) in customers.links" :key="i">
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
                            :class="[
                                link.active
                                    ? 'bg-[#003B7D] text-white'
                                    : 'text-gray-700 dark:text-gray-300',
                            ]"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- Modal 1: Add / Edit Customer -->
        <Dialog v-model:open="isFormModalOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">
                        {{ editingCustomer ? 'Edit Customer' : 'Add Customer' }}
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Enter customer details below.
                    </DialogDescription>
                </DialogHeader>

                <form
                    @submit.prevent="submitForm"
                    class="space-y-3 py-2 text-xs"
                >
                    <!-- Name -->
                    <div class="space-y-1">
                        <Label for="name" class="font-medium"
                            >Customer Name *</Label
                        >
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="Full Name"
                            class="h-9 text-xs"
                            required
                        />
                        <span
                            v-if="form.errors.name"
                            class="text-xs text-rose-600"
                            >{{ form.errors.name }}</span
                        >
                    </div>

                    <!-- Phone -->
                    <div class="space-y-1">
                        <Label for="phone" class="font-medium"
                            >Phone Number *</Label
                        >
                        <Input
                            id="phone"
                            v-model="form.phone"
                            placeholder="03001234567"
                            class="h-9 text-xs"
                            required
                        />
                        <span
                            v-if="form.errors.phone"
                            class="text-xs text-rose-600"
                            >{{ form.errors.phone }}</span
                        >
                    </div>

                    <!-- Address -->
                    <div class="space-y-1">
                        <Label for="address" class="font-medium">Address</Label>
                        <Input
                            id="address"
                            v-model="form.address"
                            placeholder="Address or City"
                            class="h-9 text-xs"
                        />
                    </div>

                    <!-- Opening Balance (Only shown when adding new customer) -->
                    <div
                        v-if="!editingCustomer"
                        class="space-y-1 rounded-lg border border-gray-200 bg-gray-50 p-2.5 dark:border-gray-800 dark:bg-gray-800/40"
                    >
                        <Label for="initial_balance" class="font-medium">
                            Opening Balance (PKR)
                        </Label>
                        <Input
                            id="initial_balance"
                            type="number"
                            step="0.01"
                            v-model="form.initial_balance"
                            placeholder="0.00"
                            class="h-9 text-xs font-bold"
                        />
                        <p class="text-[11px] text-gray-500">
                            Purana udhaar hai to رقم likhein (maslan 5000). Agar
                            advance hai to negative (-1000).
                        </p>
                    </div>

                    <DialogFooter class="pt-3">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="isFormModalOpen = false"
                            class="text-xs"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="form.processing"
                            class="bg-[#003B7D] text-xs font-semibold text-white hover:bg-[#002b5c]"
                        >
                            {{ editingCustomer ? 'Update' : 'Save' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal 2: Receive Payment (Wasooli) -->
        <Dialog v-model:open="isPaymentModalOpen">
            <DialogContent
                class="max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
            >
                <DialogHeader
                    class="border-b border-slate-100 pb-3 dark:border-slate-800"
                >
                    <DialogTitle
                        class="flex items-center gap-2.5 text-base font-black text-slate-900 dark:text-white"
                    >
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400"
                        >
                            <Wallet class="h-5 w-5" />
                        </div>
                        <span>Receive Due Payment (Wasooli)</span>
                    </DialogTitle>
                    <DialogDescription class="mt-0.5 text-xs text-slate-500">
                        Record cash or digital receipt against customer's
                        outstanding khata balance.
                    </DialogDescription>
                </DialogHeader>

                <!-- Customer Details & Current Balance Card -->
                <div
                    class="rounded-2xl border p-3.5 text-xs shadow-2xs"
                    :class="
                        paymentCustomerDebt > 0
                            ? 'border-amber-200 bg-amber-50/80 dark:border-amber-900/50 dark:bg-amber-950/30'
                            : 'border-emerald-200 bg-emerald-50/80 dark:border-emerald-900/50 dark:bg-emerald-950/30'
                    "
                >
                    <div class="mb-1 flex items-center justify-between">
                        <div>
                            <span
                                class="text-sm font-black text-slate-900 dark:text-white"
                                >{{ paymentCustomer?.name }}</span
                            >
                            <span
                                v-if="paymentCustomer?.phone"
                                class="block font-mono text-[11px] text-slate-500"
                                >{{ paymentCustomer.phone }}</span
                            >
                        </div>
                        <div class="text-right">
                            <span
                                class="block text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >Current Outstanding Due</span
                            >
                            <span
                                class="text-base font-black"
                                :class="
                                    paymentCustomerDebt > 0
                                        ? 'text-amber-700 dark:text-amber-400'
                                        : 'text-emerald-700 dark:text-emerald-400'
                                "
                            >
                                {{
                                    paymentCustomerDebt > 0
                                        ? money(paymentCustomerDebt)
                                        : 'Rs. 0 (Settled)'
                                }}
                            </span>
                        </div>
                    </div>
                </div>

                <form
                    @submit.prevent="submitPayment"
                    class="space-y-3.5 py-1 text-xs"
                >
                    <!-- Quick Amount Presets -->
                    <div v-if="paymentCustomerDebt > 0" class="space-y-1.5">
                        <Label
                            class="text-[11px] font-bold text-slate-600 dark:text-slate-400"
                            >Quick Settle Presets</Label
                        >
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                type="button"
                                @click="
                                    setQuickPaymentAmount(paymentCustomerDebt)
                                "
                                class="flex items-center gap-1 rounded-xl border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-black text-emerald-700 shadow-2xs transition hover:bg-emerald-100 active:scale-95 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                            >
                                <Check class="h-3 w-3" />
                                <span
                                    >Pay Full Due ({{
                                        money(paymentCustomerDebt)
                                    }})</span
                                >
                            </button>
                            <button
                                v-for="preset in [
                                    500, 1000, 2000, 5000, 10000,
                                ].filter((p) => p < paymentCustomerDebt)"
                                :key="preset"
                                type="button"
                                @click="setQuickPaymentAmount(preset)"
                                class="rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-bold text-slate-700 shadow-2xs transition hover:bg-slate-100 active:scale-95 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                Rs. {{ preset.toLocaleString() }}
                            </button>
                        </div>
                    </div>

                    <!-- Payment Amount Input -->
                    <div class="space-y-1.5">
                        <Label
                            for="amount"
                            class="font-bold text-slate-700 dark:text-slate-300"
                            >Amount Received (PKR) *</Label
                        >
                        <Input
                            id="amount"
                            type="number"
                            min="0.01"
                            max="99999999.99"
                            step="0.01"
                            v-model="paymentForm.amount"
                            placeholder="0.00"
                            class="h-10 rounded-xl border-slate-300 text-base font-black focus:border-[#003B7D]"
                            required
                        />
                        <span
                            v-if="paymentForm.errors.amount"
                            class="block text-xs font-bold text-rose-600"
                        >
                            {{ paymentForm.errors.amount }}
                        </span>
                    </div>

                    <!-- Live Accounting Settlement Card -->
                    <div
                        v-if="
                            paymentCustomerDebt > 0 || paymentEnteredAmount > 0
                        "
                        class="space-y-1.5 rounded-2xl border border-slate-200 bg-slate-50/80 p-3 text-xs shadow-2xs dark:border-slate-800 dark:bg-slate-800/40"
                    >
                        <div
                            class="flex items-center justify-between text-slate-600 dark:text-slate-400"
                        >
                            <span class="font-medium">Total Current Due:</span>
                            <span
                                class="font-bold text-slate-900 dark:text-white"
                                >{{ money(paymentCustomerDebt) }}</span
                            >
                        </div>
                        <div
                            class="flex items-center justify-between text-slate-600 dark:text-slate-400"
                        >
                            <span class="font-medium"
                                >Amount Received Now:</span
                            >
                            <span
                                class="font-bold text-emerald-600 dark:text-emerald-400"
                                >-{{ money(paymentEnteredAmount) }}</span
                            >
                        </div>
                        <div
                            class="flex items-center justify-between border-t border-slate-200 pt-1.5 text-sm font-black dark:border-slate-700"
                        >
                            <span
                                :class="
                                    paymentRemainingDue === 0
                                        ? 'text-emerald-600'
                                        : 'text-slate-800 dark:text-slate-200'
                                "
                            >
                                Remaining Due Balance:
                            </span>
                            <span
                                :class="
                                    paymentRemainingDue === 0
                                        ? 'font-black text-emerald-600'
                                        : 'text-amber-700 dark:text-amber-400'
                                "
                            >
                                {{
                                    paymentRemainingDue === 0
                                        ? 'Rs. 0 (Fully Settled)'
                                        : money(paymentRemainingDue)
                                }}
                            </span>
                        </div>
                        <div
                            v-if="paymentOverpaymentAdvance > 0"
                            class="mt-1 flex items-center gap-1.5 rounded-xl border border-blue-200 bg-blue-50 p-2 text-[11px] font-bold text-blue-700 dark:border-blue-900/50 dark:bg-blue-950/60 dark:text-blue-300"
                        >
                            <CheckCircle
                                class="h-3.5 w-3.5 shrink-0 text-blue-600"
                            />
                            <span
                                >Payment exceeds due by
                                {{ money(paymentOverpaymentAdvance) }}. Extra
                                amount will automatically be recorded as
                                customer advance.</span
                            >
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="space-y-1.5">
                        <Label
                            class="font-bold text-slate-700 dark:text-slate-300"
                            >Payment Mode</Label
                        >
                        <Select v-model="paymentForm.payment_method">
                            <SelectTrigger
                                class="h-9 rounded-xl text-xs font-bold"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent class="rounded-xl">
                                <SelectItem value="cash">Cash</SelectItem>
                                <SelectItem value="bank"
                                    >Bank Transfer</SelectItem
                                >
                                <SelectItem value="jazzcash"
                                    >JazzCash</SelectItem
                                >
                                <SelectItem value="easypaisa"
                                    >EasyPaisa</SelectItem
                                >
                                <SelectItem value="card"
                                    >Debit/Credit Card</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-1.5">
                        <Label
                            for="notes"
                            class="font-bold text-slate-700 dark:text-slate-300"
                            >Notes / Reference (Optional)</Label
                        >
                        <Input
                            id="notes"
                            v-model="paymentForm.notes"
                            placeholder="e.g. Cash handed to counter cashier"
                            class="h-9 rounded-xl text-xs"
                        />
                    </div>

                    <DialogFooter
                        class="gap-2 border-t border-slate-100 pt-3 dark:border-slate-800"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="isPaymentModalOpen = false"
                            class="rounded-xl text-xs font-bold"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="
                                paymentForm.processing ||
                                paymentEnteredAmount <= 0
                            "
                            class="rounded-xl bg-emerald-600 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700 active:scale-95 disabled:opacity-40"
                        >
                            <span
                                v-if="paymentForm.processing"
                                class="flex items-center gap-1.5"
                            >
                                <div
                                    class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent"
                                ></div>
                                <span>Saving Payment...</span>
                            </span>
                            <span v-else
                                >Confirm & Save ({{
                                    money(paymentEnteredAmount)
                                }})</span
                            >
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal 2.5: Deposit Advance (Peshgi Jama) -->
        <Dialog v-model:open="isAdvanceModalOpen">
            <DialogContent
                class="max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
            >
                <DialogHeader
                    class="border-b border-slate-100 pb-3 dark:border-slate-800"
                >
                    <DialogTitle
                        class="flex items-center gap-2.5 text-base font-black text-slate-900 dark:text-white"
                    >
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-950/60 dark:text-sky-400"
                        >
                            <Plus class="h-5 w-5" />
                        </div>
                        <span>Deposit Customer Advance (Peshgi)</span>
                    </DialogTitle>
                    <DialogDescription class="mt-0.5 text-xs text-slate-500">
                        Accept advance booking deposit from customer to keep as
                        store credit.
                    </DialogDescription>
                </DialogHeader>

                <!-- Customer Details Card -->
                <div
                    class="rounded-2xl border border-sky-200 bg-sky-50/70 p-3.5 text-xs shadow-2xs dark:border-sky-900/50 dark:bg-sky-950/30"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <span
                                class="text-sm font-black text-slate-900 dark:text-white"
                                >{{ advanceCustomer?.name }}</span
                            >
                            <span
                                v-if="advanceCustomer?.phone"
                                class="block font-mono text-[11px] text-slate-500"
                                >{{ advanceCustomer.phone }}</span
                            >
                        </div>
                        <div class="text-right">
                            <span
                                class="block text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >Existing Advance Credit</span
                            >
                            <span
                                class="text-base font-black text-sky-700 dark:text-sky-400"
                            >
                                {{ money(advanceCustomerCurrentAdvance) }}
                            </span>
                        </div>
                    </div>
                </div>

                <form
                    @submit.prevent="submitAdvance"
                    class="space-y-3.5 py-1 text-xs"
                >
                    <div class="space-y-1.5">
                        <Label
                            for="adv-amount"
                            class="font-bold text-slate-700 dark:text-slate-300"
                            >Advance Amount Received (PKR) *</Label
                        >
                        <Input
                            id="adv-amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            max="99999999.99"
                            v-model="advanceForm.amount"
                            placeholder="0.00"
                            class="h-10 rounded-xl border-slate-300 text-base font-black focus:border-[#003B7D]"
                            required
                        />
                        <span
                            v-if="advanceForm.errors.amount"
                            class="block text-xs font-bold text-rose-600"
                        >
                            {{ advanceForm.errors.amount }}
                        </span>
                    </div>

                    <!-- Live Advance Total Preview -->
                    <div
                        v-if="advanceEnteredAmount > 0"
                        class="space-y-1.5 rounded-2xl border border-slate-200 bg-slate-50/80 p-3 text-xs shadow-2xs dark:border-slate-800 dark:bg-slate-800/40"
                    >
                        <div
                            class="flex items-center justify-between text-slate-600 dark:text-slate-400"
                        >
                            <span>Current Advance Credit:</span>
                            <span
                                class="font-bold text-slate-900 dark:text-white"
                                >{{
                                    money(advanceCustomerCurrentAdvance)
                                }}</span
                            >
                        </div>
                        <div
                            class="flex items-center justify-between text-slate-600 dark:text-slate-400"
                        >
                            <span>New Advance Deposit:</span>
                            <span
                                class="font-bold text-sky-600 dark:text-sky-400"
                                >+{{ money(advanceEnteredAmount) }}</span
                            >
                        </div>
                        <div
                            class="flex items-center justify-between border-t border-slate-200 pt-1.5 text-sm font-black text-sky-700 dark:border-slate-700 dark:text-sky-400"
                        >
                            <span>New Total Advance Balance:</span>
                            <span>{{ money(newProjectedAdvance) }}</span>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label
                            class="font-bold text-slate-700 dark:text-slate-300"
                            >Payment Mode</Label
                        >
                        <Select v-model="advanceForm.payment_method">
                            <SelectTrigger
                                class="h-9 rounded-xl text-xs font-bold"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent class="rounded-xl">
                                <SelectItem value="cash">Cash</SelectItem>
                                <SelectItem value="bank"
                                    >Bank Transfer</SelectItem
                                >
                                <SelectItem value="jazzcash"
                                    >JazzCash</SelectItem
                                >
                                <SelectItem value="easypaisa"
                                    >EasyPaisa</SelectItem
                                >
                                <SelectItem value="card"
                                    >Debit/Credit Card</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-1.5">
                        <Label
                            for="adv-notes"
                            class="font-bold text-slate-700 dark:text-slate-300"
                            >Notes / Purpose (Optional)</Label
                        >
                        <Input
                            id="adv-notes"
                            v-model="advanceForm.notes"
                            placeholder="e.g. Booking deposit for iPhone model arrival"
                            class="h-9 rounded-xl text-xs"
                        />
                    </div>

                    <DialogFooter
                        class="gap-2 border-t border-slate-100 pt-3 dark:border-slate-800"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="isAdvanceModalOpen = false"
                            class="rounded-xl text-xs font-bold"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="
                                advanceForm.processing ||
                                advanceEnteredAmount <= 0
                            "
                            class="rounded-xl bg-sky-600 text-xs font-bold text-white shadow-sm transition hover:bg-sky-700 active:scale-95 disabled:opacity-40"
                        >
                            <span
                                v-if="advanceForm.processing"
                                class="flex items-center gap-1.5"
                            >
                                <div
                                    class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent"
                                ></div>
                                <span>Saving Advance...</span>
                            </span>
                            <span v-else
                                >Record Advance ({{
                                    money(advanceEnteredAmount)
                                }})</span
                            >
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal 2.6: Return Advance / Refund Advance -->
        <Dialog v-model:open="isRefundAdvanceModalOpen">
            <DialogContent
                class="max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
            >
                <DialogHeader
                    class="border-b border-slate-100 pb-3 dark:border-slate-800"
                >
                    <DialogTitle
                        class="flex items-center gap-2.5 text-base font-black text-slate-900 dark:text-white"
                    >
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400"
                        >
                            <RotateCcw class="h-5 w-5" />
                        </div>
                        <span>Return Advance / Refund Advance</span>
                    </DialogTitle>
                    <DialogDescription class="mt-0.5 text-xs text-slate-500">
                        Refund customer's previously deposited advance money
                        without purchase.
                    </DialogDescription>
                </DialogHeader>

                <!-- Customer Details & Available Advance Card -->
                <div
                    class="rounded-2xl border border-amber-200 bg-amber-50/70 p-3.5 text-xs shadow-2xs dark:border-amber-900/50 dark:bg-amber-950/30"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <span
                                class="text-sm font-black text-slate-900 dark:text-white"
                                >{{ refundAdvanceCustomer?.name }}</span
                            >
                            <span
                                v-if="refundAdvanceCustomer?.phone"
                                class="block font-mono text-[11px] text-slate-500"
                                >{{ refundAdvanceCustomer.phone }}</span
                            >
                        </div>
                        <div class="text-right">
                            <span
                                class="block text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >Available Advance Balance</span
                            >
                            <span
                                class="text-base font-black text-amber-800 dark:text-amber-300"
                            >
                                {{ money(customerAvailableAdvance) }}
                            </span>
                        </div>
                    </div>
                </div>

                <form
                    @submit.prevent="submitRefundAdvance"
                    class="space-y-3.5 py-1 text-xs"
                >
                    <!-- Amount Input with Full Refund Shortcut -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label
                                for="refund-amount"
                                class="font-bold text-slate-700 dark:text-slate-300"
                                >Refund Amount (PKR) *</Label
                            >
                            <button
                                type="button"
                                @click="setFullAdvanceRefund"
                                class="flex items-center gap-1 text-[11px] font-bold text-amber-700 hover:underline dark:text-amber-400"
                            >
                                <Check class="h-3 w-3" />
                                <span
                                    >Full Refund ({{
                                        money(customerAvailableAdvance)
                                    }})</span
                                >
                            </button>
                        </div>
                        <Input
                            id="refund-amount"
                            type="number"
                            step="0.01"
                            min="1"
                            :max="customerAvailableAdvance"
                            v-model="refundAdvanceForm.amount"
                            placeholder="0.00"
                            class="h-10 rounded-xl border-slate-300 text-base font-black focus:border-[#003B7D]"
                            required
                        />
                        <span
                            v-if="refundAdvanceForm.errors.amount"
                            class="block text-xs font-bold text-rose-600"
                        >
                            {{ refundAdvanceForm.errors.amount }}
                        </span>
                    </div>

                    <!-- Live Remaining Advance Card -->
                    <div
                        v-if="customerAvailableAdvance > 0"
                        class="space-y-1.5 rounded-2xl border border-slate-200 bg-slate-50/80 p-3 text-xs shadow-2xs dark:border-slate-800 dark:bg-slate-800/40"
                    >
                        <div
                            class="flex items-center justify-between text-slate-600 dark:text-slate-400"
                        >
                            <span>Available Advance:</span>
                            <span
                                class="font-bold text-slate-900 dark:text-white"
                                >{{ money(customerAvailableAdvance) }}</span
                            >
                        </div>
                        <div
                            class="flex items-center justify-between text-slate-600 dark:text-slate-400"
                        >
                            <span>Refund Paid Back:</span>
                            <span
                                class="font-bold text-rose-600 dark:text-rose-400"
                                >-{{ money(refundAdvanceEnteredAmount) }}</span
                            >
                        </div>
                        <div
                            class="flex items-center justify-between border-t border-slate-200 pt-1.5 text-sm font-black dark:border-slate-700"
                        >
                            <span
                                :class="
                                    refundRemainingAdvance === 0
                                        ? 'text-slate-600 dark:text-slate-400'
                                        : 'text-emerald-700 dark:text-emerald-400'
                                "
                            >
                                Remaining Advance Balance:
                            </span>
                            <span
                                :class="
                                    refundRemainingAdvance === 0
                                        ? 'font-bold text-slate-500'
                                        : 'text-emerald-700 dark:text-emerald-400'
                                "
                            >
                                {{
                                    refundRemainingAdvance === 0
                                        ? 'Rs. 0 (Fully Refunded)'
                                        : money(refundRemainingAdvance)
                                }}
                            </span>
                        </div>
                        <div
                            v-if="
                                refundAdvanceEnteredAmount >
                                customerAvailableAdvance
                            "
                            class="mt-1 flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 p-2 text-[11px] font-bold text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/60 dark:text-rose-300"
                        >
                            <AlertCircle
                                class="h-3.5 w-3.5 shrink-0 text-rose-600"
                            />
                            <span
                                >Refund amount cannot exceed customer's
                                available advance of
                                {{ money(customerAvailableAdvance) }}.</span
                            >
                        </div>
                    </div>

                    <!-- Payout Mode -->
                    <div class="space-y-1.5">
                        <Label
                            class="font-bold text-slate-700 dark:text-slate-300"
                            >Payout Mode</Label
                        >
                        <Select v-model="refundAdvanceForm.payment_method">
                            <SelectTrigger
                                class="h-9 rounded-xl text-xs font-bold"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent class="rounded-xl">
                                <SelectItem value="cash"
                                    >Cash Payout</SelectItem
                                >
                                <SelectItem value="bank"
                                    >Bank Transfer</SelectItem
                                >
                                <SelectItem value="jazzcash"
                                    >JazzCash</SelectItem
                                >
                                <SelectItem value="easypaisa"
                                    >EasyPaisa</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-1.5">
                        <Label
                            for="refund-notes"
                            class="font-bold text-slate-700 dark:text-slate-300"
                            >Notes / Reason (Optional)</Label
                        >
                        <Input
                            id="refund-notes"
                            v-model="refundAdvanceForm.notes"
                            placeholder="e.g. Customer cancelled order and requested advance refund"
                            class="h-9 rounded-xl text-xs"
                        />
                    </div>

                    <DialogFooter
                        class="gap-2 border-t border-slate-100 pt-3 dark:border-slate-800"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="isRefundAdvanceModalOpen = false"
                            class="rounded-xl text-xs font-bold"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="
                                refundAdvanceForm.processing ||
                                customerAvailableAdvance <= 0 ||
                                refundAdvanceEnteredAmount <= 0 ||
                                refundAdvanceEnteredAmount >
                                    customerAvailableAdvance
                            "
                            class="rounded-xl bg-amber-600 text-xs font-bold text-white shadow-sm transition hover:bg-amber-700 active:scale-95 disabled:opacity-40"
                        >
                            <span
                                v-if="refundAdvanceForm.processing"
                                class="flex items-center gap-1.5"
                            >
                                <div
                                    class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent"
                                ></div>
                                <span>Processing Refund...</span>
                            </span>
                            <span v-else
                                >Process Advance Refund ({{
                                    money(refundAdvanceEnteredAmount)
                                }})</span
                            >
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal 3: Enhanced Customer Ledger History -->
        <Dialog v-model:open="isHistoryModalOpen">
            <DialogContent
                class="flex h-[88vh] max-h-[90vh] w-[95vw] max-w-5xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
            >
                <!-- Header with Customer Avatar & Metric Summary Cards -->
                <DialogHeader
                    class="shrink-0 border-b border-slate-100 pb-3 dark:border-slate-800"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#002654] to-[#003B7D] font-black text-white shadow-sm"
                            >
                                {{
                                    historyCustomer?.name
                                        .charAt(0)
                                        .toUpperCase() || 'C'
                                }}
                            </div>
                            <div>
                                <DialogTitle
                                    class="flex items-center gap-2 text-base font-black text-slate-900 dark:text-white"
                                >
                                    <span>{{ historyCustomer?.name }}</span>
                                    <span
                                        v-if="historyCustomer?.phone"
                                        class="font-mono text-xs font-normal text-slate-400"
                                        >({{ historyCustomer.phone }})</span
                                    >
                                </DialogTitle>
                                <DialogDescription
                                    class="mt-0.5 text-xs text-slate-500"
                                >
                                    Verified audit log of all sales, payments,
                                    advances, and returns.
                                </DialogDescription>
                            </div>
                        </div>

                        <!-- 3 Stat Pills -->
                        <div class="flex shrink-0 items-center gap-2">
                            <div
                                class="rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1 text-right dark:border-slate-800 dark:bg-slate-800"
                            >
                                <span
                                    class="block text-[9px] font-bold text-slate-400 uppercase"
                                    >Total Invoiced</span
                                >
                                <span
                                    class="font-mono text-xs font-black text-amber-700 dark:text-amber-400"
                                    >{{ money(historyTotalDebits) }}</span
                                >
                            </div>
                            <div
                                class="rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1 text-right dark:border-slate-800 dark:bg-slate-800"
                            >
                                <span
                                    class="block text-[9px] font-bold text-slate-400 uppercase"
                                    >Total Received</span
                                >
                                <span
                                    class="font-mono text-xs font-black text-emerald-700 dark:text-emerald-400"
                                    >{{ money(historyTotalCredits) }}</span
                                >
                            </div>
                            <div
                                class="rounded-xl border px-3 py-1 text-right shadow-2xs"
                                :class="
                                    Number(
                                        historyCustomer?.current_balance || 0,
                                    ) > 0
                                        ? 'border-amber-200 bg-amber-50/80 dark:border-amber-900/50 dark:bg-amber-950/40'
                                        : Number(
                                                historyCustomer?.current_balance ||
                                                    0,
                                            ) < 0
                                          ? 'border-emerald-200 bg-emerald-50/80 dark:border-emerald-900/50 dark:bg-emerald-950/40'
                                          : 'border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800'
                                "
                            >
                                <span
                                    class="block text-[9px] font-bold uppercase"
                                    :class="
                                        Number(
                                            historyCustomer?.current_balance ||
                                                0,
                                        ) > 0
                                            ? 'text-amber-700 dark:text-amber-400'
                                            : 'text-emerald-700 dark:text-emerald-400'
                                    "
                                >
                                    Khata Balance
                                </span>
                                <span
                                    class="font-mono text-xs font-black"
                                    :class="
                                        Number(
                                            historyCustomer?.current_balance ||
                                                0,
                                        ) > 0
                                            ? 'text-amber-800 dark:text-amber-300'
                                            : Number(
                                                    historyCustomer?.current_balance ||
                                                        0,
                                                ) < 0
                                              ? 'text-emerald-800 dark:text-emerald-300'
                                              : 'text-slate-700 dark:text-slate-300'
                                    "
                                >
                                    {{
                                        Number(
                                            historyCustomer?.current_balance ||
                                                0,
                                        ) > 0
                                            ? `Owes ${money(historyCustomer?.current_balance || 0)}`
                                            : Number(
                                                    historyCustomer?.current_balance ||
                                                        0,
                                                ) < 0
                                              ? `Advance ${money(Math.abs(Number(historyCustomer?.current_balance || 0)))}`
                                              : 'Rs. 0 (Settled)'
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Search and Type Filter Bar inside Modal -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 pt-3"
                    >
                        <div class="relative min-w-[220px] flex-1">
                            <Search
                                class="absolute top-2.5 left-3 h-3.5 w-3.5 text-slate-400"
                            />
                            <input
                                v-model="ledgerSearchQuery"
                                type="text"
                                placeholder="Search by Ref #, Notes, Staff, Payment Method..."
                                class="h-9 w-full rounded-xl border border-slate-300 bg-white pr-3 pl-9 text-xs font-semibold text-slate-900 shadow-2xs focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            />
                        </div>
                        <div class="flex items-center gap-1.5">
                            <select
                                v-model="ledgerTypeFilter"
                                class="h-9 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 shadow-2xs focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                <option value="all">All Transactions</option>
                                <option value="sale">Sale Invoices</option>
                                <option value="payment">Due Payments</option>
                                <option value="advance">
                                    Advances Received
                                </option>
                                <option value="advance_return">
                                    Advances Returned
                                </option>
                                <option value="return">Sale Returns</option>
                                <option value="adjustment">Adjustments</option>
                            </select>
                        </div>
                    </div>
                </DialogHeader>

                <!-- Ledger Records Table -->
                <div
                    class="my-2.5 min-h-0 flex-1 [scrollbar-width:thin] overflow-y-auto rounded-2xl border border-slate-200 shadow-xs dark:border-slate-800"
                >
                    <table class="w-full text-left text-xs">
                        <thead
                            class="sticky top-0 z-10 bg-slate-100 text-[11px] tracking-wider text-slate-600 uppercase shadow-xs dark:bg-slate-800 dark:text-slate-300"
                        >
                            <tr>
                                <th class="px-3.5 py-2.5">Date & Time</th>
                                <th class="px-3.5 py-2.5">Ref / Tx ID</th>
                                <th class="px-3.5 py-2.5 text-center">Type</th>
                                <th class="px-3.5 py-2.5 text-center">Mode</th>
                                <th class="px-3.5 py-2.5">Staff</th>
                                <th class="px-3.5 py-2.5">Notes</th>
                                <th class="px-3.5 py-2.5 text-right">
                                    Debit (+)
                                </th>
                                <th class="px-3.5 py-2.5 text-right">
                                    Credit (-)
                                </th>
                                <th class="px-3.5 py-2.5 text-right">
                                    Running Balance
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800/80"
                        >
                            <tr v-if="filteredLedgers.length === 0">
                                <td
                                    colspan="9"
                                    class="py-16 text-center text-xs text-slate-400"
                                >
                                    <History
                                        class="mx-auto mb-2 h-8 w-8 text-slate-300"
                                    />
                                    <div
                                        class="font-bold text-slate-600 dark:text-slate-300"
                                    >
                                        No Ledger Transactions
                                    </div>
                                    <div class="mt-0.5 text-[11px]">
                                        No financial entries match your search
                                        criteria.
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="item in paginatedLedgers"
                                :key="item.id"
                                class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                            >
                                <td
                                    class="px-3.5 py-2.5 text-[11px] whitespace-nowrap text-slate-600 dark:text-slate-400"
                                >
                                    {{
                                        new Date(
                                            item.created_at,
                                        ).toLocaleString('en-PK', {
                                            month: 'short',
                                            day: 'numeric',
                                            year: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })
                                    }}
                                </td>
                                <td
                                    class="px-3.5 py-2.5 font-mono font-bold text-slate-900 dark:text-slate-200"
                                >
                                    {{ item.reference_id || `#TX-${item.id}` }}
                                </td>
                                <td class="px-3.5 py-2.5 text-center">
                                    <span
                                        v-if="item.type === 'sale'"
                                        class="inline-block rounded-lg border border-blue-200 bg-blue-50 px-2 py-0.5 text-[10px] font-black text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300"
                                    >
                                        Sale Invoice
                                    </span>
                                    <span
                                        v-else-if="item.type === 'payment'"
                                        class="inline-block rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300"
                                    >
                                        Due Payment
                                    </span>
                                    <span
                                        v-else-if="item.type === 'advance'"
                                        class="inline-block rounded-lg border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-black text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300"
                                    >
                                        Advance Deposit
                                    </span>
                                    <span
                                        v-else-if="
                                            item.type === 'advance_return'
                                        "
                                        class="inline-block rounded-lg border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-black text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300"
                                    >
                                        Advance Refund
                                    </span>
                                    <span
                                        v-else-if="item.type === 'return'"
                                        class="inline-block rounded-lg border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-black text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300"
                                    >
                                        Sale Return
                                    </span>
                                    <span
                                        v-else
                                        class="inline-block rounded-lg border border-purple-200 bg-purple-50 px-2 py-0.5 text-[10px] font-black text-purple-700 capitalize dark:border-purple-900 dark:bg-purple-950 dark:text-purple-300"
                                    >
                                        {{ item.type }}
                                    </span>
                                </td>
                                <td
                                    class="px-3.5 py-2.5 text-center text-[10px] font-black text-slate-600 uppercase dark:text-slate-400"
                                >
                                    {{ item.payment_method || '-' }}
                                </td>
                                <td
                                    class="px-3.5 py-2.5 font-bold text-slate-700 dark:text-slate-300"
                                >
                                    {{ item.user?.name || 'Staff' }}
                                </td>
                                <td
                                    class="max-w-xs truncate px-3.5 py-2.5 text-slate-500"
                                    :title="item.notes || ''"
                                >
                                    {{ item.notes || '-' }}
                                </td>
                                <!-- Debit (Increases debt): Sale, Advance Refund -->
                                <td
                                    class="px-3.5 py-2.5 text-right font-black text-amber-700 dark:text-amber-400"
                                >
                                    <span
                                        v-if="
                                            item.type === 'sale' ||
                                            item.type === 'advance_return'
                                        "
                                    >
                                        {{ money(item.amount) }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-slate-300 dark:text-slate-700"
                                        >-</span
                                    >
                                </td>
                                <!-- Credit (Decreases debt): Payment, Advance Received, Return -->
                                <td
                                    class="px-3.5 py-2.5 text-right font-black text-emerald-700 dark:text-emerald-400"
                                >
                                    <span
                                        v-if="
                                            item.type === 'payment' ||
                                            item.type === 'advance' ||
                                            item.type === 'return'
                                        "
                                    >
                                        {{ money(item.amount) }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-slate-300 dark:text-slate-700"
                                        >-</span
                                    >
                                </td>
                                <td
                                    class="px-3.5 py-2.5 text-right font-black"
                                    :class="
                                        Number(item.balance_after) > 0
                                            ? 'text-amber-700 dark:text-amber-400'
                                            : Number(item.balance_after) < 0
                                              ? 'text-emerald-700 dark:text-emerald-400'
                                              : 'text-slate-600'
                                    "
                                >
                                    {{ money(item.balance_after) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Ledger Pagination Bar -->
                <div
                    v-if="filteredLedgers.length > ledgerPerPage"
                    class="flex shrink-0 items-center justify-between border-t border-slate-100 px-1 pt-2 text-xs dark:border-slate-800"
                >
                    <span class="font-medium text-slate-500">
                        Showing {{ (ledgerPage - 1) * ledgerPerPage + 1 }} to
                        {{
                            Math.min(
                                ledgerPage * ledgerPerPage,
                                filteredLedgers.length,
                            )
                        }}
                        of {{ filteredLedgers.length }} records
                    </span>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7 rounded-lg text-xs font-bold"
                            :disabled="ledgerPage <= 1"
                            @click="ledgerPage--"
                        >
                            Previous
                        </Button>
                        <span
                            class="px-2 text-xs font-bold text-slate-700 dark:text-slate-300"
                        >
                            Page {{ ledgerPage }} of {{ totalLedgerPages }}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7 rounded-lg text-xs font-bold"
                            :disabled="ledgerPage >= totalLedgerPages"
                            @click="ledgerPage++"
                        >
                            Next
                        </Button>
                    </div>
                </div>

                <!-- Dialog Footer -->
                <DialogFooter
                    class="flex shrink-0 items-center justify-between gap-2 border-t border-slate-100 pt-2.5 dark:border-slate-800"
                >
                    <div class="text-xs font-bold text-slate-500">
                        Total {{ filteredLedgers.length }} of
                        {{ historyCustomer?.ledgers?.length || 0 }} records
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="gap-1.5 rounded-xl text-xs font-bold"
                            :disabled="printStatementLoading"
                            @click="
                                historyCustomer &&
                                printStatement(historyCustomer)
                            "
                        >
                            <Printer class="h-3.5 w-3.5" />
                            {{
                                printStatementLoading
                                    ? 'Preparing...'
                                    : 'Print Statement'
                            }}
                        </Button>
                        <DropdownMenu v-if="historyCustomer">
                            <DropdownMenuTrigger as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="gap-1.5 rounded-xl text-xs font-bold"
                                >
                                    <Download class="h-3.5 w-3.5" />
                                    <span>Export</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                class="w-56 rounded-2xl p-1.5 shadow-xl"
                            >
                                <DropdownMenuLabel
                                    class="px-2.5 py-1 text-xs font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Download Statement
                                </DropdownMenuLabel>
                                <DropdownMenuItem :as-child="true">
                                    <a
                                        class="flex w-full cursor-pointer items-center gap-2.5 rounded-xl px-2.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"
                                        :href="
                                            statementExportUrl(
                                                historyCustomer,
                                                'csv',
                                            )
                                        "
                                    >
                                        <FileSpreadsheet
                                            class="h-4 w-4 shrink-0 text-emerald-600"
                                        />
                                        <span>Export as CSV</span>
                                    </a>
                                </DropdownMenuItem>
                                <DropdownMenuItem :as-child="true">
                                    <a
                                        class="flex w-full cursor-pointer items-center gap-2.5 rounded-xl px-2.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"
                                        :href="
                                            statementExportUrl(
                                                historyCustomer,
                                                'xlsx',
                                            )
                                        "
                                    >
                                        <FileSpreadsheet
                                            class="h-4 w-4 shrink-0 text-blue-600"
                                        />
                                        <span>Export as Excel (XLSX)</span>
                                    </a>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="isHistoryModalOpen = false"
                            class="rounded-xl text-xs font-bold"
                        >
                            Close
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <div v-if="printStatementData" class="print-area">
            <StatementPrint
                title="Customer Khata Statement"
                :party="printStatementData"
                :entries="printStatementData.entries"
                :shop-info="
                    props.shopInfo || { name: '', phone: '', address: '' }
                "
            />
        </div>

        <ImportDialog
            v-model:open="isImportDialogOpen"
            :template-url="importTemplateUrl"
            :action-url="importActionUrl"
            title="Import Customers"
            description="Bulk upload customer Khata accounts from an Excel template."
            entity-label="customers"
        />
    </div>
</template>
