<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->string('number', 40)->unique();
            $table->string('category', 100);
            $table->decimal('amount', 18, 2);
            $table->date('business_date');
            $table->string('payee', 160)->nullable();
            $table->text('reference')->nullable();
            $table->text('note');
            $table->json('account_snapshot');
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['posted_by', 'command_key']);
            $table->index(['business_date', 'id']);
        });
        DB::statement('ALTER TABLE office_expenses ADD CONSTRAINT office_expenses_positive CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('office_expenses');
    }
};
