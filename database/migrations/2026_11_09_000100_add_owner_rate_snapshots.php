<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plot_owners', function (Blueprint $table) {
            $table->decimal('default_percentage', 7, 4)->nullable()->after('admin_user_id');
        });
        Schema::table('plots', function (Blueprint $table) {
            $table->decimal('owner_percentage', 7, 4)->nullable()->after('plot_owner_id');
        });
        DB::statement('ALTER TABLE plot_owners ADD CONSTRAINT plot_owner_percentage_range CHECK (default_percentage IS NULL OR (default_percentage >= 0 AND default_percentage <= 100))');
        DB::statement('ALTER TABLE plots ADD CONSTRAINT plot_percentage_range CHECK (owner_percentage IS NULL OR (owner_percentage >= 0 AND owner_percentage <= 100))');
    }

    public function down(): void
    {
        Schema::table('plots', fn (Blueprint $table) => $table->dropColumn('owner_percentage'));
        Schema::table('plot_owners', fn (Blueprint $table) => $table->dropColumn('default_percentage'));
    }
};
