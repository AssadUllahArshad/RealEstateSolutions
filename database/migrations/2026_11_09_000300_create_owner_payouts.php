<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plot_owner_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->string('number', 40)->unique();
            $table->decimal('amount', 18, 2);
            $table->date('business_date');
            $table->string('state', 20);
            $table->text('reference')->nullable();
            $table->text('verification_note');
            $table->json('owner_snapshot');
            $table->json('account_snapshot');
            $table->decimal('payable_snapshot', 18, 2);
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->uuid('decision_key')->nullable()->unique();
            $table->timestamps();
            $table->unique(['prepared_by', 'command_key']);
            $table->index(['plot_owner_id', 'state']);
            $table->index(['financial_account_id', 'business_date']);
        });
        DB::statement("ALTER TABLE owner_payouts ADD CONSTRAINT owner_payout_valid CHECK (amount > 0 AND payable_snapshot >= amount AND state IN ('prepared','approved','rejected') AND ((state = 'prepared' AND decided_by IS NULL AND decided_at IS NULL) OR (state IN ('approved','rejected') AND decided_by IS NOT NULL AND decided_at IS NOT NULL)))");
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_payouts');
    }
};
