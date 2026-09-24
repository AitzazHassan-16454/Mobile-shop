<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import ShopSettingsController from '@/actions/App/Http/Controllers/Settings/ShopSettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/shop-settings';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Shop settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const settings = page.props.settings as {
    shop_name: string;
    shop_tagline: string;
    shop_phone: string;
    shop_phone_secondary: string;
    shop_address: string;
    shop_ntn: string;
    invoice_header_title: string;
    invoice_paper_size: string;
    show_barcode_on_invoice: string;
    show_cashier_name: string;
    return_policy: string;
    invoice_footer: string;
    default_payment_method: string;
    enable_sound_effects: string;
};
</script>

<template>
    <Head title="Shop settings" />

    <h1 class="sr-only">Shop settings</h1>

    <div class="flex flex-col space-y-8 pb-12">
        <Heading
            variant="small"
            title="Shop & Billing Settings"
            description="Manage your shop profile, thermal receipt printing, and POS terminal default options."
        />

        <Form
            v-bind="ShopSettingsController.update.form()"
            class="space-y-8"
            v-slot="{ errors, processing }"
        >
            <!-- SECTION 1: Shop Branding & Information -->
            <div class="space-y-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="border-b border-gray-100 pb-3">
                    <h2 class="text-base font-semibold text-gray-900">Shop Profile & Info</h2>
                    <p class="text-xs text-gray-500">Business identity printed at the top of receipts and invoices.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="shop_name">Shop Name <span class="text-red-500">*</span></Label>
                        <Input
                            id="shop_name"
                            name="shop_name"
                            :default-value="settings.shop_name"
                            required
                            placeholder="e.g. Faizan Mobile & POS"
                        />
                        <InputError :message="errors.shop_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="shop_tagline">Shop Tagline / Slogan</Label>
                        <Input
                            id="shop_tagline"
                            name="shop_tagline"
                            :default-value="settings.shop_tagline"
                            placeholder="e.g. Smartphones • Accessories • Repairing"
                        />
                        <InputError :message="errors.shop_tagline" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="shop_phone">Primary Phone / WhatsApp <span class="text-red-500">*</span></Label>
                        <Input
                            id="shop_phone"
                            name="shop_phone"
                            :default-value="settings.shop_phone"
                            required
                            placeholder="e.g. +92 300 1234567"
                        />
                        <InputError :message="errors.shop_phone" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="shop_phone_secondary">Secondary Phone (Optional)</Label>
                        <Input
                            id="shop_phone_secondary"
                            name="shop_phone_secondary"
                            :default-value="settings.shop_phone_secondary"
                            placeholder="e.g. +92 321 7654321"
                        />
                        <InputError :message="errors.shop_phone_secondary" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="shop_address">Shop Address</Label>
                        <Input
                            id="shop_address"
                            name="shop_address"
                            :default-value="settings.shop_address"
                            placeholder="e.g. Shop #12, Main Mobile Market, Hall Road, Lahore"
                        />
                        <InputError :message="errors.shop_address" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="shop_ntn">NTN / Sales Tax Reg No. (Optional)</Label>
                        <Input
                            id="shop_ntn"
                            name="shop_ntn"
                            :default-value="settings.shop_ntn"
                            placeholder="e.g. NTN: 1234567-8"
                        />
                        <InputError :message="errors.shop_ntn" />
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Invoice & Receipt Printing Setup -->
            <div class="space-y-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="border-b border-gray-100 pb-3">
                    <h2 class="text-base font-semibold text-gray-900">Invoice & Thermal Receipt Setup</h2>
                    <p class="text-xs text-gray-500">Configure header title, paper dimensions, warranty conditions, and barcodes.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="invoice_header_title">Receipt Header Title</Label>
                        <Input
                            id="invoice_header_title"
                            name="invoice_header_title"
                            :default-value="settings.invoice_header_title"
                            placeholder="e.g. CASH RECEIPT / SALES INVOICE"
                        />
                        <InputError :message="errors.invoice_header_title" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="invoice_paper_size">Receipt Paper Size</Label>
                        <select
                            id="invoice_paper_size"
                            name="invoice_paper_size"
                            :value="settings.invoice_paper_size"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-[#003B7D] focus:outline-none focus:ring-1 focus:ring-[#003B7D]"
                        >
                            <option value="80mm">Standard 80mm Thermal Paper</option>
                            <option value="58mm">Compact 58mm Thermal Paper</option>
                        </select>
                        <InputError :message="errors.invoice_paper_size" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="show_barcode_on_invoice">Print Barcode on Receipt</Label>
                        <select
                            id="show_barcode_on_invoice"
                            name="show_barcode_on_invoice"
                            :value="settings.show_barcode_on_invoice"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-[#003B7D] focus:outline-none focus:ring-1 focus:ring-[#003B7D]"
                        >
                            <option value="1">Enabled (Print invoice barcode)</option>
                            <option value="0">Disabled</option>
                        </select>
                        <InputError :message="errors.show_barcode_on_invoice" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="show_cashier_name">Print Cashier / Salesman Name</Label>
                        <select
                            id="show_cashier_name"
                            name="show_cashier_name"
                            :value="settings.show_cashier_name"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-[#003B7D] focus:outline-none focus:ring-1 focus:ring-[#003B7D]"
                        >
                            <option value="1">Enabled (Show cashier name)</option>
                            <option value="0">Disabled</option>
                        </select>
                        <InputError :message="errors.show_cashier_name" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="return_policy">Return & Warranty Policy</Label>
                        <textarea
                            id="return_policy"
                            name="return_policy"
                            rows="3"
                            :default-value="settings.return_policy"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-[#003B7D] focus:outline-none focus:ring-1 focus:ring-[#003B7D]"
                            placeholder="Printed under Terms & Conditions on the printed bill"
                        />
                        <InputError :message="errors.return_policy" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="invoice_footer">Invoice Footer Note / Thank You Greeting</Label>
                        <textarea
                            id="invoice_footer"
                            name="invoice_footer"
                            rows="2"
                            :default-value="settings.invoice_footer"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-[#003B7D] focus:outline-none focus:ring-1 focus:ring-[#003B7D]"
                            placeholder="e.g. Shukriya for shopping with us! Please visit again."
                        />
                        <InputError :message="errors.invoice_footer" />
                    </div>
                </div>
            </div>

            <!-- SECTION 3: POS Terminal Preferences -->
            <div class="space-y-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="border-b border-gray-100 pb-3">
                    <h2 class="text-base font-semibold text-gray-900">POS Terminal Defaults</h2>
                    <p class="text-xs text-gray-500">Default options for fast checkout at the register.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="default_payment_method">Default Checkout Payment Method</Label>
                        <select
                            id="default_payment_method"
                            name="default_payment_method"
                            :value="settings.default_payment_method"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-[#003B7D] focus:outline-none focus:ring-1 focus:ring-[#003B7D]"
                        >
                            <option value="Cash">Cash</option>
                            <option value="Card">Card</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Credit">Credit / Udhaar</option>
                        </select>
                        <InputError :message="errors.default_payment_method" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="enable_sound_effects">Barcode Scan Beep Sound</Label>
                        <select
                            id="enable_sound_effects"
                            name="enable_sound_effects"
                            :value="settings.enable_sound_effects"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-[#003B7D] focus:outline-none focus:ring-1 focus:ring-[#003B7D]"
                        >
                            <option value="1">Enabled (Play sound on scan)</option>
                            <option value="0">Disabled (Silent scan)</option>
                        </select>
                        <InputError :message="errors.enable_sound_effects" />
                    </div>
                </div>
            </div>

            <!-- SUBMIT BUTTON -->
            <div class="flex items-center gap-4 pt-2">
                <Button
                    type="submit"
                    :disabled="processing"
                    class="bg-[#003B7D] px-6 text-white hover:bg-[#002a5c]"
                >
                    Save Settings
                </Button>
            </div>
        </Form>
    </div>
</template>
