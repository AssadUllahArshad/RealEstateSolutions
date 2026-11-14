<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_customer_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('effective_on');
            $table->text('source_note');
            $table->string('source_reference', 160)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['created_by', 'command_key']);
            $table->index(['customer_id', 'effective_on']);
        });
        DB::statement('ALTER TABLE opening_customer_balances ADD CONSTRAINT opening_customer_balances_positive CHECK (amount > 0)');

        Schema::create('opening_owner_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plot_owner_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('effective_on');
            $table->text('source_note');
            $table->string('source_reference', 160)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['created_by', 'command_key']);
            $table->index(['plot_owner_id', 'effective_on']);
        });
        DB::statement('ALTER TABLE opening_owner_balances ADD CONSTRAINT opening_owner_balances_nonzero CHECK (amount <> 0)');

        Schema::create('opening_commission_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('effective_on');
            $table->text('source_note');
            $table->string('source_reference', 160)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['created_by', 'command_key']);
            $table->index(['agent_id', 'effective_on']);
        });
        DB::statement('ALTER TABLE opening_commission_balances ADD CONSTRAINT opening_commission_balances_nonzero CHECK (amount <> 0)');

        Schema::create('opening_balance_activations', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->unsignedBigInteger('opening_id');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['kind', 'opening_id']);
            $table->unique(['actor_id', 'command_key']);
        });
        DB::statement("ALTER TABLE opening_balance_activations ADD CONSTRAINT opening_balance_activations_valid CHECK (kind IN ('customer','owner','commission'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_activations');
        Schema::dropIfExists('opening_commission_balances');
        Schema::dropIfExists('opening_owner_balances');
        Schema::dropIfExists('opening_customer_balances');
    }
};
