<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plot_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plot_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16);
            $table->string('state', 16);
            $table->string('purpose', 500);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('release_reason', 500)->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('active_plot_id')->nullable()->storedAs("CASE WHEN state = 'active' THEN plot_id ELSE NULL END")->unique();
            $table->index(['plot_id', 'id']);
        });
        DB::statement("ALTER TABLE plot_holds ADD CONSTRAINT plot_holds_valid_kind CHECK (kind IN ('reservation','block')), ADD CONSTRAINT plot_holds_valid_state CHECK (state IN ('active','released')), ADD CONSTRAINT plot_holds_release_consistency CHECK ((state = 'active' AND released_by IS NULL AND released_at IS NULL AND release_reason IS NULL) OR (state = 'released' AND released_by IS NOT NULL AND released_at IS NOT NULL AND release_reason IS NOT NULL))");
        Schema::create('inventory_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('key');
            $table->char('payload_hash', 64);
            $table->foreignId('plot_hold_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['actor_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_commands');
        Schema::dropIfExists('plot_holds');
    }
};
