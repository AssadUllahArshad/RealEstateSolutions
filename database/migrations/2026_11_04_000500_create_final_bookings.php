<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_draft_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('plot_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->date('confirmed_on');
            $table->decimal('net', 18, 2);
            $table->decimal('down_payment', 18, 2);
            $table->json('snapshot');
            $table->longText('private_snapshot');
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['confirmed_by', 'command_key']);
        });
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_positive CHECK (net > 0 AND down_payment > 0 AND down_payment <= net)');
        Schema::create('booking_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_receipt_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->unique(['booking_id', 'payment_receipt_id']);
        });
        DB::statement('ALTER TABLE booking_allocations ADD CONSTRAINT booking_allocations_positive CHECK (amount > 0)');
        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->json('details');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_events');
        Schema::dropIfExists('booking_allocations');
        Schema::dropIfExists('bookings');
    }
};
