<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Косметика, которую видно не только в профиле: эффект ника (везде,
 * где показан ник), появление профиля и след курсора для гостей.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name_effect', 40)->nullable();
            $table->string('profile_entrance', 40)->nullable();
            $table->string('cursor_trail', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['name_effect', 'profile_entrance', 'cursor_trail']);
        });
    }
};
