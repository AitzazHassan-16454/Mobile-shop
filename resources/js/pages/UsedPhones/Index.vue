<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CheckCircle,
    DollarSign,
    FileCheck,
    FileText,
    Image,
    Plus,
    Printer,
    Search,
    ShieldCheck,
    Smartphone,
    Trash2,
    User,
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
import usedPhones from '@/routes/used-phones';
import type { Team } from '@/types';

const { confirm } = useConfirm();

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
    };
    summary: SummaryStats;
    latestPurchase?: UsedPurchaseItem | null;
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
                title: 'Used Phone Buying',
                href: layoutProps.currentTeam
                    ? usedPhones.index(layoutProps.currentTeam.slug).url
                    : '/used-phones',
            },
        ],
    }),
});

const search = ref(props.filters.search || '');
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const applyFilters = () => {
    router.get(
        usedPhones.index(currentTeamSlug.value).url,
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
};

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
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
                    <FileCheck class="h-5 w-5 text-sky-600" />
                </div>
                <div class="mt-2 text-2xl font-bold text-sky-600">
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
                    <CheckCircle class="h-5 w-5 text-sky-600" />
                </div>
                <div class="mt-2 text-2xl font-bold text-gray-900">Active</div>
                <div class="mt-1 text-xs text-slate-500">
                    Synced to Used Inventory
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div
            class="flex items-center justify-between rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl"
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
        </div>

        <!-- Purchases Log Table -->
        <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead
                        class="bg-gray-50 font-semibold text-slate-500 uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3">Voucher # & Date</th>
                            <th class="px-4 py-3">Seller Identification</th>
                            <th class="px-4 py-3">Device & IMEIs</th>
                            <th class="px-4 py-3">Purchase Cost</th>
                            <th class="px-4 py-3">Legal Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-if="purchases.data.length === 0">
                            <td
                                colspan="6"
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
                            <td class="px-4 py-3 font-mono">
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

                            <td class="px-4 py-3">
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

                            <td class="px-4 py-3">
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
                                class="tnum text-foreground px-4 py-3 text-sm font-extrabold"
                            >
                                {{ formatCurrency(item.purchase_amount) }}
                                <div
                                    class="text-muted-foreground text-[10px] font-normal uppercase"
                                >
                                    {{ item.payment_method }}
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border border-[#003B7D]/20 bg-[#003B7D]/5 px-2.5 py-0.5 font-semibold text-[#003B7D]"
                                >
                                    <FileCheck class="h-3.5 w-3.5" /> Affidavit
                                    Signed
                                </span>
                            </td>

                            <td class="px-4 py-3 text-right">
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
            <DialogContent class="max-h-[85vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <ShieldCheck class="h-5 w-5 text-[#003B7D]" />
                        Used Phone Purchase & Legal Affidavit Intake
                    </DialogTitle>
                    <DialogDescription>
                        Collect seller CNIC details and device specifications
                        for legal protection affidavit.
                    </DialogDescription>
                </DialogHeader>

                <form
                    @submit.prevent="submitPurchaseForm"
                    class="space-y-4 py-2 text-xs"
                >
                    <!-- Seller Details Section -->
                    <div
                        class="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-3"
                    >
                        <div
                            class="flex items-center gap-1.5 font-bold text-[#003B7D]"
                        >
                            <User class="h-4 w-4" /> Seller Identification
                            Details
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <Label for="seller_name"
                                    >Seller Full Name *</Label
                                >
                                <Input
                                    id="seller_name"
                                    v-model="purchaseForm.seller_name"
                                    placeholder="Name as per CNIC"
                                    class="border-gray-200 bg-gray-50 text-gray-900 placeholder:text-slate-500 focus-visible:border-[#003B7D]/70 focus-visible:ring-[#003B7D]/20"
                                />
                                <span
                                    v-if="purchaseForm.errors.seller_name"
                                    class="text-xs text-rose-600"
                                    >{{ purchaseForm.errors.seller_name }}</span
                                >
                            </div>

                            <div class="space-y-1">
                                <Label for="seller_father"
                                    >Father / Husband Name</Label
                                >
                                <Input
                                    id="seller_father"
                                    v-model="purchaseForm.seller_father_name"
                                    placeholder="Father Name"
                                    class="border-gray-200 bg-gray-50 text-gray-900 placeholder:text-slate-500 focus-visible:border-[#003B7D]/70 focus-visible:ring-[#003B7D]/20"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <Label for="seller_cnic"
                                    >CNIC / National ID # *</Label
                                >
                                <Input
                                    id="seller_cnic"
                                    v-model="purchaseForm.seller_cnic"
                                    placeholder="e.g. 35201-1234567-1"
                                    font-mono
                                    class="border-gray-200 bg-gray-50 text-gray-900 placeholder:text-slate-500 focus-visible:border-[#003B7D]/70 focus-visible:ring-[#003B7D]/20"
                                />
                                <span
                                    v-if="purchaseForm.errors.seller_cnic"
                                    class="text-xs text-rose-600"
                                    >{{ purchaseForm.errors.seller_cnic }}</span
                                >
                            </div>

                            <div class="space-y-1">
                                <Label for="seller_phone"
                                    >Mobile Number *</Label
                                >
                                <Input
                                    id="seller_phone"
                                    v-model="purchaseForm.seller_phone"
                                    placeholder="03001234567"
                                    font-mono
                                    class="border-gray-200 bg-gray-50 text-gray-900 placeholder:text-slate-500 focus-visible:border-[#003B7D]/70 focus-visible:ring-[#003B7D]/20"
                                />
                                <span
                                    v-if="purchaseForm.errors.seller_phone"
                                    class="text-xs text-rose-600"
                                    >{{
                                        purchaseForm.errors.seller_phone
                                    }}</span
                                >
                            </div>
                        </div>

                        <div class="space-y-1">
                            <Label for="seller_address"
                                >Residential Address</Label
                            >
                            <Input
                                id="seller_address"
                                v-model="purchaseForm.seller_address"
                                placeholder="Full Home Address"
                                class="border-gray-200 bg-gray-50 text-gray-900 placeholder:text-slate-500 focus-visible:border-[#003B7D]/70 focus-visible:ring-[#003B7D]/20"
                            />
                        </div>
                    </div>

                    <!-- Device Specifications Section -->
                    <div
                        class="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-3"
                    >
                        <div
                            class="flex items-center gap-1.5 font-bold text-[#003B7D]"
                        >
                            <Smartphone class="h-4 w-4" /> Device & IMEI
                            Specifications
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <Label for="dev_model">Device Model *</Label>
                                <Input
                                    id="dev_model"
                                    v-model="purchaseForm.device_model"
                                    placeholder="e.g. Samsung Galaxy S21 Ultra"
                                    class="border-gray-200 bg-gray-50 text-gray-900 placeholder:text-slate-500 focus-visible:border-[#003B7D]/70 focus-visible:ring-[#003B7D]/20"
                                />
                                <span
                                    v-if="purchaseForm.errors.device_model"
                                    class="text-xs text-rose-600"
                                    >{{
                                        purchaseForm.errors.device_model
                                    }}</span
                                >
                            </div>

                            <div class="space-y-1">
                                <Label for="dev_brand">Brand</Label>
                                <Input
                                    id="dev_brand"
                                    v-model="purchaseForm.brand"
                                    placeholder="e.g. Samsung"
                                    class="border-gray-200 bg-gray-50 text-gray-900 placeholder:text-slate-500 focus-visible:border-[#003B7D]/70 focus-visible:ring-[#003B7D]/20"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <Label for="imei_1">IMEI 1 Number *</Label>
                                <Input
                                    id="imei_1"
                                    v-model="purchaseForm.imei_1"
                                    placeholder="15-digit IMEI"
                                    font-mono
                                    class="border-gray-200 bg-gray-50 text-gray-900 placeholder:text-slate-500 focus-visible:border-[#003B7D]/70 focus-visible:ring-[#003B7D]/20"
                                />
                                <span
                                    v-if="purchaseForm.errors.imei_1"
                                    class="text-xs text-rose-600"
                                    >{{ purchaseForm.errors.imei_1 }}</span
                                >
                            </div>

                            <div class="space-y-1">
                                <Label for="imei_2">IMEI 2 (SIM 2)</Label>
                                <Input
                                    id="imei_2"
                                    v-model="purchaseForm.imei_2"
                                    placeholder="Optional IMEI 2"
                                    font-mono
                                    class="border-gray-200 bg-gray-50 text-gray-900 placeholder:text-slate-500 focus-visible:border-[#003B7D]/70 focus-visible:ring-[#003B7D]/20"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <Label>Color</Label>
                                <Input
                                    v-model="purchaseForm.color"
                                    placeholder="e.g. Black"
                                    class="h-8 border-gray-200 bg-gray-50 text-xs text-gray-900 placeholder:text-slate-500 focus-visible:border-[#003B7D]/70 focus-visible:ring-[#003B7D]/20"
                                />
                            </div>
                            <div>
                                <Label>Storage</Label>
                                <Input
                                    v-model="purchaseForm.storage"
                                    placeholder="e.g. 128GB"
                                    class="h-8 border-gray-200 bg-gray-50 text-xs text-gray-900 placeholder:text-slate-500 focus-visible:border-[#003B7D]/70 focus-visible:ring-[#003B7D]/20"
                                />
                            </div>
                            <div>
                                <Label>PTA Status</Label>
                                <Select v-model="purchaseForm.pta_status">
                                    <SelectTrigger class="h-8 text-xs"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
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
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="purch_amt"
                                >Agreed Purchase Amount (PKR) *</Label
                            >
                            <Input
                                id="purch_amt"
                                type="number"
                                step="0.01"
                                v-model="purchaseForm.purchase_amount"
                                placeholder="0.00"
                                class="tnum border-gray-200 bg-gray-50 text-gray-900 placeholder:text-slate-500 focus-visible:border-[#003B7D]/70 focus-visible:ring-[#003B7D]/20"
                            />
                            <span
                                v-if="purchaseForm.errors.purchase_amount"
                                class="text-xs text-rose-600"
                                >{{ purchaseForm.errors.purchase_amount }}</span
                            >
                        </div>

                        <div class="space-y-1">
                            <Label>Payment Method</Label>
                            <Select v-model="purchaseForm.payment_method">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="cash"
                                        >Cash Payment</SelectItem
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

                    <div class="flex items-center gap-2 border-t pt-2">
                        <input
                            id="auto_stock"
                            type="checkbox"
                            v-model="purchaseForm.auto_add_stock"
                            class="rounded border-gray-200 bg-gray-50 accent-[#003B7D]"
                        />
                        <Label
                            for="auto_stock"
                            class="cursor-pointer text-xs font-semibold"
                        >
                            Auto-add this handset to Shop Used Inventory Stock
                        </Label>
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
                            :disabled="purchaseForm.processing"
                            class="bg-[#003B7D] font-semibold text-white shadow-sm hover:bg-[#002b5c]"
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

        <!-- MODAL 2: Legal Affidavit Purchase Agreement Print Slip (80mm / 58mm / A4) -->
        <Dialog v-model:open="isVoucherModalOpen">
            <DialogContent class="max-w-md p-4">
                <DialogHeader class="no-print">
                    <DialogTitle class="text-center text-sm"
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

                <DialogFooter class="no-print flex justify-between pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isVoucherModalOpen = false"
                        >Close</Button
                    >
                    <Button
                        type="button"
                        @click="printVoucher"
                        class="gap-1 bg-[#003B7D] font-semibold text-white shadow-sm hover:bg-[#002b5c]"
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
