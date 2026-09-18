<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_imeis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('imei_1')->unique();
            $table->string('imei_2')->nullable();
            $table->string('color')->nullable();
            $table->string('storage')->nullable();
            $table->string('condition')->default('new'); // new, used
            $table->string('pta_status')->default('approved'); // approved, non_pta, jv, cpid, software
            $table->decimal('purchase_cost', 12, 2);
            $table->integer('warranty_days')->default(0);
            $table->string('status')->default('in_stock'); // in_stock, sold, repairing, returned
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_imeis');
    }
};
