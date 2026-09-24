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
        Schema::table('users', function (Blueprint $table) {
            // Рамка аватара
            $table->string('avatar_frame', 32)->default('default')->after('avatar');

            // Эффект профиля (glow, gradient, animated)
            $table->string('profile_effect', 32)->nullable()->after('avatar_frame');

            // Акцентный цвет профиля (переопределяет banner_color для карточки)
            $table->string('accent_color', 16)->nullable()->after('profile_effect');

            // Статус (кастомный текст под ником)
            $table->string('status', 64)->nullable()->after('bio');

            // Любимая цитата
            $table->string('quote', 160)->nullable()->after('status');

            // Любимый клан (витрина)
            $table->foreignId('favorite_clan_id')->nullable()->after('quote')
                ->constrained('clans')->nullOnDelete();

            // Витрина ачивок (id ачивок, до 6 штук)
            $table->json('featured_achievements')->nullable()->after('favorite_clan_id');

            // Приватность
            $table->enum('profile_visibility', ['public', 'friends', 'private'])
                ->default('public')->after('featured_achievements');

            // Кастомный фон профиля (картинка поверх cover)
            $table->string('card_background')->nullable()->after('profile_visibility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
