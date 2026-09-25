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
    product_imei?: {
        imei_1?: string;
        imei_2?: string;
    } | null;
    quantity: number;
    unit_price: number | string;
    line_total: number | string;
    imei?: string | null;
}

export interface ReceiptCustomer {
    id?: number;
    name: string;
    phone?: string | null;
    cnic?: string | null;
    current_balance?: number | string;
}

export interface ReceiptData {
    id?: number;
    invoice_no: string;
    created_at?: string;
    cashier_name?: string;
    cashier?: { name: string };
    salesman?: { name: string } | null;
    customer?: ReceiptCustomer | null;
    payment_method?: string;
    payment_details?: Record<string, number> | null;
    items: ReceiptItem[];
    total_amount: number | string;
    discount_amount?: number | string;
    discount_code?: string | null;
    trade_in_amount?: number | string;
    used_phone_purchase?: {
        device_model?: string;
        purchase_amount?: number | string;
    } | null;
    net_amount: number | string;
    paid_amount?: number | string;
    change_amount?: number | string;
    due_balance?: number | string;
    previous_customer_balance?: number | string;
    new_customer_balance?: number | string;
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
            tagline: 'Smartphones • Accessories • Repairs • Trade-In',
            address: 'Main Mobile Market, Shop # 12, Lahore',
            phone: '+92 300 1234567 / +92 321 9876543',
            ntn: '7482910-3',
            return_policy:
                '7 Days checking warranty. Claim valid with original box & receipt. No cash refund on opened items.',
        }),
        paperWidth: '80mm',
        showBarcode: true,
    },
);

const formattedDate = computed(() => {
    const raw = props.receipt?.created_at;
    if (!raw) {
        return new Date().toLocaleString('en-PK', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    }
    return new Date(raw).toLocaleString('en-PK', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    });
});

const cashierName = computed(() => {
    return (
        props.receipt?.salesman?.name ||
        props.receipt?.cashier?.name ||
        props.receipt?.cashier_name ||
        'Cashier Counter'
    );
});

const formatCurrency = (val: number | string | undefined | null) => {
    return `Rs. ${Number(val || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 })}`;
};

const formatBalanceLabel = (val: number | string | undefined | null) => {
    const num = Number(val || 0);
    if (num > 0) return `Rs. ${num.toLocaleString('en-PK')} (Due)`;
    if (num < 0) return `Rs. ${Math.abs(num).toLocaleString('en-PK')} (Advance)`;
    return 'Rs. 0 (Clear)';
};

const barcodeSvg = computed(() => {
    if (!props.receipt?.invoice_no) return '';
    return generateBarcodeSvg(props.receipt.invoice_no, 38);
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

const getItemImeis = (item: any) => {
    const list: string[] = [];
    if (item.imei) list.push(item.imei);
    if (item.product_imei?.imei_1) list.push(item.product_imei.imei_1);
    if (item.product_imei?.imei_2) list.push(item.product_imei.imei_2);
    return [...new Set(list)];
};

const getUnitPrice = (item: any) => {
    return Number(item.unit_price || item.price || 0);
};

const getLineTotal = (item: any) => {
    if (
        item.line_total !== undefined &&
        item.line_total !== null &&
        item.line_total !== ''
    ) {
        return Number(item.line_total);
    }
    const qty = Number(item.quantity) || 1;
    const price = getUnitPrice(item);
    return qty * price;
};

const tradeInDeviceName = computed(() => {
    if (props.receipt?.used_phone_purchase?.device_model) {
        return props.receipt.used_phone_purchase.device_model;
    }
    return 'Used Handset';
});
</script>

<template>
    <div
        id="thermal-invoice-printable"
        class="thermal-receipt-container font-mono text-[11px] leading-snug text-black select-none bg-white p-3 border border-slate-300 rounded-xl shadow-sm"
        :class="
            paperWidth === '58mm' ? 'max-w-[240px]' : 'mx-auto max-w-[340px]'
        "
    >
        <!-- Modern Header Branding -->
        <div class="border-b-2 border-black pb-2 text-center">
            <div class="mb-1 flex items-center justify-center gap-1.5">
                <span class="inline-block h-3 w-3 rounded-full bg-black"></span>
                <h2 class="text-base font-black tracking-tight uppercase">
                    {{ shopInfo.name }}
                </h2>
            </div>
            <p
                v-if="shopInfo.tagline"
                class="text-[9px] font-bold text-slate-800 uppercase tracking-wide"
            >
                {{ shopInfo.tagline }}
            </p>
            <p class="text-[9.5px] mt-0.5 leading-tight font-medium">
                {{ shopInfo.address }}
            </p>
            <p class="text-[9.5px] font-semibold">
                Ph: {{ shopInfo.phone }}
                <span v-if="shopInfo.ntn" class="ml-1"
                    >| NTN: {{ shopInfo.ntn }}</span
                >
            </p>
        </div>

        <!-- Receipt Title Banner -->
        <div class="my-1.5 border-b border-dashed border-black py-1 text-center">
            <span
                class="inline-block rounded border border-black px-2.5 py-0.5 text-xs font-black tracking-wider uppercase bg-slate-100"
            >
                CASH / KHATA SALE INVOICE
            </span>
        </div>

        <!-- Metadata Section -->
        <div class="space-y-1 border-b border-black pb-1.5 text-[10px]">
            <div class="flex justify-between">
                <span class="font-bold">INVOICE #:</span>
                <span class="text-xs font-black">{{ receipt.invoice_no }}</span>
            </div>
            <div class="flex justify-between">
                <span>DATE/TIME:</span>
                <span class="font-medium">{{ formattedDate }}</span>
            </div>
            <div class="flex justify-between">
                <span>SALESPERSON:</span>
                <span class="font-bold">{{ cashierName }}</span>
            </div>
            <div class="flex justify-between" v-if="receipt.payment_method">
                <span>PAYMENT TYPE:</span>
                <span class="font-extrabold uppercase bg-slate-100 px-1 rounded border border-slate-300">
                    {{ receipt.payment_method }}
                </span>
            </div>

            <!-- Customer Details -->
            <div
                v-if="receipt.customer"
                class="mt-1 border-t border-dashed border-slate-400 pt-1 space-y-0.5"
            >
                <div class="flex justify-between">
                    <span class="font-bold">CUSTOMER:</span>
                    <span class="font-extrabold text-right">
                        {{ receipt.customer.name }}
                    </span>
                </div>
                <div v-if="receipt.customer.phone" class="flex justify-between text-[9.5px]">
                    <span>CONTACT:</span>
                    <span class="font-mono">{{ receipt.customer.phone }}</span>
                </div>

                <!-- Ledger Balance Summary (Before & After Sale) -->
                <div
                    v-if="receipt.previous_customer_balance !== undefined || receipt.customer.current_balance !== undefined"
                    class="mt-1 rounded border border-slate-300 bg-slate-50 p-1 text-[9px] space-y-0.5"
                >
                    <div v-if="receipt.previous_customer_balance !== undefined" class="flex justify-between">
                        <span>PREV KHATA BAL:</span>
                        <span class="font-bold">{{ formatBalanceLabel(receipt.previous_customer_balance) }}</span>
                    </div>
                    <div v-if="receipt.new_customer_balance !== undefined || receipt.customer.current_balance !== undefined" class="flex justify-between border-t border-slate-200 pt-0.5 font-extrabold">
                        <span>NEW KHATA BAL:</span>
                        <span>{{ formatBalanceLabel(receipt.new_customer_balance ?? receipt.customer.current_balance) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="border-b-2 border-black py-2">
            <table class="w-full table-fixed text-left text-[10px]">
                <thead>
                    <tr class="border-b-2 border-black text-[9px] font-black uppercase">
                        <th class="w-5 py-0.5 text-left">QTY</th>
                        <th class="w-auto py-0.5 text-left">ITEM & IMEI / SN</th>
                        <th class="w-12 py-0.5 text-right">PRICE</th>
                        <th class="w-14 py-0.5 text-right">TOTAL</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dashed divide-slate-300">
                    <template v-for="(item, idx) in receipt.items" :key="idx">
                        <tr>
                            <td class="py-1 text-center align-top font-black">
                                {{ item.quantity }}
                            </td>
                            <td class="py-1 pr-1 align-top break-words">
                                <div class="font-bold text-slate-900 leading-snug">
                                    {{ getItemName(item) }}
                                </div>

                                <!-- IMEI / Serial Numbers -->
                                <div
                                    v-if="getItemImeis(item).length > 0"
                                    class="mt-0.5 font-mono text-[8.5px] font-bold text-slate-700 bg-slate-100 px-1 py-0.5 rounded inline-block"
                                >
                                    <span v-for="(imeiNum, i) in getItemImeis(item)" :key="i" class="block">
                                        IMEI {{ i + 1 }}: {{ imeiNum }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-1 text-right align-top whitespace-nowrap font-medium">
                                {{ getUnitPrice(item).toLocaleString('en-PK') }}
                            </td>
                            <td class="py-1 text-right align-top font-black whitespace-nowrap">
                                {{ getLineTotal(item).toLocaleString('en-PK') }}
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Financial Calculation Totals -->
        <div class="space-y-1 border-b-2 border-black py-1.5 text-[10px]">
            <div class="flex justify-between">
                <span>SUBTOTAL:</span>
                <span class="font-bold">{{ formatCurrency(receipt.total_amount) }}</span>
            </div>

            <div v-if="Number(receipt.discount_amount) > 0" class="flex justify-between font-semibold text-rose-700">
                <span>DISCOUNT {{ receipt.discount_code ? `(${receipt.discount_code})` : '' }}:</span>
                <span>-{{ formatCurrency(receipt.discount_amount) }}</span>
            </div>

            <div v-if="Number(receipt.trade_in_amount) > 0" class="flex justify-between font-bold text-amber-800">
                <span>TRADE-IN ({{ tradeInDeviceName }}):</span>
                <span>-{{ formatCurrency(receipt.trade_in_amount) }}</span>
            </div>

            <!-- Net Total Box -->
            <div class="my-1 flex justify-between rounded border-2 border-black bg-slate-100 px-1.5 py-1 text-xs font-black">
                <span>NET PAYABLE:</span>
                <span>{{ formatCurrency(receipt.net_amount) }}</span>
            </div>

            <!-- Payment Details & Split Breakdown -->
            <div class="flex justify-between font-bold">
                <span>PAID AMOUNT:</span>
                <span>{{ formatCurrency(receipt.paid_amount || receipt.net_amount) }}</span>
            </div>

            <div v-if="receipt.payment_details && Object.keys(receipt.payment_details).length > 0" class="rounded border border-slate-200 bg-slate-50 p-1 text-[9px] space-y-0.5 my-1">
                <div class="font-black border-b border-slate-200 pb-0.5 uppercase">Tender Breakdown:</div>
                <div v-for="(amt, pkey) in receipt.payment_details" :key="pkey" class="flex justify-between capitalize">
                    <span>{{ pkey }}:</span>
                    <span class="font-bold">Rs. {{ Number(amt).toLocaleString('en-PK') }}</span>
                </div>
            </div>

            <div v-if="Number(receipt.change_amount) > 0" class="flex justify-between font-bold text-emerald-700">
                <span>CHANGE RETURN:</span>
                <span>{{ formatCurrency(receipt.change_amount) }}</span>
            </div>

            <div v-if="Number(receipt.due_balance) > 0" class="flex justify-between font-extrabold text-rose-700">
                <span>UNPAID DUE ADDED:</span>
                <span>{{ formatCurrency(receipt.due_balance) }}</span>
            </div>
        </div>

        <!-- Barcode SVG -->
        <div v-if="showBarcode && receipt.invoice_no" class="border-b border-dashed border-black py-2 text-center">
            <div class="mx-auto w-48 py-1" v-html="barcodeSvg"></div>
            <div class="font-mono text-[9px] font-bold tracking-widest uppercase">
                * {{ receipt.invoice_no }} *
            </div>
        </div>

        <!-- Signature & Stamp Section -->
        <div class="grid grid-cols-2 gap-2 pt-3 text-[8.5px] font-bold text-center">
            <div class="border-t border-slate-400 pt-1">
                Customer Signature
            </div>
            <div class="border-t border-slate-400 pt-1">
                Shop Stamp & Signature
            </div>
        </div>

        <!-- Terms, Policy & Footer -->
        <div class="space-y-1 pt-2.5 text-center text-[8.5px] leading-tight">
            <p class="font-black tracking-tight uppercase">
                *** TERMS & CONDITIONS ***
            </p>
            <p class="text-slate-800">
                {{ shopInfo.return_policy }}
            </p>
            <p class="pt-1 text-[9.5px] font-black tracking-wider uppercase">
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
    #thermal-invoice-printable,
    #thermal-invoice-printable * {
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
