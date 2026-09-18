<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('used_phone_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no')->unique();
            $table->string('seller_name');
            $table->string('seller_father_name')->nullable();
            $table->string('seller_cnic');
            $table->string('seller_phone');
            $table->text('seller_address')->nullable();
            $table->string('cnic_front_image')->nullable();
            $table->string('cnic_back_image')->nullable();
            $table->string('device_model');
            $table->string('imei_1');
            $table->string('imei_2')->nullable();
            $table->decimal('purchase_amount', 12, 2);
            $table->string('payment_method')->default('cash');
            $table->boolean('agreement_signed')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('used_phone_purchases');
    }
};
