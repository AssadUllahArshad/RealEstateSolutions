<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_charge_waivers', function (Blueprint $table) {
            $table->decimal('amount', 18, 2)->nullable()->after('booking_charge_id');
            $table->index('booking_charge_id', 'bcw_charge_idx');
        });
        Schema::table('booking_charge_waivers', function (Blueprint $table) {
            $table->dropUnique(['booking_charge_id']);
        });
        DB::statement('UPDATE booking_charge_waivers waivers JOIN booking_charges charges ON charges.id = waivers.booking_charge_id SET waivers.amount = charges.amount');
        DB::statement('ALTER TABLE booking_charge_waivers MODIFY amount DECIMAL(18,2) NOT NULL');
        DB::statement('ALTER TABLE booking_charge_waivers ADD CONSTRAINT bcw_amount_positive CHECK (amount > 0)');

        Schema::create('booking_charge_payment_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id');
            $table->foreignId('payment_receipt_id');
            $table->foreignId('actor_id');
            $table->foreign('booking_id', 'bcpb_booking_fk')->references('id')->on('bookings')->restrictOnDelete();
            $table->foreign('payment_receipt_id', 'bcpb_receipt_fk')->references('id')->on('payment_receipts')->restrictOnDelete();
            $table->foreign('actor_id', 'bcpb_actor_fk')->references('id')->on('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->decimal('amount', 18, 2);
            $table->date('business_date');
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['booking_id', 'version'], 'bcpb_booking_version_uq');
            $table->unique(['actor_id', 'command_key'], 'bcpb_actor_command_uq');
        });
        DB::statement('ALTER TABLE booking_charge_payment_batches ADD CONSTRAINT bcpb_amount_positive CHECK (amount > 0 AND version > 0)');

        Schema::create('booking_charge_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_charge_payment_batch_id');
            $table->foreignId('booking_charge_id');
            $table->foreign('booking_charge_payment_batch_id', 'bcpa_batch_fk')->references('id')->on('booking_charge_payment_batches')->restrictOnDelete();
            $table->foreign('booking_charge_id', 'bcpa_charge_fk')->references('id')->on('booking_charges')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->unique(['booking_charge_payment_batch_id', 'booking_charge_id'], 'bcpa_batch_charge_uq');
        });
        DB::statement('ALTER TABLE booking_charge_payment_allocations ADD CONSTRAINT bcpa_amount_positive CHECK (amount > 0)');

        Schema::create('booking_charge_payment_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_charge_payment_batch_id')->unique('bcpr_batch_uq');
            $table->foreignId('payment_receipt_id');
            $table->foreignId('actor_id');
            $table->foreign('booking_charge_payment_batch_id', 'bcpr_batch_fk')->references('id')->on('booking_charge_payment_batches')->restrictOnDelete();
            $table->foreign('payment_receipt_id', 'bcpr_receipt_fk')->references('id')->on('payment_receipts')->restrictOnDelete();
            $table->foreign('actor_id', 'bcpr_actor_fk')->references('id')->on('users')->restrictOnDelete();
            $table->date('business_date');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_charge_payment_reversals');
        Schema::dropIfExists('booking_charge_payment_allocations');
        Schema::dropIfExists('booking_charge_payment_batches');
        DB::statement('ALTER TABLE booking_charge_waivers DROP CHECK bcw_amount_positive');
        Schema::table('booking_charge_waivers', function (Blueprint $table) {
            $table->unique('booking_charge_id');
        });
        Schema::table('booking_charge_waivers', function (Blueprint $table) {
            $table->dropIndex('bcw_charge_idx');
            $table->dropColumn('amount');
        });
    }
};
