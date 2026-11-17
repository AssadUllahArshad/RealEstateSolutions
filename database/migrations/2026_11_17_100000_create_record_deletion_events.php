<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_deletion_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('resource_table', 64);
            $table->unsignedBigInteger('resource_id');
            $table->string('resource_label', 255);
            $table->text('reason');
            $table->json('snapshot');
            $table->unsignedInteger('deleted_rows');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['resource_table', 'resource_id']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_deletion_events');
    }
};
