<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_charge_series', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('next_number')->default(1);
        });
        DB::table('booking_charge_series')->insert(['id' => 1, 'next_number' => 1]);
        Schema::create('booking_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('number', 24)->unique();
            $table->decimal('amount', 18, 2);
            $table->date('business_date');
            $table->string('reason', 160);
            $table->text('note')->nullable();
            $table->string('evidence_reference', 160)->nullable();
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['posted_by', 'command_key']);
        });
        DB::statement('ALTER TABLE booking_charges ADD CONSTRAINT booking_charges_positive CHECK (amount > 0)');
        Schema::create('booking_charge_waivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_charge_id')->unique()->constrained()->restrictOnDelete();
            $table->date('business_date');
            $table->string('reason', 500);
            $table->foreignId('waived_by')->constrained('users')->restrictOnDelete();
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['waived_by', 'command_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_charge_waivers');
        Schema::dropIfExists('booking_charges');
        Schema::dropIfExists('booking_charge_series');
    }
};
