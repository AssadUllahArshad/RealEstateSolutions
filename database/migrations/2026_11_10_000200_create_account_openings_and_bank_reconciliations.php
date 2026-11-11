<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_account_opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_account_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('effective_on');
            $table->text('source_note');
            $table->string('source_reference', 160)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['created_by', 'command_key']);
        });

        Schema::create('financial_account_opening_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_id')->constrained('financial_account_opening_balances')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('effective_on');
            $table->text('reason');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['actor_id', 'command_key'], 'faoc_actor_command_uq');
            $table->index(['opening_balance_id', 'effective_on', 'id'], 'faoc_opening_date_idx');
        });
        DB::statement('ALTER TABLE financial_account_opening_corrections ADD CONSTRAINT faoc_nonzero CHECK (amount <> 0)');

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('statement_opening_balance', 18, 2);
            $table->decimal('statement_closing_balance', 18, 2);
            $table->string('state', 20);
            $table->text('note');
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->uuid('decision_key')->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['prepared_by', 'command_key']);
            $table->index(['financial_account_id', 'period_start', 'period_end', 'state'], 'br_account_period_idx');
        });
        DB::statement("ALTER TABLE bank_reconciliations ADD CONSTRAINT bank_reconciliations_valid CHECK (period_end >= period_start AND state IN ('prepared','finalized','rejected') AND ((state = 'prepared' AND decided_by IS NULL AND decided_at IS NULL) OR (state IN ('finalized','rejected') AND decided_by IS NOT NULL AND decided_at IS NOT NULL)))");

        Schema::create('bank_reconciliation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained()->restrictOnDelete();
            $table->date('statement_date');
            $table->decimal('amount', 18, 2);
            $table->string('direction', 10);
            $table->string('description', 200);
            $table->string('reference', 160)->nullable();
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->unique(['bank_reconciliation_id', 'source_type', 'source_id'], 'brl_reconciliation_source_uq');
            $table->index(['source_type', 'source_id'], 'brl_source_idx');
        });
        DB::statement("ALTER TABLE bank_reconciliation_lines ADD CONSTRAINT bank_reconciliation_lines_valid CHECK (amount > 0 AND direction IN ('inflow','outflow') AND source_type IN ('account_movement','office_expense','owner_payout','customer_refund','commission_payment'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_lines');
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('financial_account_opening_corrections');
        Schema::dropIfExists('financial_account_opening_balances');
    }
};
