<?php

namespace Database\Seeders;

use App\Models\ForumCategory;
use Illuminate\Database\Seeder;

/**
 * Разделы форума по умолчанию.
 * Сидер идемпотентный: повторный запуск обновляет описания, но не плодит дубли.
 */
class ForumCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug' => 'announcements',
                'name' => 'Объявления',
                'description' => 'Новости платформы, обновления и важные анонсы от администрации.',
                'icon' => 'send',
                'color' => '#facc15',
                'sort_order' => 10,
                'post_policy' => 'staff',
            ],
            [
                'slug' => 'general',
                'name' => 'Общее',
                'description' => 'Свободное общение на любые темы, не связанные с остальными разделами.',
                'icon' => 'globe',
                'color' => '#8b5cf6',
                'sort_order' => 20,
                'post_policy' => 'all',
            ],
            [
                'slug' => 'tier-tests',
                'name' => 'Тир-тесты',
                'description' => 'Обсуждение результатов тестов, аспектов и очереди на проверку.',
                'icon' => 'target',
                'color' => '#06b6d4',
                'sort_order' => 30,
                'post_policy' => 'all',
            ],
            [
                'slug' => 'clans',
                'name' => 'Кланы',
                'description' => 'Поиск клана или набора в состав, войны и внутренние дела.',
                'icon' => 'shield',
                'color' => '#22c55e',
                'sort_order' => 40,
                'post_policy' => 'all',
            ],
            [
                'slug' => 'tournaments',
                'name' => 'Турниры',
                'description' => 'Анонсы турниров, поиск напарников и разбор сеток.',
                'icon' => 'trophy',
                'color' => '#f97316',
                'sort_order' => 50,
                'post_policy' => 'all',
            ],
            [
                'slug' => 'guides',
                'name' => 'Гайды',
                'description' => 'Полезные материалы: настройки, тактики, разборы механик.',
                'icon' => 'doc',
                'color' => '#a855f7',
                'sort_order' => 60,
                'post_policy' => 'verified',
            ],
            [
                'slug' => 'bugs',
                'name' => 'Баги и идеи',
                'description' => 'Сообщения об ошибках, предложения по улучшению сайта.',
                'icon' => 'bolt',
                'color' => '#ef4444',
                'sort_order' => 70,
                'post_policy' => 'all',
            ],
        ];

        foreach ($categories as $category) {
            ForumCategory::updateOrCreate(
                ['slug' => $category['slug']],
                array_merge(['is_active' => true], $category)
            );
        }
    }
}
