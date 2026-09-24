<script setup lang="ts">
import { computed } from 'vue';
import { generateBarcodeSvg } from '@/utils/barcodeSvg';

export interface ReceiptItem {
    id?: number;
    product_name?: string;
    product_brand?: string;
    product?: {
        name: string;
        brand: string;
        is_serialized?: boolean;
    };
    quantity: number;
    unit_price: number | string;
    line_total: number | string;
    imei?: string | null;
}

export interface ReceiptCustomer {
    name: string;
    phone?: string | null;
    cnic?: string | null;
}

export interface ReceiptData {
    invoice_no: string;
    created_at?: string;
    cashier_name?: string;
    cashier?: { name: string };
    customer?: ReceiptCustomer | null;
    payment_method?: string;
    items: ReceiptItem[];
    total_amount: number | string;
    discount_amount?: number | string;
    discount_code?: string | null;
    trade_in_amount?: number | string;
    net_amount: number | string;
    paid_amount?: number | string;
    change_amount?: number | string;
    due_balance?: number | string;
}

export interface ShopInfo {
    name: string;
    tagline?: string;
    address: string;
    phone: string;
    ntn?: string;
    return_policy?: string;
}

const props = withDefaults(
    defineProps<{
        receipt: ReceiptData;
        shopInfo?: ShopInfo;
        paperWidth?: '80mm' | '58mm';
        showBarcode?: boolean;
    }>(),
    {
        shopInfo: () => ({
            name: 'FAIZAN MOBILE & POS',
            tagline: 'Smartphones • Accessories • Mobile Repairing',
            address: 'Main Mobile Market, Shop # 12, Lahore',
            phone: '+92 300 1234567',
            return_policy: '7 Days checking warranty. Accessories warranty valid with original box & receipt.',
        }),
        paperWidth: '80mm',
        showBarcode: true,
    }
);

const formattedDate = computed(() => {
    if (!props.receipt?.created_at) {
        return new Date().toLocaleString('en-PK', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    }
    return new Date(props.receipt.created_at).toLocaleString('en-PK', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    });
});

const cashierName = computed(() => {
    return props.receipt?.cashier?.name || props.receipt?.cashier_name || 'Counter Cashier';
});

const formatCurrency = (val: number | string | undefined | null) => {
    return `Rs. ${Number(val || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 })}`;
};

const barcodeSvg = computed(() => {
    if (!props.receipt?.invoice_no) return '';
    return generateBarcodeSvg(props.receipt.invoice_no, 36);
});

const getItemName = (item: any) => {
    if (!item) return 'Item';
    if (item.product?.name) {
        const brand = item.product.brand ? `${item.product.brand} ` : '';
        return `${brand}${item.product.name}`.trim();
    }
    if (item.name) {
        const brand = item.brand ? `${item.brand} ` : '';
        return `${brand}${item.name}`.trim();
    }
    if (item.product_name) {
        const brand = item.product_brand ? `${item.product_brand} ` : '';
        return `${brand}${item.product_name}`.trim();
    }
    return 'Item';
};

const getUnitPrice = (item: any) => {
    return Number(item.unit_price || item.price || 0);
};

const getLineTotal = (item: any) => {
    if (item.line_total !== undefined && item.line_total !== null && item.line_total !== '') {
        return Number(item.line_total);
    }
    const qty = Number(item.quantity) || 1;
    const price = getUnitPrice(item);
    return qty * price;
};
</script>

<template>
    <div
        id="thermal-invoice-printable"
        class="thermal-receipt-container font-mono text-[11px] leading-snug text-black bg-white select-none p-4 rounded-xl border border-slate-200 shadow-sm"
        :class="paperWidth === '58mm' ? 'max-w-[240px]' : 'max-w-[320px] mx-auto'"
    >
        <!-- Header Branding -->
        <div class="text-center space-y-1 pb-2 border-b-2 border-black">
            <h2 class="text-base font-black tracking-tight uppercase">
                {{ shopInfo.name }}
            </h2>
            <p v-if="shopInfo.tagline" class="text-[9px] font-bold uppercase text-slate-700 leading-tight">
                {{ shopInfo.tagline }}
            </p>
            <p class="text-[10px] leading-tight">
                {{ shopInfo.address }}
            </p>
            <p class="text-[10px] font-semibold">
                Ph: {{ shopInfo.phone }}
                <span v-if="shopInfo.ntn" class="ml-1">| NTN: {{ shopInfo.ntn }}</span>
            </p>
        </div>

        <!-- Receipt Title Banner -->
        <div class="py-1.5 text-center border-b border-dashed border-black my-1">
            <span class="text-xs font-black uppercase tracking-widest px-2 py-0.5 border border-black rounded">
                RETAIL CASH INVOICE
            </span>
        </div>

        <!-- Metadata Section -->
        <div class="space-y-0.5 text-[10px] py-1 border-b border-black">
            <div class="flex justify-between">
                <span class="font-bold">INVOICE #:</span>
                <span class="font-black text-xs">{{ receipt.invoice_no }}</span>
            </div>
            <div class="flex justify-between">
                <span>DATE/TIME:</span>
                <span>{{ formattedDate }}</span>
            </div>
            <div class="flex justify-between">
                <span>CASHIER:</span>
                <span class="font-semibold">{{ cashierName }}</span>
            </div>
            <div class="flex justify-between" v-if="receipt.payment_method">
                <span>PAYMENT TYPE:</span>
                <span class="font-bold uppercase">{{ receipt.payment_method }}</span>
            </div>
            <div v-if="receipt.customer" class="flex justify-between pt-1 border-t border-dotted border-slate-400">
                <span class="font-bold">CUSTOMER:</span>
                <span class="font-bold truncate max-w-[140px] text-right">
                    {{ receipt.customer.name }}
                    <span v-if="receipt.customer.phone" class="block text-[9px] font-normal text-slate-600">
                        {{ receipt.customer.phone }}
                    </span>
                </span>
            </div>
        </div>

        <!-- Items Table -->
        <div class="py-2 border-b-2 border-black">
            <table class="w-full text-left text-[10px] table-fixed">
                <thead>
                    <tr class="border-b border-black font-black uppercase text-[9px]">
                        <th class="w-6 text-left py-0.5">QTY</th>
                        <th class="w-auto text-left py-0.5">ITEM DESCRIPTION</th>
                        <th class="w-12 text-right py-0.5">PRICE</th>
                        <th class="w-14 text-right py-0.5">TOTAL</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dashed divide-slate-300">
                    <template v-for="(item, idx) in receipt.items" :key="idx">
                        <tr>
                            <td class="align-top py-1 font-bold text-center">
                                {{ item.quantity }}
                            </td>
                            <td class="align-top py-1 font-bold pr-1 break-words">
                                {{ getItemName(item) }}
                                <div v-if="item.imei || (item as any).product_imei?.imei_1" class="text-[9px] font-normal font-mono text-slate-700 tracking-tight">
                                    S/N: {{ item.imei || (item as any).product_imei?.imei_1 }}
                                </div>
                            </td>
                            <td class="align-top py-1 text-right whitespace-nowrap">
                                {{ getUnitPrice(item).toLocaleString('en-PK') }}
                            </td>
                            <td class="align-top py-1 text-right font-bold whitespace-nowrap">
                                {{ getLineTotal(item).toLocaleString('en-PK') }}
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Financial Calculation Totals -->
        <div class="py-1.5 space-y-1 border-b-2 border-black text-[10px]">
            <div class="flex justify-between">
                <span>SUBTOTAL:</span>
                <span class="font-bold">{{ formatCurrency(receipt.total_amount) }}</span>
            </div>

            <div v-if="Number(receipt.discount_amount) > 0" class="flex justify-between font-semibold">
                <span>DISCOUNT {{ receipt.discount_code ? `(${receipt.discount_code})` : '' }}:</span>
                <span>-{{ formatCurrency(receipt.discount_amount) }}</span>
            </div>

            <div v-if="Number(receipt.trade_in_amount) > 0" class="flex justify-between font-bold text-[10px]">
                <span>TRADE-IN CREDIT:</span>
                <span class="text-black">-{{ formatCurrency(receipt.trade_in_amount) }}</span>
            </div>

            <!-- Net Total Highlight Box -->
            <div class="flex justify-between text-xs font-black py-1 px-1.5 border-2 border-black bg-slate-100 rounded my-1">
                <span>NET TOTAL:</span>
                <span>{{ formatCurrency(receipt.net_amount) }}</span>
            </div>

            <div class="flex justify-between">
                <span>PAID AMOUNT:</span>
                <span class="font-bold">{{ formatCurrency(receipt.paid_amount || receipt.net_amount) }}</span>
            </div>

            <div v-if="Number(receipt.change_amount) > 0" class="flex justify-between font-bold">
                <span>CHANGE RETURN:</span>
                <span>{{ formatCurrency(receipt.change_amount) }}</span>
            </div>

            <div v-if="Number(receipt.due_balance) > 0" class="flex justify-between font-bold text-rose-700">
                <span>BALANCE DUE (KHATA):</span>
                <span>{{ formatCurrency(receipt.due_balance) }}</span>
            </div>
        </div>

        <!-- Invoice Barcode SVG -->
        <div v-if="showBarcode && receipt.invoice_no" class="py-2 text-center border-b border-dashed border-black">
            <div class="w-48 mx-auto py-1" v-html="barcodeSvg"></div>
            <div class="text-[9px] font-bold tracking-widest font-mono">
                {{ receipt.invoice_no }}
            </div>
        </div>

        <!-- Terms, Policy & Footer -->
        <div class="pt-2 text-center space-y-1 text-[9px] leading-tight">
            <p class="font-bold uppercase tracking-tight">
                *** TERMS & CONDITIONS ***
            </p>
            <p class="text-slate-700">
                {{ shopInfo.return_policy }}
            </p>
            <p class="pt-1 text-[10px] font-black uppercase tracking-wider">
                *** THANK YOU FOR YOUR VISIT! ***
            </p>
        </div>
    </div>
</template>

<style>
.thermal-receipt-container,
.thermal-receipt-container *,
#thermal-invoice-printable,
#thermal-invoice-printable * {
    background-color: #ffffff !important;
    color: #000000 !important;
}

@media print {
    body * {
        visibility: hidden;
    }
    #thermal-invoice-printable, #thermal-invoice-printable * {
        visibility: visible;
        background-color: #ffffff !important;
        color: #000000 !important;
    }
    #thermal-invoice-printable {
        position: absolute;
        left: 0;
        top: 0;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 4px !important;
        border: none !important;
        box-shadow: none !important;
        background: #ffffff !important;
    }
    @page {
        size: auto;
        margin: 0mm;
    }
}
</style>
