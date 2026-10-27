<?php

namespace App\Domains\Players\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Запись в гостевой книге профиля.
 */
class GuestbookEntry extends Model
{
    protected $table = 'profile_guestbook_entries';

    protected $fillable = ['owner_id', 'author_id', 'body'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
