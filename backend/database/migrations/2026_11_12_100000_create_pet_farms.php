<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Фермы питомцев (фаза A «Питомцы 2.0»): Общая ферма (растущие питомцы,
 * бронь и выкуп), Личная ферма (грядки и спаривание в Кладовке),
 * энергия питомцев. Ключи убранного «Сундука питомцев» уходят в ключи
 * Королевского сундука.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_pets', function (Blueprint $table) {
            $table->id();
            $table->string('tier', 12);
            $table->foreignId('shop_item_id')->constrained('shop_items')->cascadeOnDelete();
            $table->json('traits');
            $table->unsignedInteger('price');
            // growing — растёт или уже выросла (по ready_at), reserved — чья-то бронь, sold — выкуплена
            $table->string('status', 12)->default('growing');
            $table->timestamp('ready_at');
            $table->timestamp('expires_at');
            $table->unsignedBigInteger('reserved_by')->nullable();
            $table->timestamp('reserved_until')->nullable();
            $table->unsignedInteger('deposit')->default(0);
            $table->unsignedBigInteger('sold_to')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'ready_at']);
            $table->index(['reserved_by', 'status']);
            $table->index(['sold_to', 'sold_at']);
        });

        Schema::create('personal_farms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('plots')->default(3);
            $table->timestamps();
        });

        Schema::create('farm_plots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tier', 12);
            $table->foreignId('shop_item_id')->constrained('shop_items')->cascadeOnDelete();
            $table->json('traits');
            $table->timestamp('ready_at');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'claimed_at']);
        });

        Schema::table('pet_breedings', function (Blueprint $table) {
            $table->unsignedBigInteger('offspring_id')->nullable()->change();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->json('plan')->nullable();
            // mating — спаривание в Кладовке, cross — скрещивание (родители сгорают)
            $table->string('kind', 8)->default('mating');
        });

        Schema::table('user_inventory', function (Blueprint $table) {
            // Энергия для спаривания: значение на момент pet_energy_at, дальше растёт сама
            $table->unsignedTinyInteger('pet_energy')->default(100);
            $table->timestamp('pet_energy_at')->nullable();
        });

        // Старые записи скрещивания уже завершены
        DB::table('pet_breedings')->whereNull('claimed_at')->update(['claimed_at' => DB::raw('created_at')]);

        foreach (DB::table('loot_case_keys')->where('case_slug', 'pets')->get() as $row) {
            $royal = DB::table('loot_case_keys')->where('user_id', $row->user_id)->where('case_slug', 'royal')->first();

            if ($royal) {
                DB::table('loot_case_keys')->where('id', $royal->id)->update(['count' => $royal->count + $row->count, 'updated_at' => now()]);
            } else {
                DB::table('loot_case_keys')->where('id', $row->id)->update(['case_slug' => 'royal', 'updated_at' => now()]);
                continue;
            }

            DB::table('loot_case_keys')->where('id', $row->id)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('user_inventory', function (Blueprint $table) {
            $table->dropColumn(['pet_energy', 'pet_energy_at']);
        });

        Schema::table('pet_breedings', function (Blueprint $table) {
            $table->dropColumn(['ends_at', 'claimed_at', 'plan', 'kind']);
        });

        Schema::dropIfExists('farm_plots');
        Schema::dropIfExists('personal_farms');
        Schema::dropIfExists('farm_pets');
    }
};
