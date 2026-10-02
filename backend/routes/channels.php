<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Канал присутствия: кто сейчас на сайте.
 *
 * Возвращаемые данные попадают в список участников у всех подписчиков.
 * Отметки о входе и выходе делает приложение, поэтому канал только
 * подтверждает личность.
 */
Broadcast::channel('online', function ($user) {
    return [
        'id' => (int) $user->id,
        'username' => $user->username,
    ];
});
