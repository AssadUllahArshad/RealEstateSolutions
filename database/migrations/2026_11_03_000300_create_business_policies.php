<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_policies', function (Blueprint $table) {
            $table->string('key', 40)->primary();
            $table->json('values');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE business_policies ADD CONSTRAINT business_policy_version_positive CHECK (version > 0)');
        Schema::create('business_policy_events', function (Blueprint $table) {
            $table->id();
            $table->string('policy_key', 40);
            $table->foreign('policy_key')->references('key')->on('business_policies')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['policy_key', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_policy_events');
        Schema::dropIfExists('business_policies');
    }
};
