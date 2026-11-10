<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE owner_entitlement_events DROP CHECK owner_entitlement_values');
        DB::statement("ALTER TABLE owner_entitlement_events ADD CONSTRAINT owner_entitlement_values CHECK (percentage >= 0 AND percentage <= 100 AND ((source_kind IN ('booking_allocation','installment_batch') AND eligible_amount > 0 AND entitlement_amount >= 0) OR (source_kind = 'customer_refund' AND eligible_amount < 0 AND entitlement_amount <= 0)))");
        Schema::create('customer_refunds', function (Blueprint $table) {
            $table->id(); $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->string('number', 40)->unique(); $table->decimal('amount', 18, 2); $table->decimal('deduction_amount', 18, 2)->default(0);
            $table->date('business_date'); $table->string('state', 20); $table->text('deduction_reason')->nullable();
            $table->text('reference')->nullable(); $table->text('verification_note'); $table->json('booking_snapshot'); $table->json('account_snapshot');
            $table->decimal('refundable_snapshot', 18, 2); $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete(); $table->text('decision_note')->nullable(); $table->timestamp('decided_at')->nullable();
            $table->uuid('command_key'); $table->char('payload_hash', 64); $table->uuid('decision_key')->nullable()->unique(); $table->timestamps();
            $table->unique(['prepared_by', 'command_key']); $table->index(['booking_id', 'state']); $table->index(['financial_account_id', 'business_date']);
        });
        DB::statement("ALTER TABLE customer_refunds ADD CONSTRAINT customer_refunds_valid CHECK (amount > 0 AND deduction_amount >= 0 AND refundable_snapshot >= amount + deduction_amount AND state IN ('prepared','approved','rejected'))");
    }
    public function down(): void { Schema::dropIfExists('customer_refunds'); DB::statement('ALTER TABLE owner_entitlement_events DROP CHECK owner_entitlement_values'); DB::statement("ALTER TABLE owner_entitlement_events ADD CONSTRAINT owner_entitlement_values CHECK (eligible_amount > 0 AND percentage >= 0 AND percentage <= 100 AND entitlement_amount >= 0 AND source_kind IN ('booking_allocation', 'installment_batch', 'refund_reversal'))"); }
};
