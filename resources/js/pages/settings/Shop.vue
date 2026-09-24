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
    shop_phone: string;
    shop_address: string;
    return_policy: string;
    invoice_footer: string;
};
</script>

<template>
    <Head title="Shop settings" />

    <h1 class="sr-only">Shop settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Shop"
            description="Shop details printed on invoices, receipts and vouchers"
        />

        <Form
            v-bind="ShopSettingsController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="shop_name">Shop name</Label>
                <Input
                    id="shop_name"
                    class="mt-1 block w-full"
                    name="shop_name"
                    :default-value="settings.shop_name"
                    required
                    placeholder="e.g. Horizon Studio"
                />
                <InputError class="mt-2" :message="errors.shop_name" />
            </div>

            <div class="grid gap-2">
                <Label for="shop_phone">Phone number</Label>
                <Input
                    id="shop_phone"
                    class="mt-1 block w-full"
                    name="shop_phone"
                    :default-value="settings.shop_phone"
                    required
                    placeholder="e.g. +92 300 1234567"
                />
                <InputError class="mt-2" :message="errors.shop_phone" />
            </div>

            <div class="grid gap-2">
                <Label for="shop_address">Address</Label>
                <Input
                    id="shop_address"
                    class="mt-1 block w-full"
                    name="shop_address"
                    :default-value="settings.shop_address"
                    placeholder="e.g. Main Mobile Market, Shop #12, Lahore"
                />
                <InputError class="mt-2" :message="errors.shop_address" />
            </div>

            <div class="grid gap-2">
                <Label for="return_policy">Return / warranty policy</Label>
                <textarea
                    id="return_policy"
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#003B7D] focus:ring-[#003B7D]"
                    name="return_policy"
                    rows="3"
                    :default-value="settings.return_policy"
                    placeholder="Printed under the TERMS & CONDITIONS section of the receipt"
                />
                <InputError class="mt-2" :message="errors.return_policy" />
            </div>

            <div class="grid gap-2">
                <Label for="invoice_footer">Invoice footer message</Label>
                <textarea
                    id="invoice_footer"
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#003B7D] focus:ring-[#003B7D]"
                    name="invoice_footer"
                    rows="2"
                    :default-value="settings.invoice_footer"
                    placeholder="Optional closing message, e.g. Shukriya! Clean checking warranty valid for 7 days."
                />
                <InputError class="mt-2" :message="errors.invoice_footer" />
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing">Save</Button>
            </div>
        </Form>
    </div>
</template>