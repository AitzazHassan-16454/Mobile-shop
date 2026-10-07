<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('used_phone_purchases', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('agreement_signed');
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('rejection_reason')->nullable()->after('reviewed_at');
        });

        // Credits already redeemed against a sale were implicitly approved before
        // this column existed, so backfill them to keep reports accurate.
        DB::table('used_phone_purchases')
            ->whereNotNull('applied_at')
            ->update(['status' => 'approved', 'reviewed_at' => DB::raw('applied_at')]);
    }

    public function down(): void
    {
        Schema::table('used_phone_purchases', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['status', 'reviewed_by', 'reviewed_at', 'rejection_reason']);
        });
    }
};
