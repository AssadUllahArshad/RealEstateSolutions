<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plots', function (Blueprint $table) {
            $table->string('category', 24)->default('residential')->after('number');
            $table->decimal('extra_area_sq_ft', 10, 2)->default(0)->after('plot_size_id');
            $table->index(['category', 'number']);
        });
        DB::statement("ALTER TABLE plots ADD CONSTRAINT plots_valid_category CHECK (category IN ('residential', 'commercial'))");
        DB::statement('ALTER TABLE plots ADD CONSTRAINT plots_non_negative_extra_area CHECK (extra_area_sq_ft >= 0)');
    }

    public function down(): void
    {
        Schema::table('plots', function (Blueprint $table) {
            $table->dropIndex(['category', 'number']);
            $table->dropColumn(['category', 'extra_area_sq_ft']);
        });
    }
};
