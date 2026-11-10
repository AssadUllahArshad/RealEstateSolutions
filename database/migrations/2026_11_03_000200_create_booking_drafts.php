<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('plot_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->decimal('quoted_price', 18, 2);
            $table->decimal('catalogue_price_snapshot', 18, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->uuid('creation_key');
            $table->char('creation_hash', 64);
            $table->timestamps();
            $table->unique(['created_by', 'creation_key']);
            $table->index(['customer_id', 'id']);
        });
        DB::statement('ALTER TABLE booking_drafts ADD CONSTRAINT booking_drafts_positive CHECK (quoted_price > 0 AND version > 0)');
        Schema::create('booking_draft_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_draft_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('details');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['booking_draft_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_draft_events');
        Schema::dropIfExists('booking_drafts');
    }
};
