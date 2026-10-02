<?php

namespace App\Domains\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Users\Models\User;

class ConversationParticipant extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'last_read_at',
        'conversation_id'
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
