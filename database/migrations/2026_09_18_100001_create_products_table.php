<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('brand');
            $table->string('category');
            $table->string('barcode')->nullable()->unique();
            $table->boolean('is_serialized')->default(false); // true for phones, false for accessories
            $table->decimal('sale_price', 12, 2);
            $table->integer('alert_quantity')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
