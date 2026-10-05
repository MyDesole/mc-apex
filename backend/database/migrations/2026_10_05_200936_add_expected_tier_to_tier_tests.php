<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ожидаемый ранг, который игрок указывает при записи на тир-тест.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tier_tests', function (Blueprint $table) {
            $table->string('expected_tier', 8)->nullable()->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('tier_tests', function (Blueprint $table) {
            $table->dropColumn('expected_tier');
        });
    }
};
