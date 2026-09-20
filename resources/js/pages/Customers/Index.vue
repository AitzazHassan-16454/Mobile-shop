<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowUpRight,
    BookOpen,
    DollarSign,
    Edit3,
    FileText,
    History,
    Phone,
    Plus,
    Printer,
    Search,
    Trash2,
    UserCheck,
    UserPlus,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import customers from '@/routes/customers';
import type { Team } from '@/types';

interface LedgerItem {
    id: number;
    customer_id: number;
    type: 'sale' | 'payment' | 'return' | 'adjustment';
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

interface ShopInfo {
    name: string;
    phone: string;
    address: string;
}

interface SummaryStats {
    total_customers: number;
    total_receivables: number;
    total_advances: number;
}

const props = defineProps<{
    customers: {
        data: CustomerItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    shopInfo: ShopInfo;
    filters: {
        search: string;
        balance_filter: string;
    };
    summary: SummaryStats;
    latestPayment?: any;
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
                title: 'Customer Khata',
                href: layoutProps.currentTeam
                    ? customers.index(layoutProps.currentTeam.slug).url
                    : '/customers',
            },
        ],
    }),
});

const search = ref(props.filters.search || '');
const selectedBalanceFilter = ref(props.filters.balance_filter || 'all');
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const applyFilters = () => {
    router.get(
        customers.index(currentTeamSlug.value).url,
        {
            search: search.value || undefined,
            balance_filter:
                selectedBalanceFilter.value !== 'all'
                    ? selectedBalanceFilter.value
                    : undefined,
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

// Modals
const isCreateCustomerModalOpen = ref(false);
const editingCustomer = ref<CustomerItem | null>(null);

const customerForm = useForm({
    name: '',
    phone: '',
    address: '',
    initial_balance: 0,
});

const openCreateCustomerModal = () => {
    editingCustomer.value = null;
    customerForm.reset();
    customerForm.clearErrors();
    isCreateCustomerModalOpen.value = true;
};

const openEditCustomerModal = (customer: CustomerItem) => {
    editingCustomer.value = customer;
    customerForm.clearErrors();
    customerForm.name = customer.name;
    customerForm.phone = customer.phone;
    customerForm.address = customer.address || '';
    customerForm.initial_balance = Number(customer.current_balance);
    isCreateCustomerModalOpen.value = true;
};

const submitCustomerForm = () => {
    if (editingCustomer.value) {
        customerForm.put(
            customers.update([currentTeamSlug.value, editingCustomer.value.id])
                .url,
            {
                onSuccess: () => {
                    isCreateCustomerModalOpen.value = false;
                },
            },
        );
    } else {
        customerForm.post(customers.store(currentTeamSlug.value).url, {
            onSuccess: () => {
                isCreateCustomerModalOpen.value = false;
            },
        });
    }
};

// Record Wasooli (Payment Received) Modal
const isWasooliModalOpen = ref(false);
const activeCustomerForWasooli = ref<CustomerItem | null>(null);

const wasooliForm = useForm({
    amount: '',
    payment_method: 'cash',
    notes: '',
});

const openWasooliModal = (customer: CustomerItem) => {
    activeCustomerForWasooli.value = customer;
    wasooliForm.reset();
    wasooliForm.clearErrors();
    wasooliForm.amount = String(Math.max(0, Number(customer.current_balance)));
    isWasooliModalOpen.value = true;
};

const submitWasooli = () => {
    if (!activeCustomerForWasooli.value) return;

    wasooliForm.post(
        customers.payments.store([
            currentTeamSlug.value,
            activeCustomerForWasooli.value.id,
        ]).url,
        {
            onSuccess: () => {
                isWasooliModalOpen.value = false;
            },
        },
    );
};

// Ledger Statement Modal
const isLedgerModalOpen = ref(false);
const activeLedgerCustomer = ref<CustomerItem | null>(null);

const openLedgerModal = (customer: CustomerItem) => {
    activeLedgerCustomer.value = customer;
    isLedgerModalOpen.value = true;
};

// Wasooli Payment Receipt Print Modal
const isPaymentReceiptModalOpen = ref(false);
const activePaymentReceipt = ref<any>(props.latestPayment || null);

watch(
    () => props.latestPayment,
    (newPay) => {
        if (newPay) {
            activePaymentReceipt.value = newPay;
            isPaymentReceiptModalOpen.value = true;
        }
    },
    { immediate: true },
);

const deleteCustomer = (customer: CustomerItem) => {
    if (confirm(`Delete customer "${customer.name}"?`)) {
        router.delete(
            customers.destroy([currentTeamSlug.value, customer.id]).url,
        );
    }
};

const printReceipt = () => {
    window.print();
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
    <Head title="Customer Khata Directory" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Header Banner -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-5 shadow-[0_1px_2px_rgba(2,43,90,0.06)] backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-bold tracking-tight text-gray-900"
                >
                    <Users class="h-7 w-7 text-violet-600" />
                    Customer Khata Directory & Wasooli Ledger
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Manage Udhaar accounts, Wasooli payments, running statements
                    & balances.
                </p>
            </div>
            <div>
                <Button
                    @click="openCreateCustomerModal"
                    class="gap-2 bg-[#003b7d] font-bold text-white shadow-sm hover:bg-[#0f4c81]"
                >
                    <UserPlus class="h-4 w-4" /> + Add Customer to Khata
                </Button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_1px_2px_rgba(2,43,90,0.06)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">Total Customers</span>
                    <Users class="h-5 w-5 text-violet-600" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-gray-900">
                    {{ summary.total_customers }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Registered Khata accounts
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_1px_2px_rgba(2,43,90,0.06)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">Total Shop Receivables (Udhaar)</span>
                    <ArrowDownLeft class="h-5 w-5 text-amber-600" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-amber-600">
                    {{ formatCurrency(summary.total_receivables) }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Customers owe this to shop
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_1px_2px_rgba(2,43,90,0.06)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">Total Customer Advances</span>
                    <ArrowUpRight class="h-5 w-5 text-violet-600" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-violet-600">
                    {{ formatCurrency(summary.total_advances) }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Advance deposits received
                </div>
            </div>
        </div>

        <!-- Search & Filter Bar -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_1px_2px_rgba(2,43,90,0.06)] backdrop-blur-xl md:flex-row md:items-center md:justify-between"
        >
            <div class="relative max-w-md flex-1">
                <Search
                    class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2"
                />
                <Input
                    v-model="search"
                    placeholder="Search customer by name, phone or address..."
                    class="pl-9"
                />
            </div>

            <div class="w-48">
                <Select v-model="selectedBalanceFilter">
                    <SelectTrigger>
                        <SelectValue placeholder="All Balances" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Balances</SelectItem>
                        <SelectItem value="has_debt"
                            >Udhaar / Receivables Only</SelectItem
                        >
                        <SelectItem value="advance"
                            >Advance Balances Only</SelectItem
                        >
                        <SelectItem value="zero">Zero Balance</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <!-- Customers Directory Table -->
        <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead
                        class="bg-muted/50 text-muted-foreground font-semibold uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3">Customer Name</th>
                            <th class="px-4 py-3">Phone & Address</th>
                            <th class="px-4 py-3">Khata Running Balance</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-border divide-y">
                        <tr v-if="customers.data.length === 0">
                            <td
                                colspan="4"
                                class="text-muted-foreground px-4 py-8 text-center"
                            >
                                No customer Khata records found.
                            </td>
                        </tr>

                        <tr
                            v-for="customer in customers.data"
                            :key="customer.id"
                            class="transition-colors hover:bg-gray-50"
                        >
                            <td class="px-4 py-3">
                                <div
                                    class="text-foreground flex items-center gap-2 text-sm font-bold"
                                >
                                    <UserCheck
                                        class="h-4 w-4 text-violet-600"
                                    />
                                    {{ customer.name }}
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <div
                                    class="tnum text-foreground font-mono font-semibold"
                                >
                                    {{ customer.phone }}
                                </div>
                                <div class="text-muted-foreground text-[11px]">
                                    {{
                                        customer.address || 'No address logged'
                                    }}
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <div
                                    :class="[
                                        'tnum text-sm font-extrabold',
                                        Number(customer.current_balance) > 0
                                            ? 'text-amber-600'
                                            : Number(customer.current_balance) <
                                                0
                                              ? 'text-violet-600'
                                              : 'text-muted-foreground',
                                    ]"
                                >
                                    {{
                                        formatCurrency(customer.current_balance)
                                    }}
                                </div>
                                <div class="text-muted-foreground text-[10px]">
                                    {{
                                        Number(customer.current_balance) > 0
                                            ? 'Customer Owes Shop'
                                            : Number(customer.current_balance) <
                                                0
                                              ? 'Advance Credit'
                                              : 'Balanced'
                                    }}
                                </div>
                            </td>

                            <td class="px-4 py-3 text-right">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <Button
                                        size="sm"
                                        variant="default"
                                        @click="openWasooliModal(customer)"
                                        title="Receive Wasooli Payment"
                                        class="h-8 gap-1 bg-violet-600 text-gray-900 hover:bg-violet-700"
                                    >
                                        <Wallet class="h-3.5 w-3.5" /> Wasooli
                                    </Button>

                                    <Button
                                        size="sm"
                                        variant="outline"
                                        @click="openLedgerModal(customer)"
                                        title="View Statement History"
                                        class="h-8 gap-1 border-violet-200 text-violet-600 hover:border-violet-400/50 hover:bg-violet-50"
                                    >
                                        <History class="h-3.5 w-3.5" />
                                        Statement
                                    </Button>

                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        @click="openEditCustomerModal(customer)"
                                        title="Edit Customer"
                                        class="h-8 w-8 p-0"
                                    >
                                        <Edit3
                                            class="text-muted-foreground h-4 w-4"
                                        />
                                    </Button>

                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        @click="deleteCustomer(customer)"
                                        title="Delete Customer"
                                        class="h-8 w-8 p-0 text-rose-600 hover:bg-rose-50 hover:text-rose-600"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="customers.links.length > 3"
                class="bg-muted/20 flex items-center justify-between border-t px-4 py-3"
            >
                <div class="text-muted-foreground text-xs">
                    Page
                    <span class="font-semibold">{{
                        customers.current_page
                    }}</span>
                    of
                    <span class="font-semibold">{{ customers.last_page }}</span>
                    ({{ customers.total }} customers)
                </div>
                <div class="flex gap-1">
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
                            class="h-8 px-3 text-xs"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- MODAL 1: Create / Edit Customer Modal -->
        <Dialog v-model:open="isCreateCustomerModalOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>{{
                        editingCustomer
                            ? 'Edit Customer'
                            : 'Add Customer to Khata Directory'
                    }}</DialogTitle>
                </DialogHeader>

                <form
                    @submit.prevent="submitCustomerForm"
                    class="space-y-3 py-2 text-xs"
                >
                    <div class="space-y-1">
                        <Label for="cust_name">Customer Full Name *</Label>
                        <Input
                            id="cust_name"
                            v-model="customerForm.name"
                            placeholder="Full Name"
                        />
                        <span
                            v-if="customerForm.errors.name"
                            class="text-xs text-rose-600"
                            >{{ customerForm.errors.name }}</span
                        >
                    </div>

                    <div class="space-y-1">
                        <Label for="cust_phone">Mobile Phone Number *</Label>
                        <Input
                            id="cust_phone"
                            v-model="customerForm.phone"
                            placeholder="03001234567"
                            font-mono
                        />
                        <span
                            v-if="customerForm.errors.phone"
                            class="text-xs text-rose-600"
                            >{{ customerForm.errors.phone }}</span
                        >
                    </div>

                    <div class="space-y-1">
                        <Label for="cust_address">Address / City</Label>
                        <Input
                            id="cust_address"
                            v-model="customerForm.address"
                            placeholder="Residential or Shop Address"
                        />
                    </div>

                    <div
                        v-if="!editingCustomer"
                        class="space-y-1 border-t pt-2"
                    >
                        <Label for="init_bal"
                            >Opening / Previous Udhaar Balance (PKR)</Label
                        >
                        <Input
                            id="init_bal"
                            type="number"
                            step="0.01"
                            v-model="customerForm.initial_balance"
                            placeholder="0.00"
                        />
                    </div>

                    <DialogFooter class="pt-3">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isCreateCustomerModalOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="customerForm.processing"
                            class="bg-[#003b7d] text-white shadow-sm hover:bg-[#0f4c81]"
                        >
                            {{
                                editingCustomer
                                    ? 'Update Customer'
                                    : 'Save Customer'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL 2: Record Wasooli Payment Received Modal -->
        <Dialog v-model:open="isWasooliModalOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Wallet class="h-5 w-5 text-violet-600" />
                        Receive Wasooli Payment &bull;
                        {{ activeCustomerForWasooli?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Current Debt Balance:
                        <span
                            class="tnum text-sm font-extrabold text-amber-600"
                            >{{
                                formatCurrency(
                                    activeCustomerForWasooli?.current_balance ||
                                        0,
                                )
                            }}</span
                        >
                    </DialogDescription>
                </DialogHeader>

                <form
                    @submit.prevent="submitWasooli"
                    class="space-y-3 py-2 text-xs"
                >
                    <div class="space-y-1">
                        <Label for="wasooli_amt">Amount Received (PKR) *</Label>
                        <Input
                            id="wasooli_amt"
                            type="number"
                            step="0.01"
                            v-model="wasooliForm.amount"
                            placeholder="0.00"
                            class="tnum h-10 font-mono text-base font-bold"
                        />
                        <span
                            v-if="wasooliForm.errors.amount"
                            class="text-xs text-rose-600"
                            >{{ wasooliForm.errors.amount }}</span
                        >
                    </div>

                    <div class="space-y-1">
                        <Label>Payment Mode</Label>
                        <Select v-model="wasooliForm.payment_method">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cash"
                                    >Cash Received</SelectItem
                                >
                                <SelectItem value="jazzcash"
                                    >JazzCash Transfer</SelectItem
                                >
                                <SelectItem value="easypaisa"
                                    >EasyPaisa Transfer</SelectItem
                                >
                                <SelectItem value="bank"
                                    >Bank Transfer (Raast)</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-1">
                        <Label for="wasooli_notes">Notes / Reference</Label>
                        <Input
                            id="wasooli_notes"
                            v-model="wasooliForm.notes"
                            placeholder="e.g. Cash received in shop"
                        />
                    </div>

                    <DialogFooter class="pt-3">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isWasooliModalOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="wasooliForm.processing"
                            class="bg-[#003b7d] text-white shadow-sm hover:bg-[#0f4c81]"
                        >
                            Receive Wasooli & Print Slip
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL 3: Statement History Ledger Drawer -->
        <Dialog v-model:open="isLedgerModalOpen">
            <DialogContent class="max-h-[85vh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <History class="h-5 w-5 text-violet-600" />
                        Khata Statement &bull; {{ activeLedgerCustomer?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Ph: {{ activeLedgerCustomer?.phone }} &bull; Current
                        Balance:
                        <span class="tnum text-foreground font-bold">{{
                            formatCurrency(
                                activeLedgerCustomer?.current_balance || 0,
                            )
                        }}</span>
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <div class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-left text-xs">
                            <thead
                                class="bg-muted/50 text-muted-foreground font-semibold uppercase"
                            >
                                <tr>
                                    <th class="p-2.5">Date & Ref</th>
                                    <th class="p-2.5">Type</th>
                                    <th class="p-2.5">Notes</th>
                                    <th class="p-2.5 text-right">Amount</th>
                                    <th class="p-2.5 text-right">
                                        Balance After
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-border divide-y">
                                <tr
                                    v-if="
                                        !activeLedgerCustomer?.ledgers ||
                                        activeLedgerCustomer.ledgers.length ===
                                            0
                                    "
                                >
                                    <td
                                        colspan="5"
                                        class="text-muted-foreground p-4 text-center"
                                    >
                                        No ledger history entries on record.
                                    </td>
                                </tr>

                                <tr
                                    v-for="entry in activeLedgerCustomer?.ledgers"
                                    :key="entry.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="p-2.5 font-mono">
                                        <div>
                                            {{
                                                new Date(
                                                    entry.created_at,
                                                ).toLocaleString('en-PK')
                                            }}
                                        </div>
                                        <div
                                            v-if="entry.reference_id"
                                            class="text-muted-foreground text-[10px]"
                                        >
                                            Ref: {{ entry.reference_id }}
                                        </div>
                                    </td>

                                    <td class="p-2.5">
                                        <span
                                            :class="[
                                                'inline-flex rounded border px-2 py-0.5 text-[10px] font-semibold uppercase',
                                                entry.type === 'sale'
                                                    ? 'border-amber-200 bg-amber-50 text-amber-600'
                                                    : entry.type === 'payment'
                                                      ? 'border-violet-200 bg-violet-50 text-violet-600'
                                                      : 'border-sky-200 bg-sky-50 text-sky-600',
                                            ]"
                                        >
                                            {{ entry.type }}
                                        </span>
                                    </td>

                                    <td class="text-muted-foreground p-2.5">
                                        {{ entry.notes || '-' }}
                                    </td>

                                    <td class="tnum p-2.5 text-right font-bold">
                                        <span
                                            :class="
                                                entry.type === 'payment'
                                                    ? 'text-violet-600'
                                                    : 'text-amber-600'
                                            "
                                        >
                                            {{
                                                entry.type === 'payment'
                                                    ? '-'
                                                    : '+'
                                            }}{{ formatCurrency(entry.amount) }}
                                        </span>
                                    </td>

                                    <td
                                        class="tnum text-foreground p-2.5 text-right font-bold"
                                    >
                                        {{
                                            formatCurrency(entry.balance_after)
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- MODAL 4: Wasooli Payment Receipt Print Slip (80mm / 58mm) -->
        <Dialog v-model:open="isPaymentReceiptModalOpen">
            <DialogContent class="max-w-sm p-4">
                <DialogHeader class="no-print">
                    <DialogTitle class="text-center text-sm"
                        >Wasooli Payment Receipt</DialogTitle
                    >
                </DialogHeader>

                <div
                    id="wasooli-receipt-slip"
                    class="space-y-3 bg-white p-2 font-mono text-[11px] leading-tight text-black"
                >
                    <div class="border-b pb-2 text-center">
                        <div class="text-sm font-extrabold uppercase">
                            {{ shopInfo.name }}
                        </div>
                        <div class="text-[10px]">{{ shopInfo.address }}</div>
                        <div class="text-[10px]">Ph: {{ shopInfo.phone }}</div>
                        <div
                            class="mt-1 inline-block border bg-slate-100 px-2 py-0.5 text-xs font-extrabold"
                        >
                            WASOOLI PAYMENT RECEIPT
                        </div>
                    </div>

                    <div class="space-y-0.5 border-b pb-1 text-[10px]">
                        <div class="flex justify-between font-bold">
                            <span>Receipt #:</span>
                            <span>{{ activePaymentReceipt?.ref_no }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Date:</span>
                            <span>{{
                                activePaymentReceipt?.date
                                    ? new Date(
                                          activePaymentReceipt.date,
                                      ).toLocaleString('en-PK')
                                    : ''
                            }}</span>
                        </div>
                        <div class="flex justify-between font-bold">
                            <span>Customer:</span>
                            <span>{{
                                activePaymentReceipt?.customer?.name
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Phone:</span>
                            <span>{{
                                activePaymentReceipt?.customer?.phone
                            }}</span>
                        </div>
                    </div>

                    <div class="space-y-1 border-b pb-2 text-xs">
                        <div
                            class="flex justify-between font-bold text-violet-800"
                        >
                            <span>Amount Received:</span>
                            <span>{{
                                formatCurrency(
                                    activePaymentReceipt?.paid_amount || 0,
                                )
                            }}</span>
                        </div>
                        <div
                            class="text-muted-foreground flex justify-between text-[10px]"
                        >
                            <span>Payment Mode:</span>
                            <span class="font-bold uppercase">{{
                                activePaymentReceipt?.payment_method
                            }}</span>
                        </div>
                    </div>

                    <div class="space-y-1 border-b pb-2 text-[11px]">
                        <div class="flex justify-between font-bold">
                            <span>Remaining Khata Balance:</span>
                            <span>{{
                                formatCurrency(
                                    activePaymentReceipt?.customer
                                        ?.current_balance || 0,
                                )
                            }}</span>
                        </div>
                    </div>

                    <div class="pt-1 text-center text-[9px]">
                        *** JazakAllah / Thank You For Your Payment ***
                    </div>
                </div>

                <DialogFooter class="no-print flex justify-between pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isPaymentReceiptModalOpen = false"
                        >Close</Button
                    >
                    <Button
                        type="button"
                        @click="printReceipt"
                        class="gap-1 bg-[#003b7d] text-white shadow-sm hover:bg-[#0f4c81]"
                    >
                        <Printer class="h-4 w-4" /> Print Wasooli Receipt
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #wasooli-receipt-slip,
    #wasooli-receipt-slip * {
        visibility: visible;
    }
    #wasooli-receipt-slip {
        position: absolute;
        left: 0;
        top: 0;
        width: 80mm;
    }
    .no-print {
        display: none !important;
    }
}
</style>
