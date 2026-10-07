<script setup lang="ts">
import { computed } from 'vue';
import { generateBarcodeSvg } from '@/utils/barcodeSvg';

export type InvoiceStyle = 'classic' | 'bold_banner' | 'formal_retail' | 'minimal_line';

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
    invoice_style?: InvoiceStyle;
}

const props = withDefaults(
    defineProps<{
        receipt: ReceiptData;
        shopInfo?: ShopInfo;
        paperWidth?: '80mm' | '58mm';
        showBarcode?: boolean;
        invoiceStyle?: InvoiceStyle;
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
            invoice_style: 'classic',
        }),
        paperWidth: '80mm',
        showBarcode: true,
    },
);

const currentStyle = computed<InvoiceStyle>(() => {
    return props.invoiceStyle || props.shopInfo?.invoice_style || 'classic';
});

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
    if (num < 0)
        return `Rs. ${Math.abs(num).toLocaleString('en-PK')} (Advance)`;
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
        class="thermal-receipt-container font-mono text-[11px] leading-snug text-black select-none bg-white p-3"
        :class="[
            paperWidth === '58mm' ? 'max-w-[240px]' : 'mx-auto max-w-[340px]',
            currentStyle === 'formal_retail' ? 'border-4 border-double border-black rounded-sm' : 'rounded-xl border border-slate-300 shadow-sm'
        ]"
    >
        <!-- ========================================== -->
        <!-- STYLE 1: CLASSIC (MODERN CLASSIC THERMAL) -->
        <!-- ========================================== -->
        <template v-if="currentStyle === 'classic'">
            <!-- Header Branding -->
            <div class="border-b-2 border-black pb-2 text-center">
                <div class="mb-1 flex items-center justify-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded-full bg-black"></span>
                    <h2 class="text-base font-black tracking-tight uppercase">
                        {{ shopInfo.name }}
                    </h2>
                </div>
                <p v-if="shopInfo.tagline" class="text-[9px] font-bold tracking-wide text-slate-800 uppercase">
                    {{ shopInfo.tagline }}
                </p>
                <p class="mt-0.5 text-[9.5px] leading-tight font-medium">
                    {{ shopInfo.address }}
                </p>
                <p class="text-[9.5px] font-semibold">
                    Ph: {{ shopInfo.phone }}
                    <span v-if="shopInfo.ntn" class="ml-1">| NTN: {{ shopInfo.ntn }}</span>
                </p>
            </div>

            <!-- Receipt Title Banner -->
            <div class="my-1.5 border-b border-dashed border-black py-1 text-center">
                <span class="inline-block rounded border border-black bg-slate-100 px-2.5 py-0.5 text-xs font-black tracking-wider uppercase">
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
                <div v-if="receipt.payment_method" class="flex justify-between">
                    <span>PAYMENT TYPE:</span>
                    <span class="rounded border border-slate-300 bg-slate-100 px-1 font-extrabold uppercase">
                        {{ receipt.payment_method }}
                    </span>
                </div>

                <!-- Customer Details -->
                <div v-if="receipt.customer" class="mt-1 space-y-0.5 border-t border-dashed border-slate-400 pt-1">
                    <div class="flex justify-between">
                        <span class="font-bold">CUSTOMER:</span>
                        <span class="text-right font-extrabold">{{ receipt.customer.name }}</span>
                    </div>
                    <div v-if="receipt.customer.phone" class="flex justify-between text-[9.5px]">
                        <span>CONTACT:</span>
                        <span class="font-mono">{{ receipt.customer.phone }}</span>
                    </div>

                    <div v-if="receipt.previous_customer_balance !== undefined || receipt.customer.current_balance !== undefined"
                        class="mt-1 space-y-0.5 rounded border border-slate-300 bg-slate-50 p-1 text-[9px]">
                        <div v-if="receipt.previous_customer_balance !== undefined" class="flex justify-between">
                            <span>PREV KHATA BAL:</span>
                            <span class="font-bold">{{ formatBalanceLabel(receipt.previous_customer_balance) }}</span>
                        </div>
                        <div v-if="receipt.new_customer_balance !== undefined || receipt.customer.current_balance !== undefined"
                            class="flex justify-between border-t border-slate-200 pt-0.5 font-extrabold">
                            <span>NEW KHATA BAL:</span>
                            <span>{{ formatBalanceLabel(receipt.new_customer_balance ?? receipt.customer.current_balance) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- ========================================== -->
        <!-- STYLE 2: BOLD BANNER (INVERTED HEADER)     -->
        <!-- ========================================== -->
        <template v-else-if="currentStyle === 'bold_banner'">
            <div class="-mx-3 -mt-3 mb-2 bg-black p-3 text-center text-white rounded-t-lg">
                <h2 class="text-base font-black tracking-wider uppercase text-white">
                    {{ shopInfo.name }}
                </h2>
                <p v-if="shopInfo.tagline" class="text-[8.5px] font-bold tracking-widest text-slate-200 uppercase">
                    {{ shopInfo.tagline }}
                </p>
                <p class="mt-1 text-[9px] text-slate-300">
                    {{ shopInfo.address }} | Ph: {{ shopInfo.phone }}
                </p>
            </div>

            <div class="mb-2 rounded bg-black py-1 text-center text-white">
                <span class="text-xs font-black tracking-widest uppercase">
                    SALES RECEIPT #{{ receipt.invoice_no }}
                </span>
            </div>

            <div class="space-y-1 border-b-2 border-black pb-2 text-[10px]">
                <div class="flex justify-between">
                    <span class="font-bold text-slate-600">DATE:</span>
                    <span class="font-bold">{{ formattedDate }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-bold text-slate-600">CASHIER:</span>
                    <span class="font-bold">{{ cashierName }}</span>
                </div>
                <div v-if="receipt.payment_method" class="flex justify-between items-center">
                    <span class="font-bold text-slate-600">METHOD:</span>
                    <span class="bg-black px-1.5 py-0.5 text-[9px] font-black text-white uppercase rounded">
                        {{ receipt.payment_method }}
                    </span>
                </div>

                <div v-if="receipt.customer" class="mt-1 rounded-md border-l-4 border-black bg-slate-100 p-1.5">
                    <div class="flex justify-between text-[10px]">
                        <span class="font-bold text-slate-700">CUSTOMER:</span>
                        <span class="font-black">{{ receipt.customer.name }}</span>
                    </div>
                    <div v-if="receipt.customer.phone" class="flex justify-between text-[9px] text-slate-600">
                        <span>PHONE:</span>
                        <span class="font-mono font-bold">{{ receipt.customer.phone }}</span>
                    </div>
                </div>
            </div>
        </template>

        <!-- ========================================== -->
        <!-- STYLE 3: FORMAL RETAIL (DOUBLE BORDER GRID)-->
        <!-- ========================================== -->
        <template v-else-if="currentStyle === 'formal_retail'">
            <div class="border-b-2 border-black pb-2 text-center">
                <h2 class="text-lg font-black tracking-tight uppercase text-black">
                    {{ shopInfo.name }}
                </h2>
                <p v-if="shopInfo.tagline" class="text-[9px] font-semibold text-slate-700 uppercase">
                    {{ shopInfo.tagline }}
                </p>
                <p class="text-[9px] font-medium leading-tight">
                    {{ shopInfo.address }}
                </p>
                <p class="text-[9px] font-bold">
                    TEL: {{ shopInfo.phone }} <span v-if="shopInfo.ntn">| REG NTN: {{ shopInfo.ntn }}</span>
                </p>
            </div>

            <div class="my-2 border border-black p-1.5 text-[9.5px]">
                <div class="grid grid-cols-2 gap-1 border-b border-black pb-1 font-bold">
                    <div>INV #: <span class="font-black">{{ receipt.invoice_no }}</span></div>
                    <div class="text-right">DATE: {{ formattedDate }}</div>
                </div>
                <div class="grid grid-cols-2 gap-1 pt-1">
                    <div>PAYMENT: <span class="font-bold uppercase">{{ receipt.payment_method || 'CASH' }}</span></div>
                    <div class="text-right">STAFF: {{ cashierName }}</div>
                </div>
            </div>

            <div v-if="receipt.customer" class="mb-2 border border-black p-1.5 text-[9.5px]">
                <div class="flex justify-between">
                    <span class="font-bold">CUSTOMER NAME:</span>
                    <span class="font-black uppercase">{{ receipt.customer.name }}</span>
                </div>
                <div v-if="receipt.customer.phone" class="flex justify-between text-[9px]">
                    <span>CONTACT NO:</span>
                    <span class="font-mono">{{ receipt.customer.phone }}</span>
                </div>
            </div>
        </template>

        <!-- ========================================== -->
        <!-- STYLE 4: MINIMAL LINE (COMPACT SLEEK)     -->
        <!-- ========================================== -->
        <template v-else-if="currentStyle === 'minimal_line'">
            <div class="border-b border-black pb-1 text-center">
                <h2 class="text-sm font-bold uppercase tracking-widest">
                    {{ shopInfo.name }}
                </h2>
                <p class="text-[8.5px] text-slate-600">
                    {{ shopInfo.phone }} | {{ shopInfo.address }}
                </p>
            </div>

            <div class="py-1 text-[9.5px] border-b border-black flex flex-wrap justify-between gap-x-2 gap-y-0.5">
                <div><span class="text-slate-500">No:</span> <strong>{{ receipt.invoice_no }}</strong></div>
                <div><span class="text-slate-500">Date:</span> {{ formattedDate }}</div>
                <div v-if="receipt.customer"><span class="text-slate-500">Client:</span> <strong>{{ receipt.customer.name }}</strong></div>
                <div><span class="text-slate-500">Pay:</span> <span class="uppercase font-bold">{{ receipt.payment_method || 'Cash' }}</span></div>
            </div>
        </template>

        <!-- ========================================== -->
        <!-- ITEMS TABLE (ADAPTIVE TO STYLES)          -->
        <!-- ========================================== -->
        <div class="py-2" :class="currentStyle === 'formal_retail' ? '' : 'border-b-2 border-black'">
            <table class="w-full text-left text-[10px]" :class="currentStyle === 'formal_retail' ? 'border-collapse border border-black' : 'table-fixed'">
                <thead>
                    <tr class="text-[9px] font-black uppercase" :class="{
                        'border-b-2 border-black': currentStyle === 'classic' || currentStyle === 'minimal_line',
                        'bg-black text-white': currentStyle === 'bold_banner',
                        'border border-black bg-slate-200 text-black p-1': currentStyle === 'formal_retail'
                    }">
                        <th class="py-1 text-left" :class="currentStyle === 'formal_retail' ? 'border border-black px-1 w-6' : 'w-5'">QTY</th>
                        <th class="py-1 text-left" :class="currentStyle === 'formal_retail' ? 'border border-black px-1' : 'w-auto'">DESCRIPTION & IMEI</th>
                        <th class="py-1 text-right" :class="currentStyle === 'formal_retail' ? 'border border-black px-1 w-12' : 'w-12'">PRICE</th>
                        <th class="py-1 text-right" :class="currentStyle === 'formal_retail' ? 'border border-black px-1 w-14' : 'w-14'">AMOUNT</th>
                    </tr>
                </thead>
                <tbody :class="currentStyle === 'classic' ? 'divide-y divide-dashed divide-slate-300' : 'divide-y divide-slate-200'">
                    <template v-for="(item, idx) in receipt.items" :key="idx">
                        <tr>
                            <td class="py-1 text-center align-top font-black" :class="currentStyle === 'formal_retail' ? 'border border-black px-1' : ''">
                                {{ item.quantity }}
                            </td>
                            <td class="py-1 pr-1 align-top break-words" :class="currentStyle === 'formal_retail' ? 'border border-black px-1' : ''">
                                <div class="leading-snug font-bold text-slate-900">
                                    {{ getItemName(item) }}
                                </div>
                                <div v-if="getItemImeis(item).length > 0"
                                    class="mt-0.5 inline-block rounded px-1 py-0.5 font-mono text-[8.5px] font-bold"
                                    :class="currentStyle === 'bold_banner' ? 'bg-slate-200 text-slate-900' : 'bg-slate-100 text-slate-700'">
                                    <span v-for="(imeiNum, i) in getItemImeis(item)" :key="i" class="block">
                                        IMEI {{ i + 1 }}: {{ imeiNum }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-1 text-right align-top font-medium whitespace-nowrap" :class="currentStyle === 'formal_retail' ? 'border border-black px-1' : ''">
                                {{ getUnitPrice(item).toLocaleString('en-PK') }}
                            </td>
                            <td class="py-1 text-right align-top font-black whitespace-nowrap" :class="currentStyle === 'formal_retail' ? 'border border-black px-1' : ''">
                                {{ getLineTotal(item).toLocaleString('en-PK') }}
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- ========================================== -->
        <!-- FINANCIAL TOTALS & PAYMENTS                -->
        <!-- ========================================== -->
        <div class="space-y-1 py-1.5 text-[10px]" :class="currentStyle === 'formal_retail' ? 'border-b border-black' : 'border-b-2 border-black'">
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
            <div v-if="currentStyle === 'bold_banner'" class="my-1.5 flex justify-between rounded bg-black p-2 text-white text-xs font-black">
                <span class="text-white uppercase">NET PAYABLE:</span>
                <span class="text-white">{{ formatCurrency(receipt.net_amount) }}</span>
            </div>
            <div v-else-if="currentStyle === 'formal_retail'" class="my-1 flex justify-between border-2 border-black bg-slate-100 p-1 text-xs font-black">
                <span>TOTAL PAYABLE:</span>
                <span>{{ formatCurrency(receipt.net_amount) }}</span>
            </div>
            <div v-else class="my-1 flex justify-between rounded border-2 border-black bg-slate-100 px-1.5 py-1 text-xs font-black">
                <span>NET PAYABLE:</span>
                <span>{{ formatCurrency(receipt.net_amount) }}</span>
            </div>

            <div class="flex justify-between font-bold">
                <span>PAID AMOUNT:</span>
                <span>{{ formatCurrency(receipt.paid_amount || receipt.net_amount) }}</span>
            </div>

            <div v-if="receipt.payment_details && Object.keys(receipt.payment_details).length > 0"
                class="my-1 space-y-0.5 rounded border border-slate-200 bg-slate-50 p-1 text-[9px]">
                <div class="border-b border-slate-200 pb-0.5 font-black uppercase">
                    Tender Breakdown:
                </div>
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
        <div class="grid grid-cols-2 gap-2 pt-3 text-center text-[8.5px] font-bold">
            <div class="border-t border-black pt-1">Customer Signature</div>
            <div class="border-t border-black pt-1">Shop Stamp & Signature</div>
        </div>

        <!-- Terms, Policy & Footer -->
        <div class="space-y-1 pt-2 text-center text-[8.5px] leading-tight">
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

.thermal-receipt-container .bg-black,
#thermal-invoice-printable .bg-black {
    background-color: #000000 !important;
    color: #ffffff !important;
}

.thermal-receipt-container .text-white,
#thermal-invoice-printable .text-white {
    color: #ffffff !important;
}

@media print {
    /* Hide non-printable UI elements */
    body * {
        visibility: hidden !important;
    }

    /* Reset HTML, Body, App root, and Dialog wrappers to destroy flex centering & transforms */
    html,
    body,
    #app,
    [role="dialog"],
    [data-radix-focus-guard],
    [role="dialog"] > div {
        visibility: hidden !important;
        overflow: visible !important;
        max-height: none !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        transform: none !important;
        top: 0 !important;
        left: 0 !important;
        display: block !important;
        position: static !important;
        background: #ffffff !important;
        border: none !important;
        box-shadow: none !important;
    }

    /* Show only printable receipt and its child contents */
    #thermal-invoice-printable,
    #thermal-invoice-printable * {
        visibility: visible !important;
    }

    /* Full professional page utilization & print styling */
    #thermal-invoice-printable {
        display: block !important;
        position: relative !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        max-width: 680px !important;
        margin: 0 auto !important;
        padding: 12px 20px !important;
        box-sizing: border-box !important;
        border: none !important;
        box-shadow: none !important;
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 13px !important;
        line-height: 1.5 !important;
    }

    /* Scaled print typography for crisp readability */
    #thermal-invoice-printable h2 {
        font-size: 20px !important;
        font-weight: 900 !important;
        letter-spacing: 0.05em !important;
    }

    #thermal-invoice-printable table {
        width: 100% !important;
        font-size: 12px !important;
    }

    #thermal-invoice-printable table th {
        font-size: 12px !important;
        font-weight: 900 !important;
        padding: 6px 4px !important;
    }

    #thermal-invoice-printable table td {
        font-size: 12px !important;
        padding: 6px 4px !important;
    }

    #thermal-invoice-printable svg {
        max-width: 240px !important;
        height: auto !important;
        margin: 0 auto !important;
    }

    @page {
        size: portrait;
        margin: 8mm 10mm;
    }
}
</style>
