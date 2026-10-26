<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Пятая волна косметики: питомец в шапке профиля, оформление своих
 * сообщений в чате и титул под ником.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_pet', 40)->nullable();
            $table->string('chat_bubble', 40)->nullable();
            $table->string('profile_title', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['profile_pet', 'chat_bubble', 'profile_title']);
        });
    }
};
