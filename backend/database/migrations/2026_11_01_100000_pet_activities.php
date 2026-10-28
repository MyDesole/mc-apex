<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Жизнь питомца: экспедиции, арена, трюки, питомцедекс.
 *
 * Опыт сверх ухода (экспедиции, бои), рейтинг арены и выбранный трюк
 * хранятся в строке инвентаря — уходят вместе с питомцем при продаже.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_inventory', function (Blueprint $table) {
            $table->unsignedInteger('pet_xp')->default(0);
            $table->unsignedInteger('pet_rating')->default(1000);
            $table->unsignedInteger('pet_wins')->default(0);
            $table->unsignedInteger('pet_losses')->default(0);
            $table->string('pet_trick', 24)->nullable();
        });

        Schema::create('pet_expeditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_id')->constrained('user_inventory')->cascadeOnDelete();
            $table->string('zone', 24);
            $table->timestamp('ends_at');
            $table->json('reward')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'claimed_at']);
            $table->index(['inventory_id', 'claimed_at']);
        });

        Schema::create('pet_battles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_id')->constrained('user_inventory')->cascadeOnDelete();
            $table->foreignId('opponent_inventory_id')->constrained('user_inventory')->cascadeOnDelete();
            // active | won | lost
            $table->string('status', 12)->default('active');
            $table->json('state');
            $table->integer('rating_change')->default(0);
            $table->unsignedInteger('coins')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        // Питомцедекс: что игрок когда-либо получал — вид и каждая черта
        Schema::create('pet_dex', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // species | model | backdrop | accessory
            $table->string('kind', 12);
            $table->string('key', 24);
            $table->timestamp('created_at')->nullable();

            $table->primary(['user_id', 'kind', 'key']);
        });

        Schema::create('pet_dex_rewards', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('milestone', 32);
            $table->timestamp('created_at')->nullable();

            $table->primary(['user_id', 'milestone']);
        });

        // Питомцедекс заполняем тем, что у игроков есть сейчас
        $rows = DB::table('user_inventory as ui')
            ->join('shop_items as si', 'si.id', '=', 'ui.shop_item_id')
            ->where('si.type', 'profile_pet')
            ->whereNotNull('ui.pet_traits')
            ->get(['ui.user_id', 'si.effect_value', 'ui.pet_traits']);

        $entries = [];
        foreach ($rows as $row) {
            $traits = json_decode((string) $row->pet_traits, true) ?: [];
            $entries["{$row->user_id}:species:{$row->effect_value}"] = ['user_id' => $row->user_id, 'kind' => 'species', 'key' => $row->effect_value, 'created_at' => now()];
            foreach (['model', 'backdrop', 'accessory'] as $kind) {
                if (! empty($traits[$kind])) {
                    $entries["{$row->user_id}:{$kind}:{$traits[$kind]}"] = ['user_id' => $row->user_id, 'kind' => $kind, 'key' => $traits[$kind], 'created_at' => now()];
                }
            }
        }

        foreach (array_chunk(array_values($entries), 500) as $chunk) {
            DB::table('pet_dex')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_dex_rewards');
        Schema::dropIfExists('pet_dex');
        Schema::dropIfExists('pet_battles');
        Schema::dropIfExists('pet_expeditions');

        Schema::table('user_inventory', function (Blueprint $table) {
            $table->dropColumn(['pet_xp', 'pet_rating', 'pet_wins', 'pet_losses', 'pet_trick']);
        });
    }
};
