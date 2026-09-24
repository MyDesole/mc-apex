<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            if (!Schema::hasColumn('clans', 'wins')) {
                $table->unsignedInteger('wins')->default(0);
            }
            if (!Schema::hasColumn('clans', 'losses')) {
                $table->unsignedInteger('losses')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            //
        });
    }
};
