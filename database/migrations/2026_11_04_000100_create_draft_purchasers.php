<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_drafts', fn (Blueprint $table) => $table->unsignedInteger('purchasers_version')->default(0));
        Schema::create('draft_purchasers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_draft_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->decimal('share', 7, 4);
            $table->unique(['booking_draft_id', 'customer_id']);
        });
        DB::statement('ALTER TABLE draft_purchasers ADD CONSTRAINT draft_purchasers_share CHECK (share > 0 AND share <= 100)');
        Schema::create('draft_purchaser_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_draft_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('before');
            $table->json('after');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['booking_draft_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_purchaser_events');
        Schema::dropIfExists('draft_purchasers');
        Schema::table('booking_drafts', fn (Blueprint $table) => $table->dropColumn('purchasers_version'));
    }
};
