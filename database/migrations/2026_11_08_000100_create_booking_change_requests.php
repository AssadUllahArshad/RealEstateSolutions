<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_events', function (Blueprint $table) {
            $table->index('booking_id');
        });
        Schema::table('booking_events', function (Blueprint $table) {
            $table->dropUnique('booking_events_booking_id_unique');
        });
        Schema::create('booking_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->enum('kind', ['correction', 'cancellation']);
            $table->text('reason');
            $table->text('proposed_change')->nullable();
            $table->enum('state', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->uuid('command_key');
            $table->timestamps();
            $table->unique(['requested_by', 'command_key']);
            $table->index(['booking_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_change_requests');
        Schema::table('booking_events', function (Blueprint $table) {
            $table->unique('booking_id');
        });
        Schema::table('booking_events', function (Blueprint $table) {
            $table->dropIndex(['booking_id']);
        });
    }
};
