<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Шахта: новые улучшения — выносливость (+удары) и магнит (+% монет).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('miner_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('stamina')->default(0)->after('cart');
            $table->unsignedTinyInteger('magnet')->default(0)->after('stamina');
        });
    }

    public function down(): void
    {
        Schema::table('miner_profiles', function (Blueprint $table) {
            $table->dropColumn(['stamina', 'magnet']);
        });
    }
};
