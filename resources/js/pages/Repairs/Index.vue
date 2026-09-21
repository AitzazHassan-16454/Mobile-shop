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
    Smartphone,
    Trash2,
    User,
    Wrench,
    XCircle,
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
import { useConfirm } from '@/composables/useConfirm';
import repairs from '@/routes/repairs';
import type { Team } from '@/types';

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
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const applyFilters = () => {
    router.get(
        repairs.index(currentTeamSlug.value).url,
        {
            search: search.value || undefined,
            status:
                selectedStatusTab.value !== 'all'
                    ? selectedStatusTab.value
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
            return 'bg-sky-50 text-sky-600 border border-sky-200';
        case 'in_diagnosis':
            return 'bg-amber-50 text-amber-600 border border-amber-200';
        case 'waiting_parts':
            return 'bg-purple-500/10 text-purple-300 border border-purple-500/25';
        case 'ready':
            return 'bg-[#003B7D]/5 text-[#003B7D] border border-[#003B7D]/20';
        case 'delivered':
            return 'bg-[#003B7D] text-white font-semibold';
        case 'cancelled':
            return 'bg-rose-50 text-rose-600 border border-rose-200';
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
                    <Clock class="h-5 w-5 text-sky-600" />
                </div>
                <div class="tnum mt-2 text-2xl font-bold text-sky-600">
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

            <!-- Status Tabs -->
            <div class="no-scrollbar flex gap-1 overflow-x-auto pb-1 text-xs">
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
        </div>

        <!-- Repair Tickets Table -->
        <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead
                        class="bg-muted/50 text-muted-foreground font-semibold uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3">Ticket # & Date</th>
                            <th class="px-4 py-3">Customer Details</th>
                            <th class="px-4 py-3">Device & Security</th>
                            <th class="px-4 py-3">Problem / Complaint</th>
                            <th class="px-4 py-3">Est. Cost & Balance</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
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
                            <td class="px-4 py-3">
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

                            <td class="px-4 py-3">
                                <div class="text-foreground text-sm font-bold">
                                    {{ ticket.customer_name }}
                                </div>
                                <div class="text-muted-foreground font-mono">
                                    {{ ticket.customer_phone }}
                                </div>
                            </td>

                            <td class="px-4 py-3">
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

                            <td class="max-w-xs px-4 py-3">
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

                            <td class="px-4 py-3">
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

                            <td class="px-4 py-3">
                                <Select
                                    :model-value="ticket.status"
                                    @update:model-value="
                                        (val) => updateTicketStatus(ticket, val)
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

                            <td class="px-4 py-3 text-right">
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
            <DialogContent class="max-w-xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Wrench class="h-5 w-5 text-[#003B7D]" />
                        Create Repair Job Sheet & Token
                    </DialogTitle>
                    <DialogDescription>
                        Generate a new customer repair intake sheet.
                    </DialogDescription>
                </DialogHeader>

                <form
                    @submit.prevent="submitCreateTicket"
                    class="space-y-4 py-2 text-xs"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="cust_name">Customer Name *</Label>
                            <Input
                                id="cust_name"
                                v-model="ticketForm.customer_name"
                                placeholder="e.g. Ali Raza"
                            />
                            <span
                                v-if="ticketForm.errors.customer_name"
                                class="text-xs text-rose-600"
                                >{{ ticketForm.errors.customer_name }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="cust_phone">Mobile Number *</Label>
                            <Input
                                id="cust_phone"
                                v-model="ticketForm.customer_phone"
                                placeholder="03001234567"
                            />
                            <span
                                v-if="ticketForm.errors.customer_phone"
                                class="text-xs text-rose-600"
                                >{{ ticketForm.errors.customer_phone }}</span
                            >
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="space-y-1">
                            <Label for="device_model">Device Model *</Label>
                            <Input
                                id="device_model"
                                v-model="ticketForm.device_model"
                                placeholder="e.g. Redmi Note 12"
                            />
                            <span
                                v-if="ticketForm.errors.device_model"
                                class="text-xs text-rose-600"
                                >{{ ticketForm.errors.device_model }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="imei">IMEI Number (Optional)</Label>
                            <Input
                                id="imei"
                                v-model="ticketForm.imei"
                                placeholder="15-digit IMEI"
                                class="font-mono"
                            />
                        </div>

                        <div class="space-y-1">
                            <Label for="pattern_or_pin"
                                >Pattern / Lock PIN</Label
                            >
                            <Input
                                id="pattern_or_pin"
                                v-model="ticketForm.pattern_or_pin"
                                placeholder="e.g. 1234 or L-Shape"
                                class="font-mono"
                            />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <Label for="problem_description"
                            >Customer Complaint / Problem *</Label
                        >
                        <textarea
                            id="problem_description"
                            v-model="ticketForm.problem_description"
                            rows="3"
                            placeholder="e.g. Screen flickering, battery draining fast, charging port loose..."
                            class="w-full rounded-md border border-gray-200 bg-gray-50 p-2 text-xs text-gray-900 placeholder:text-slate-500 focus:ring-2 focus:ring-[#003B7D]/20 focus:outline-none"
                        ></textarea>
                        <span
                            v-if="ticketForm.errors.problem_description"
                            class="text-xs text-rose-600"
                            >{{ ticketForm.errors.problem_description }}</span
                        >
                    </div>

                    <div class="space-y-1">
                        <Label for="condition_notes"
                            >Physical Condition Notes</Label
                        >
                        <Input
                            id="condition_notes"
                            v-model="ticketForm.condition_notes"
                            placeholder="e.g. Body dented, screen glass cracked, SIM tray ok"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4 border-t pt-2">
                        <div class="space-y-1">
                            <Label for="estimated_cost"
                                >Estimated Cost (PKR) *</Label
                            >
                            <Input
                                id="estimated_cost"
                                type="number"
                                step="0.01"
                                v-model="ticketForm.estimated_cost"
                                placeholder="0.00"
                            />
                            <span
                                v-if="ticketForm.errors.estimated_cost"
                                class="text-xs text-rose-600"
                                >{{ ticketForm.errors.estimated_cost }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label for="advance_paid"
                                >Advance Received (PKR)</Label
                            >
                            <Input
                                id="advance_paid"
                                type="number"
                                step="0.01"
                                v-model="ticketForm.advance_paid"
                                placeholder="0.00"
                            />
                        </div>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isCreateModalOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="ticketForm.processing"
                            class="bg-[#003B7D] text-white shadow-sm hover:bg-[#002b5c]"
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
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Consume Spare Part from Stock</DialogTitle>
                    <DialogDescription>
                        Select a repair part / LCD / Flex from inventory to
                        charge to ticket #{{
                            activeTicketForSparePart?.ticket_no
                        }}.
                    </DialogDescription>
                </DialogHeader>

                <form
                    @submit.prevent="submitSparePart"
                    class="space-y-4 py-2 text-xs"
                >
                    <div class="space-y-1">
                        <Label>Select Spare Part / Item *</Label>
                        <Select v-model="sparePartForm.product_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Choose part..." />
                            </SelectTrigger>
                            <SelectContent>
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
                            class="text-xs text-rose-600"
                            >{{ sparePartForm.errors.product_id }}</span
                        >
                    </div>

                    <div class="space-y-1">
                        <Label for="part_qty">Quantity *</Label>
                        <Input
                            id="part_qty"
                            type="number"
                            min="1"
                            v-model="sparePartForm.quantity"
                        />
                    </div>

                    <DialogFooter class="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isSparePartModalOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="sparePartForm.processing"
                            class="bg-[#003B7D] text-white shadow-sm hover:bg-[#002b5c]"
                        >
                            Deduct Stock & Add Cost
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL 3: Customer Claim Token Slip (80mm / 58mm Thermal Print) -->
        <Dialog v-model:open="isSlipModalOpen">
            <DialogContent class="max-w-sm p-4">
                <DialogHeader class="no-print">
                    <DialogTitle class="text-center text-sm"
                        >Customer Claim Slip</DialogTitle
                    >
                </DialogHeader>

                <div
                    id="repair-token-slip"
                    class="space-y-3 bg-white p-2 font-mono text-[11px] leading-tight text-black"
                >
                    <div class="border-b pb-2 text-center">
                        <div class="text-sm font-extrabold uppercase">
                            {{ shopInfo.name }}
                        </div>
                        <div class="text-[10px]">{{ shopInfo.address }}</div>
                        <div class="text-[10px]">Ph: {{ shopInfo.phone }}</div>
                        <div
                            class="mt-1 inline-block border px-2 py-0.5 text-xs font-bold"
                        >
                            REPAIR CLAIM TOKEN
                        </div>
                    </div>

                    <div class="space-y-0.5 border-b pb-1 text-[10px]">
                        <div
                            class="flex justify-between text-xs font-extrabold"
                        >
                            <span>TOKEN #:</span>
                            <span>{{ activeSlipTicket?.ticket_no }}</span>
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
                            <span>{{
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
                        <div>
                            No warranty for water damaged or burnt devices.
                        </div>
                    </div>
                </div>

                <DialogFooter class="no-print flex justify-between pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isSlipModalOpen = false"
                        >Close</Button
                    >
                    <Button
                        type="button"
                        @click="printSlip"
                        class="gap-1 bg-[#003B7D] text-white shadow-sm hover:bg-[#002b5c]"
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
