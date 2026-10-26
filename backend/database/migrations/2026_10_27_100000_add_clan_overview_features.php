<?php

use App\Domains\Clan\Support\ClanLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Обзор «Моего клана»: объявление руководства и ежедневные отметки,
 * из которых растёт уровень клана.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->text('motd')->nullable();
            $table->timestamp('motd_updated_at')->nullable();
            $table->foreignId('motd_by')->nullable()->constrained('users')->nullOnDelete();

            // Уровень хранится, чтобы список кланов считал места одним запросом
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedSmallInteger('level')->default(1);
        });

        Schema::create('clan_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->timestamps();

            // Одна отметка в день на игрока, даже если он сменил клан:
            // иначе отметки можно было бы накручивать переходами
            $table->unique(['user_id', 'day']);
            $table->index(['clan_id', 'day']);
        });

        // Победы уже дают опыт: у старых кланов уровень сразу по ним
        foreach (DB::table('clans')->where('wins', '>', 0)->get(['id', 'wins']) as $clan) {
            $xp = (int) $clan->wins * ClanLevel::XP_PER_WIN;

            DB::table('clans')->where('id', $clan->id)->update([
                'xp' => $xp,
                'level' => ClanLevel::levelFor($xp),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_checkins');

        Schema::table('clans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('motd_by');
            $table->dropColumn(['motd', 'motd_updated_at', 'xp', 'level']);
        });
    }
};
