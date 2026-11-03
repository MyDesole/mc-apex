<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'igrovoy-server-i-sayt';

    public function up(): void
    {
        if (DB::table('changelog_entries')->where('slug', self::SLUG)->exists()) {
            return;
        }

        DB::table('changelog_entries')->insert([
            'title' => 'Игровой сервер и сайт заодно',
            'slug' => self::SLUG,
            'version' => null,
            'category' => 'feature',
            'body' => 'Тир-тесты на дуэлях, клановые войны и BedWars, история дуэлей со статистикой по режимам и магазин лобби (полёт и префиксы) теперь связаны с сайтом.',
            'is_published' => true,
            'published_at' => now(),
            'author_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('changelog_entries')->where('slug', self::SLUG)->delete();
    }
};
