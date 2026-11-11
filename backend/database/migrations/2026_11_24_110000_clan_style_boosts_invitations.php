<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Кланы: оформление (девиз, эмблема, тема, цвет тега), набор (текст и порог рейтинга),
 * бусты за казну, приглашения игроков и награды за недельные цели.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            // Оформление
            $table->string('motto', 100)->nullable();
            $table->string('emblem', 24)->nullable();
            $table->string('theme', 24)->nullable();
            $table->string('tag_color', 7)->nullable();

            // Набор: текст «кого ищем» и минимальные очки рейтинга для заявки
            $table->string('recruit_text', 200)->nullable();
            $table->unsignedInteger('min_rating')->default(0);

            // Бусты из казны: бонусный опыт и время действия
            $table->unsignedInteger('bonus_xp')->default(0);
            $table->timestamp('xp_boost_until')->nullable();
            $table->timestamp('coin_boost_until')->nullable();
        });

        Schema::create('clan_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message', 200)->nullable();
            // pending | accepted | declined | cancelled
            $table->string('status', 16)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['clan_id', 'status']);
        });

        Schema::create('clan_goal_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained()->cascadeOnDelete();
            // Понедельник недели, за которую забрали награду (Y-m-d)
            $table->string('week', 10);
            $table->string('goal', 24);
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('coins');
            $table->timestamps();

            $table->unique(['clan_id', 'week', 'goal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_goal_claims');
        Schema::dropIfExists('clan_invitations');

        Schema::table('clans', function (Blueprint $table) {
            $table->dropColumn([
                'motto', 'emblem', 'theme', 'tag_color', 'recruit_text', 'min_rating',
                'bonus_xp', 'xp_boost_until', 'coin_boost_until',
            ]);
        });
    }
};
