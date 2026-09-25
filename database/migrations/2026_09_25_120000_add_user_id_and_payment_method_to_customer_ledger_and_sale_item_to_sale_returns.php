<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_ledger', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            $table->string('payment_method')->nullable()->after('amount');
        });

        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->foreignId('sale_item_id')->nullable()->after('sale_return_id')->constrained('sale_items')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->dropForeign(['sale_item_id']);
            $table->dropColumn('sale_item_id');
        });

        Schema::table('customer_ledger', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'payment_method']);
        });
    }
};
