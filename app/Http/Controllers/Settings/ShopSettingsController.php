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
                'shop_phone' => AppSetting::get('shop_phone', '+92 300 1234567'),
                'shop_address' => AppSetting::get('shop_address', 'Main Mobile Market, Shop #12'),
                'return_policy' => AppSetting::get('return_policy', '7 Days Checking Warranty. Physical & Water Damage Not Covered.'),
                'invoice_footer' => AppSetting::get('invoice_footer', ''),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:255'],
            'shop_phone' => ['required', 'string', 'max:255'],
            'shop_address' => ['nullable', 'string', 'max:500'],
            'return_policy' => ['nullable', 'string', 'max:1000'],
            'invoice_footer' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($validated as $key => $value) {
            AppSetting::set($key, $value);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shop settings updated.')]);

        return to_route('shop-settings.edit');
    }
}
