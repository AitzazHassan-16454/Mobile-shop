<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CreditCard,
    Plus,
    Search,
    Store,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import suppliers from '@/routes/suppliers';
import type { Team } from '@/types';

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
    suppliers: {
        data: SupplierItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    filters: { search: string };
    summary: {
        total_suppliers: number;
        total_payables: number;
        total_credits: number;
    };
}>();

const page = usePage();
const currentTeamSlug = computed(
    () => (page.props.currentTeam as Team | undefined)?.slug || 'default',
);
const search = ref(props.filters.search || '');
const showCreate = ref(false);
const openEntry = ref<number | null>(null);
const entryType = ref<'purchase' | 'payment'>('purchase');

const createForm = useForm({
    name: '',
    company: '',
    phone: '',
    address: '',
    opening_balance: '',
});
const entryForm = useForm({ amount: '', reference_id: '', notes: '' });

const money = (value: number | string) =>
    `Rs. ${Number(value || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 })}`;

const applySearch = () => {
    router.get(
        suppliers.index(currentTeamSlug.value).url,
        { search: search.value },
        { preserveState: true, replace: true },
    );
};

const createSupplier = () =>
    createForm.post(suppliers.store(currentTeamSlug.value).url, {
        onSuccess: () => {
            createForm.reset();
            showCreate.value = false;
        },
    });

const submitEntry = (supplierId: number) => {
    const route =
        entryType.value === 'purchase'
            ? suppliers.purchases.store({
                  current_team: currentTeamSlug.value,
                  supplier: supplierId,
              }).url
            : suppliers.payments.store({
                  current_team: currentTeamSlug.value,
                  supplier: supplierId,
              }).url;

    entryForm.post(route, {
        onSuccess: () => {
            entryForm.reset();
            openEntry.value = null;
        },
    });
};

const setEntry = (supplierId: number, type: 'purchase' | 'payment') => {
    openEntry.value = supplierId;
    entryType.value = type;
    entryForm.reset();
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
        <section
            class="bg-card/60 flex flex-col gap-4 rounded-2xl border border-gray-200 p-5 shadow-[0_16px_40px_-16px_rgba(7,28,61,0.35)] backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="eyebrow mb-2">Purchasing desk</p>
                <h1
                    class="flex items-center gap-2 text-2xl font-bold text-gray-900"
                >
                    <Store class="h-7 w-7 text-[#003B7D]" /> Suppliers &
                    Payables
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Manage distributor khata, purchase bills and outstanding
                    payments.
                </p>
            </div>
            <Button
                class="gap-2 bg-[#003B7D] font-bold text-white shadow-sm hover:bg-[#002b5c]"
                @click="showCreate = !showCreate"
                ><Plus class="h-4 w-4" /> Add Supplier</Button
            >
        </section>

        <section
            v-if="showCreate"
            class="rounded-2xl border border-[#003B7D]/20 bg-[#003B7D]/5 p-5 backdrop-blur-xl"
        >
            <div
                class="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900"
            >
                <Building2 class="h-5 w-5 text-[#003B7D]" /> New supplier
                profile
            </div>
            <form
                class="grid gap-4 md:grid-cols-2 lg:grid-cols-5"
                @submit.prevent="createSupplier"
            >
                <div class="grid gap-2">
                    <Label for="supplier-name">Name</Label
                    ><Input
                        id="supplier-name"
                        v-model="createForm.name"
                        required
                        placeholder="Supplier name"
                        class="border-gray-200 bg-gray-50 text-gray-900"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="supplier-company">Company</Label
                    ><Input
                        id="supplier-company"
                        v-model="createForm.company"
                        placeholder="Distributor / company"
                        class="border-gray-200 bg-gray-50 text-gray-900"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="supplier-phone">Phone</Label
                    ><Input
                        id="supplier-phone"
                        v-model="createForm.phone"
                        placeholder="0300..."
                        class="border-gray-200 bg-gray-50 text-gray-900"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="supplier-opening">Opening payable</Label
                    ><Input
                        id="supplier-opening"
                        v-model="createForm.opening_balance"
                        type="number"
                        min="0"
                        placeholder="0"
                        class="border-gray-200 bg-gray-50 text-gray-900"
                    />
                </div>
                <div class="flex items-end">
                    <Button
                        class="w-full bg-[#003B7D] font-semibold text-white shadow-sm hover:bg-[#002b5c]"
                        :disabled="createForm.processing"
                        >Save supplier</Button
                    >
                </div>
                <div class="grid gap-2 md:col-span-2 lg:col-span-5">
                    <Label for="supplier-address">Address</Label
                    ><Input
                        id="supplier-address"
                        v-model="createForm.address"
                        placeholder="Market, city or address"
                        class="border-gray-200 bg-gray-50 text-gray-900"
                    />
                </div>
            </form>
        </section>

        <section class="grid gap-4 sm:grid-cols-3">
            <div
                class="bg-card/60 rounded-2xl border border-gray-200 p-4 backdrop-blur-xl"
            >
                <p class="text-xs tracking-wider text-slate-500 uppercase">
                    Suppliers
                </p>
                <p class="tnum mt-2 text-2xl font-bold text-gray-900">
                    {{ summary.total_suppliers }}
                </p>
            </div>
            <div
                class="bg-card/60 rounded-2xl border border-gray-200 p-4 backdrop-blur-xl"
            >
                <p class="text-xs tracking-wider text-slate-500 uppercase">
                    Outstanding payables
                </p>
                <p class="tnum mt-2 text-2xl font-bold text-amber-600">
                    {{ money(summary.total_payables) }}
                </p>
            </div>
            <div
                class="bg-card/60 rounded-2xl border border-gray-200 p-4 backdrop-blur-xl"
            >
                <p class="text-xs tracking-wider text-slate-500 uppercase">
                    Supplier credits
                </p>
                <p class="tnum mt-2 text-2xl font-bold text-[#003B7D]">
                    {{ money(summary.total_credits) }}
                </p>
            </div>
        </section>

        <section
            class="bg-card/60 flex items-center gap-3 rounded-2xl border border-gray-200 p-4 backdrop-blur-xl"
        >
            <Search class="h-5 w-5 text-slate-500" /><Input
                v-model="search"
                class="border-gray-200 bg-gray-50 text-gray-900"
                placeholder="Search supplier, company or phone..."
                @keyup.enter="applySearch"
            /><Button
                variant="outline"
                class="border-gray-200 bg-gray-50 text-gray-900 hover:bg-gray-100"
                @click="applySearch"
                >Search</Button
            >
        </section>

        <section
            class="bg-card/60 overflow-hidden rounded-2xl border border-gray-200 backdrop-blur-xl"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-gray-200 text-xs tracking-wider text-slate-500 uppercase"
                    >
                        <tr>
                            <th class="px-5 py-4">Supplier</th>
                            <th class="px-5 py-4">Contact</th>
                            <th class="px-5 py-4">Balance</th>
                            <th class="px-5 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr
                            v-for="supplier in suppliers.data"
                            :key="supplier.id"
                            class="text-slate-600 transition-colors hover:bg-gray-50"
                        >
                            <td class="px-5 py-4">
                                <div class="font-semibold text-gray-900">
                                    {{ supplier.name }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{
                                        supplier.company ||
                                        'Independent supplier'
                                    }}
                                </div>
                            </td>
                            <td class="px-5 py-4 text-slate-500">
                                {{ supplier.phone || 'No phone added' }}
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    class="tnum"
                                    :class="
                                        Number(supplier.current_balance) > 0
                                            ? 'text-amber-600'
                                            : 'text-[#003B7D]'
                                    "
                                    >{{
                                        money(
                                            Math.abs(
                                                Number(
                                                    supplier.current_balance,
                                                ),
                                            ),
                                        )
                                    }}</span
                                ><span class="ml-2 text-xs text-slate-500">{{
                                    Number(supplier.current_balance) > 0
                                        ? 'payable'
                                        : 'credit'
                                }}</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="border-amber-200 bg-amber-50 text-amber-600 hover:bg-amber-100"
                                        @click="
                                            setEntry(supplier.id, 'purchase')
                                        "
                                        ><CreditCard class="mr-1 h-4 w-4" />
                                        Purchase</Button
                                    ><Button
                                        size="sm"
                                        variant="outline"
                                        class="border-[#003B7D]/20 bg-[#003B7D]/5 text-[#003B7D] hover:bg-[#003B7D]/10"
                                        @click="
                                            setEntry(supplier.id, 'payment')
                                        "
                                        ><Wallet class="mr-1 h-4 w-4" />
                                        Pay</Button
                                    >
                                </div>
                                <form
                                    v-if="openEntry === supplier.id"
                                    class="mt-3 flex flex-wrap justify-end gap-2"
                                    @submit.prevent="submitEntry(supplier.id)"
                                >
                                    <Input
                                        v-model="entryForm.amount"
                                        required
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        class="w-32 border-gray-200 bg-gray-50 text-gray-900"
                                        :placeholder="
                                            entryType === 'purchase'
                                                ? 'Bill amount'
                                                : 'Payment'
                                        "
                                    /><Input
                                        v-model="entryForm.reference_id"
                                        class="w-32 border-gray-200 bg-gray-50 text-gray-900"
                                        placeholder="Bill / ref #"
                                    /><Button
                                        size="sm"
                                        class="bg-[#003B7D] text-white hover:bg-[#002b5c]"
                                        :disabled="entryForm.processing"
                                        >Save</Button
                                    >
                                </form>
                            </td>
                        </tr>
                        <tr v-if="suppliers.data.length === 0">
                            <td
                                colspan="4"
                                class="px-5 py-12 text-center text-slate-500"
                            >
                                No suppliers found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
