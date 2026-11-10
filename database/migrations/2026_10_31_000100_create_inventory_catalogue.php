<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('societies', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 160)->nullable();
            $table->string('office_name', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('address', 500)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE societies ADD CONSTRAINT societies_single_installation CHECK (id = 1)');
        DB::table('societies')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('society_id')->default(1);
            $table->foreign('society_id')->references('id')->on('societies')->restrictOnDelete();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('plot_sizes', function (Blueprint $table) {
            $table->id();
            $table->string('label', 80)->unique();
            $table->decimal('area_marla', 8, 2);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE plot_sizes ADD CONSTRAINT plot_sizes_positive_area CHECK (area_marla > 0)');

        Schema::create('plots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained()->restrictOnDelete();
            $table->foreignId('plot_size_id')->constrained()->restrictOnDelete();
            $table->string('number', 64);
            $table->decimal('list_price', 18, 2)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['block_id', 'number']);
            $table->index(['plot_size_id', 'number']);
        });
        DB::statement('ALTER TABLE plots ADD CONSTRAINT plots_positive_price CHECK (list_price IS NULL OR list_price > 0)');

        Schema::create('inventory_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('event', 64);
            $table->json('before');
            $table->json('after');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_events');
        Schema::dropIfExists('plots');
        Schema::dropIfExists('plot_sizes');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('societies');
    }
};
