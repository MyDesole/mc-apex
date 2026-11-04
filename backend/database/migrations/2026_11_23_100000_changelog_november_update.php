<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @return array<int, array{slug:string, title:string, category:string, body:string}> */
    private function entries(): array
    {
        return [
            [
                'slug' => 'pitomcy-2-0',
                'title' => 'Питомцы 2.0',
                'category' => 'feature',
                'body' => "Питомцы стали отдельной игрой: уровень до 100, подземелья по редкости, рейд «Разлом», кузница с рецептами, рынок игроков и дальние экспедиции. Появились новая валюта Рубли, карта-мир с постройками, Лавка, Академия с древом из 24 талантов, стихии ветра и земли, восемь новых питомцев и экипировка со свойствами и комплектами (438 вещей). Есть платная локация Цитадель.",
            ],
            [
                'slug' => 'zadaniya-i-zhetony-udachi',
                'title' => 'Задания дня и недели, Жетоны удачи',
                'category' => 'feature',
                'body' => 'Доска заданий с наградами за ежедневные и недельные цели, Жетоны удачи и новый экран «Награда» с фейерверком при получении призов.',
            ],
            [
                'slug' => 'peresdacha-bridzha',
                'title' => 'Пересдача бриджа',
                'category' => 'feature',
                'body' => 'Если вид бриджа уже подтверждён, на него можно подать новое видео. Прежняя оценка действует, пока тестер не решит: подтвердит новые оценки или оставит старые. Пересдача попадает в очередь проверки.',
            ],
            [
                'slug' => 'panel-servera',
                'title' => 'Панель сервера в админке',
                'category' => 'feature',
                'body' => 'Метрики игрового сервера (онлайн, TPS, память, диск), хвост логов и файловый менеджер с правкой конфигов, бэкапом перед сохранением и журналом действий.',
            ],
            [
                'slug' => 'sutki-po-moskve',
                'title' => 'Сутки по Москве',
                'category' => 'improvement',
                'body' => 'Суточные сбросы (ежедневный бонус, отметка клана, лимиты арены, подземелий, ферм и кейсов, задания шахты) теперь начинаются в полночь по Москве, а не в 03:00.',
            ],
            [
                'slug' => 'ispravleniya-pitomcev-i-aukciona',
                'title' => 'Исправления: питомцы, аукцион, главная',
                'category' => 'fix',
                'body' => 'Исправлены ошибки на главной странице и в арене питомцев, порядок в рейтинге «Восхождение к APEX», редкость скинов питомцев стала честнее, а питомцы выпадают только из кейсов.',
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->entries() as $i => $entry) {
            if (DB::table('changelog_entries')->where('slug', $entry['slug'])->exists()) {
                continue;
            }

            // Свежие записи выше: разводим время публикации на минуту
            $at = now()->subMinutes($i);

            DB::table('changelog_entries')->insert([
                'title' => $entry['title'],
                'slug' => $entry['slug'],
                'version' => null,
                'category' => $entry['category'],
                'body' => $entry['body'],
                'is_published' => true,
                'published_at' => $at,
                'author_id' => null,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('changelog_entries')
            ->whereIn('slug', array_column($this->entries(), 'slug'))
            ->delete();
    }
};
