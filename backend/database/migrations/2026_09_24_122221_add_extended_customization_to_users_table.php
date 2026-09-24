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

            // Любимые режимы (json-массив: ['bedwars', 'skywars', 'duels'])
            $table->json('favorite_modes')->nullable()->after('socials');

            // Discord tag
            $table->string('discord_tag', 64)->nullable()->after('socials');

            // Verified
            $table->boolean('is_verified')->default(false)->after('is_banned');
            $table->string('verified_reason', 128)->nullable()->after('is_verified');

            // Клан-партнёр (отдельно от clan_member — это "любимый"/"основной")
            $table->timestamp('clan_joined_at')->nullable()->after('favorite_clan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'card_background', 'favorite_modes', 'discord_tag',
                'is_verified', 'verified_reason', 'clan_joined_at',
            ]);
        });
    }
};
