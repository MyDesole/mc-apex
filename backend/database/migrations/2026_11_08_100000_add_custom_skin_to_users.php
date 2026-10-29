<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Свой скин: игрок загружает PNG, и витрина с карточкой берут его
 * вместо скина из Minecraft. Сам файл — на диске, здесь только когда
 * загружен (он же версия для кэша браузера) и модель рук.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('custom_skin_at')->nullable()->after('minecraft_linked_at');
            $table->string('custom_skin_model', 8)->nullable()->after('custom_skin_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['custom_skin_at', 'custom_skin_model']);
        });
    }
};
