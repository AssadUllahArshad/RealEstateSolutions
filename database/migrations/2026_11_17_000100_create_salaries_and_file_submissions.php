<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->string('employee_name', 120);
            $table->date('salary_month');
            $table->decimal('amount', 18, 2);
            $table->date('paid_on');
            $table->string('method', 30)->default('cash');
            $table->string('reference', 160)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['salary_month', 'employee_name']);
        });
        DB::statement('ALTER TABLE salary_payments ADD CONSTRAINT salary_payments_positive CHECK (amount > 0)');

        Schema::create('booking_file_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->date('submitted_on');
            $table->text('note')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->uuid('command_key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_file_submissions');
        Schema::dropIfExists('salary_payments');
    }
};
