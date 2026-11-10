<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_draft_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('draft_term_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_draft_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['booking_draft_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_term_events');
        Schema::dropIfExists('draft_terms');
    }
};
