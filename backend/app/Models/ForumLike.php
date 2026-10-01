<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumLike extends Model
{
    protected $fillable = ['user_id', 'likeable_type', 'likeable_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Поставить или снять лайк. Возвращает новое состояние и счётчик.
     *
     * @return array{liked: bool, count: int}
     */
    public static function toggle(User $user, string $type, int $id, Model $likeable): array
    {
        $existing = static::where('user_id', $user->id)
            ->where('likeable_type', $type)
            ->where('likeable_id', $id)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
            $likeable->decrement('likes_count');
        } else {
            static::create([
                'user_id' => $user->id,
                'likeable_type' => $type,
                'likeable_id' => $id,
            ]);
            $liked = true;
            $likeable->increment('likes_count');
        }

        return [
            'liked' => $liked,
            'count' => max(0, (int) $likeable->fresh()->likes_count),
        ];
    }
}
