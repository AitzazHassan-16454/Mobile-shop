<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Check,
    Edit3,
    History,
    MoreVertical,
    Plus,
    Search,
    SlidersHorizontal,
    Trash2,
    Users,
    Wallet,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import customerRoutes from '@/routes/customers';
import type { Team } from '@/types';

const { confirm } = useConfirm();

interface LedgerItem {
    id: number;
    customer_id: number;
    type: string;
    amount: number | string;
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
            },
        },
    );
};

// Ledger History Modal
const isHistoryModalOpen = ref(false);
const historyCustomer = ref<CustomerItem | null>(null);

const openHistoryModal = (customer: CustomerItem) => {
    historyCustomer.value = customer;
    isHistoryModalOpen.value = true;
};

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
                    class="text-xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-2xl"
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
                <div class="flex items-center justify-between text-amber-800">
                    <span class="text-xs font-semibold"
                        >Total Udhaar (Receivables)</span
                    >
                    <ArrowDownLeft class="h-4 w-4 text-amber-600" />
                </div>
                <div class="mt-2 text-2xl font-bold text-amber-600">
                    {{ money(summary.total_receivables) }}
                </div>
            </div>

            <div
                class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 shadow-sm dark:border-emerald-900/30 dark:bg-emerald-950/20"
            >
                <div class="flex items-center justify-between text-emerald-800">
                    <span class="text-xs font-semibold"
                        >Total Advance Deposits</span
                    >
                    <ArrowUpRight class="h-4 w-4 text-emerald-600" />
                </div>
                <div class="mt-2 text-2xl font-bold text-emerald-600">
                    {{ money(summary.total_advances) }}
                </div>
            </div>
        </div>

        <!-- Search & Filter Bar -->
        <div
            class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between"
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
                            <SelectItem value="has_debt">Udhaar Only</SelectItem>
                            <SelectItem value="advance">Advance Only</SelectItem>
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
                            <SlidersHorizontal class="h-3.5 w-3.5 text-[#003B7D]" />
                            <span>Columns</span>
                            <span
                                class="ml-1 rounded-full bg-[#003B7D]/10 px-1.5 py-0.5 text-[10px] font-bold text-[#003B7D]"
                            >
                                {{ activeColumnCount }}/5
                            </span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-60 p-2 space-y-1">
                        <DropdownMenuLabel class="flex items-center justify-between text-xs font-bold text-gray-900 dark:text-white px-1 py-1">
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
                            v-for="(label, key) in customerColumnLabels"
                            :key="key"
                            @click.stop="toggleCustomerColumn(key)"
                            class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer select-none transition-colors"
                        >
                            <span>{{ label }}</span>
                            <input
                                type="checkbox"
                                :checked="visibleColumns[key as keyof typeof visibleColumns]"
                                @change="toggleCustomerColumn(key)"
                                @click.stop
                                class="h-4 w-4 rounded border-gray-300 text-[#003B7D] focus:ring-[#003B7D] cursor-pointer"
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
                            <th v-if="visibleColumns.name" class="px-4 py-3 font-semibold">Name</th>
                            <th v-if="visibleColumns.phone" class="px-4 py-3 font-semibold">Phone</th>
                            <th v-if="visibleColumns.address" class="px-4 py-3 font-semibold">Address</th>
                            <th v-if="visibleColumns.balance" class="px-4 py-3 font-semibold">Balance</th>
                            <th v-if="visibleColumns.actions" class="px-4 py-3 text-right font-semibold">
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
                                <div v-if="inlineEditingCustomerId === customer.id">
                                    <Input
                                        v-model="inlineCustomerForm.name"
                                        type="text"
                                        class="h-7 text-xs font-semibold"
                                        @keydown.enter.prevent="saveInlineCustomerEdit"
                                        @keydown.escape.prevent="cancelInlineCustomerEdit"
                                        autofocus
                                    />
                                </div>
                                <div v-else class="flex items-center gap-1.5 group cursor-pointer" @click="startInlineCustomerEdit(customer)" title="Click to edit customer details inline">
                                    <span>{{ customer.name }}</span>
                                    <button class="text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity hover:text-[#003B7D]" title="Edit Customer">
                                        <Edit3 class="h-3 w-3" />
                                    </button>
                                </div>
                            </td>

                            <!-- Phone -->
                            <td
                                v-if="visibleColumns.phone"
                                class="px-4 py-3 font-mono text-gray-600 dark:text-gray-300"
                            >
                                <div v-if="inlineEditingCustomerId === customer.id">
                                    <Input
                                        v-model="inlineCustomerForm.phone"
                                        type="text"
                                        class="h-7 text-xs font-mono"
                                        @keydown.enter.prevent="saveInlineCustomerEdit"
                                        @keydown.escape.prevent="cancelInlineCustomerEdit"
                                    />
                                </div>
                                <span v-else>{{ customer.phone }}</span>
                            </td>

                            <!-- Address -->
                            <td
                                v-if="visibleColumns.address"
                                class="px-4 py-3 text-gray-500 dark:text-gray-400"
                            >
                                <div v-if="inlineEditingCustomerId === customer.id" class="flex items-center gap-1">
                                    <Input
                                        v-model="inlineCustomerForm.address"
                                        type="text"
                                        class="h-7 text-xs"
                                        placeholder="Address"
                                        @keydown.enter.prevent="saveInlineCustomerEdit"
                                        @keydown.escape.prevent="cancelInlineCustomerEdit"
                                    />
                                    <button @click="saveInlineCustomerEdit" :disabled="inlineCustomerForm.processing" class="flex h-6 w-6 items-center justify-center rounded bg-emerald-600 text-white hover:bg-emerald-700" title="Save Customer">
                                        <Check class="h-3.5 w-3.5" />
                                    </button>
                                    <button @click="cancelInlineCustomerEdit" class="flex h-6 w-6 items-center justify-center rounded bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-800 dark:text-gray-300" title="Cancel">
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                                <span v-else>{{ customer.address || '-' }}</span>
                            </td>

                            <td v-if="visibleColumns.balance" class="px-4 py-3">
                                <span
                                    :class="[
                                        'font-bold',
                                        Number(customer.current_balance) > 0
                                            ? 'text-amber-600'
                                            : Number(customer.current_balance) <
                                                0
                                              ? 'text-emerald-600'
                                              : 'text-gray-500',
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
                                    class="ml-1 text-[10px] text-amber-700"
                                    >(Udhaar)</span
                                >
                                <span
                                    v-else-if="
                                        Number(customer.current_balance) < 0
                                    "
                                    class="ml-1 text-[10px] text-emerald-700"
                                    >(Advance)</span
                                >
                            </td>

                            <td v-if="visibleColumns.actions" class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-2xs transition-all hover:bg-gray-50 hover:text-gray-900 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#003B7D]/20 data-[state=open]:bg-gray-100 data-[state=open]:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white dark:data-[state=open]:bg-gray-700"
                                                title="Customer Actions"
                                            >
                                                <MoreVertical class="h-4 w-4" />
                                                <span class="sr-only">Customer Actions</span>
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="end"
                                            class="w-52 rounded-xl border border-gray-200/80 bg-white/95 p-1.5 shadow-xl backdrop-blur-md dark:border-gray-800 dark:bg-gray-900/95"
                                        >
                                            <DropdownMenuItem
                                                @click="openPaymentModal(customer)"
                                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white"
                                            >
                                                <Wallet class="h-4 w-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                                <span>Wasooli (Payment)</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="openHistoryModal(customer)"
                                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white"
                                            >
                                                <History class="h-4 w-4 text-blue-600 dark:text-blue-400 shrink-0" />
                                                <span>Ledger History</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="openEditModal(customer)"
                                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white"
                                            >
                                                <Edit3 class="h-4 w-4 text-indigo-500 dark:text-indigo-400 shrink-0" />
                                                <span>Edit Details</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator class="my-1 bg-gray-100 dark:bg-gray-800" />
                                            <DropdownMenuItem
                                                @click="deleteCustomer(customer)"
                                                class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"
                                            >
                                                <Trash2 class="h-4 w-4 text-rose-600 dark:text-rose-400 shrink-0" />
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
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-gray-200 bg-gray-50/50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900/50"
            >
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <span class="font-semibold text-gray-700 dark:text-gray-300">Items per page:</span>
                    <select
                        v-model="perPage"
                        @change="changePerPage()"
                        class="h-8 rounded-lg border border-gray-200 bg-white px-2.5 text-xs font-bold text-gray-800 shadow-2xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#003B7D]"
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

                <div v-if="customers.links && customers.links.length > 3" class="flex items-center gap-1">
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
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">
                        Receive Payment (Wasooli)
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Customer:
                        <span class="font-bold text-gray-900 dark:text-white">{{
                            paymentCustomer?.name
                        }}</span>
                        &bull; Balance:
                        <span class="font-bold text-amber-600">{{
                            money(paymentCustomer?.current_balance || 0)
                        }}</span>
                    </DialogDescription>
                </DialogHeader>

                <form
                    @submit.prevent="submitPayment"
                    class="space-y-3 py-2 text-xs"
                >
                    <!-- Amount -->
                    <div class="space-y-1">
                        <Label for="amount" class="font-medium"
                            >Amount Received (PKR) *</Label
                        >
                        <Input
                            id="amount"
                            type="number"
                            step="0.01"
                            v-model="paymentForm.amount"
                            placeholder="0.00"
                            class="h-9 text-sm font-bold"
                            required
                        />
                        <span
                            v-if="paymentForm.errors.amount"
                            class="text-xs text-rose-600"
                            >{{ paymentForm.errors.amount }}</span
                        >
                    </div>

                    <!-- Payment Method -->
                    <div class="space-y-1">
                        <Label class="font-medium">Payment Mode</Label>
                        <Select v-model="paymentForm.payment_method">
                            <SelectTrigger class="h-9 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cash">Cash</SelectItem>
                                <SelectItem value="jazzcash"
                                    >JazzCash</SelectItem
                                >
                                <SelectItem value="easypaisa"
                                    >EasyPaisa</SelectItem
                                >
                                <SelectItem value="bank"
                                    >Bank Transfer</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-1">
                        <Label for="notes" class="font-medium"
                            >Notes (Optional)</Label
                        >
                        <Input
                            id="notes"
                            v-model="paymentForm.notes"
                            placeholder="e.g. Cash received"
                            class="h-9 text-xs"
                        />
                    </div>

                    <DialogFooter class="pt-3">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="isPaymentModalOpen = false"
                            class="text-xs"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="paymentForm.processing"
                            class="bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700"
                        >
                            Save Payment
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal 3: Ledger History -->
        <Dialog v-model:open="isHistoryModalOpen">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold">
                        Khata History &bull; {{ historyCustomer?.name }}
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Current Balance:
                        <span class="font-bold text-gray-900 dark:text-white">{{
                            money(historyCustomer?.current_balance || 0)
                        }}</span>
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-96 overflow-y-auto rounded-lg border">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="border-b bg-gray-50 text-gray-600 uppercase dark:bg-gray-800"
                        >
                            <tr>
                                <th class="p-2.5">Date</th>
                                <th class="p-2.5">Type</th>
                                <th class="p-2.5">Notes</th>
                                <th class="p-2.5 text-right">Amount</th>
                                <th class="p-2.5 text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-if="
                                    !historyCustomer?.ledgers ||
                                    historyCustomer.ledgers.length === 0
                                "
                            >
                                <td
                                    colspan="5"
                                    class="p-4 text-center text-gray-500"
                                >
                                    No history records found.
                                </td>
                            </tr>
                            <tr
                                v-for="item in historyCustomer?.ledgers"
                                :key="item.id"
                                class="hover:bg-gray-50"
                            >
                                <td class="p-2.5 text-[11px] text-gray-600">
                                    {{
                                        new Date(
                                            item.created_at,
                                        ).toLocaleDateString('en-PK')
                                    }}
                                </td>
                                <td class="p-2.5 font-semibold capitalize">
                                    {{ item.type }}
                                </td>
                                <td class="p-2.5 text-gray-500">
                                    {{ item.notes || '-' }}
                                </td>
                                <td
                                    :class="[
                                        'p-2.5 text-right font-bold',
                                        item.type === 'payment'
                                            ? 'text-emerald-600'
                                            : 'text-amber-600',
                                    ]"
                                >
                                    {{ item.type === 'payment' ? '-' : '+'
                                    }}{{ money(item.amount) }}
                                </td>
                                <td class="p-2.5 text-right font-bold">
                                    {{ money(item.balance_after) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <DialogFooter class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="isHistoryModalOpen = false"
                        class="text-xs"
                    >
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
