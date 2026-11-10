<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_allocation_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('installment_allocation_id');
            $table->foreign('installment_allocation_id', 'iac_allocation_fk')
                ->references('id')->on('installment_allocations')->restrictOnDelete();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('reason', 2000);
            $table->string('state', 16);
            $table->text('decision_note')->nullable();
            $table->date('business_date')->nullable();
            $table->unsignedInteger('ledger_version')->nullable();
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->uuid('decision_key')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['prepared_by', 'command_key'], 'iac_prepared_command_uq');
            $table->unique('decision_key');
            $table->index(['booking_id', 'state']);
        });
        DB::statement("ALTER TABLE installment_allocation_corrections ADD CONSTRAINT installment_correction_positive CHECK (amount > 0 AND state IN ('prepared', 'approved', 'rejected'))");
        DB::statement("ALTER TABLE installment_allocation_corrections ADD CONSTRAINT installment_correction_decision CHECK ((state = 'prepared' AND decided_by IS NULL AND decision_key IS NULL AND business_date IS NULL AND ledger_version IS NULL) OR (state IN ('approved', 'rejected') AND decided_by IS NOT NULL AND decision_key IS NOT NULL AND decided_at IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_allocation_corrections');
    }
};
