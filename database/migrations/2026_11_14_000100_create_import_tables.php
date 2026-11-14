<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('template', 40);
            $table->unsignedInteger('template_version');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('state', 20);
            $table->string('source_filename', 255)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->uuid('command_key');
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('committed_at')->nullable();
            $table->unique(['actor_id', 'command_key']);
            $table->index(['template', 'state', 'created_at'], 'import_batches_template_state_idx');
        });
        DB::statement("ALTER TABLE import_batches ADD CONSTRAINT import_batches_valid CHECK (state IN ('dry_run','committed','failed') AND template IN ('customers','plot_owners','inventory','bookings'))");

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('payload');
            $table->json('errors')->nullable();
            $table->string('state', 20);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['import_batch_id', 'row_number']);
        });
        DB::statement("ALTER TABLE import_rows ADD CONSTRAINT import_rows_valid CHECK (state IN ('valid','invalid','committed','skipped'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};
