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
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('trade_in_amount', 12, 2)->default(0.00)->after('discount_amount');
            $table->unsignedBigInteger('used_phone_purchase_id')->nullable()->after('trade_in_amount');
            $table->foreign('used_phone_purchase_id')
                ->references('id')
                ->on('used_phone_purchases')
                ->nullOnDelete();
        });

        Schema::table('used_phone_purchases', function (Blueprint $table) {
            $table->timestamp('applied_at')->nullable()->after('agreement_signed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['used_phone_purchase_id']);
            $table->dropColumn(['used_phone_purchase_id', 'trade_in_amount']);
        });

        Schema::table('used_phone_purchases', function (Blueprint $table) {
            $table->dropColumn('applied_at');
        });
    }
};
