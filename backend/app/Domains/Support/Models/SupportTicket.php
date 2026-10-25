<?php

namespace App\Domains\Support\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Обращение в техподдержку.
 *
 * open — ждёт ответа персонала, answered — персонал ответил и ждёт игрока,
 * closed — вопрос решён (игрок может написать снова, и тикет откроется).
 */
class SupportTicket extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_CLOSED = 'closed';

    public const CATEGORIES = [
        'account' => 'Аккаунт и вход',
        'tier_test' => 'Тир-тесты',
        'shop' => 'Магазин и ApexCoin',
        'clan' => 'Кланы',
        'bug' => 'Ошибка на сайте',
        'other' => 'Другое',
    ];

    protected $fillable = ['user_id', 'subject', 'category', 'status', 'assigned_to', 'last_message_at'];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id');
    }
}
