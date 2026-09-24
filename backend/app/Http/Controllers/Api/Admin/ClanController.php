<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClanController extends Controller
{
    public function ban(Request $request, Clan $clan): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $clan->update([
            'is_banned' => true,
            'ban_reason' => $validated['reason'] ?? 'Нарушение правил',
        ]);

        return response()->json(['clan' => $clan->fresh()]);
    }

    public function unban(Request $request, Clan $clan): JsonResponse
    {
        $clan->update([
            'is_banned' => false,
            'ban_reason' => null,
        ]);

        return response()->json(['clan' => $clan->fresh()]);
    }

    public function removeAvatar(Request $request, Clan $clan): JsonResponse
    {
        if ($clan->avatar) {
            Storage::disk('public')->delete($clan->avatar);
            $clan->update(['avatar' => null]);
        }

        return response()->json(['clan' => $clan->fresh()]);
    }

    public function removeCover(Request $request, Clan $clan): JsonResponse
    {
        if ($clan->cover_path) {
            Storage::disk('public')->delete($clan->cover_path);
            $clan->update(['cover_path' => null]);
        }

        return response()->json(['clan' => $clan->fresh()]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Clan::query()
            ->with('leader:id,username,avatar')
            ->withCount('members');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('tag', 'like', "%{$search}%");
            });
        }

        if ($request->query('banned') === '1') {
            $query->where('is_banned', true);
        }

        $clans = $query->orderByDesc('created_at')->paginate(30);

        return response()->json($clans);
    }

    public function destroy(Request $request, Clan $clan): JsonResponse
    {
        $clan->delete();

        return response()->json(['ok' => true]);
    }
}
