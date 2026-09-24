<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/Shop', [
            'settings' => [
                'shop_name' => AppSetting::get('shop_name', 'Horizon Studio'),
                'shop_tagline' => AppSetting::get('shop_tagline', 'Smartphones • Accessories • Repairing'),
                'shop_phone' => AppSetting::get('shop_phone', '+92 300 1234567'),
                'shop_phone_secondary' => AppSetting::get('shop_phone_secondary', ''),
                'shop_address' => AppSetting::get('shop_address', 'Main Mobile Market, Shop #12, Lahore'),
                'shop_ntn' => AppSetting::get('shop_ntn', ''),
                'invoice_header_title' => AppSetting::get('invoice_header_title', 'CASH RECEIPT'),
                'invoice_paper_size' => AppSetting::get('invoice_paper_size', '80mm'),
                'show_barcode_on_invoice' => AppSetting::get('show_barcode_on_invoice', '1'),
                'show_cashier_name' => AppSetting::get('show_cashier_name', '1'),
                'return_policy' => AppSetting::get('return_policy', '7 Days Checking Warranty. Physical & Water Damage Not Covered.'),
                'invoice_footer' => AppSetting::get('invoice_footer', 'Shukriya for shopping with us! Please visit again.'),
                'default_payment_method' => AppSetting::get('default_payment_method', 'Cash'),
                'enable_sound_effects' => AppSetting::get('enable_sound_effects', '1'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:255'],
            'shop_tagline' => ['nullable', 'string', 'max:255'],
            'shop_phone' => ['required', 'string', 'max:255'],
            'shop_phone_secondary' => ['nullable', 'string', 'max:255'],
            'shop_address' => ['nullable', 'string', 'max:500'],
            'shop_ntn' => ['nullable', 'string', 'max:255'],
            'invoice_header_title' => ['nullable', 'string', 'max:255'],
            'invoice_paper_size' => ['required', 'string', 'in:80mm,58mm'],
            'show_barcode_on_invoice' => ['nullable', 'in:0,1'],
            'show_cashier_name' => ['nullable', 'in:0,1'],
            'return_policy' => ['nullable', 'string', 'max:1000'],
            'invoice_footer' => ['nullable', 'string', 'max:1000'],
            'default_payment_method' => ['required', 'string', 'in:Cash,Card,Bank Transfer,Credit'],
            'enable_sound_effects' => ['nullable', 'in:0,1'],
        ]);

        foreach ($validated as $key => $value) {
            AppSetting::set($key, (string) ($value ?? ''));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shop settings updated.')]);

        return to_route('shop-settings.edit');
    }
}
