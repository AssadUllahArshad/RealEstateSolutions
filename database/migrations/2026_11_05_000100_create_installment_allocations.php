<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->decimal('amount', 18, 2);
            $table->date('business_date');
            $table->json('policy_snapshot');
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['booking_id', 'version']);
            $table->unique(['actor_id', 'command_key']);
            $table->unique(['id', 'booking_id', 'payment_receipt_id'], 'installment_batch_identity');
        });
        Schema::create('installment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_receipt_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->decimal('amount', 18, 2);
            $table->unique(['installment_batch_id', 'sequence']);
            $table->index(['booking_id', 'sequence']);
            $table->foreign(['installment_batch_id', 'booking_id', 'payment_receipt_id'], 'installment_allocation_parent')->references(['id', 'booking_id', 'payment_receipt_id'])->on('installment_batches')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE installment_batches ADD CONSTRAINT installment_batch_positive CHECK (amount > 0 AND version > 0)');
        DB::statement('ALTER TABLE installment_allocations ADD CONSTRAINT installment_allocation_positive CHECK (amount > 0 AND sequence BETWEEN 1 AND 360)');
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_allocations');
        Schema::dropIfExists('installment_batches');
    }
};
