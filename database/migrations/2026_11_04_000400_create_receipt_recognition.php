<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_series', function (Blueprint $table) {
            $table->string('prefix', 15)->primary();
            $table->unsignedBigInteger('starting_number');
            $table->unsignedBigInteger('next_number');
        });
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_draft_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->string('number', 40)->unique();
            $table->string('series_prefix', 15);
            $table->foreign('series_prefix')->references('prefix')->on('receipt_series')->restrictOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->decimal('amount', 18, 2);
            $table->string('method', 25);
            $table->string('state', 25);
            $table->string('bank_stage', 15)->nullable();
            $table->date('accepted_on');
            $table->date('recognized_on')->nullable();
            $table->date('cleared_on')->nullable();
            $table->date('reversed_on')->nullable();
            $table->text('reference')->nullable();
            $table->text('verification_note');
            $table->char('account_reference_hash', 64)->nullable()->unique();
            $table->char('global_reference_hash', 64)->nullable()->index();
            $table->json('snapshot');
            $table->unsignedInteger('version');
            $table->foreignId('accepted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['series_prefix', 'sequence']);
        });
        DB::statement("ALTER TABLE payment_receipts ADD CONSTRAINT payment_receipts_valid CHECK (amount > 0 AND version > 0 AND state IN ('pending_clearance','recognized','reversed') AND ((method = 'cheque' AND bank_stage IN ('deposited','cleared')) OR (method <> 'cheque' AND bank_stage IS NULL)) AND (state <> 'recognized' OR recognized_on IS NOT NULL) AND (state <> 'reversed' OR reversed_on IS NOT NULL))");
        Schema::create('account_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 20);
            $table->decimal('amount', 18, 2);
            $table->date('business_date');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['payment_receipt_id', 'kind']);
            $table->index(['financial_account_id', 'business_date', 'id']);
        });
        DB::statement("ALTER TABLE account_movements ADD CONSTRAINT account_movements_valid CHECK (amount > 0 AND kind IN ('receipt_credit','receipt_reversal'))");
        Schema::create('payment_receipt_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('event', 30);
            $table->json('details');
            $table->text('private_note');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['payment_receipt_id', 'version']);
        });
        Schema::create('receipt_posting_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->foreignId('payment_receipt_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['actor_id', 'command_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_posting_commands');
        Schema::dropIfExists('payment_receipt_events');
        Schema::dropIfExists('account_movements');
        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('receipt_series');
    }
};
