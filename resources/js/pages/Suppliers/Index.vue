<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Building2,
    CreditCard,
    Download,
    FileSpreadsheet,
    FileText,
    History,
    MapPin,
    Pencil,
    Phone,
    Plus,
    Printer,
    Search,
    SlidersHorizontal,
    Store,
    Trash2,
    Wallet,
    X,
} from '@lucide/vue';
import { computed, nextTick, onMounted, ref } from 'vue';
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
import ImportDialog from '@/components/ImportDialog.vue';
import StatementPrint from '@/components/StatementPrint.vue';
import suppliers from '@/routes/suppliers';
import supplierStatementRoutes from '@/routes/suppliers/statement';
import type { Team } from '@/types';

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

// Table Column Customizer State
const defaultVisibleColumns = {
    profile: true,
    contact: true,
    balance: true,
    actions: true,
};

const visibleColumns = ref({ ...defaultVisibleColumns });

const supplierColumnLabels: Record<keyof typeof defaultVisibleColumns, string> = {
    profile: 'Supplier Profile',
    contact: 'Contact & Location',
    balance: 'Current Balance',
    actions: 'Actions',
};

const STORAGE_KEY = 'faizan_mobile_suppliers_table_columns_v1';
const PER_PAGE_STORAGE_KEY = 'faizan_mobile_suppliers_per_page_v1';

onMounted(() => {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            visibleColumns.value = { ...defaultVisibleColumns, ...JSON.parse(saved) };
        }
        const savedPerPage = localStorage.getItem(PER_PAGE_STORAGE_KEY);
        if (savedPerPage && Number(savedPerPage) !== perPage.value) {
            perPage.value = Number(savedPerPage);
            applyFilters();
        }
    } catch (e) {
        console.error(e);
    }
});

const toggleSupplierColumn = (key: string) => {
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

interface SupplierLedgerItem {
    id: number;
    type: string;
    amount: number | string;
    balance_after: number | string;
    reference_id?: string | null;
    notes?: string | null;
    created_at: string;
}

interface SupplierItem {
    id: number;
    name: string;
    company?: string | null;
    phone?: string | null;
    address?: string | null;
    current_balance: number | string;
    ledgers?: SupplierLedgerItem[];
}

const props = defineProps<{
    suppliers?: {
        data?: SupplierItem[];
        links?: Array<{ url: string | null; label: string; active: boolean }>;
        total?: number;
    };
    filters?: { search?: string; balance_filter?: string; per_page?: number };
    summary?: {
        total_suppliers?: number;
        total_payables?: number;
        total_credits?: number;
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
    () => `/${currentTeamSlug.value}/suppliers/import/template`,
);
const importActionUrl = computed(
    () => `/${currentTeamSlug.value}/suppliers/import`,
);

const supplierList = computed<SupplierItem[]>(
    () => props.suppliers?.data || [],
);

const summaryStats = computed(() => ({
    total_suppliers: props.summary?.total_suppliers ?? 0,
    total_payables: props.summary?.total_payables ?? 0,
    total_credits: props.summary?.total_credits ?? 0,
}));

const search = ref(props.filters?.search || '');
const balanceFilter = ref(props.filters?.balance_filter || 'all');
const perPage = ref(props.filters?.per_page || 15);
const showCreate = ref(false);

// Modal States
const editingSupplier = ref<SupplierItem | null>(null);
const viewingSupplierModal = ref<SupplierItem | null>(null);
const activeSupplierTab = ref<'ledger' | 'profile' | 'action'>('ledger');
const managingBalanceSupplier = ref<SupplierItem | null>(null);
const balanceActionType = ref<'purchase' | 'payment'>('purchase');
const deletingSupplier = ref<SupplierItem | null>(null);

const createForm = useForm({
    name: '',
    company: '',
    phone: '',
    address: '',
    opening_balance: '',
    balance_type: 'due',
});

const editForm = useForm({
    name: '',
    company: '',
    phone: '',
    address: '',
});

const balanceForm = useForm({ amount: '', reference_id: '', notes: '' });

const money = (value: number | string) =>
    `Rs. ${Number(value || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 })}`;

const formatDate = (dateString: string) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-PK', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Khata statement print + export
const printStatementData = ref<PrintStatement | null>(null);
const printStatementLoading = ref(false);

const printStatement = async (supplier: SupplierItem) => {
    printStatementLoading.value = true;
    try {
        const response = await fetch(
            suppliers.statement([currentTeamSlug.value, supplier.id]).url,
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

const statementExportUrl = (supplier: SupplierItem, format: 'csv' | 'xlsx') => {
    return supplierStatementRoutes.export(
        [currentTeamSlug.value, supplier.id],
        { query: { format } },
    ).url;
};

const applyFilters = () => {
    try {
        localStorage.setItem(PER_PAGE_STORAGE_KEY, String(perPage.value));
    } catch (e) {
        console.error(e);
    }
    router.get(
        suppliers.index(currentTeamSlug.value).url,
        {
            search: search.value || undefined,
            balance_filter:
                balanceFilter.value !== 'all' ? balanceFilter.value : undefined,
            per_page: perPage.value,
        },
        { preserveState: true, replace: true },
    );
};

const applySearch = () => {
    applyFilters();
};

const clearSearch = () => {
    search.value = '';
    balanceFilter.value = 'all';
    applyFilters();
};

const createSupplier = () =>
    createForm.post(suppliers.store(currentTeamSlug.value).url, {
        onSuccess: () => {
            createForm.reset();
            showCreate.value = false;
        },
    });

const openEditModal = (supplier: SupplierItem) => {
    editingSupplier.value = supplier;
    editForm.name = supplier.name;
    editForm.company = supplier.company || '';
    editForm.phone = supplier.phone || '';
    editForm.address = supplier.address || '';
};

const submitEditSupplier = () => {
    if (!editingSupplier.value) return;
    editForm.put(
        suppliers.update({
            current_team: currentTeamSlug.value,
            supplier: editingSupplier.value.id,
        }).url,
        {
            onSuccess: () => {
                editingSupplier.value = null;
            },
        },
    );
};

const confirmDeleteSupplier = (supplier: SupplierItem) => {
    deletingSupplier.value = supplier;
};

const deleteSupplier = () => {
    if (!deletingSupplier.value) return;
    router.delete(
        suppliers.destroy({
            current_team: currentTeamSlug.value,
            supplier: deletingSupplier.value.id,
        }).url,
        {
            onSuccess: () => {
                deletingSupplier.value = null;
            },
        },
    );
};

const openSupplierProfileModal = (
    supplier: SupplierItem,
    tab: 'ledger' | 'profile' | 'action' = 'ledger',
) => {
    viewingSupplierModal.value = supplier;
    activeSupplierTab.value = tab;
};

const openManageBalanceModal = (
    supplier: SupplierItem,
    type: 'purchase' | 'payment' = 'purchase',
) => {
    managingBalanceSupplier.value = supplier;
    balanceActionType.value = type;
    balanceForm.reset();
    balanceForm.clearErrors();
};

const submitBalanceAction = () => {
    if (!managingBalanceSupplier.value) return;
    const supplierId = managingBalanceSupplier.value.id;
    const route =
        balanceActionType.value === 'purchase'
            ? suppliers.purchases.store({
                  current_team: currentTeamSlug.value,
                  supplier: supplierId,
              }).url
            : suppliers.payments.store({
                  current_team: currentTeamSlug.value,
                  supplier: supplierId,
              }).url;

    balanceForm.post(route, {
        onSuccess: () => {
            balanceForm.reset();
            managingBalanceSupplier.value = null;
            if (viewingSupplierModal.value?.id === supplierId) {
                const updated = supplierList.value.find(
                    (s) => s.id === supplierId,
                );
                if (updated) viewingSupplierModal.value = updated;
            }
        },
    });
};

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam ? '/dashboard' : '/',
            },
            {
                title: 'Suppliers & Payables',
                href: layoutProps.currentTeam
                    ? suppliers.index(layoutProps.currentTeam.slug).url
                    : '/suppliers',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Suppliers & Payables" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Header Banner -->
        <section
            class="glass-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="eyebrow mb-1.5">Purchasing & Khata Desk</p>
                <h1
                    class="flex items-center gap-2.5 text-2xl font-black text-slate-900"
                >
                    <Store class="h-7 w-7 text-primary" /> Suppliers &
                    Payables
                </h1>
                <p class="mt-1 text-xs text-slate-500 font-medium">
                    Manage distributor khata, purchase bills and vendor payments in one place.
                </p>
            </div>
            <Button
                class="gap-2 bg-primary font-bold text-white shadow-md hover:bg-primary/90"
                @click="showCreate = true"
            >
                <Plus class="h-4 w-4" /> Add New Supplier
            </Button>
            <Button
                variant="outline"
                class="gap-2 font-bold text-slate-600 hover:bg-slate-100"
                @click="isImportDialogOpen = true"
            >
                <FileSpreadsheet class="h-4 w-4 text-primary" /> Import /
                Export
            </Button>
        </section>

        <!-- Add New Supplier Modal Window -->
        <div
            v-if="showCreate"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-md p-4"
        >
            <div class="w-full max-w-lg rounded-3xl border border-slate-200/80 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900 text-slate-900 dark:text-white">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#003B7D]/10 text-[#003B7D] dark:bg-blue-500/20 dark:text-blue-400">
                            <Building2 class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-lg font-black tracking-tight text-slate-900 dark:text-white">Add New Supplier</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Create a new distributor / vendor profile</p>
                        </div>
                    </div>
                    <Button variant="ghost" size="sm" class="rounded-xl dark:hover:bg-slate-800" @click="showCreate = false">
                        <X class="h-5 w-5 text-slate-400" />
                    </Button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="createSupplier">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="supplier-name" class="text-xs font-semibold">Supplier Name *</Label>
                            <Input
                                id="supplier-name"
                                v-model="createForm.name"
                                required
                                placeholder="e.g. Hall Road Electronics"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="supplier-company" class="text-xs font-semibold">Company / Distributor</Label>
                            <Input
                                id="supplier-company"
                                v-model="createForm.company"
                                placeholder="e.g. Samsung Official"
                                class="mt-1"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="supplier-phone" class="text-xs font-semibold">Phone Number</Label>
                            <Input
                                id="supplier-phone"
                                v-model="createForm.phone"
                                placeholder="03001234567"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="supplier-address" class="text-xs font-semibold">Address / Market Location</Label>
                            <Input
                                id="supplier-address"
                                v-model="createForm.address"
                                placeholder="e.g. Shop # 42, Hafeez Center, Lahore"
                                class="mt-1"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 space-y-3">
                        <Label class="text-xs font-bold text-slate-800">Opening Balance Setup</Label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <Label for="supplier-opening" class="text-[11px] font-medium text-slate-600">Opening Amount (Rs.)</Label>
                                <Input
                                    id="supplier-opening"
                                    v-model="createForm.opening_balance"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="0"
                                    class="mt-1 bg-white text-xs"
                                />
                            </div>
                            <div>
                                <Label class="text-[11px] font-medium text-slate-600">Balance Status Type</Label>
                                <div class="mt-1 flex gap-1.5">
                                    <button
                                        type="button"
                                        class="flex-1 rounded-lg border py-1.5 text-[11px] font-bold transition-all"
                                        :class="
                                            createForm.balance_type === 'due'
                                                ? 'border-amber-500 bg-amber-50 text-amber-800 shadow-2xs'
                                                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100'
                                        "
                                        @click="createForm.balance_type = 'due'"
                                    >
                                        Payable / Udhaar (+)
                                    </button>
                                    <button
                                        type="button"
                                        class="flex-1 rounded-lg border py-1.5 text-[11px] font-bold transition-all"
                                        :class="
                                            createForm.balance_type === 'advance'
                                                ? 'border-primary bg-primary/10 text-primary shadow-2xs'
                                                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100'
                                        "
                                        @click="createForm.balance_type = 'advance'"
                                    >
                                        Advance / Peshgi (-)
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <Button type="button" variant="outline" @click="showCreate = false">
                            Cancel
                        </Button>
                        <Button class="bg-primary text-white font-bold shadow-md hover:bg-primary/90" :disabled="createForm.processing">
                            Save Supplier Profile
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Metric Cards -->
        <section class="grid gap-4 sm:grid-cols-3">
            <div class="glass-card p-4">
                <p class="eyebrow text-slate-500">Total Suppliers</p>
                <p class="tnum mt-1.5 text-2xl font-black text-slate-900">
                    {{ summaryStats.total_suppliers }}
                </p>
            </div>
            <div class="glass-card p-4">
                <p class="eyebrow text-amber-600">Total Outstanding Payables</p>
                <p class="tnum mt-1.5 text-2xl font-black text-amber-600">
                    {{ money(summaryStats.total_payables) }}
                </p>
            </div>
            <div class="glass-card p-4">
                <p class="eyebrow text-primary">Supplier Credits / Advance</p>
                <p class="tnum mt-1.5 text-2xl font-black text-primary">
                    {{ money(summaryStats.total_credits) }}
                </p>
            </div>
        </section>

        <!-- Search Bar & Filters -->
        <section class="glass-card flex flex-col sm:flex-row items-stretch sm:items-center gap-3 p-3.5">
            <div class="flex flex-1 items-center gap-2">
                <Search class="h-4 w-4 text-slate-400 ml-1" />
                <Input
                    v-model="search"
                    class="border-slate-200 bg-white text-slate-900 text-xs font-medium focus:ring-primary/20"
                    placeholder="Search supplier by name, company, or phone number..."
                    @keyup.enter="applySearch"
                />
                <Button
                    variant="outline"
                    size="sm"
                    class="border-slate-200 bg-white text-slate-900 hover:bg-slate-50 font-semibold text-xs"
                    @click="applySearch"
                >
                    Search
                </Button>
                <Button
                    v-if="search || balanceFilter !== 'all'"
                    variant="ghost"
                    size="sm"
                    class="text-xs text-slate-500"
                    @click="clearSearch"
                >
                    Clear
                </Button>
            </div>

            <!-- Balance Filter Pills & Columns Dropdown -->
            <div class="flex items-center gap-2 overflow-x-auto border-t sm:border-t-0 sm:border-l border-slate-200 pt-2 sm:pt-0 sm:pl-3">
                <div class="flex items-center gap-1">
                    <button
                        v-for="tab in [
                            { id: 'all', label: 'All' },
                            { id: 'payable', label: 'Payables (Udhaar)' },
                            { id: 'advance', label: 'Advances (Peshgi)' },
                            { id: 'zero', label: 'Zero Balance' },
                        ]"
                        :key="tab.id"
                        type="button"
                        class="rounded-lg px-2.5 py-1 text-xs font-bold transition-colors whitespace-nowrap"
                        :class="
                            balanceFilter === tab.id
                                ? 'bg-primary text-white shadow-2xs'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        "
                        @click="balanceFilter = tab.id; applyFilters();"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <!-- Table Columns Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 gap-1.5 text-xs font-semibold whitespace-nowrap"
                        >
                            <SlidersHorizontal class="h-3.5 w-3.5 text-primary" />
                            <span>Columns</span>
                            <span class="ml-1 rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold text-primary">
                                {{ activeColumnCount }}/4
                            </span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56 p-2 space-y-1">
                        <DropdownMenuLabel class="flex items-center justify-between text-xs font-bold px-1 py-1">
                            <span>Table Columns</span>
                            <button
                                type="button"
                                @click="resetColumns"
                                class="text-[11px] font-semibold text-primary hover:underline cursor-pointer"
                            >
                                Reset All
                            </button>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator class="my-1" />
                        <div
                            v-for="(label, key) in supplierColumnLabels"
                            :key="key"
                            @click.stop="toggleSupplierColumn(key)"
                            class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-xs font-medium hover:bg-slate-100 cursor-pointer select-none transition-colors"
                        >
                            <span>{{ label }}</span>
                            <input
                                type="checkbox"
                                :checked="visibleColumns[key as keyof typeof visibleColumns]"
                                @change="toggleSupplierColumn(key)"
                                @click.stop
                                class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary cursor-pointer"
                            />
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>

                <!-- Per-Page Selection -->
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-slate-500">Show:</span>
                    <select
                        v-model="perPage"
                        @change="applyFilters"
                        class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-xs font-medium text-slate-700 shadow-2xs focus:border-primary focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
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
        </section>

        <!-- Suppliers Data Table -->
        <section class="glass-card overflow-hidden rounded-2xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200/80 bg-slate-50/50 text-[11px] font-black tracking-wider text-slate-500 uppercase">
                        <tr>
                            <th v-if="visibleColumns.profile" class="px-5 py-3.5">Supplier Profile</th>
                            <th v-if="visibleColumns.contact" class="px-5 py-3.5">Contact & Location</th>
                            <th v-if="visibleColumns.balance" class="px-5 py-3.5">Current Balance</th>
                            <th v-if="visibleColumns.actions" class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="supplier in supplierList"
                            :key="supplier.id"
                            class="text-slate-600 transition-colors hover:bg-slate-50/60"
                        >
                            <td v-if="visibleColumns.profile" class="px-5 py-4">
                                <button
                                    type="button"
                                    class="text-left group focus:outline-none"
                                    @click="openSupplierProfileModal(supplier, 'profile')"
                                    title="Click to visit Supplier Profile"
                                >
                                    <div class="font-bold text-slate-900 text-sm group-hover:text-primary transition-colors flex items-center gap-1.5">
                                        {{ supplier.name }}
                                        <span class="text-[10px] text-primary/70 font-semibold opacity-0 group-hover:opacity-100 transition-opacity">Visit Profile &rarr;</span>
                                    </div>
                                    <div class="mt-0.5 flex items-center gap-1 text-xs text-slate-500">
                                        <Building2 class="h-3 w-3 text-slate-400" />
                                        <span>{{ supplier.company || 'Independent Distributor' }}</span>
                                    </div>
                                </button>
                            </td>
                            <td v-if="visibleColumns.contact" class="px-5 py-4">
                                <div class="flex items-center gap-1.5 text-xs text-slate-700 font-medium">
                                    <Phone class="h-3.5 w-3.5 text-slate-400" />
                                    <span>{{ supplier.phone || 'No phone' }}</span>
                                </div>
                                <div v-if="supplier.address" class="mt-0.5 flex items-center gap-1 text-[11px] text-slate-400">
                                    <MapPin class="h-3 w-3" />
                                    <span class="truncate max-w-[180px]">{{ supplier.address }}</span>
                                </div>
                            </td>
                            <td v-if="visibleColumns.balance" class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="tnum text-base font-black"
                                        :class="
                                            Number(supplier.current_balance) > 0
                                                ? 'text-amber-600'
                                                : 'text-primary'
                                        "
                                    >
                                        {{ money(Math.abs(Number(supplier.current_balance))) }}
                                    </span>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase"
                                        :class="
                                            Number(supplier.current_balance) > 0
                                                ? 'bg-amber-100 text-amber-700'
                                                : Number(supplier.current_balance) < 0
                                                ? 'bg-primary/10 text-primary'
                                                : 'bg-slate-100 text-slate-600'
                                        "
                                    >
                                        {{
                                            Number(supplier.current_balance) > 0
                                                ? 'Payable'
                                                : Number(supplier.current_balance) < 0
                                                ? 'Advance Credit'
                                                : 'Clear'
                                        }}
                                    </span>
                                </div>
                            </td>
                            <td v-if="visibleColumns.actions" class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- Manage Balance Popup Trigger -->
                                    <Button
                                        size="sm"
                                        class="h-8 bg-amber-500 text-xs font-bold text-white hover:bg-amber-600 shadow-2xs gap-1"
                                        @click="openManageBalanceModal(supplier, 'purchase')"
                                        title="Manage Balance / Add Bill & Payment"
                                    >
                                        <Wallet class="h-3.5 w-3.5" /> Manage Balance
                                    </Button>

                                    <!-- Supplier Profile & Ledger Navigation Trigger -->
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="h-8 border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-100 shadow-2xs gap-1"
                                        @click="openSupplierProfileModal(supplier, 'ledger')"
                                        title="Supplier Profile & Khata Ledger"
                                    >
                                        <FileText class="h-3.5 w-3.5 text-primary" /> Profile & Ledger
                                    </Button>

                                    <!-- Edit -->
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="h-8 w-8 p-0 text-slate-500 hover:text-slate-900"
                                        @click="openEditModal(supplier)"
                                        title="Edit Supplier"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                    </Button>

                                    <!-- Delete -->
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="h-8 w-8 p-0 text-rose-500 hover:bg-rose-50 hover:text-rose-700"
                                        @click="confirmDeleteSupplier(supplier)"
                                        title="Delete Supplier"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="supplierList.length === 0">
                            <td colspan="4" class="px-5 py-12 text-center text-slate-500 font-medium">
                                No suppliers found. Click "Add New Supplier" to create your first distributor profile.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Manage Balance Popup Modal Window -->
        <div
            v-if="managingBalanceSupplier"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-md p-4"
        >
            <div class="w-full max-w-lg rounded-3xl border border-slate-200/80 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900 text-slate-900 dark:text-white">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400">
                            <Wallet class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-lg font-black tracking-tight text-slate-900 dark:text-white">Manage Supplier Balance</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                                {{ managingBalanceSupplier.name }} &bull; Current Balance:
                                <strong :class="Number(managingBalanceSupplier.current_balance) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-[#003B7D] dark:text-blue-400'">
                                    {{ money(Math.abs(Number(managingBalanceSupplier.current_balance))) }}
                                    ({{ Number(managingBalanceSupplier.current_balance) > 0 ? 'Payable' : Number(managingBalanceSupplier.current_balance) < 0 ? 'Advance Credit' : 'Clear' }})
                                </strong>
                            </p>
                        </div>
                    </div>
                    <Button variant="ghost" size="sm" class="rounded-xl dark:hover:bg-slate-800" @click="managingBalanceSupplier = null">
                        <X class="h-5 w-5 text-slate-400" />
                    </Button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submitBalanceAction">
                    <div>
                        <Label class="text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 block">Select Transaction Action</Label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="flex items-center justify-center gap-2 rounded-2xl border p-3 text-xs font-bold transition-all text-left"
                                :class="
                                    balanceActionType === 'purchase'
                                        ? 'border-amber-500 bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-300 ring-2 ring-amber-500/20 shadow-xs'
                                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300 dark:hover:bg-slate-800'
                                "
                                @click="balanceActionType = 'purchase'"
                            >
                                <CreditCard class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                <div>
                                    <div>+ Purchase Bill</div>
                                    <div class="text-[10px] font-normal text-slate-500 dark:text-slate-400">Increases Payable</div>
                                </div>
                            </button>

                            <button
                                type="button"
                                class="flex items-center justify-center gap-2 rounded-2xl border p-3 text-xs font-bold transition-all text-left"
                                :class="
                                    balanceActionType === 'payment'
                                        ? 'border-[#003B7D] bg-blue-50 text-[#003B7D] dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-500 ring-2 ring-blue-500/20 shadow-xs'
                                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300 dark:hover:bg-slate-800'
                                "
                                @click="balanceActionType = 'payment'"
                            >
                                <Wallet class="h-4 w-4 text-[#003B7D] dark:text-blue-400" />
                                <div>
                                    <div>- Record Payment</div>
                                    <div class="text-[10px] font-normal text-slate-500 dark:text-slate-400">Decreases / Advance</div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <div>
                        <Label for="balance-amount" class="text-xs font-semibold text-slate-700 dark:text-slate-300">Amount (Rs.) *</Label>
                        <Input
                            id="balance-amount"
                            v-model="balanceForm.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            required
                            placeholder="Enter amount in PKR"
                            class="mt-1 dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                        />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <Label for="balance-ref" class="text-xs font-semibold text-slate-700 dark:text-slate-300">Invoice / Reference #</Label>
                            <Input
                                id="balance-ref"
                                v-model="balanceForm.reference_id"
                                placeholder="e.g. INV-2024-001"
                                class="mt-1 text-xs dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <Label for="balance-notes" class="text-xs font-semibold text-slate-700 dark:text-slate-300">Notes / Remarks</Label>
                            <Input
                                id="balance-notes"
                                v-model="balanceForm.notes"
                                placeholder="e.g. Paid via Cash / Bank"
                                class="mt-1 text-xs dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <Button type="button" variant="outline" class="rounded-xl dark:border-slate-700 dark:text-slate-300" @click="managingBalanceSupplier = null">
                            Cancel
                        </Button>
                        <Button
                            class="font-bold text-white shadow-md rounded-xl"
                            :class="balanceActionType === 'purchase' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-[#003B7D] hover:bg-[#002855]'"
                            :disabled="balanceForm.processing"
                        >
                            {{ balanceActionType === 'purchase' ? 'Record Purchase Payable' : 'Record Payment Made' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Supplier Modal -->
        <div
            v-if="editingSupplier"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-md p-4"
        >
            <div class="w-full max-w-md rounded-3xl border border-slate-200/80 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900 text-slate-900 dark:text-white">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h2 class="text-lg font-black tracking-tight text-slate-900 dark:text-white">Edit Supplier Details</h2>
                    <Button variant="ghost" size="sm" class="rounded-xl dark:hover:bg-slate-800" @click="editingSupplier = null">
                        <X class="h-4 w-4" />
                    </Button>
                </div>
                <form class="mt-4 space-y-4" @submit.prevent="submitEditSupplier">
                    <div>
                        <Label class="text-xs font-semibold text-slate-700 dark:text-slate-300">Supplier Name *</Label>
                        <Input v-model="editForm.name" required class="mt-1 dark:bg-slate-800 dark:border-slate-700 dark:text-white" />
                    </div>
                    <div>
                        <Label class="text-xs font-semibold text-slate-700 dark:text-slate-300">Company / Distributor</Label>
                        <Input v-model="editForm.company" class="mt-1 dark:bg-slate-800 dark:border-slate-700 dark:text-white" />
                    </div>
                    <div>
                        <Label class="text-xs font-semibold text-slate-700 dark:text-slate-300">Phone Number</Label>
                        <Input v-model="editForm.phone" class="mt-1 dark:bg-slate-800 dark:border-slate-700 dark:text-white" />
                    </div>
                    <div>
                        <Label class="text-xs font-semibold text-slate-700 dark:text-slate-300">Address / Location</Label>
                        <Input v-model="editForm.address" class="mt-1 dark:bg-slate-800 dark:border-slate-700 dark:text-white" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" class="rounded-xl dark:border-slate-700 dark:text-slate-300" @click="editingSupplier = null">Cancel</Button>
                        <Button class="bg-[#003B7D] text-white font-bold rounded-xl hover:bg-[#002855]" :disabled="editForm.processing">Save Changes</Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Supplier Profile & Ledger Navigation Modal -->
        <div
            v-if="viewingSupplierModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-md p-4"
        >
            <div class="w-full max-w-3xl max-h-[90vh] flex flex-col rounded-3xl border border-slate-200/80 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900 text-slate-900 dark:text-white overflow-hidden">
                <!-- Header Banner -->
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-800/40 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#003B7D]/10 text-[#003B7D] dark:bg-blue-500/20 dark:text-blue-400 font-black text-lg">
                            {{ viewingSupplierModal.name.charAt(0).toUpperCase() }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ viewingSupplierModal.name }}</h2>
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase"
                                    :class="
                                        Number(viewingSupplierModal.current_balance) > 0
                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300'
                                            : Number(viewingSupplierModal.current_balance) < 0
                                            ? 'bg-blue-100 text-[#003B7D] dark:bg-blue-950/60 dark:text-blue-300'
                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                                    "
                                >
                                    {{
                                        Number(viewingSupplierModal.current_balance) > 0
                                            ? 'Payable'
                                            : Number(viewingSupplierModal.current_balance) < 0
                                            ? 'Advance Credit'
                                            : 'Clear'
                                    }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">
                                {{ viewingSupplierModal.company || 'Distributor' }} &bull; {{ viewingSupplierModal.phone || 'No phone' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="text-right">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Net Balance</span>
                            <span
                                class="tnum text-lg font-black"
                                :class="Number(viewingSupplierModal.current_balance) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-[#003B7D] dark:text-blue-400'"
                            >
                                {{ money(Math.abs(Number(viewingSupplierModal.current_balance))) }}
                            </span>
                        </div>
                        <Button variant="ghost" size="sm" class="rounded-xl dark:hover:bg-slate-800" @click="viewingSupplierModal = null">
                            <X class="h-5 w-5 text-slate-400" />
                        </Button>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-6 pt-2">
                    <button
                        type="button"
                        class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold border-b-2 transition-all cursor-pointer"
                        :class="
                            activeSupplierTab === 'ledger'
                                ? 'border-[#003B7D] text-[#003B7D] dark:border-blue-400 dark:text-blue-400'
                                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'
                        "
                        @click="activeSupplierTab = 'ledger'"
                    >
                        <History class="h-4 w-4" /> Khata Ledger Statement
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold border-b-2 transition-all"
                        :class="
                            activeSupplierTab === 'profile'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        "
                        @click="activeSupplierTab = 'profile'"
                    >
                        <Building2 class="h-4 w-4" /> Supplier Profile
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold border-b-2 transition-all"
                        :class="
                            activeSupplierTab === 'action'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        "
                        @click="activeSupplierTab = 'action'"
                    >
                        <Wallet class="h-4 w-4" /> Quick Balance Action
                    </button>
                </div>

                <!-- Modal Body Content -->
                <div class="flex-1 overflow-y-auto p-6">
                    <!-- TAB 1: Ledger Statement -->
                    <div v-if="activeSupplierTab === 'ledger'" class="space-y-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-xs font-black tracking-wider text-slate-400 uppercase">Khata Statement Transactions</h3>
                            <div class="flex items-center gap-2">
                                <Button
                                    size="sm"
                                    class="h-7 gap-1.5 bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-700"
                                    :disabled="printStatementLoading"
                                    @click="viewingSupplierModal && printStatement(viewingSupplierModal)"
                                >
                                    <Printer class="h-3.5 w-3.5" />
                                    {{ printStatementLoading ? 'Preparing...' : 'Print Statement' }}
                                </Button>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="outline" size="sm" class="h-7 gap-1.5 text-xs">
                                            <Download class="h-3.5 w-3.5" /> Export
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-56 rounded-xl p-1.5">
                                        <DropdownMenuLabel class="px-2 py-1 text-xs">
                                            Download Statement
                                        </DropdownMenuLabel>
                                        <DropdownMenuItem :as-child="true">
                                            <a
                                                class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium"
                                                :href="statementExportUrl(viewingSupplierModal, 'csv')"
                                            >
                                                <FileSpreadsheet class="h-4 w-4 text-emerald-600 shrink-0" />
                                                Export as CSV
                                            </a>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem :as-child="true">
                                            <a
                                                class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium"
                                                :href="statementExportUrl(viewingSupplierModal, 'xlsx')"
                                            >
                                                <FileSpreadsheet class="h-4 w-4 text-blue-600 shrink-0" />
                                                Export as Excel (XLSX)
                                            </a>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                <Button
                                    size="sm"
                                    class="h-7 bg-amber-500 text-xs font-bold text-white hover:bg-amber-600"
                                    @click="openManageBalanceModal(viewingSupplierModal)"
                                >
                                    + Manage Balance
                                </Button>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200">
                                    <tr>
                                        <th class="px-4 py-3">Date & Time</th>
                                        <th class="px-4 py-3">Type</th>
                                        <th class="px-4 py-3">Ref / Invoice #</th>
                                        <th class="px-4 py-3 text-right">Amount</th>
                                        <th class="px-4 py-3 text-right">Balance After</th>
                                        <th class="px-4 py-3">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-medium">
                                    <tr
                                        v-for="ledger in viewingSupplierModal.ledgers"
                                        :key="ledger.id"
                                        class="hover:bg-slate-50/50"
                                    >
                                        <td class="px-4 py-3 text-slate-500 whitespace-nowrap">
                                            {{ formatDate(ledger.created_at) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-bold uppercase"
                                                :class="
                                                    ledger.type === 'purchase'
                                                        ? 'bg-amber-100 text-amber-800'
                                                        : ledger.type === 'payment'
                                                        ? 'bg-emerald-100 text-emerald-800'
                                                        : 'bg-slate-100 text-slate-700'
                                                "
                                            >
                                                <component
                                                    :is="ledger.type === 'purchase' ? ArrowUpRight : ArrowDownLeft"
                                                    class="h-3 w-3"
                                                />
                                                {{ ledger.type }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-mono text-slate-700">
                                            {{ ledger.reference_id || '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-right tnum font-bold text-slate-900">
                                            {{ money(ledger.amount) }}
                                        </td>
                                        <td class="px-4 py-3 text-right tnum font-bold text-slate-600">
                                            {{ money(ledger.balance_after) }}
                                        </td>
                                        <td class="px-4 py-3 text-slate-500 max-w-[150px] truncate">
                                            {{ ledger.notes || '-' }}
                                        </td>
                                    </tr>
                                    <tr v-if="!viewingSupplierModal.ledgers?.length">
                                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                            No ledger transactions recorded yet.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 2: Supplier Profile Information -->
                    <div v-else-if="activeSupplierTab === 'profile'" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 space-y-3">
                                <h4 class="text-xs font-bold uppercase text-slate-500">Contact & Organization</h4>
                                <div class="space-y-2 text-xs">
                                    <div class="flex items-center justify-between border-b border-slate-200/60 pb-1.5">
                                        <span class="text-slate-500">Supplier Name:</span>
                                        <span class="font-bold text-slate-900">{{ viewingSupplierModal.name }}</span>
                                    </div>
                                    <div class="flex items-center justify-between border-b border-slate-200/60 pb-1.5">
                                        <span class="text-slate-500">Company:</span>
                                        <span class="font-bold text-slate-900">{{ viewingSupplierModal.company || 'N/A' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between border-b border-slate-200/60 pb-1.5">
                                        <span class="text-slate-500">Phone:</span>
                                        <span class="font-mono font-bold text-slate-900">{{ viewingSupplierModal.phone || 'N/A' }}</span>
                                    </div>
                                    <div class="flex items-start justify-between">
                                        <span class="text-slate-500">Market Address:</span>
                                        <span class="font-medium text-slate-900 text-right max-w-[180px]">{{ viewingSupplierModal.address || 'N/A' }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 space-y-3">
                                <h4 class="text-xs font-bold uppercase text-slate-500">Balance Summary</h4>
                                <div class="space-y-2 text-xs">
                                    <div class="flex items-center justify-between border-b border-slate-200/60 pb-1.5">
                                        <span class="text-slate-500">Current Balance:</span>
                                        <span class="font-bold" :class="Number(viewingSupplierModal.current_balance) > 0 ? 'text-amber-600' : 'text-primary'">
                                            {{ money(Math.abs(Number(viewingSupplierModal.current_balance))) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between border-b border-slate-200/60 pb-1.5">
                                        <span class="text-slate-500">Status:</span>
                                        <span class="font-bold uppercase">
                                            {{ Number(viewingSupplierModal.current_balance) > 0 ? 'Payable (Udhaar)' : Number(viewingSupplierModal.current_balance) < 0 ? 'Advance Credit' : 'Clear' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500">Ledger Count:</span>
                                        <span class="font-bold text-slate-900">{{ viewingSupplierModal.ledgers?.length || 0 }} Entries</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button variant="outline" size="sm" @click="openEditModal(viewingSupplierModal)">
                                <Pencil class="h-3.5 w-3.5 mr-1" /> Edit Profile Details
                            </Button>
                        </div>
                    </div>

                    <!-- TAB 3: Quick Action -->
                    <div v-else-if="activeSupplierTab === 'action'" class="p-4 text-center space-y-4">
                        <p class="text-xs text-slate-600 font-medium">Record a purchase bill or vendor payment for {{ viewingSupplierModal.name }}:</p>
                        <div class="flex justify-center gap-3">
                            <Button class="bg-amber-600 text-white font-bold" @click="openManageBalanceModal(viewingSupplierModal, 'purchase')">
                                <CreditCard class="mr-1.5 h-4 w-4" /> + Record Purchase Bill
                            </Button>
                            <Button class="bg-primary text-white font-bold" @click="openManageBalanceModal(viewingSupplierModal, 'payment')">
                                <Wallet class="mr-1.5 h-4 w-4" /> - Record Payment Made
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="border-t border-slate-100 bg-slate-50/50 px-6 py-3 flex justify-end">
                    <Button variant="outline" size="sm" @click="viewingSupplierModal = null">
                        Close Navigation Window
                    </Button>
                </div>
            </div>
        </div>

        <!-- Delete Supplier Confirmation Modal -->
        <div
            v-if="deletingSupplier"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4"
        >
            <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-rose-600 mb-3">
                    <Trash2 class="h-6 w-6" />
                </div>
                <h3 class="text-base font-black text-slate-900">Delete Supplier Profile?</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Are you sure you want to delete <strong class="text-slate-900">{{ deletingSupplier.name }}</strong>? This action cannot be undone.
                </p>
                <div class="mt-5 flex justify-center gap-2">
                    <Button variant="outline" size="sm" @click="deletingSupplier = null">Cancel</Button>
                    <Button size="sm" class="bg-rose-600 text-white font-bold hover:bg-rose-700" @click="deleteSupplier">
                        Delete Supplier
                    </Button>
                </div>
            </div>
        </div>

        <ImportDialog
            v-model:open="isImportDialogOpen"
            :template-url="importTemplateUrl"
            :action-url="importActionUrl"
            title="Import Suppliers"
            description="Bulk upload supplier / distributor profiles from an Excel template."
            entity-label="suppliers"
        />

        <div v-if="printStatementData" class="print-area">
            <StatementPrint
                title="Supplier Khata Statement"
                :party="printStatementData"
                :entries="printStatementData.entries"
                :shop-info="props.shopInfo || { name: '', phone: '', address: '' }"
            />
        </div>
    </div>
</template>
