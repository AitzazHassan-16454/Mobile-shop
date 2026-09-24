<script setup lang="ts">
import { computed } from 'vue';
import { Printer } from '@lucide/vue';

interface StatementEntry {
    Date: string;
    Type: string;
    Reference: string;
    Notes: string;
    Debit: number | string;
    Credit: number | string;
    Balance: number | string;
}

interface Party {
    name: string;
    phone?: string | null;
    address?: string | null;
    current_balance: number | string;
}

const printNow = () => window.print();

const props = withDefaults(
    defineProps<{
        slipId?: string;
        title: string;
        party: Party;
        shopInfo: {
            name: string;
            phone: string;
            address: string;
        };
        entries: StatementEntry[];
    }>(),
    {
        slipId: 'khata-statement-slip',
    },
);

const formatCurrency = (val: number | string) =>
    'Rs. ' +
    Math.round(Number(val) || 0).toLocaleString('en-PK', {
        maximumFractionDigits: 0,
    });

const totalDebit = computed(() =>
    props.entries.reduce((sum, entry) => sum + (Number(entry.Debit) || 0), 0),
);
const totalCredit = computed(() =>
    props.entries.reduce((sum, entry) => sum + (Number(entry.Credit) || 0), 0),
);

const formatDate = (value: string) => {
    if (!value) return '';
    const parts = value.split(' ')[0].split('-');
    if (parts.length === 3) {
        return `${parts[2]}-${parts[1]}-${parts[0]}`;
    }
    return value;
};
</script>

<template>
    <div class="no-print flex justify-end py-2">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg bg-[#003B7D] px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#002b5c]"
            @click="printNow"
        >
            <Printer class="h-4 w-4" /> Print Statement
        </button>
    </div>

    <div :id="slipId" class="space-y-2 bg-white p-2 font-mono text-[11px] leading-tight text-black">
        <div class="border-b pb-2 text-center">
            <div class="text-sm font-extrabold uppercase">{{ shopInfo.name }}</div>
            <div class="text-[10px]">{{ shopInfo.address }}</div>
            <div class="text-[10px]">Ph: {{ shopInfo.phone }}</div>
            <div
                class="mt-1 inline-block border bg-slate-100 px-2 py-0.5 text-xs font-extrabold uppercase"
            >
                {{ title }}
            </div>
            <div class="mt-1 text-[10px] text-gray-600">{{ new Date().toLocaleDateString('en-PK') }}</div>
        </div>

        <div class="space-y-0.5 border-b pb-1 text-[10px]">
            <div class="flex justify-between font-bold">
                <span>Name:</span>
                <span>{{ party.name }}</span>
            </div>
            <div class="flex justify-between">
                <span>Phone:</span>
                <span>{{ party.phone || '-' }}</span>
            </div>
            <div v-if="party.address" class="flex justify-between">
                <span>Address:</span>
                <span>{{ party.address }}</span>
            </div>
            <div class="flex justify-between font-extrabold">
                <span>Current Balance:</span>
                <span>{{ formatCurrency(party.current_balance) }}</span>
            </div>
        </div>

        <table class="w-full border-collapse text-[10px]">
            <thead>
                <tr class="border-b-2 border-black font-bold uppercase">
                    <th class="py-0.5 text-left">Date</th>
                    <th class="py-0.5 text-left">Type</th>
                    <th class="py-0.5 text-left">Ref</th>
                    <th class="py-0.5 text-right">Debit</th>
                    <th class="py-0.5 text-right">Credit</th>
                    <th class="py-0.5 text-right">Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!entries.length">
                    <td colspan="6" class="py-2 text-center text-gray-600">
                        No transactions recorded.
                    </td>
                </tr>
                <tr
                    v-for="(entry, index) in entries"
                    :key="index"
                    class="border-b border-dashed border-gray-300"
                >
                    <td class="py-1 pr-1 whitespace-nowrap">{{ formatDate(entry.Date) }}</td>
                    <td class="py-1 pr-1 capitalize">{{ entry.Type }}</td>
                    <td class="py-1 pr-1">{{ entry.Reference || '-' }}</td>
                    <td class="py-1 pr-1 text-right whitespace-nowrap">
                        {{ Number(entry.Debit) ? formatCurrency(entry.Debit) : '' }}
                    </td>
                    <td class="py-1 pr-1 text-right whitespace-nowrap">
                        {{ Number(entry.Credit) ? formatCurrency(entry.Credit) : '' }}
                    </td>
                    <td class="py-1 text-right whitespace-nowrap">
                        {{ formatCurrency(entry.Balance) }}
                    </td>
                </tr>
                <tr class="border-t-2 border-black font-bold">
                    <td colspan="3" class="py-1">Total</td>
                    <td class="py-1 text-right">{{ formatCurrency(totalDebit) }}</td>
                    <td class="py-1 text-right">{{ formatCurrency(totalCredit) }}</td>
                    <td class="py-1 text-right">{{ formatCurrency(party.current_balance) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="grid grid-cols-2 gap-4 pt-6 text-center text-[10px]">
            <div class="border-t border-dashed pt-1 font-bold">Signature</div>
            <div class="border-t border-dashed pt-1 font-bold">Stamp</div>
        </div>
    </div>
</template>

<style>
.print-area {
    display: none;
}

@media print {
    body * {
        visibility: hidden;
    }
    .print-area {
        display: block !important;
    }
    #khata-statement-slip,
    #khata-statement-slip * {
        visibility: visible;
    }
    #khata-statement-slip {
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