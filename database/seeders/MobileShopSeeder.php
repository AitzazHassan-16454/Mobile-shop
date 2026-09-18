<?php

namespace Database\Seeders;

use App\Enums\ImeiStatus;
use App\Enums\PhoneCondition;
use App\Enums\PtaStatus;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductImei;
use Illuminate\Database\Seeder;

class MobileShopSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Initial Shop Settings
        AppSetting::set('shop_name', 'Faizan Mobile & Repairing Lab');
        AppSetting::set('shop_phone', '0300-1234567');
        AppSetting::set('shop_address', 'Main Mobile Market, Shop #12, Lahore');
        AppSetting::set('invoice_footer', 'Shukriya! Clean checking warranty valid for 7 days with original receipt.');

        // 2. Sample Handset Products
        $iphone15 = Product::create([
            'name' => 'iPhone 15 Pro Max',
            'brand' => 'Apple',
            'category' => 'Mobile Handsets',
            'is_serialized' => true,
            'sale_price' => 450000.00,
            'alert_quantity' => 2,
        ]);

        ProductImei::create([
            'product_id' => $iphone15->id,
            'imei_1' => '359281048123451',
            'imei_2' => '359281048123452',
            'color' => 'Natural Titanium',
            'storage' => '256GB',
            'condition' => PhoneCondition::New,
            'pta_status' => PtaStatus::Approved,
            'purchase_cost' => 420000.00,
            'warranty_days' => 7,
            'status' => ImeiStatus::InStock,
        ]);

        ProductImei::create([
            'product_id' => $iphone15->id,
            'imei_1' => '359281048123453',
            'imei_2' => '359281048123454',
            'color' => 'Blue Titanium',
            'storage' => '256GB',
            'condition' => PhoneCondition::Used,
            'pta_status' => PtaStatus::Jv,
            'purchase_cost' => 310000.00,
            'warranty_days' => 7,
            'status' => ImeiStatus::InStock,
        ]);

        $samsungS24 = Product::create([
            'name' => 'Samsung Galaxy S24 Ultra',
            'brand' => 'Samsung',
            'category' => 'Mobile Handsets',
            'is_serialized' => true,
            'sale_price' => 395000.00,
            'alert_quantity' => 2,
        ]);

        ProductImei::create([
            'product_id' => $samsungS24->id,
            'imei_1' => '358102938192831',
            'imei_2' => '358102938192832',
            'color' => 'Titanium Black',
            'storage' => '512GB',
            'condition' => PhoneCondition::New,
            'pta_status' => PtaStatus::Approved,
            'purchase_cost' => 365000.00,
            'warranty_days' => 7,
            'status' => ImeiStatus::InStock,
        ]);

        // 3. Sample Accessory Products
        Product::create([
            'name' => 'Fast Charger 20W Type-C',
            'brand' => 'Anker',
            'category' => 'Chargers & Cables',
            'barcode' => '8901234567890',
            'is_serialized' => false,
            'sale_price' => 3500.00,
            'alert_quantity' => 10,
        ]);

        Product::create([
            'name' => 'Premium Tempered Glass Protector',
            'brand' => 'Ronin',
            'category' => 'Protectors & Covers',
            'barcode' => '8901234567891',
            'is_serialized' => false,
            'sale_price' => 500.00,
            'alert_quantity' => 15,
        ]);

        // 4. Sample Customers
        Customer::create([
            'name' => 'Ali Raza',
            'phone' => '03009876543',
            'address' => 'Model Town, Lahore',
            'current_balance' => 0.00,
        ]);

        Customer::create([
            'name' => 'Muhammad Usman',
            'phone' => '03214567890',
            'address' => 'Gulberg III, Lahore',
            'current_balance' => 15000.00,
        ]);
    }
}
