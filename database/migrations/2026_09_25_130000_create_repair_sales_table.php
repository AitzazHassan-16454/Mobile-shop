<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_sales', function (Blueprint $table) {
            $table->id();
            $table->string('item_name');
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('cost_price', 12, 2)->default(0.00);
            $table->decimal('sell_price', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('payment_method')->default('cash'); // cash, jazzcash, easypaisa, bank, udhaar
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_sales');
    }
};
