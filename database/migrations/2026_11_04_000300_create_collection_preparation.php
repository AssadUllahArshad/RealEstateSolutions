<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('type', 20);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('creation_key');
            $table->char('creation_hash', 64);
            $table->timestamps();
            $table->unique(['created_by', 'creation_key']);
        });
        DB::statement("ALTER TABLE financial_accounts ADD CONSTRAINT financial_accounts_valid CHECK (version > 0 AND type IN ('cash', 'bank', 'wallet', 'other'))");
        Schema::create('financial_account_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('details');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['financial_account_id', 'version']);
        });
        Schema::create('receipt_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_draft_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('reported_on');
            $table->string('method', 25);
            $table->text('reference')->nullable();
            $table->text('notes')->nullable();
            $table->json('policy_snapshot');
            $table->json('account_snapshot');
            $table->unsignedInteger('version');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('creation_key');
            $table->char('creation_hash', 64);
            $table->timestamps();
            $table->unique(['created_by', 'creation_key']);
            $table->index(['customer_id', 'id']);
        });
        DB::statement("ALTER TABLE receipt_drafts ADD CONSTRAINT receipt_drafts_valid CHECK (amount > 0 AND version > 0 AND method IN ('cash', 'bank_transfer', 'cheque', 'mobile_wallet', 'other'))");
        Schema::create('receipt_draft_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_draft_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('details');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['receipt_draft_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_draft_events');
        Schema::dropIfExists('receipt_drafts');
        Schema::dropIfExists('financial_account_events');
        Schema::dropIfExists('financial_accounts');
    }
};
