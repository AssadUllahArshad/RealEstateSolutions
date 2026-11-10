<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('state', 20)->default('pending');
            $table->text('reason');
            $table->json('outgoing_snapshot');
            $table->json('incoming_snapshot');
            $table->longText('private_snapshot');
            $table->json('document_snapshot');
            $table->decimal('outstanding_snapshot', 18, 2);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('decision_note')->nullable();
            $table->date('effective_on')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['requested_by', 'command_key']);
            $table->index(['booking_id', 'state', 'id']);
        });
        Schema::create('booking_transfer_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_transfer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->decimal('share', 7, 4);
            $table->unique(['booking_transfer_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_transfer_parties');
        Schema::dropIfExists('booking_transfers');
    }
};
