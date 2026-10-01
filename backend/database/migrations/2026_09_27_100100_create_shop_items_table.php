<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 128);
            $table->text('description')->nullable();

            // avatar_frame | profile_effect | accent_color | card_background | badge | tier_priority | coin_bundle
            $table->string('type', 32);
            $table->string('rarity', 16)->default('common');

            // Значение, которое проставляется в поле профиля при надевании:
            // id рамки ('legendary'), hex ('#7c3aed') или путь к картинке.
            $table->string('effect_value', 128)->nullable();

            $table->unsignedInteger('price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            // Расходники: сколько применений даёт покупка и можно ли покупать повторно
            $table->boolean('is_consumable')->default(false);
            $table->boolean('is_repeatable')->default(false);
            $table->unsignedInteger('max_quantity')->nullable();

            // Произвольные данные: бейдж (icon/color), набор монет (amount) и т.п.
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_items');
    }
};
