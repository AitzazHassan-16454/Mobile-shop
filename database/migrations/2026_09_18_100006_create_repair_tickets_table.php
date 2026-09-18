<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no')->unique();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('device_model');
            $table->string('imei')->nullable();
            $table->string('pattern_or_pin')->nullable();
            $table->text('problem_description');
            $table->text('condition_notes')->nullable();
            $table->decimal('estimated_cost', 12, 2);
            $table->decimal('advance_paid', 12, 2)->default(0.00);
            $table->string('status')->default('received'); // received, in_diagnosis, waiting_parts, ready, delivered, cancelled
            $table->decimal('spare_parts_cost', 12, 2)->default(0.00);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_tickets');
    }
};
