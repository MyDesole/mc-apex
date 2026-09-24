<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Сохраняем старые данные (если есть)
        $old = DB::table('player_aspects')->get();

        Schema::dropIfExists('player_aspects');

        // === PvP ===
        Schema::create('player_aspects_pvp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('block_placing')->default(0);
            $table->unsignedTinyInteger('rotka')->default(0);
            $table->unsignedTinyInteger('movement')->default(0);
            $table->unsignedTinyInteger('aim')->default(0);
            $table->unsignedTinyInteger('game_sense')->default(0);
            $table->timestamps();

            $table->unique('user_id');
        });

        // === BedWars ===
        Schema::create('player_aspects_bedwars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('pvp')->default(0);
            $table->unsignedTinyInteger('game_sense')->default(0);
            $table->unsignedTinyInteger('bed_play')->default(0);
            $table->unsignedTinyInteger('teamplay')->default(0);
            $table->unsignedTinyInteger('building')->default(0);
            $table->timestamps();

            $table->unique('user_id');
        });

        // Мигрируем старые данные
        foreach ($old as $row) {
            if ($row->mode === 'pvp') {
                DB::table('player_aspects_pvp')->insert([
                    'user_id' => $row->user_id,
                    'block_placing' => $row->block_placing ?? 0,
                    'rotka' => $row->rotka ?? 0,
                    'movement' => $row->movement ?? 0,
                    'aim' => $row->ppl ?? 0,            // старое ppl → aim
                    'game_sense' => 0,                    // новый аспект
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('player_aspects_bedwars')->insert([
                    'user_id' => $row->user_id,
                    'pvp' => $row->block_placing ?? 0,    // старое бп → pvp
                    'game_sense' => 0,
                    'bed_play' => 0,
                    'teamplay' => 0,
                    'building' => $row->building ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('player_aspects_pvp');
        Schema::dropIfExists('player_aspects_bedwars');
    }
};
