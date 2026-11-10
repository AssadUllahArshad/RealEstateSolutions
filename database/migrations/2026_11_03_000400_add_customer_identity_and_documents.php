<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('cnic')->nullable();
            $table->text('address')->nullable();
        });
        Schema::create('customer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('kind', 40);
            $table->string('path')->unique();
            $table->string('mime', 80);
            $table->unsignedInteger('bytes');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'kind', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_documents');
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['cnic', 'address']));
    }
};
