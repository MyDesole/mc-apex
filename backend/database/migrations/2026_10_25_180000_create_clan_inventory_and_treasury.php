<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Инвентарь и казна клана.
 *
 * Оформление подсветки раньше лежало в личном инвентаре лидера и терялось
 * при передаче лидерства. Теперь оно принадлежит клану, лидер выбирает,
 * что надеть. Казна — общие ApexCoin клана: участники скидываются,
 * лидер платит из неё за клановые предметы.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_item_id')->constrained('shop_items')->cascadeOnDelete();
            $table->foreignId('purchased_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('equipped_at')->nullable();
            $table->timestamps();

            $table->unique(['clan_id', 'shop_item_id']);
        });

        Schema::table('clans', function (Blueprint $table) {
            $table->unsignedBigInteger('treasury')->default(0)->after('power');
        });

        Schema::create('clan_treasury_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Положительная сумма — взнос, отрицательная — трата
            $table->integer('amount');
            $table->unsignedBigInteger('balance_after');
            $table->string('type', 24);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['clan_id', 'created_at']);
        });

        $this->moveStylesToClans();
    }

    /** Купленное оформление из личных инвентарей переносим в клан игрока. */
    private function moveStylesToClans(): void
    {
        $rows = DB::table('user_inventory')
            ->join('shop_items', 'shop_items.id', '=', 'user_inventory.shop_item_id')
            ->join('clan_members', 'clan_members.user_id', '=', 'user_inventory.user_id')
            ->where('shop_items.type', 'clan_highlight_style')
            ->get([
                'user_inventory.id', 'user_inventory.user_id', 'user_inventory.shop_item_id',
                'user_inventory.equipped_at', 'user_inventory.created_at', 'clan_members.clan_id',
            ]);

        foreach ($rows as $row) {
            DB::table('clan_inventory')->insertOrIgnore([
                'clan_id' => $row->clan_id,
                'shop_item_id' => $row->shop_item_id,
                'purchased_by' => $row->user_id,
                'equipped_at' => $row->equipped_at,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => now(),
            ]);

            DB::table('user_inventory')->where('id', $row->id)->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_treasury_transactions');

        Schema::table('clans', function (Blueprint $table) {
            $table->dropColumn('treasury');
        });

        Schema::dropIfExists('clan_inventory');
    }
};
