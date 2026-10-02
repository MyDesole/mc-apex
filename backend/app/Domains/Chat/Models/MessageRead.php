<?php

namespace App\Domains\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Users\Models\User;

class MessageRead extends Model
{
    public $timestamps = false;

    protected $fillable = ['message_id', 'user_id', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
