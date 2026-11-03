<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_bridge_techniques', function (Blueprint $table) {
            // Пересдача подтверждённого вида: оценка остаётся, пока тестер не решит
            $table->boolean('retake_pending')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('user_bridge_techniques', function (Blueprint $table) {
            $table->dropColumn('retake_pending');
        });
    }
};
