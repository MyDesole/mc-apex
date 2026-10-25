<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * В уведомлениях об ачивках текст был «Вы получили ачивку orb «Свой»»:
 * иконки стали ключами пиксельных картинок вместо эмодзи. Убираем
 * лишнее слово из уже разосланных уведомлений.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('notifications')
            ->where('data', 'like', '%Вы получили ачивку%')
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $data = json_decode($row->data, true);

                    if (! is_array($data) || ! isset($data['message'])) {
                        continue;
                    }

                    $fixed = preg_replace('/^Вы получили ачивку [^«]+«/u', 'Вы получили ачивку «', $data['message']);

                    if ($fixed !== $data['message']) {
                        $data['message'] = $fixed;
                        DB::table('notifications')->where('id', $row->id)
                            ->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Исходный текст не восстанавливаем — он был ошибочным
    }
};
