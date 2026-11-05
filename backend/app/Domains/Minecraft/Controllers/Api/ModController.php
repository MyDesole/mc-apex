<?php

namespace App\Domains\Minecraft\Controllers\Api;

use App\Domains\Minecraft\Services\ModPlayerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Публичный API клиентского мода: без авторизации, только публичные данные. */
class ModController extends Controller
{
    public function __construct(private readonly ModPlayerService $players)
    {
    }

    public function player(string $query): JsonResponse
    {
        $query = trim($query);

        if ($query === '' || mb_strlen($query) > 32) {
            return response()->json(['message' => 'Игрок не найден'], 404);
        }

        $data = $this->players->player($query);

        return $data
            ? response()->json(['player' => $data])
            : response()->json(['message' => 'Игрок не найден'], 404);
    }

    public function tiers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nicks' => ['required', 'array', 'min:1', 'max:60'],
            'nicks.*' => ['string', 'max:32'],
        ]);

        $nicks = collect($data['nicks'])->map(fn (string $n) => trim($n))->filter()->unique()->values()->all();

        return response()->json(['players' => $this->players->tiers($nicks)]);
    }
}
