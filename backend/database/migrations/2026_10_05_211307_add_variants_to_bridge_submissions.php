<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Подвиды у заявки и «особый» подвид.
 *
 * Игрок при подаче отмечает, какие подвиды вида он показывает. Тестер
 * может поправить набор при проверке, а игрок — в любой момент до
 * подтверждения. Особый подвид помечает куратор: он выделяется в списке.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bridge_technique_variants', function (Blueprint $table) {
            $table->boolean('is_special')->default(false)->after('label');
        });

        Schema::table('user_bridge_techniques', function (Blueprint $table) {
            // Какие подвиды заявлены или подтверждены
            $table->json('variants')->nullable()->after('video_path');
        });
    }

    public function down(): void
    {
        Schema::table('user_bridge_techniques', function (Blueprint $table) {
            $table->dropColumn('variants');
        });

        Schema::table('bridge_technique_variants', function (Blueprint $table) {
            $table->dropColumn('is_special');
        });
    }
};
