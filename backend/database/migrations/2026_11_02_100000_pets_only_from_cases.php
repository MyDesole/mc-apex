<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Питомцы — только из сундука: убираем их из наборов, набор из одних
 * питомцев снимаем с продажи, а Призрака и Дракончика (раньше были
 * только в наборах) отдаём в сундуки.
 */
return new class extends Migration
{
    private const BUNDLES = [
        'bundle-dragon' => ['price' => 13000, 'description' => 'Огненные сообщения, огненный ник и след и титул Повелителя Незера.'],
        'bundle-phantom' => ['price' => 14000, 'description' => 'Ник-тень, титул Ночного охотника, звёздные сообщения и появление в дыму.'],
        'bundle-idol' => ['price' => 18000, 'description' => null],
    ];

    public function up(): void
    {
        DB::table('shop_items')->where('slug', 'bundle-best-friends')->update(['is_active' => false, 'updated_at' => now()]);

        foreach (self::BUNDLES as $slug => $changes) {
            $bundle = DB::table('shop_items')->where('slug', $slug)->first();
            if (! $bundle) {
                continue;
            }

            $metadata = json_decode((string) $bundle->metadata, true) ?: [];
            $metadata['items'] = array_values(array_filter($metadata['items'] ?? [], fn ($item) => ! str_starts_with($item, 'pet-')));

            DB::table('shop_items')->where('id', $bundle->id)->update(array_filter([
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'price' => $changes['price'],
                'description' => $changes['description'],
                'updated_at' => now(),
            ], fn ($value) => $value !== null));
        }

        DB::table('shop_items')->whereIn('slug', ['pet-ghost', 'pet-dragon'])->update(['is_active' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Данные витрины — назад не откатываем
    }
};
