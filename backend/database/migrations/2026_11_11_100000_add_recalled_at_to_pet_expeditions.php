<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Досрочное возвращение питомца с экспедиции: отметка отзыва отдельно от
 * забора добычи, чтобы интерфейс различал «забрал добычу» и «вернул».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pet_expeditions', function (Blueprint $table) {
            $table->timestamp('recalled_at')->nullable()->after('claimed_at');
        });
    }

    public function down(): void
    {
        Schema::table('pet_expeditions', function (Blueprint $table) {
            $table->dropColumn('recalled_at');
        });
    }
};
