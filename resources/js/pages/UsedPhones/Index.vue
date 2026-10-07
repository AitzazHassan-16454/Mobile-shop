<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CheckCircle,
    CircleSlash,
    Clock,
    DollarSign,
    FileCheck,
    FileText,
    Image,
    Plus,
    Printer,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    Smartphone,
    Trash2,
    User,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
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
import {
    DropdownMenu,
    DropdownMenuContent,
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
import usedPhones from '@/routes/used-phones';
import type { Team } from '@/types';
import { toast } from 'vue-sonner';

// Table Column Customizer State
const defaultVisibleColumns = {
    voucher: true,
    seller: true,
    device: true,
    cost: true,
    legal: true,
    credit: true,
    actions: true,
};

const visibleColumns = ref({ ...defaultVisibleColumns });

const usedPhoneColumnLabels: Record<
    keyof typeof defaultVisibleColumns,
    string
> = {
    voucher: 'Voucher # & Date',
    seller: 'Seller Identification',
    device: 'Device & IMEIs',
    cost: 'Purchase Cost',
    legal: 'Legal Status',
    credit: 'Credit Status',
    actions: 'Actions',
};

const STORAGE_KEY = 'faizan_mobile_used_phones_table_columns_v1';
const PER_PAGE_STORAGE_KEY = 'faizan_mobile_used_phones_per_page_v1';

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
            applyFilters();
        }
    } catch (e) {
        console.error(e);
    }
});

const toggleUsedPhoneColumn = (key: string) => {
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

const { confirm } = useConfirm();

type CreditStatus = 'pending' | 'approved' | 'rejected';

interface UsedPurchaseItem {
    id: number;
    voucher_no: string;
    seller_name: string;
    seller_father_name?: string | null;
    seller_cnic: string;
    seller_phone: string;
    seller_address?: string | null;
    cnic_front_image?: string | null;
    cnic_back_image?: string | null;
    device_model: string;
    imei_1: string;
    imei_2?: string | null;
    purchase_amount: number | string;
    payment_method: string;
    agreement_signed: boolean;
    status: CreditStatus;
    rejection_reason?: string | null;
    reviewed_at?: string | null;
    reviewer?: { name: string } | null;
    applied_at?: string | null;
    created_at: string;
}

interface ShopInfo {
    name: string;
    phone: string;
    address: string;
}

interface SummaryStats {
    total_purchases: number;
    total_payout: number;
    pending_count: number;
    pending_amount: number;
    approved_count: number;
    approved_amount: number;
    rejected_count: number;
}

const props = defineProps<{
    purchases: {
        data: UsedPurchaseItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    shopInfo: ShopInfo;
    filters: {
        search: string;
        status?: string;
        per_page?: number;
    };
    summary: SummaryStats;
    latestPurchase?: UsedPurchaseItem | null;
}>();

const page = usePage();
const currentTeamSlug = computed(
    () => (page.props.currentTeam as Team | undefined)?.slug || 'default',
);
const canApprove = computed(() => page.props.auth?.isAdmin !== false);

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam ? '/dashboard' : '/',
            },
            {
                title: 'Used Phone Buying',
                href: layoutProps.currentTeam
                    ? usedPhones.index(layoutProps.currentTeam.slug).url
                    : '/used-phones',
            },
        ],
    }),
});

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');
const perPage = ref(props.filters.per_page || 15);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const applyFilters = () => {
    try {
        localStorage.setItem(PER_PAGE_STORAGE_KEY, String(perPage.value));
    } catch (e) {
        console.error(e);
    }
    router.get(
        usedPhones.index(currentTeamSlug.value).url,
        {
            search: search.value || undefined,
            status:
                statusFilter.value === 'all' ? undefined : statusFilter.value,
            per_page: perPage.value,
        },
        { preserveState: true, replace: true },
    );
};

watch(perPage, () => {
    applyFilters();
});

watch(statusFilter, () => {
    applyFilters();
});

watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 350);
});

// Create Modal
const isCreateModalOpen = ref(false);
const purchaseForm = useForm({
    seller_name: '',
    seller_father_name: '',
    seller_cnic: '',
    seller_phone: '',
    seller_address: '',
    device_model: '',
    brand: '',
    color: '',
    storage: '',
    pta_status: 'approved',
    imei_1: '',
    imei_2: '',
    purchase_amount: '',
    payment_method: 'cash',
    cnic_front_image: null as File | null,
    cnic_back_image: null as File | null,
    auto_add_stock: true,
});

const openCreateModal = () => {
    purchaseForm.reset();
    purchaseForm.clearErrors();
    isCreateModalOpen.value = true;
};

const submitPurchaseForm = () => {
    purchaseForm.clearErrors();

    if (Number(purchaseForm.purchase_amount) > 1000000) {
        purchaseForm.setError(
            'purchase_amount',
            'خریداری رقم 10 لاکھ (Rs 1,000,000) سے زیادہ نہیں ہو سکتی / Purchase amount cannot exceed Rs 1,000,000.',
        );
        toast.error('قیمت کی حد سے تجاوز', {
            description:
                'فون کی خریداری رقم زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔',
        });
        return;
    }

    purchaseForm.post(usedPhones.store(currentTeamSlug.value).url, {
        onSuccess: () => {
            isCreateModalOpen.value = false;
        },
    });
};

// Affidavit Voucher Modal
const isVoucherModalOpen = ref(false);
const activeVoucher = ref<UsedPurchaseItem | null>(
    props.latestPurchase || null,
);

const openVoucherModal = (purchase: UsedPurchaseItem) => {
    activeVoucher.value = purchase;
    isVoucherModalOpen.value = true;
};

watch(
    () => props.latestPurchase,
    (newPurchase) => {
        if (newPurchase) {
            activeVoucher.value = newPurchase;
            isVoucherModalOpen.value = true;
        }
    },
    { immediate: true },
);

// Trade-in Credit Approval
const isReviewing = ref<number | null>(null);

const statusLabel = (status: CreditStatus) =>
    ({
        pending: 'Pending Approval',
        approved: 'Approved',
        rejected: 'Rejected',
    })[status];

const statusBadgeClass = (status: CreditStatus) =>
    ({
        pending:
            'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-400',
        approved:
            'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-400',
        rejected:
            'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-400',
    })[status];

const submitReview = (
    purchase: UsedPurchaseItem,
    status: CreditStatus,
    rejectionReason?: string,
) => {
    isReviewing.value = purchase.id;

    router.put(
        usedPhones.status.update([currentTeamSlug.value, purchase.id]).url,
        {
            status,
            rejection_reason: rejectionReason ?? undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                isReviewing.value = null;
                isRejectModalOpen.value = false;
                rejectReason.value = '';
            },
            onError: () => {
                isReviewing.value = null;
            },
        },
    );
};

const approvePurchase = async (purchase: UsedPurchaseItem) => {
    const ok = await confirm({
        title: 'Approve Trade-in Credit',
        message: `Approve ${formatCurrency(purchase.purchase_amount)} credit for voucher "${purchase.voucher_no}"? It will become usable as a trade-in discount on new sales.`,
        confirmText: 'Yes, Approve',
        cancelText: 'Cancel',
    });

    if (ok) {
        submitReview(purchase, 'approved');
    }
};

const reopenPurchase = async (purchase: UsedPurchaseItem) => {
    const ok = await confirm({
        title: 'Move Back To Pending',
        message: `Move voucher "${purchase.voucher_no}" back to pending approval?`,
        confirmText: 'Yes, Reset',
        cancelText: 'Cancel',
    });

    if (ok) {
        submitReview(purchase, 'pending');
    }
};

const isRejectModalOpen = ref(false);
const rejectReason = ref('');
const activeRejectPurchase = ref<UsedPurchaseItem | null>(null);

const openRejectModal = (purchase: UsedPurchaseItem) => {
    activeRejectPurchase.value = purchase;
    rejectReason.value = '';
    isRejectModalOpen.value = true;
};

const confirmReject = () => {
    if (!activeRejectPurchase.value) return;

    if (!rejectReason.value.trim()) {
        toast.error('Reason is required', {
            description:
                'Please tell the team why this credit is being rejected.',
        });
        return;
    }

    submitReview(
        activeRejectPurchase.value,
        'rejected',
        rejectReason.value.trim(),
    );
};

const deletePurchase = async (purchase: UsedPurchaseItem) => {
    const ok = await confirm({
        title: 'Delete Purchase Voucher',
        message: `Are you sure you want to delete purchase voucher "${purchase.voucher_no}"?`,
        confirmText: 'Yes, Delete',
        cancelText: 'Cancel',
        variant: 'destructive',
    });
    if (ok) {
        router.delete(
            usedPhones.destroy([currentTeamSlug.value, purchase.id]).url,
        );
    }
};

const printVoucher = () => {
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
    <Head title="Used Phone Purchase & Legal Protection" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Header Banner -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-bold tracking-tight text-gray-900"
                >
                    <ShieldCheck class="h-7 w-7 text-[#003B7D]" />
                    Used Phone Purchase & Legal Affidavit Log
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Legal protection intake with seller CNIC verification &
                    auto-inflow into used inventory.
                </p>
            </div>
            <div>
                <Button
                    @click="openCreateModal"
                    class="gap-2 bg-[#003B7D] font-semibold text-white shadow-sm hover:bg-[#002b5c]"
                >
                    <Plus class="h-4 w-4" /> Buy Used Phone (Affidavit Entry)
                </Button>
            </div>
        </div>

        <!-- Summary Metrics -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >Purchased Units</span
                    >
                    <Smartphone class="h-5 w-5 text-[#003B7D]" />
                </div>
                <div class="mt-2 text-2xl font-bold text-gray-900">
                    {{ summary.total_purchases }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Total used handsets logged
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >Total Payout Amount</span
                    >
                    <DollarSign class="h-5 w-5 text-[#003B7D]" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-[#003B7D]">
                    {{ formatCurrency(summary.total_payout) }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Total cash paid out to sellers
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >Legal Protection</span
                    >
                    <FileCheck class="h-5 w-5 text-sky-600 dark:text-sky-400" />
                </div>
                <div
                    class="mt-2 text-2xl font-bold text-sky-600 dark:text-sky-400"
                >
                    100% Signed
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Affidavits on record
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >Stock Auto-Inflow</span
                    >
                    <CheckCircle
                        class="h-5 w-5 text-sky-600 dark:text-sky-400"
                    />
                </div>
                <div class="mt-2 text-2xl font-bold text-gray-900">Active</div>
                <div class="mt-1 text-xs text-slate-500">
                    Synced to Used Inventory
                </div>
            </div>

            <div
                class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-amber-700 uppercase"
                        >Pending Approval</span
                    >
                    <Clock class="h-5 w-5 text-amber-600" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-amber-700">
                    {{ summary.pending_count }}
                </div>
                <div class="tnum mt-1 text-xs text-amber-700/80">
                    {{ formatCurrency(summary.pending_amount) }} awaiting review
                </div>
            </div>

            <div
                class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-emerald-700 uppercase"
                        >Approved &amp; Unapplied</span
                    >
                    <CheckCircle class="h-5 w-5 text-emerald-600" />
                </div>
                <div class="mt-2 text-2xl font-bold text-emerald-700">
                    {{ summary.approved_count }}
                </div>
                <div class="tnum mt-1 text-xs text-emerald-700/80">
                    {{ formatCurrency(summary.approved_amount) }} usable on new
                    sales
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >Rejected</span
                    >
                    <CircleSlash class="h-5 w-5 text-rose-500" />
                </div>
                <div class="mt-2 text-2xl font-bold text-gray-900">
                    {{ summary.rejected_count }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Credits not allowed as trade-in
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div
            class="flex items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
        >
            <div class="relative max-w-md flex-1">
                <Search
                    class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2"
                />
                <Input
                    v-model="search"
                    placeholder="Search by voucher #, seller name, CNIC, phone or IMEI..."
                    class="border-gray-200 bg-gray-50 pl-9 text-gray-900 placeholder:text-slate-500 focus-visible:border-[#003B7D]/70 focus-visible:ring-[#003B7D]/20"
                />
            </div>

            <!-- Table Columns Dropdown -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 gap-1.5 text-xs font-semibold whitespace-nowrap"
                    >
                        <SlidersHorizontal class="h-3.5 w-3.5 text-[#003B7D]" />
                        <span>Columns</span>
                        <span
                            class="ml-1 rounded-full bg-[#003B7D]/10 px-1.5 py-0.5 text-[10px] font-bold text-[#003B7D]"
                        >
                            {{ activeColumnCount }}/{{
                                Object.keys(visibleColumns).length
                            }}
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
                        v-for="(label, key) in usedPhoneColumnLabels"
                        :key="key"
                        @click.stop="toggleUsedPhoneColumn(key)"
                        class="flex cursor-pointer items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors select-none hover:bg-gray-100 dark:hover:bg-gray-800"
                    >
                        <span>{{ label }}</span>
                        <input
                            type="checkbox"
                            :checked="
                                visibleColumns[
                                    key as keyof typeof visibleColumns
                                ]
                            "
                            @change="toggleUsedPhoneColumn(key)"
                            @click.stop
                            class="h-4 w-4 cursor-pointer rounded border-gray-300 text-[#003B7D] focus:ring-[#003B7D]"
                        />
                    </div>
                </DropdownMenuContent>
            </DropdownMenu>

            <!-- Credit Status Filter -->
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-slate-500">Status:</span>
                <select
                    v-model="statusFilter"
                    class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-xs font-medium text-slate-700 shadow-2xs focus:border-[#003B7D] focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
                >
                    <option value="all">All</option>
                    <option value="pending">Pending Approval</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <!-- Per-Page Selection -->
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-slate-500">Show:</span>
                <select
                    v-model="perPage"
                    @change="applyFilters"
                    class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-xs font-medium text-slate-700 shadow-2xs focus:border-[#003B7D] focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
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

        <!-- Purchases Log Table -->
        <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead
                        class="bg-gray-50 font-semibold text-slate-500 uppercase"
                    >
                        <tr>
                            <th v-if="visibleColumns.voucher" class="px-4 py-3">
                                Voucher # & Date
                            </th>
                            <th v-if="visibleColumns.seller" class="px-4 py-3">
                                Seller Identification
                            </th>
                            <th v-if="visibleColumns.device" class="px-4 py-3">
                                Device & IMEIs
                            </th>
                            <th v-if="visibleColumns.cost" class="px-4 py-3">
                                Purchase Cost
                            </th>
                            <th v-if="visibleColumns.legal" class="px-4 py-3">
                                Legal Status
                            </th>
                            <th v-if="visibleColumns.credit" class="px-4 py-3">
                                Credit Status
                            </th>
                            <th
                                v-if="visibleColumns.actions"
                                class="px-4 py-3 text-right"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-if="purchases.data.length === 0">
                            <td
                                :colspan="
                                    Object.keys(usedPhoneColumnLabels).filter(
                                        (key) =>
                                            visibleColumns[
                                                key as keyof typeof visibleColumns
                                            ],
                                    ).length
                                "
                                class="text-muted-foreground px-4 py-8 text-center"
                            >
                                No used phone purchases found.
                            </td>
                        </tr>

                        <tr
                            v-for="item in purchases.data"
                            :key="item.id"
                            class="transition-colors hover:bg-gray-50"
                        >
                            <td
                                v-if="visibleColumns.voucher"
                                class="px-4 py-3 font-mono"
                            >
                                <div class="text-sm font-bold text-[#003B7D]">
                                    {{ item.voucher_no }}
                                </div>
                                <div class="text-muted-foreground text-[11px]">
                                    {{
                                        new Date(
                                            item.created_at,
                                        ).toLocaleDateString('en-PK')
                                    }}
                                </div>
                            </td>

                            <td v-if="visibleColumns.seller" class="px-4 py-3">
                                <div class="text-foreground text-sm font-bold">
                                    {{ item.seller_name }}
                                </div>
                                <div
                                    v-if="item.seller_father_name"
                                    class="text-muted-foreground text-[11px]"
                                >
                                    S/O: {{ item.seller_father_name }}
                                </div>
                                <div
                                    class="mt-0.5 font-mono font-semibold text-[#003B7D]/90"
                                >
                                    CNIC: {{ item.seller_cnic }}
                                </div>
                                <div class="text-muted-foreground font-mono">
                                    Ph: {{ item.seller_phone }}
                                </div>
                            </td>

                            <td v-if="visibleColumns.device" class="px-4 py-3">
                                <div class="text-foreground font-bold">
                                    {{ item.device_model }}
                                </div>
                                <div
                                    class="font-mono text-[11px] font-semibold text-[#003B7D]/90"
                                >
                                    IMEI 1: {{ item.imei_1 }}
                                </div>
                                <div
                                    v-if="item.imei_2"
                                    class="text-muted-foreground font-mono text-[10px]"
                                >
                                    IMEI 2: {{ item.imei_2 }}
                                </div>
                            </td>

                            <td
                                v-if="visibleColumns.cost"
                                class="tnum text-foreground px-4 py-3 text-sm font-extrabold"
                            >
                                {{ formatCurrency(item.purchase_amount) }}
                                <div
                                    class="text-muted-foreground text-[10px] font-normal uppercase"
                                >
                                    {{ item.payment_method }}
                                </div>
                            </td>

                            <td v-if="visibleColumns.legal" class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border border-[#003B7D]/20 bg-[#003B7D]/5 px-2.5 py-0.5 font-semibold text-[#003B7D]"
                                >
                                    <FileCheck class="h-3.5 w-3.5" /> Affidavit
                                    Signed
                                </span>
                            </td>

                            <td v-if="visibleColumns.credit" class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold uppercase"
                                    :class="statusBadgeClass(item.status)"
                                >
                                    {{ statusLabel(item.status) }}
                                </span>

                                <div
                                    v-if="item.applied_at"
                                    class="mt-1 text-[10px] font-semibold text-sky-600 dark:text-sky-400"
                                >
                                    Applied to a sale
                                </div>
                                <div
                                    v-else-if="item.rejection_reason"
                                    class="text-muted-foreground mt-1 max-w-[220px] text-[10px] leading-tight"
                                >
                                    {{ item.rejection_reason }}
                                </div>
                                <div
                                    v-else-if="
                                        item.reviewed_at && item.reviewer
                                    "
                                    class="text-muted-foreground mt-1 text-[10px]"
                                >
                                    by {{ item.reviewer.name }}
                                </div>

                                <div
                                    v-if="
                                        canApprove &&
                                        !item.applied_at &&
                                        isReviewing !== item.id
                                    "
                                    class="mt-1.5 flex items-center gap-1"
                                >
                                    <Button
                                        v-if="item.status !== 'approved'"
                                        size="sm"
                                        @click="approvePurchase(item)"
                                        class="h-7 gap-1 bg-emerald-600 px-2 text-[11px] font-semibold text-white hover:bg-emerald-700"
                                    >
                                        <CheckCircle class="h-3 w-3" />
                                        Approve
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        @click="
                                            item.status === 'pending'
                                                ? openRejectModal(item)
                                                : reopenPurchase(item)
                                        "
                                        class="h-7 px-2 text-[11px] font-semibold"
                                    >
                                        {{
                                            item.status === 'pending'
                                                ? 'Reject'
                                                : 'Reset'
                                        }}
                                    </Button>
                                </div>

                                <div
                                    v-else-if="
                                        canApprove && isReviewing === item.id
                                    "
                                    class="text-muted-foreground mt-1.5 text-[10px]"
                                >
                                    Saving...
                                </div>
                            </td>

                            <td
                                v-if="visibleColumns.actions"
                                class="px-4 py-3 text-right"
                            >
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        @click="openVoucherModal(item)"
                                        title="Print Legal Affidavit Voucher"
                                        class="h-8 gap-1 border-[#003B7D]/20 text-[#003B7D] hover:border-[#003B7D]/40 hover:bg-[#003B7D]/5"
                                    >
                                        <Printer class="h-3.5 w-3.5" />
                                        Affidavit
                                    </Button>

                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        @click="deletePurchase(item)"
                                        title="Delete Purchase Log"
                                        class="h-8 w-8 p-0 text-rose-600 hover:bg-rose-50 hover:text-rose-600 dark:text-rose-400 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"
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
                v-if="purchases.links.length > 3"
                class="flex items-center justify-between border-t border-gray-200 bg-transparent px-4 py-3"
            >
                <div class="text-muted-foreground text-xs">
                    Page
                    <span class="font-semibold">{{
                        purchases.current_page
                    }}</span>
                    of
                    <span class="font-semibold">{{ purchases.last_page }}</span>
                    ({{ purchases.total }} items)
                </div>
                <div class="flex gap-1">
                    <template v-for="(link, i) in purchases.links" :key="i">
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

        <!-- MODAL 1: Create Purchase & Affidavit Intake Modal -->
        <Dialog v-model:open="isCreateModalOpen">
            <DialogContent
                class="max-w-2xl rounded-3xl border border-slate-200/90 bg-white p-5 shadow-2xl sm:p-6 dark:border-slate-800 dark:bg-slate-900"
            >
                <DialogHeader
                    class="border-b border-slate-100 pb-3 dark:border-slate-800"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#003B7D]/10 text-[#003B7D] shadow-xs dark:bg-blue-500/20 dark:text-blue-400"
                        >
                            <ShieldCheck class="h-6 w-6" />
                        </div>
                        <div>
                            <DialogTitle
                                class="text-base font-black text-slate-900 dark:text-white"
                            >
                                Used Phone Purchase & Legal Affidavit Intake
                            </DialogTitle>
                            <DialogDescription
                                class="mt-0.5 text-xs text-slate-500"
                            >
                                Collect seller CNIC details and device
                                specifications for legal protection affidavit.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form
                    @submit.prevent="submitPurchaseForm"
                    class="space-y-4 py-1 text-xs"
                >
                    <!-- Seller Details Section -->
                    <div
                        class="space-y-3 rounded-2xl border border-amber-200 bg-amber-50/50 p-3.5 dark:border-amber-900/50 dark:bg-amber-950/20"
                    >
                        <div
                            class="flex items-center gap-1.5 text-[11px] font-black tracking-wider text-amber-900 uppercase dark:text-amber-300"
                        >
                            <User class="h-3.5 w-3.5" />
                            <span
                                >1. Seller Identification & CNIC
                                Verification</span
                            >
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label
                                    for="seller_name"
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Seller Full Name
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="seller_name"
                                    v-model="purchaseForm.seller_name"
                                    placeholder="Name as per CNIC"
                                    class="h-9 rounded-xl border-slate-200 bg-white text-xs font-semibold dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="purchaseForm.errors.seller_name"
                                    class="block text-xs font-bold text-rose-600"
                                    >{{ purchaseForm.errors.seller_name }}</span
                                >
                            </div>

                            <div class="space-y-1">
                                <Label
                                    for="seller_father"
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Father / Husband Name</Label
                                >
                                <Input
                                    id="seller_father"
                                    v-model="purchaseForm.seller_father_name"
                                    placeholder="Father Name"
                                    class="h-9 rounded-xl border-slate-200 bg-white text-xs dark:border-slate-700 dark:bg-slate-800"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label
                                    for="seller_cnic"
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >CNIC / National ID #
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="seller_cnic"
                                    v-model="purchaseForm.seller_cnic"
                                    placeholder="e.g. 35201-1234567-1"
                                    class="h-9 rounded-xl border-slate-200 bg-white font-mono text-xs font-bold dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="purchaseForm.errors.seller_cnic"
                                    class="block text-xs font-bold text-rose-600"
                                    >{{ purchaseForm.errors.seller_cnic }}</span
                                >
                            </div>

                            <div class="space-y-1">
                                <Label
                                    for="seller_phone"
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Mobile Number
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="seller_phone"
                                    v-model="purchaseForm.seller_phone"
                                    placeholder="03001234567"
                                    class="h-9 rounded-xl border-slate-200 bg-white font-mono text-xs font-semibold dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="purchaseForm.errors.seller_phone"
                                    class="block text-xs font-bold text-rose-600"
                                    >{{
                                        purchaseForm.errors.seller_phone
                                    }}</span
                                >
                            </div>
                        </div>

                        <div class="space-y-1">
                            <Label
                                for="seller_address"
                                class="font-bold text-slate-700 dark:text-slate-300"
                                >Residential Address</Label
                            >
                            <Input
                                id="seller_address"
                                v-model="purchaseForm.seller_address"
                                placeholder="Full Home Address"
                                class="h-9 rounded-xl border-slate-200 bg-white text-xs dark:border-slate-700 dark:bg-slate-800"
                            />
                        </div>
                    </div>

                    <!-- Device Specifications Section -->
                    <div
                        class="space-y-3 rounded-2xl border border-blue-200 bg-blue-50/50 p-3.5 dark:border-blue-900/50 dark:bg-blue-950/20"
                    >
                        <div
                            class="flex items-center gap-1.5 text-[11px] font-black tracking-wider text-[#003B7D] uppercase dark:text-blue-300"
                        >
                            <Smartphone class="h-3.5 w-3.5" />
                            <span>2. Device & IMEI Specifications</span>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label
                                    for="dev_model"
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Device Model
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="dev_model"
                                    v-model="purchaseForm.device_model"
                                    placeholder="e.g. Samsung Galaxy S21 Ultra"
                                    class="h-9 rounded-xl border-slate-200 bg-white text-xs font-semibold dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="purchaseForm.errors.device_model"
                                    class="block text-xs font-bold text-rose-600"
                                    >{{
                                        purchaseForm.errors.device_model
                                    }}</span
                                >
                            </div>

                            <div class="space-y-1">
                                <Label
                                    for="dev_brand"
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Brand</Label
                                >
                                <Input
                                    id="dev_brand"
                                    v-model="purchaseForm.brand"
                                    placeholder="e.g. Samsung"
                                    class="h-9 rounded-xl border-slate-200 bg-white text-xs dark:border-slate-700 dark:bg-slate-800"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label
                                    for="imei_1"
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >IMEI 1 Number
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="imei_1"
                                    v-model="purchaseForm.imei_1"
                                    placeholder="15-digit IMEI"
                                    class="h-9 rounded-xl border-slate-200 bg-white font-mono text-xs font-bold dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="purchaseForm.errors.imei_1"
                                    class="block text-xs font-bold text-rose-600"
                                    >{{ purchaseForm.errors.imei_1 }}</span
                                >
                            </div>

                            <div class="space-y-1">
                                <Label
                                    for="imei_2"
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >IMEI 2 (SIM 2)</Label
                                >
                                <Input
                                    id="imei_2"
                                    v-model="purchaseForm.imei_2"
                                    placeholder="Optional IMEI 2"
                                    class="h-9 rounded-xl border-slate-200 bg-white font-mono text-xs dark:border-slate-700 dark:bg-slate-800"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Color</Label
                                >
                                <Input
                                    v-model="purchaseForm.color"
                                    placeholder="e.g. Black"
                                    class="h-9 rounded-xl border-slate-200 bg-white text-xs dark:border-slate-700 dark:bg-slate-800"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Storage</Label
                                >
                                <Input
                                    v-model="purchaseForm.storage"
                                    placeholder="e.g. 128GB"
                                    class="h-9 rounded-xl border-slate-200 bg-white text-xs dark:border-slate-700 dark:bg-slate-800"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >PTA Status</Label
                                >
                                <Select v-model="purchaseForm.pta_status">
                                    <SelectTrigger
                                        class="h-9 rounded-xl border-slate-200 bg-white text-xs font-bold dark:border-slate-700 dark:bg-slate-800"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent class="rounded-xl">
                                        <SelectItem value="approved"
                                            >PTA Approved</SelectItem
                                        >
                                        <SelectItem value="non_pta"
                                            >Non-PTA</SelectItem
                                        >
                                        <SelectItem value="jv"
                                            >JV Locked</SelectItem
                                        >
                                        <SelectItem value="cpid"
                                            >CPID Approved</SelectItem
                                        >
                                        <SelectItem value="software"
                                            >Software Approved</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>

                    <!-- Financial & Agreement Section -->
                    <div
                        class="space-y-3 rounded-2xl border border-slate-200/80 bg-slate-50/60 p-3.5 dark:border-slate-800 dark:bg-slate-800/40"
                    >
                        <div
                            class="flex items-center gap-1.5 text-[11px] font-black tracking-wider text-slate-700 uppercase dark:text-slate-300"
                        >
                            <ShieldCheck class="h-3.5 w-3.5 text-emerald-600" />
                            <span>3. Agreed Amount & Payment Settlement</span>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <Label
                                        for="purch_amt"
                                        class="font-bold text-slate-700 dark:text-slate-300"
                                        >Agreed Purchase Amount (PKR)
                                        <span class="text-rose-500"
                                            >*</span
                                        ></Label
                                    >
                                    <span class="text-[10px] text-slate-400"
                                        >Max: 10 Lakh</span
                                    >
                                </div>
                                <Input
                                    id="purch_amt"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="1000000"
                                    v-model="purchaseForm.purchase_amount"
                                    placeholder="0.00"
                                    class="h-10 rounded-xl border-slate-200 bg-white text-sm font-black dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="purchaseForm.errors.purchase_amount"
                                    class="block text-xs font-bold text-rose-600"
                                    >{{
                                        purchaseForm.errors.purchase_amount
                                    }}</span
                                >
                            </div>

                            <div class="space-y-1">
                                <Label
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Payment Payout Method</Label
                                >
                                <Select v-model="purchaseForm.payment_method">
                                    <SelectTrigger
                                        class="h-10 rounded-xl border-slate-200 bg-white text-xs font-bold dark:border-slate-700 dark:bg-slate-800"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent class="rounded-xl">
                                        <SelectItem value="cash"
                                            >Cash in Hand</SelectItem
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
                        </div>

                        <div
                            class="flex items-center gap-2 border-t border-slate-200/80 pt-2 dark:border-slate-700"
                        >
                            <input
                                id="auto_stock"
                                type="checkbox"
                                v-model="purchaseForm.auto_add_stock"
                                class="h-4 w-4 rounded border-gray-300 accent-[#003B7D]"
                            />
                            <Label
                                for="auto_stock"
                                class="cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-300"
                            >
                                Automatically add this handset to Shop Used
                                Inventory Stock
                            </Label>
                        </div>
                    </div>

                    <DialogFooter
                        class="gap-2 border-t border-slate-100 pt-2 dark:border-slate-800"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="isCreateModalOpen = false"
                            class="rounded-xl text-xs font-bold"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="purchaseForm.processing"
                            class="rounded-xl bg-[#003B7D] text-xs font-bold text-white shadow-sm transition hover:bg-[#002b5c] active:scale-95"
                        >
                            {{
                                purchaseForm.processing
                                    ? 'Saving...'
                                    : 'Save & Print Legal Affidavit'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL 2: Reject Trade-in Credit -->
        <Dialog v-model:open="isRejectModalOpen">
            <DialogContent
                class="max-w-md rounded-3xl border border-slate-200/90 bg-white p-5 shadow-2xl sm:p-6 dark:border-slate-800 dark:bg-slate-900"
            >
                <DialogHeader
                    class="border-b border-slate-100 pb-3 dark:border-slate-800"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400"
                        >
                            <CircleSlash class="h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle
                                class="text-base font-black text-slate-900 dark:text-white"
                            >
                                Reject Trade-in Credit
                            </DialogTitle>
                            <DialogDescription
                                class="mt-0.5 text-xs text-slate-500"
                            >
                                {{
                                    activeRejectPurchase
                                        ? `Voucher ${activeRejectPurchase.voucher_no} — ${formatCurrency(
                                              activeRejectPurchase.purchase_amount,
                                          )} will not be usable as a trade-in discount.`
                                        : 'Void this trade-in voucher.'
                                }}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div class="space-y-2 py-2">
                    <Label
                        for="rejection-reason"
                        class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >Reason for Rejection
                        <span class="text-rose-500">*</span></Label
                    >
                    <textarea
                        id="rejection-reason"
                        v-model="rejectReason"
                        rows="3"
                        placeholder="e.g. IMEI 1 failed verification, seller CNIC invalid, device lock detected..."
                        class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-rose-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    ></textarea>
                </div>

                <DialogFooter
                    class="gap-2 border-t border-slate-100 pt-2 dark:border-slate-800"
                >
                    <Button
                        type="button"
                        variant="outline"
                        @click="isRejectModalOpen = false"
                        class="rounded-xl text-xs font-bold"
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        :disabled="isReviewing !== null"
                        @click="confirmReject"
                        class="rounded-xl bg-rose-600 text-xs font-bold text-white shadow-sm transition hover:bg-rose-700 active:scale-95"
                    >
                        Reject Trade-in Voucher
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- MODAL 3: Legal Affidavit Purchase Agreement Print Slip (80mm / 58mm / A4) -->
        <Dialog v-model:open="isVoucherModalOpen">
            <DialogContent
                class="max-w-md rounded-3xl border border-slate-200/90 bg-white p-5 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
            >
                <DialogHeader
                    class="no-print border-b border-slate-100 pb-2 dark:border-slate-800"
                >
                    <DialogTitle
                        class="text-center text-sm font-black text-slate-900 dark:text-white"
                        >Legal Purchase Affidavit Voucher</DialogTitle
                    >
                </DialogHeader>

                <div
                    id="legal-affidavit-slip"
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
                            USED PHONE PURCHASE AGREEMENT
                        </div>
                    </div>

                    <div class="space-y-0.5 border-b pb-1 text-[10px]">
                        <div class="flex justify-between font-bold">
                            <span>VOUCHER #:</span>
                            <span>{{ activeVoucher?.voucher_no }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Date:</span>
                            <span>{{
                                activeVoucher?.created_at
                                    ? new Date(
                                          activeVoucher.created_at,
                                      ).toLocaleString('en-PK')
                                    : ''
                            }}</span>
                        </div>
                    </div>

                    <!-- Seller Info -->
                    <div class="space-y-1 border-b pb-2">
                        <div class="text-xs font-bold uppercase underline">
                            Seller Information:
                        </div>
                        <div class="flex justify-between">
                            <span>Name:</span>
                            <span class="font-bold">{{
                                activeVoucher?.seller_name
                            }}</span>
                        </div>
                        <div
                            v-if="activeVoucher?.seller_father_name"
                            class="flex justify-between"
                        >
                            <span>Father Name:</span>
                            <span>{{ activeVoucher.seller_father_name }}</span>
                        </div>
                        <div
                            class="flex justify-between font-bold text-slate-900"
                        >
                            <span>CNIC #:</span>
                            <span>{{ activeVoucher?.seller_cnic }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Phone:</span>
                            <span>{{ activeVoucher?.seller_phone }}</span>
                        </div>
                    </div>

                    <!-- Device Info -->
                    <div class="space-y-1 border-b pb-2">
                        <div class="text-xs font-bold uppercase underline">
                            Device Specifications:
                        </div>
                        <div class="flex justify-between">
                            <span>Model:</span>
                            <span class="font-bold">{{
                                activeVoucher?.device_model
                            }}</span>
                        </div>
                        <div class="flex justify-between font-bold">
                            <span>IMEI 1:</span>
                            <span>{{ activeVoucher?.imei_1 }}</span>
                        </div>
                        <div
                            v-if="activeVoucher?.imei_2"
                            class="flex justify-between font-bold"
                        >
                            <span>IMEI 2:</span>
                            <span>{{ activeVoucher.imei_2 }}</span>
                        </div>
                    </div>

                    <div class="border-b pb-2">
                        <div
                            class="flex justify-between text-xs font-extrabold"
                        >
                            <span>Agreed Purchase Price:</span>
                            <span>{{
                                formatCurrency(
                                    activeVoucher?.purchase_amount || 0,
                                )
                            }}</span>
                        </div>
                    </div>

                    <!-- Urdu Legal Declaration -->
                    <div
                        class="border-b pb-2 text-justify text-[10px] leading-normal"
                    >
                        <div class="mb-1 font-bold underline">
                            Legal Declaration / Halafnama:
                        </div>
                        "Mein tasdeeq karta hoon ke ye mobile phone meri shakhsi
                        malkiyat hai aur is per koi police record, chori ya
                        criminal case nahi hai. Agar future mein koi legal masla
                        hua to iska poora zimmadar mein honga."
                    </div>

                    <!-- Signature & Thumb Impression Space -->
                    <div
                        class="grid grid-cols-2 gap-4 pt-4 text-center text-[10px]"
                    >
                        <div class="border-t border-dashed pt-1 font-bold">
                            Seller Signature
                        </div>
                        <div
                            class="flex h-12 items-center justify-center border border-dashed text-[9px] text-gray-500"
                        >
                            Thumb Impression (Anghootha)
                        </div>
                    </div>
                </div>

                <DialogFooter
                    class="no-print flex justify-between gap-2 border-t border-slate-100 pt-2 dark:border-slate-800"
                >
                    <Button
                        type="button"
                        variant="outline"
                        @click="isVoucherModalOpen = false"
                        class="rounded-xl text-xs font-bold"
                        >Close</Button
                    >
                    <Button
                        type="button"
                        @click="printVoucher"
                        class="gap-1.5 rounded-xl bg-[#003B7D] text-xs font-bold text-white shadow-sm transition hover:bg-[#002b5c] active:scale-95"
                    >
                        <Printer class="h-4 w-4" /> Print Affidavit Voucher
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
    #legal-affidavit-slip,
    #legal-affidavit-slip * {
        visibility: visible;
    }
    #legal-affidavit-slip {
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
