<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plot_owners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->foreignId('admin_user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::table('plots', function (Blueprint $table) {
            $table->foreignId('plot_owner_id')->nullable()->constrained('plot_owners')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('plots', fn (Blueprint $table) => $table->dropConstrainedForeignId('plot_owner_id'));
        Schema::dropIfExists('plot_owners');
    }
};
