<?php

namespace App\Domains\Support\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Сообщение в обращении: от игрока или от персонала. */
class SupportMessage extends Model
{
    protected $fillable = ['ticket_id', 'user_id', 'is_staff', 'body'];

    protected $casts = [
        'is_staff' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
