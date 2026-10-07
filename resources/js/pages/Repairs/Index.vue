<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    CheckCircle,
    Clock,
    DollarSign,
    Edit3,
    Key,
    Layers,
    Package,
    Plus,
    Printer,
    QrCode,
    Search,
    SlidersHorizontal,
    Smartphone,
    Trash2,
    User,
    Wrench,
    XCircle,
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
import repairs from '@/routes/repairs';
import type { Team } from '@/types';
import { toast } from 'vue-sonner';

// Table Column Customizer State
const defaultVisibleColumns = {
    ticket: true,
    customer: true,
    device: true,
    problem: true,
    cost: true,
    status: true,
    actions: true,
};

const visibleColumns = ref({ ...defaultVisibleColumns });

const repairColumnLabels: Record<keyof typeof defaultVisibleColumns, string> = {
    ticket: 'Ticket # & Date',
    customer: 'Customer Details',
    device: 'Device & Security',
    problem: 'Problem / Complaint',
    cost: 'Est. Cost & Balance',
    status: 'Status',
    actions: 'Actions',
};

const STORAGE_KEY = 'faizan_mobile_repairs_table_columns_v1';
const PER_PAGE_STORAGE_KEY = 'faizan_mobile_repairs_per_page_v1';

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

const toggleRepairColumn = (key: string) => {
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

interface RepairTicketItem {
    id: number;
    ticket_no: string;
    customer_name: string;
    customer_phone: string;
    device_model: string;
    imei?: string | null;
    pattern_or_pin?: string | null;
    problem_description: string;
    condition_notes?: string | null;
    estimated_cost: number | string;
    advance_paid: number | string;
    status:
        | 'received'
        | 'in_diagnosis'
        | 'waiting_parts'
        | 'ready'
        | 'delivered'
        | 'cancelled';
    spare_parts_cost: number | string;
    delivered_at?: string | null;
    created_at: string;
}

interface SparePartItem {
    id: number;
    name: string;
    brand: string;
    category: string;
    cost_price: number | string;
    sale_price: number | string;
    stock_quantity: number;
}

interface ShopInfo {
    name: string;
    phone: string;
    address: string;
}

interface SummaryStats {
    total_active: number;
    received_count: number;
    in_progress_count: number;
    ready_count: number;
    delivered_revenue: number;
}

const props = defineProps<{
    tickets: {
        data: RepairTicketItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    spareParts: SparePartItem[];
    shopInfo: ShopInfo;
    filters: {
        search: string;
        status: string;
        per_page?: number;
    };
    summary: SummaryStats;
    latestRepair?: RepairTicketItem | null;
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
                title: 'Repairing Lab',
                href: layoutProps.currentTeam
                    ? repairs.index(layoutProps.currentTeam.slug).url
                    : '/repairs',
            },
        ],
    }),
});

// Search & Filter
const search = ref(props.filters.search || '');
const selectedStatusTab = ref(props.filters.status || 'all');
const perPage = ref(props.filters.per_page || 15);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const applyFilters = () => {
    try {
        localStorage.setItem(PER_PAGE_STORAGE_KEY, String(perPage.value));
    } catch (e) {
        console.error(e);
    }
    router.get(
        repairs.index(currentTeamSlug.value).url,
        {
            search: search.value || undefined,
            status:
                selectedStatusTab.value !== 'all'
                    ? selectedStatusTab.value
                    : undefined,
            per_page: perPage.value,
        },
        { preserveState: true, replace: true },
    );
};

watch(perPage, () => {
    applyFilters();
});

watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 350);
});

watch(selectedStatusTab, () => {
    applyFilters();
});

// Create Ticket Modal
const isCreateModalOpen = ref(false);
const ticketForm = useForm({
    customer_name: '',
    customer_phone: '',
    device_model: '',
    imei: '',
    pattern_or_pin: '',
    problem_description: '',
    condition_notes: '',
    estimated_cost: '',
    advance_paid: 0,
});

const openCreateModal = () => {
    ticketForm.reset();
    ticketForm.clearErrors();
    isCreateModalOpen.value = true;
};

const submitCreateTicket = () => {
    ticketForm.clearErrors();

    if (Number(ticketForm.estimated_cost) > 1000000) {
        ticketForm.setError(
            'estimated_cost',
            'تخمینہ لاگت 10 لاکھ (Rs 1,000,000) سے زیادہ نہیں ہو سکتی / Estimated cost cannot exceed Rs 1,000,000.',
        );
        toast.error('قیمت کی حد سے تجاوز', {
            description:
                'مرمت کی تخمینہ لاگت زیادہ سے زیادہ 10 لاکھ (Rs 1,000,000) ہو سکتی ہے۔',
        });
        return;
    }

    ticketForm.post(repairs.store(currentTeamSlug.value).url, {
        onSuccess: () => {
            isCreateModalOpen.value = false;
        },
    });
};

// Update Ticket Status
const updateTicketStatus = (ticket: RepairTicketItem, newStatus: string) => {
    router.put(
        repairs.updateStatus([currentTeamSlug.value, ticket.id]).url,
        { status: newStatus },
        { preserveState: true },
    );
};

// Add Spare Part Modal
const isSparePartModalOpen = ref(false);
const activeTicketForSparePart = ref<RepairTicketItem | null>(null);

const sparePartForm = useForm({
    product_id: 0,
    quantity: 1,
});

const openSparePartModal = (ticket: RepairTicketItem) => {
    activeTicketForSparePart.value = ticket;
    sparePartForm.reset();
    sparePartForm.clearErrors();
    if (props.spareParts.length > 0) {
        sparePartForm.product_id = props.spareParts[0].id;
    }
    isSparePartModalOpen.value = true;
};

const submitSparePart = () => {
    if (!activeTicketForSparePart.value) return;

    sparePartForm.post(
        repairs.spareParts.add([
            currentTeamSlug.value,
            activeTicketForSparePart.value.id,
        ]).url,
        {
            onSuccess: () => {
                isSparePartModalOpen.value = false;
            },
        },
    );
};

// Claim Token Slip Modal
const isSlipModalOpen = ref(false);
const activeSlipTicket = ref<RepairTicketItem | null>(
    props.latestRepair || null,
);

const openSlipModal = (ticket: RepairTicketItem) => {
    activeSlipTicket.value = ticket;
    isSlipModalOpen.value = true;
};

watch(
    () => props.latestRepair,
    (newTicket) => {
        if (newTicket) {
            activeSlipTicket.value = newTicket;
            isSlipModalOpen.value = true;
        }
    },
    { immediate: true },
);

const deleteTicket = async (ticket: RepairTicketItem) => {
    const ok = await confirm({
        title: 'Delete Repair Ticket',
        message: `Are you sure you want to delete repair ticket "${ticket.ticket_no}"?`,
        confirmText: 'Yes, Delete',
        cancelText: 'Cancel',
        variant: 'destructive',
    });
    if (ok) {
        router.delete(repairs.destroy([currentTeamSlug.value, ticket.id]).url);
    }
};

const printSlip = () => {
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

const getStatusBadgeClass = (status: string) => {
    switch (status) {
        case 'received':
            return 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-900/40';
        case 'in_diagnosis':
            return 'bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-900/40';
        case 'waiting_parts':
            return 'bg-purple-500/10 text-purple-300 border border-purple-500/25';
        case 'ready':
            return 'bg-[#003B7D]/5 text-[#003B7D] border border-[#003B7D]/20 dark:bg-sky-500/10 dark:text-sky-300 dark:border-sky-500/30';
        case 'delivered':
            return 'bg-[#003B7D] text-white font-semibold dark:bg-sky-600';
        case 'cancelled':
            return 'bg-rose-50 text-rose-600 border border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-900/40';
        default:
            return 'bg-muted text-muted-foreground';
    }
};
</script>

<template>
    <Head title="Mobile Repairing Lab" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Top Header Banner -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-bold tracking-tight text-gray-900"
                >
                    <Wrench class="h-7 w-7 text-[#003B7D]" />
                    Mobile Repairing Lab & Service Ticketing
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Manage Repair Job Sheets, Advance Tokens, Pattern Locks &
                    Spare Parts Inward.
                </p>
            </div>
            <div>
                <Button
                    @click="openCreateModal"
                    class="gap-2 bg-[#003B7D] font-bold text-white shadow-sm hover:bg-[#002b5c]"
                >
                    <Plus class="h-4 w-4" /> + New Repair Ticket
                </Button>
            </div>
        </div>

        <!-- Summary Stats Grid -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">Active Tickets</span>
                    <Wrench class="h-5 w-5 text-[#003B7D]" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-gray-900">
                    {{ summary.total_active }}
                </div>
                <div class="mt-1 text-xs text-slate-500">Currently in lab</div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">Tokens Generated</span>
                    <Clock class="h-5 w-5 text-sky-600 dark:text-sky-400" />
                </div>
                <div
                    class="tnum mt-2 text-2xl font-bold text-sky-600 dark:text-sky-400"
                >
                    {{ summary.received_count }}
                </div>
                <div class="mt-1 text-xs text-slate-500">Received / Intake</div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">In Progress / Parts</span>
                    <Layers class="h-5 w-5 text-amber-600" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-amber-600">
                    {{ summary.in_progress_count }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    Diagnosis & Spare Parts
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">Ready for Delivery</span>
                    <CheckCircle class="h-5 w-5 text-[#003B7D]" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-[#003B7D]">
                    {{ summary.ready_count }}
                </div>
                <div class="mt-1 text-xs text-slate-500">Repair completed</div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">Lab Revenue</span>
                    <DollarSign class="h-5 w-5 text-[#003B7D]" />
                </div>
                <div class="tnum mt-2 text-xl font-bold text-[#003B7D]">
                    {{ formatCurrency(summary.delivered_revenue) }}
                </div>
                <div class="mt-1 text-xs text-slate-500">Delivered tickets</div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl md:flex-row md:items-center md:justify-between"
        >
            <div class="relative flex-1">
                <Search
                    class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2"
                />
                <Input
                    v-model="search"
                    placeholder="Search by ticket #, customer name, phone, device model or IMEI..."
                    class="pl-9"
                />
            </div>

            <!-- Status Tabs & Columns Dropdown -->
            <div
                class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200/80 pt-3 md:border-t-0 md:pt-0"
            >
                <div
                    class="no-scrollbar flex gap-1 overflow-x-auto pb-1 text-xs"
                >
                    <button
                        v-for="st in [
                            'all',
                            'received',
                            'in_diagnosis',
                            'waiting_parts',
                            'ready',
                            'delivered',
                            'cancelled',
                        ]"
                        :key="st"
                        @click="selectedStatusTab = st"
                        :class="[
                            'rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap capitalize transition-colors',
                            selectedStatusTab === st
                                ? 'bg-[#003B7D] font-semibold text-white shadow-sm'
                                : 'bg-muted hover:bg-muted/80 text-muted-foreground',
                        ]"
                    >
                        {{ st.replace('_', ' ') }}
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
                            <SlidersHorizontal
                                class="h-3.5 w-3.5 text-[#003B7D]"
                            />
                            <span>Columns</span>
                            <span
                                class="ml-1 rounded-full bg-[#003B7D]/10 px-1.5 py-0.5 text-[10px] font-bold text-[#003B7D]"
                            >
                                {{ activeColumnCount }}/7
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
                            v-for="(label, key) in repairColumnLabels"
                            :key="key"
                            @click.stop="toggleRepairColumn(key)"
                            class="flex cursor-pointer items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors select-none hover:bg-gray-100"
                        >
                            <span>{{ label }}</span>
                            <input
                                type="checkbox"
                                :checked="
                                    visibleColumns[
                                        key as keyof typeof visibleColumns
                                    ]
                                "
                                @change="toggleRepairColumn(key)"
                                @click.stop
                                class="h-4 w-4 cursor-pointer rounded border-gray-300 text-[#003B7D] focus:ring-[#003B7D]"
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
        </div>

        <!-- Repair Tickets Table -->
        <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead
                        class="bg-muted/50 text-muted-foreground font-semibold uppercase"
                    >
                        <tr>
                            <th v-if="visibleColumns.ticket" class="px-4 py-3">
                                Ticket # & Date
                            </th>
                            <th
                                v-if="visibleColumns.customer"
                                class="px-4 py-3"
                            >
                                Customer Details
                            </th>
                            <th v-if="visibleColumns.device" class="px-4 py-3">
                                Device & Security
                            </th>
                            <th v-if="visibleColumns.problem" class="px-4 py-3">
                                Problem / Complaint
                            </th>
                            <th v-if="visibleColumns.cost" class="px-4 py-3">
                                Est. Cost & Balance
                            </th>
                            <th v-if="visibleColumns.status" class="px-4 py-3">
                                Status
                            </th>
                            <th
                                v-if="visibleColumns.actions"
                                class="px-4 py-3 text-right"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-border divide-y">
                        <tr v-if="tickets.data.length === 0">
                            <td
                                colspan="7"
                                class="text-muted-foreground px-4 py-8 text-center"
                            >
                                No repair tickets found matching your query.
                            </td>
                        </tr>

                        <tr
                            v-for="ticket in tickets.data"
                            :key="ticket.id"
                            class="transition-colors hover:bg-gray-50"
                        >
                            <td v-if="visibleColumns.ticket" class="px-4 py-3">
                                <div
                                    class="tnum font-mono text-sm font-bold text-[#003B7D]"
                                >
                                    {{ ticket.ticket_no }}
                                </div>
                                <div class="text-muted-foreground text-[11px]">
                                    {{
                                        new Date(
                                            ticket.created_at,
                                        ).toLocaleDateString('en-PK')
                                    }}
                                </div>
                            </td>

                            <td
                                v-if="visibleColumns.customer"
                                class="px-4 py-3"
                            >
                                <div class="text-foreground text-sm font-bold">
                                    {{ ticket.customer_name }}
                                </div>
                                <div class="text-muted-foreground font-mono">
                                    {{ ticket.customer_phone }}
                                </div>
                            </td>

                            <td v-if="visibleColumns.device" class="px-4 py-3">
                                <div class="text-foreground font-semibold">
                                    {{ ticket.device_model }}
                                </div>
                                <div
                                    v-if="ticket.imei"
                                    class="text-muted-foreground font-mono text-[11px]"
                                >
                                    IMEI: {{ ticket.imei }}
                                </div>
                                <div
                                    v-if="ticket.pattern_or_pin"
                                    class="mt-1 flex items-center gap-1 font-mono text-[11px] text-amber-600"
                                >
                                    <Key class="h-3 w-3" /> Lock:
                                    {{ ticket.pattern_or_pin }}
                                </div>
                            </td>

                            <td
                                v-if="visibleColumns.problem"
                                class="max-w-xs px-4 py-3"
                            >
                                <div
                                    class="text-foreground line-clamp-2 font-medium"
                                >
                                    {{ ticket.problem_description }}
                                </div>
                                <div
                                    v-if="ticket.condition_notes"
                                    class="text-muted-foreground mt-0.5 line-clamp-1 text-[10px] italic"
                                >
                                    Notes: {{ ticket.condition_notes }}
                                </div>
                            </td>

                            <td v-if="visibleColumns.cost" class="px-4 py-3">
                                <div class="tnum text-foreground font-bold">
                                    {{ formatCurrency(ticket.estimated_cost) }}
                                </div>
                                <div
                                    class="tnum text-muted-foreground text-[11px]"
                                >
                                    Adv:
                                    {{ formatCurrency(ticket.advance_paid) }}
                                </div>
                                <div
                                    class="tnum text-[11px] font-semibold text-[#003B7D]"
                                >
                                    Due:
                                    {{
                                        formatCurrency(
                                            Number(ticket.estimated_cost) -
                                                Number(ticket.advance_paid),
                                        )
                                    }}
                                </div>
                            </td>

                            <td v-if="visibleColumns.status" class="px-4 py-3">
                                <Select
                                    :model-value="ticket.status"
                                    @update:model-value="
                                        (val) =>
                                            updateTicketStatus(
                                                ticket,
                                                String(val ?? ''),
                                            )
                                    "
                                >
                                    <SelectTrigger
                                        class="h-7 text-[11px] font-semibold uppercase"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="received"
                                            >Received</SelectItem
                                        >
                                        <SelectItem value="in_diagnosis"
                                            >In Diagnosis</SelectItem
                                        >
                                        <SelectItem value="waiting_parts"
                                            >Waiting Parts</SelectItem
                                        >
                                        <SelectItem value="ready"
                                            >Ready for Delivery</SelectItem
                                        >
                                        <SelectItem value="delivered"
                                            >Delivered & Paid</SelectItem
                                        >
                                        <SelectItem value="cancelled"
                                            >Cancelled</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
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
                                        @click="openSlipModal(ticket)"
                                        title="Print Claim Token Slip"
                                        class="h-8 gap-1 border-[#003B7D]/20 text-[#003B7D] hover:border-[#003B7D]/40 hover:bg-[#003B7D]/5"
                                    >
                                        <Printer class="h-3.5 w-3.5" /> Token
                                    </Button>

                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        @click="openSparePartModal(ticket)"
                                        title="Add Spare Part"
                                        class="h-8 px-2"
                                    >
                                        <Package class="h-3.5 w-3.5" />
                                    </Button>

                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        @click="deleteTicket(ticket)"
                                        title="Delete Ticket"
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

            <!-- Pagination Bar -->
            <div
                v-if="tickets.links.length > 3"
                class="bg-muted/20 flex items-center justify-between border-t px-4 py-3"
            >
                <div class="text-muted-foreground text-xs">
                    Page
                    <span class="font-semibold">{{
                        tickets.current_page
                    }}</span>
                    of
                    <span class="font-semibold">{{ tickets.last_page }}</span>
                    ({{ tickets.total }} tickets)
                </div>
                <div class="flex gap-1">
                    <template v-for="(link, i) in tickets.links" :key="i">
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

        <!-- MODAL 1: Create Repair Ticket Modal -->
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
                            <Wrench class="h-6 w-6" />
                        </div>
                        <div>
                            <DialogTitle
                                class="text-base font-black text-slate-900 dark:text-white"
                            >
                                Create Repair Job Sheet & Token
                            </DialogTitle>
                            <DialogDescription
                                class="mt-0.5 text-xs text-slate-500"
                            >
                                Generate a customer device intake token with
                                fault diagnostic notes.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form
                    @submit.prevent="submitCreateTicket"
                    class="space-y-4 py-1 text-xs"
                >
                    <!-- 1. Customer Info -->
                    <div
                        class="space-y-3 rounded-2xl border border-slate-200/80 bg-slate-50/60 p-3.5 dark:border-slate-800 dark:bg-slate-800/40"
                    >
                        <div
                            class="flex items-center gap-1.5 text-[11px] font-black tracking-wider text-slate-700 uppercase dark:text-slate-300"
                        >
                            <User
                                class="h-3.5 w-3.5 text-[#003B7D] dark:text-blue-400"
                            />
                            <span>1. Customer Contact Details</span>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label
                                    for="cust_name"
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Customer Name
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="cust_name"
                                    v-model="ticketForm.customer_name"
                                    placeholder="e.g. Ali Raza"
                                    class="h-9 rounded-xl border-slate-200 bg-white text-xs font-semibold dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="ticketForm.errors.customer_name"
                                    class="block text-xs font-bold text-rose-600"
                                    >{{ ticketForm.errors.customer_name }}</span
                                >
                            </div>

                            <div class="space-y-1">
                                <Label
                                    for="cust_phone"
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Mobile Number
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="cust_phone"
                                    v-model="ticketForm.customer_phone"
                                    placeholder="03001234567"
                                    class="h-9 rounded-xl border-slate-200 bg-white font-mono text-xs font-semibold dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="ticketForm.errors.customer_phone"
                                    class="block text-xs font-bold text-rose-600"
                                    >{{
                                        ticketForm.errors.customer_phone
                                    }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- 2. Device & Lock Details -->
                    <div
                        class="space-y-3 rounded-2xl border border-blue-200/80 bg-blue-50/40 p-3.5 dark:border-blue-900/40 dark:bg-blue-950/20"
                    >
                        <div
                            class="flex items-center gap-1.5 text-[11px] font-black tracking-wider text-[#003B7D] uppercase dark:text-blue-300"
                        >
                            <Smartphone class="h-3.5 w-3.5" />
                            <span>2. Device Model & Screen Lock</span>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="space-y-1">
                                <Label
                                    for="device_model"
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Device Model
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="device_model"
                                    v-model="ticketForm.device_model"
                                    placeholder="e.g. Redmi Note 12"
                                    class="h-9 rounded-xl border-slate-200 bg-white text-xs font-semibold dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="ticketForm.errors.device_model"
                                    class="block text-xs font-bold text-rose-600"
                                    >{{ ticketForm.errors.device_model }}</span
                                >
                            </div>

                            <div class="space-y-1">
                                <Label
                                    for="imei"
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >IMEI Number (Optional)</Label
                                >
                                <Input
                                    id="imei"
                                    v-model="ticketForm.imei"
                                    placeholder="15-digit IMEI"
                                    class="h-9 rounded-xl border-slate-200 bg-white font-mono text-xs dark:border-slate-700 dark:bg-slate-800"
                                />
                            </div>

                            <div class="space-y-1">
                                <Label
                                    for="pattern_or_pin"
                                    class="flex items-center gap-1 font-bold text-slate-700 dark:text-slate-300"
                                >
                                    <Key class="h-3 w-3 text-slate-500" />
                                    <span>Pattern / PIN Lock</span>
                                </Label>
                                <Input
                                    id="pattern_or_pin"
                                    v-model="ticketForm.pattern_or_pin"
                                    placeholder="e.g. 1234 or Z-Shape"
                                    class="h-9 rounded-xl border-slate-200 bg-white font-mono text-xs dark:border-slate-700 dark:bg-slate-800"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- 3. Fault & Physical Condition -->
                    <div
                        class="space-y-3 rounded-2xl border border-slate-200/80 bg-slate-50/60 p-3.5 dark:border-slate-800 dark:bg-slate-800/40"
                    >
                        <div class="space-y-1">
                            <Label
                                for="problem_description"
                                class="font-bold text-slate-700 dark:text-slate-300"
                                >Customer Complaint / Problem
                                <span class="text-rose-500">*</span></Label
                            >
                            <textarea
                                id="problem_description"
                                v-model="ticketForm.problem_description"
                                rows="2"
                                placeholder="e.g. Display glass broken, touch working, charging port loose..."
                                class="w-full rounded-xl border border-slate-200 bg-white p-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:border-[#003B7D] focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            ></textarea>
                            <span
                                v-if="ticketForm.errors.problem_description"
                                class="block text-xs font-bold text-rose-600"
                                >{{
                                    ticketForm.errors.problem_description
                                }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label
                                for="condition_notes"
                                class="font-bold text-slate-700 dark:text-slate-300"
                                >Physical Condition & Body Scratches</Label
                            >
                            <Input
                                id="condition_notes"
                                v-model="ticketForm.condition_notes"
                                placeholder="e.g. Back cover dented, camera lens ok, SIM tray present"
                                class="h-9 rounded-xl border-slate-200 bg-white text-xs dark:border-slate-700 dark:bg-slate-800"
                            />
                        </div>
                    </div>

                    <!-- 4. Estimated Cost & Advance Paid -->
                    <div
                        class="space-y-3 rounded-2xl border border-emerald-200/80 bg-emerald-50/40 p-3.5 dark:border-emerald-900/40 dark:bg-emerald-950/20"
                    >
                        <div
                            class="flex items-center gap-1.5 text-[11px] font-black tracking-wider text-emerald-800 uppercase dark:text-emerald-300"
                        >
                            <DollarSign class="h-3.5 w-3.5 text-emerald-600" />
                            <span>4. Estimated Cost & Advance Received</span>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <Label
                                        for="estimated_cost"
                                        class="font-bold text-slate-700 dark:text-slate-300"
                                        >Estimated Cost (PKR)
                                        <span class="text-rose-500"
                                            >*</span
                                        ></Label
                                    >
                                    <span class="text-[10px] text-slate-400"
                                        >Max: 10 Lakh</span
                                    >
                                </div>
                                <Input
                                    id="estimated_cost"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="1000000"
                                    v-model="ticketForm.estimated_cost"
                                    placeholder="0.00"
                                    class="h-10 rounded-xl border-slate-200 bg-white text-sm font-black dark:border-slate-700 dark:bg-slate-800"
                                />
                                <span
                                    v-if="ticketForm.errors.estimated_cost"
                                    class="block text-xs font-bold text-rose-600"
                                    >{{
                                        ticketForm.errors.estimated_cost
                                    }}</span
                                >
                            </div>

                            <div class="space-y-1">
                                <Label
                                    for="advance_paid"
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                    >Advance Received (PKR)</Label
                                >
                                <Input
                                    id="advance_paid"
                                    type="number"
                                    step="0.01"
                                    v-model="ticketForm.advance_paid"
                                    placeholder="0.00"
                                    class="h-10 rounded-xl border-slate-200 bg-white text-sm font-black text-emerald-600 dark:border-slate-700 dark:bg-slate-800 dark:text-emerald-400"
                                />
                            </div>
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
                            :disabled="ticketForm.processing"
                            class="rounded-xl bg-[#003B7D] text-xs font-bold text-white shadow-sm transition hover:bg-[#002b5c] active:scale-95"
                        >
                            {{
                                ticketForm.processing
                                    ? 'Creating...'
                                    : 'Create & Generate Token'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL 2: Add Spare Part Modal -->
        <Dialog v-model:open="isSparePartModalOpen">
            <DialogContent
                class="max-w-md rounded-3xl border border-slate-200/90 bg-white p-5 shadow-2xl sm:p-6 dark:border-slate-800 dark:bg-slate-900"
            >
                <DialogHeader
                    class="border-b border-slate-100 pb-3 dark:border-slate-800"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400"
                        >
                            <Layers class="h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle
                                class="text-base font-black text-slate-900 dark:text-white"
                            >
                                Consume Spare Part
                            </DialogTitle>
                            <DialogDescription
                                class="mt-0.5 text-xs text-slate-500"
                            >
                                Select a repair part from inventory to charge to
                                ticket #{{
                                    activeTicketForSparePart?.ticket_no
                                }}.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form
                    @submit.prevent="submitSparePart"
                    class="space-y-4 py-2 text-xs"
                >
                    <div class="space-y-1.5">
                        <Label
                            class="font-bold text-slate-700 dark:text-slate-300"
                            >Select Spare Part / Item *</Label
                        >
                        <Select v-model="sparePartForm.product_id">
                            <SelectTrigger
                                class="h-10 rounded-xl border-slate-200 bg-white text-xs font-semibold dark:border-slate-700 dark:bg-slate-800"
                            >
                                <SelectValue
                                    placeholder="Choose repair part..."
                                />
                            </SelectTrigger>
                            <SelectContent class="rounded-xl">
                                <SelectItem
                                    v-for="part in spareParts"
                                    :key="part.id"
                                    :value="part.id"
                                >
                                    {{ part.name }} ({{ part.brand }}) &bull;
                                    Stock: {{ part.stock_quantity }} &bull;
                                    Cost: {{ formatCurrency(part.cost_price) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <span
                            v-if="sparePartForm.errors.product_id"
                            class="block text-xs font-bold text-rose-600"
                            >{{ sparePartForm.errors.product_id }}</span
                        >
                    </div>

                    <div class="space-y-1.5">
                        <Label
                            for="part_qty"
                            class="font-bold text-slate-700 dark:text-slate-300"
                            >Quantity to Deduct *</Label
                        >
                        <Input
                            id="part_qty"
                            type="number"
                            min="1"
                            v-model="sparePartForm.quantity"
                            class="h-9 rounded-xl border-slate-200 bg-white text-xs font-bold dark:border-slate-700 dark:bg-slate-800"
                        />
                    </div>

                    <DialogFooter
                        class="gap-2 border-t border-slate-100 pt-2 dark:border-slate-800"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="isSparePartModalOpen = false"
                            class="rounded-xl text-xs font-bold"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="sparePartForm.processing"
                            class="rounded-xl bg-[#003B7D] text-xs font-bold text-white shadow-sm transition hover:bg-[#002b5c] active:scale-95"
                        >
                            Deduct Stock & Add Cost
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL 3: Customer Claim Token Slip (80mm / 58mm Thermal Print) -->
        <Dialog v-model:open="isSlipModalOpen">
            <DialogContent
                class="max-w-md rounded-3xl border border-slate-200/90 bg-white p-5 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
            >
                <DialogHeader
                    class="no-print border-b border-slate-100 pb-2 dark:border-slate-800"
                >
                    <DialogTitle
                        class="text-center text-sm font-black text-slate-900 dark:text-white"
                        >Customer Repair Claim Slip</DialogTitle
                    >
                </DialogHeader>

                <div
                    id="repair-token-slip"
                    class="space-y-3 rounded-xl border border-slate-200 bg-white p-3 font-mono text-[11px] leading-tight text-black shadow-xs"
                >
                    <div class="border-b pb-2 text-center">
                        <div class="text-sm font-extrabold uppercase">
                            {{ shopInfo.name }}
                        </div>
                        <div class="text-[10px] text-slate-600">
                            {{ shopInfo.address }}
                        </div>
                        <div class="text-[10px] text-slate-600">
                            Ph: {{ shopInfo.phone }}
                        </div>
                        <div
                            class="mt-1.5 inline-block rounded-md border border-slate-900 px-2 py-0.5 text-xs font-black"
                        >
                            REPAIR CLAIM TOKEN
                        </div>
                    </div>

                    <div class="space-y-0.5 border-b pb-1 text-[10px]">
                        <div
                            class="flex justify-between text-xs font-extrabold"
                        >
                            <span>TOKEN #:</span>
                            <span class="text-sm font-black">{{
                                activeSlipTicket?.ticket_no
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Date:</span>
                            <span>{{
                                activeSlipTicket?.created_at
                                    ? new Date(
                                          activeSlipTicket.created_at,
                                      ).toLocaleString('en-PK')
                                    : ''
                            }}</span>
                        </div>
                        <div class="flex justify-between font-bold">
                            <span>Customer:</span>
                            <span>{{ activeSlipTicket?.customer_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Phone:</span>
                            <span>{{ activeSlipTicket?.customer_phone }}</span>
                        </div>
                    </div>

                    <div class="space-y-1 border-b pb-2">
                        <div class="flex justify-between">
                            <span class="font-bold">Device Model:</span>
                            <span class="font-bold">{{
                                activeSlipTicket?.device_model
                            }}</span>
                        </div>
                        <div
                            v-if="activeSlipTicket?.imei"
                            class="flex justify-between"
                        >
                            <span>IMEI:</span>
                            <span>{{ activeSlipTicket.imei }}</span>
                        </div>
                        <div
                            v-if="activeSlipTicket?.pattern_or_pin"
                            class="flex justify-between text-[11px] font-bold"
                        >
                            <span>Lock/Pattern:</span>
                            <span>{{ activeSlipTicket.pattern_or_pin }}</span>
                        </div>
                        <div class="pt-1">
                            <span class="font-bold">Problem:</span>
                            <div class="text-[10px]">
                                {{ activeSlipTicket?.problem_description }}
                            </div>
                        </div>
                        <div
                            v-if="activeSlipTicket?.condition_notes"
                            class="pt-0.5"
                        >
                            <span class="font-bold">Condition Notes:</span>
                            <div class="text-[10px] italic">
                                {{ activeSlipTicket.condition_notes }}
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1 border-b pb-2 text-[11px]">
                        <div class="flex justify-between">
                            <span>Estimated Cost:</span>
                            <span>{{
                                formatCurrency(
                                    activeSlipTicket?.estimated_cost || 0,
                                )
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Advance Paid:</span>
                            <span>{{
                                formatCurrency(
                                    activeSlipTicket?.advance_paid || 0,
                                )
                            }}</span>
                        </div>
                        <div
                            class="flex justify-between border-t pt-1 text-xs font-extrabold"
                        >
                            <span>Balance Due at Delivery:</span>
                            <span class="font-black text-rose-600">{{
                                formatCurrency(
                                    Number(
                                        activeSlipTicket?.estimated_cost || 0,
                                    ) -
                                        Number(
                                            activeSlipTicket?.advance_paid || 0,
                                        ),
                                )
                            }}</span>
                        </div>
                    </div>

                    <div class="space-y-1 pt-1 text-center text-[9px]">
                        <div class="font-bold">
                            *** PLEASE PRESENT THIS SLIP TO COLLECT DEVICE ***
                        </div>
                        <div class="text-slate-500">
                            No warranty for water damaged or burnt devices.
                        </div>
                    </div>
                </div>

                <DialogFooter
                    class="no-print flex justify-between gap-2 border-t border-slate-100 pt-2 dark:border-slate-800"
                >
                    <Button
                        type="button"
                        variant="outline"
                        @click="isSlipModalOpen = false"
                        class="rounded-xl text-xs font-bold"
                        >Close</Button
                    >
                    <Button
                        type="button"
                        @click="printSlip"
                        class="gap-1.5 rounded-xl bg-[#003B7D] text-xs font-bold text-white shadow-sm transition hover:bg-[#002b5c] active:scale-95"
                    >
                        <Printer class="h-4 w-4" /> Print Token
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
    #repair-token-slip,
    #repair-token-slip * {
        visibility: visible;
    }
    #repair-token-slip {
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
