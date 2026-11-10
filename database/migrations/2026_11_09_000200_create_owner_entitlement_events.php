<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_entitlement_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plot_owner_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('source_kind', 32);
            $table->unsignedBigInteger('source_id');
            $table->decimal('eligible_amount', 18, 2);
            $table->decimal('percentage', 7, 4);
            $table->decimal('entitlement_amount', 18, 2);
            $table->date('business_date');
            $table->json('policy_snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['source_kind', 'source_id']);
            $table->index(['plot_owner_id', 'business_date']);
            $table->index(['booking_id', 'business_date']);
        });
        DB::statement("ALTER TABLE owner_entitlement_events ADD CONSTRAINT owner_entitlement_values CHECK (eligible_amount > 0 AND percentage >= 0 AND percentage <= 100 AND entitlement_amount >= 0 AND source_kind IN ('booking_allocation', 'installment_batch', 'refund_reversal'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_entitlement_events');
    }
};
