<?php

use App\Domains\Bridge\Models\BridgeVideoUpload;

/**
 * Настройки загрузки видео бриджа.
 *
 * Размер части ограничен сразу двумя местами: php-fpm
 * (upload_max_filesize, post_max_size) и nginx (client_max_body_size).
 * Если на сервере лимиты меньше, размер части можно уменьшить через
 * BRIDGE_CHUNK_SIZE, не меняя код.
 */
return [
    // Размер одной части при чанковой загрузке
    'chunk_size' => (int) env('BRIDGE_CHUNK_SIZE', BridgeVideoUpload::CHUNK_SIZE),

    // Наибольший размер ролика
    'max_video_size' => (int) env('BRIDGE_MAX_VIDEO_SIZE', BridgeVideoUpload::MAX_SIZE),

    // Сколько живёт подписанная ссылка на видео
    'video_url_ttl_hours' => (int) env('BRIDGE_VIDEO_TTL_HOURS', 3),
];
