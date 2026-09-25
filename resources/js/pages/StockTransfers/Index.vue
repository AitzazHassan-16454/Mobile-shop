<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    Plus,
    Search,
    Send,
    Trash2,
    Truck,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { useConfirm } from '@/composables/useConfirm';
import type { Team } from '@/types';

const { confirm } = useConfirm();
const page = usePage();
const currentTeamSlug = computed(
    () => (page.props.currentTeam as Team | undefined)?.slug || 'default',
);

interface TransferItem {
    id: number;
    quantity: number;
    product: { id: number; name: string; brand: string };
    imei?: {
        id: number;
        imei_1: string;
        imei_2?: string | null;
        color?: string | null;
        storage?: string | null;
    } | null;
}

interface ImeiOption {
    id: number;
    product_id: number;
    imei_1: string;
    imei_2?: string | null;
    color?: string | null;
    storage?: string | null;
    purchase_cost: number | string;
    product?: { id: number; name: string; brand: string };
}

interface AccessoryOption {
    id: number;
    name: string;
    brand: string;
    sale_price: number | string;
    stock_quantity: number;
}

interface StockTransferRow {
    id: number;
    reference_no: string;
    status: 'in_transit' | 'received' | 'cancelled';
    note?: string | null;
    received_at?: string | null;
    created_at: string;
    from_team: { id: number; name: string };
    to_team: { id: number; name: string };
    sender?: { id: number; name: string } | null;
    items: TransferItem[];
}

interface TeamOption {
    id: number;
    name: string;
    slug: string;
    is_personal: boolean;
}

const props = defineProps<{
    transfers: {
        data: StockTransferRow[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    teams: TeamOption[];
    currentTeam: { id: number; name: string } | null;
    catalog: {
        imeis: ImeiOption[];
        accessories: AccessoryOption[];
    };
    filters: {
        search: string;
        status: string;
        per_page: number;
    };
    summary: {
        total: number;
        in_transit: number;
        received: number;
        total_units: number;
    };
}>();

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');
const expandedTransfer = ref<number | null>(null);

const applyFilters = () => {
    router.get(
        `/${currentTeamSlug.value}/stock-transfers`,
        {
            search: search.value || undefined,
            status:
                statusFilter.value === 'all' ? undefined : statusFilter.value,
        },
        { preserveState: true, replace: true },
    );
};

const setStatus = (value: string) => {
    statusFilter.value = value;
    applyFilters();
};

const statusStyles: Record<string, string> = {
    in_transit:
        'bg-amber-100 text-amber-700 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-900/40',
    received:
        'bg-emerald-100 text-emerald-700 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-900/40',
    cancelled: 'bg-slate-100 text-slate-500 border-slate-300',
};

// New transfer dialog
const isNewTransferOpen = ref(false);
const toTeamId = ref<number | null>(null);
const note = ref('');
const imeiQuery = ref('');
const selectedImeiIds = ref<number[]>([]);
const accessoryProductId = ref<number | null>(null);
const accessoryQty = ref<number>(1);
const accessoryRows = ref<{ product_id: number; quantity: number }[]>([]);
const isSubmitting = ref(false);

const filteredImeis = computed(() => {
    const q = imeiQuery.value.trim().toLowerCase();
    if (!q) return props.catalog.imeis;
    return props.catalog.imeis.filter(
        (i) =>
            i.imei_1.toLowerCase().includes(q) ||
            (i.imei_2 || '').toLowerCase().includes(q) ||
            (i.product?.name || '').toLowerCase().includes(q) ||
            (i.storage || '').toLowerCase().includes(q),
    );
});

const selectedImeis = computed(() =>
    props.catalog.imeis.filter((i) => selectedImeiIds.value.includes(i.id)),
);

const totalUnits = computed(
    () =>
        selectedImeiIds.value.length +
        accessoryRows.value.reduce((sum, r) => sum + r.quantity, 0),
);

const addAccessory = () => {
    if (!accessoryProductId.value) return;
    const existing = accessoryRows.value.find(
        (r) => r.product_id === accessoryProductId.value,
    );
    if (existing) {
        existing.quantity = Math.min(
            existing.quantity + Math.max(1, accessoryQty.value),
            props.catalog.accessories.find(
                (a) => a.id === accessoryProductId.value,
            )?.stock_quantity || existing.quantity,
        );
    } else {
        accessoryRows.value.push({
            product_id: accessoryProductId.value,
            quantity: Math.max(1, accessoryQty.value),
        });
    }
    accessoryProductId.value = null;
    accessoryQty.value = 1;
};

const accessoryName = (productId: number) =>
    props.catalog.accessories.find((a) => a.id === productId)?.name ||
    `#${productId}`;

const resetTransferForm = () => {
    toTeamId.value = null;
    note.value = '';
    imeiQuery.value = '';
    selectedImeiIds.value = [];
    accessoryProductId.value = null;
    accessoryQty.value = 1;
    accessoryRows.value = [];
};

const submitTransfer = async () => {
    if (!toTeamId.value) {
        toast.error('Destination Required', {
            description: 'Choose the branch receiving the stock.',
        });
        return;
    }
    if (totalUnits.value === 0) {
        toast.error('Nothing to Send', {
            description: 'Select at least one IMEI or accessory.',
        });
        return;
    }

    isSubmitting.value = true;
    try {
        await router.post(
            `/${currentTeamSlug.value}/stock-transfers`,
            {
                to_team_id: toTeamId.value,
                note: note.value,
                imei_ids: selectedImeiIds.value,
                accessories: accessoryRows.value,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success('Transfer Created', {
                        description: `${totalUnits.value} unit(s) dispatched.`,
                    });
                    isNewTransferOpen.value = false;
                    resetTransferForm();
                },
                onError: (errors) => {
                    const errorMsg =
                        Object.values(errors).flat().join(' ') ||
                        'Could not create the transfer.';
                    toast.error('Transfer Failed', { description: errorMsg });
                },
            },
        );
    } finally {
        isSubmitting.value = false;
    }
};

const markReceived = (transfer: StockTransferRow) => {
    router.post(
        `/${currentTeamSlug.value}/stock-transfers/${transfer.id}/receive`,
        {},
        {
            preserveScroll: true,
            onSuccess: () =>
                toast.success('Transfer Received', {
                    description: `${transfer.reference_no} marked as received.`,
                }),
        },
    );
};

const deleteTransfer = async (transfer: StockTransferRow) => {
    const ok = await confirm({
        title: 'Remove Transfer',
        message: `Remove in-transit transfer ${transfer.reference_no}? This cannot be undone.`,
        confirmText: 'Yes, Remove',
        cancelText: 'Cancel',
        variant: 'destructive',
    });
    if (ok) {
        router.delete(
            `/${currentTeamSlug.value}/stock-transfers/${transfer.id}`,
            {
                preserveScroll: true,
            },
        );
    }
};

const currency = (val: number | string) =>
    `Rs ${Number(val || 0).toLocaleString()}`;

const transferItemsLabel = (transfer: StockTransferRow) => {
    const handsets = transfer.items.filter((i) => i.imei);
    const accessories = transfer.items.filter((i) => !i.imei);
    const parts: string[] = [];
    if (handsets.length) parts.push(`${handsets.length} handset(s)`);
    accessories.forEach((a) => parts.push(`${a.quantity}× ${a.product.name}`));
    return parts.join(' • ') || '—';
};
</script>

<template>
    <Head title="Stock Transfers" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <section
            class="glass-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="eyebrow mb-1.5">Inventory Movement</p>
                <h1
                    class="flex items-center gap-2.5 text-2xl font-black text-slate-900"
                >
                    <ArrowLeftRight class="text-primary h-7 w-7" /> Stock
                    Transfers
                </h1>
                <p class="mt-1 text-xs font-medium text-slate-500">
                    Move stock between branches and keep a complete transfer
                    history.
                </p>
            </div>
            <Button
                class="bg-primary hover:bg-primary/90 gap-2 font-bold text-white shadow-md"
                @click="isNewTransferOpen = true"
            >
                <Plus class="h-4 w-4" /> New Transfer
            </Button>
        </section>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="glass-card p-4">
                <p class="eyebrow text-amber-600">In Transit</p>
                <p class="mt-1 text-2xl font-black text-slate-900">
                    {{ summary.in_transit }}
                </p>
            </div>
            <div class="glass-card p-4">
                <p class="eyebrow text-emerald-600">Received</p>
                <p class="mt-1 text-2xl font-black text-slate-900">
                    {{ summary.received }}
                </p>
            </div>
            <div class="glass-card p-4">
                <p class="eyebrow text-primary">Total Transfers</p>
                <p class="mt-1 text-2xl font-black text-slate-900">
                    {{ summary.total }}
                </p>
            </div>
            <div class="glass-card p-4">
                <p class="eyebrow text-slate-500">Units Moved</p>
                <p class="mt-1 text-2xl font-black text-slate-900">
                    {{ summary.total_units }}
                </p>
            </div>
        </div>

        <section
            class="glass-card flex flex-col items-stretch gap-3 p-3.5 sm:flex-row sm:items-center"
        >
            <div class="relative flex-1">
                <Search
                    class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                />
                <input
                    v-model="search"
                    placeholder="Search reference, branch..."
                    class="focus:border-primary h-10 w-full rounded-xl border border-slate-200 bg-white/70 pr-3 pl-9 text-xs font-semibold placeholder:text-slate-400 focus:outline-none"
                    @keydown.enter="applyFilters"
                />
            </div>
            <div class="flex items-center gap-1.5">
                <button
                    v-for="option in ['all', 'in_transit', 'received']"
                    :key="option"
                    type="button"
                    @click="setStatus(option)"
                    :class="
                        statusFilter === option
                            ? 'bg-primary font-black text-white'
                            : 'bg-white/60 font-bold text-slate-600 hover:bg-white'
                    "
                    class="h-10 rounded-xl px-3 text-xs capitalize transition"
                >
                    {{ option.replace('_', ' ') }}
                </button>
            </div>
        </section>

        <section class="glass-card overflow-hidden rounded-2xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-slate-100 bg-white/60">
                        <tr
                            class="text-[10px] font-black tracking-wider text-slate-400 uppercase"
                        >
                            <th class="px-4 py-3">Reference</th>
                            <th class="px-4 py-3">Route</th>
                            <th class="px-4 py-3">Contents</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Sent</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="transfer in transfers.data"
                            :key="transfer.id"
                        >
                            <td class="px-4 py-3">
                                <button
                                    type="button"
                                    class="text-primary font-black hover:underline"
                                    @click="
                                        expandedTransfer =
                                            expandedTransfer === transfer.id
                                                ? null
                                                : transfer.id
                                    "
                                >
                                    {{ transfer.reference_no }}
                                </button>
                                <div
                                    class="text-[10px] font-semibold text-slate-400"
                                >
                                    by {{ transfer.sender?.name || '—' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    class="flex items-center gap-1.5 font-bold text-slate-700"
                                >
                                    <span>{{ transfer.from_team.name }}</span>
                                    <ArrowLeftRight
                                        class="h-3 w-3 text-slate-300"
                                    />
                                    <span>{{ transfer.to_team.name }}</span>
                                </div>
                            </td>
                            <td
                                class="max-w-[220px] truncate px-4 py-3 font-semibold text-slate-600"
                            >
                                {{ transferItemsLabel(transfer) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-black capitalize',
                                        statusStyles[transfer.status],
                                    ]"
                                >
                                    {{ transfer.status.replace('_', ' ') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                {{
                                    new Date(
                                        transfer.created_at,
                                    ).toLocaleDateString()
                                }}
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <Button
                                        v-if="transfer.status === 'in_transit'"
                                        type="button"
                                        size="sm"
                                        class="gap-1 text-[10px] font-bold"
                                        @click="markReceived(transfer)"
                                    >
                                        <Truck class="h-3 w-3" /> Mark Received
                                    </Button>
                                    <Button
                                        v-if="transfer.status === 'in_transit'"
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        class="h-7 w-7 text-rose-500 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40"
                                        @click="deleteTransfer(transfer)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="transfers.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center">
                                <Truck
                                    class="mx-auto mb-2 h-8 w-8 text-slate-200"
                                />
                                <p class="text-xs font-bold text-slate-400">
                                    No stock transfers yet.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="expandedTransfer"
                class="border-t border-slate-100 bg-white/60 px-4 py-3 text-xs"
            >
                <div
                    class="mb-1.5 text-[10px] font-black tracking-wider text-slate-400 uppercase"
                >
                    Items
                </div>
                <div class="space-y-1">
                    <div
                        v-for="item in transfers.data.find(
                            (t) => t.id === expandedTransfer,
                        )?.items || []"
                        :key="item.id"
                        class="flex items-center justify-between font-semibold text-slate-600"
                    >
                        <span>
                            • {{ item.quantity }}× {{ item.product.name }} ({{
                                item.product.brand
                            }})
                            <span
                                v-if="item.imei"
                                class="text-primary font-mono"
                                >{{ item.imei.imei_1 }}</span
                            >
                        </span>
                        <span class="text-slate-400">{{
                            item.imei?.storage || ''
                        }}</span>
                    </div>
                </div>
            </div>

            <!-- Pagination Footer -->
            <div
                v-if="transfers.total > 0"
                class="flex flex-col items-center justify-between gap-4 border-t border-slate-200/80 bg-slate-50/50 px-6 py-3.5 sm:flex-row dark:border-slate-800 dark:bg-slate-800/20"
            >
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    Showing
                    <span class="font-medium text-slate-900 dark:text-slate-200">{{ transfers.data.length }}</span>
                    of
                    <span class="font-medium text-slate-900 dark:text-slate-200">{{ transfers.total }}</span>
                    transfers
                </div>

                <div v-if="transfers.links && transfers.links.length > 3" class="flex items-center gap-1.5">
                    <template v-for="(link, idx) in transfers.links" :key="idx">
                        <Button
                            v-if="link.url"
                            variant="outline"
                            size="sm"
                            :class="[
                                'h-8 px-3 text-xs',
                                link.active
                                    ? 'border-[#003B7D] bg-[#003B7D] font-semibold text-white hover:bg-[#002b5c]'
                                    : 'border-slate-200 text-slate-700 dark:border-slate-800 dark:text-slate-300',
                            ]"
                            @click="router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="px-2 text-xs text-slate-400 dark:text-slate-600"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </section>
    </div>

    <Dialog
        :open="isNewTransferOpen"
        @update:open="(value: boolean) => !value && (isNewTransferOpen = false)"
    >
        <DialogContent
            class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
        >
            <DialogHeader>
                <DialogTitle class="text-lg font-black text-slate-900"
                    >New Stock Transfer</DialogTitle
                >
                <DialogDescription class="text-xs text-slate-500">
                    Dispatch handsets (IMEI) and/or accessories to another
                    branch.
                </DialogDescription>
            </DialogHeader>

            <div class="max-h-[60vh] space-y-4 overflow-y-auto py-2">
                <div>
                    <label
                        class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                        >From</label
                    >
                    <div
                        class="flex h-10 items-center rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-500"
                    >
                        {{ currentTeam?.name || 'Current Branch' }}
                    </div>
                </div>

                <div>
                    <label
                        class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                        >Destination Branch *</label
                    >
                    <select
                        v-model="toTeamId"
                        class="focus:border-primary h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-semibold focus:outline-none"
                    >
                        <option :value="null" disabled>Select branch...</option>
                        <option
                            v-for="team in teams.filter(
                                (t) => t.id !== currentTeam?.id,
                            )"
                            :key="team.id"
                            :value="team.id"
                        >
                            {{ team.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label
                            class="text-[11px] font-bold text-slate-700 uppercase"
                            >Handsets (IMEI)</label
                        >
                        <span class="text-primary text-[10px] font-black"
                            >{{ selectedImeiIds.length }} selected</span
                        >
                    </div>
                    <div class="relative mb-2">
                        <Search
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model="imeiQuery"
                            placeholder="Scan / search IMEI or model..."
                            class="focus:border-primary h-9 w-full rounded-xl border border-slate-200 pr-3 pl-9 text-xs font-semibold focus:outline-none"
                        />
                    </div>
                    <div
                        class="max-h-40 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200"
                    >
                        <label
                            v-for="imei in filteredImeis"
                            :key="imei.id"
                            class="flex cursor-pointer items-center gap-2.5 px-3 py-2 hover:bg-slate-50"
                        >
                            <input
                                type="checkbox"
                                :value="imei.id"
                                v-model="selectedImeiIds"
                                class="text-primary focus:ring-primary h-4 w-4 rounded border-slate-300"
                            />
                            <span
                                class="font-mono text-xs font-bold text-slate-700"
                                >{{ imei.imei_1 }}</span
                            >
                            <span
                                class="text-[10px] font-semibold text-slate-400"
                            >
                                {{ imei.product?.name }}
                                {{ imei.storage || '' }}
                                {{ imei.color ? '• ' + imei.color : '' }}
                            </span>
                        </label>
                        <p
                            v-if="filteredImeis.length === 0"
                            class="px-3 py-4 text-center text-[11px] font-bold text-slate-400"
                        >
                            No in-stock handsets match.
                        </p>
                    </div>
                </div>

                <div>
                    <label
                        class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                        >Accessories</label
                    >
                    <div class="grid grid-cols-[1fr_100px_auto] gap-2">
                        <select
                            v-model="accessoryProductId"
                            class="focus:border-primary h-9 rounded-xl border border-slate-300 bg-white px-3 text-xs font-semibold focus:outline-none"
                        >
                            <option :value="null" disabled>
                                Select accessory...
                            </option>
                            <option
                                v-for="acc in catalog.accessories"
                                :key="acc.id"
                                :value="acc.id"
                            >
                                {{ acc.name }} ({{ acc.stock_quantity }} in
                                stock)
                            </option>
                        </select>
                        <input
                            v-model="accessoryQty"
                            type="number"
                            min="1"
                            class="focus:border-primary h-9 rounded-xl border border-slate-300 px-3 text-xs font-bold focus:outline-none"
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="addAccessory"
                            class="h-9 text-xs font-bold"
                            >+ Add</Button
                        >
                    </div>
                    <div v-if="accessoryRows.length" class="mt-2 space-y-1">
                        <div
                            v-for="(row, index) in accessoryRows"
                            :key="index"
                            class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5"
                        >
                            <span class="text-xs font-bold text-slate-700"
                                >{{ row.quantity }}×
                                {{ accessoryName(row.product_id) }}</span
                            >
                            <button
                                type="button"
                                @click="accessoryRows.splice(index, 1)"
                                class="text-rose-500 hover:text-rose-700"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label
                        class="mb-1 block text-[11px] font-bold text-slate-700 uppercase"
                        >Note</label
                    >
                    <textarea
                        v-model="note"
                        rows="2"
                        placeholder="Optional dispatch note..."
                        class="focus:border-primary w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold focus:outline-none"
                    ></textarea>
                </div>
            </div>

            <DialogFooter class="pt-3">
                <div
                    class="mr-auto flex items-center gap-2 text-xs font-black text-slate-600"
                >
                    <Send class="text-primary h-4 w-4" />
                    {{ totalUnits }} unit(s)
                </div>
                <Button
                    type="button"
                    variant="outline"
                    @click="isNewTransferOpen = false"
                    >Cancel</Button
                >
                <Button
                    type="button"
                    :disabled="isSubmitting"
                    class="bg-primary hover:bg-primary/90 font-bold text-white"
                    @click="submitTransfer"
                >
                    <span
                        v-if="isSubmitting"
                        class="mr-1.5 inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"
                    ></span>
                    Dispatch Transfer <Send class="h-3.5 w-3.5" />
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
