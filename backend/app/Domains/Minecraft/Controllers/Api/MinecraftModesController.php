<?php

namespace App\Domains\Minecraft\Controllers\Api;

use App\Domains\Minecraft\Services\MinecraftModesService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MinecraftModesController extends Controller
{
    public function __construct(private readonly MinecraftModesService $modes) {}

    public function store(Request $request): JsonResponse
    {
        $rules = [
            'captured_at' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(5)->toIso8601String()],
            'bedwars' => ['required', 'array:configured,players,arenas,online'],
            'bedwars.configured' => ['required', 'boolean'],
            'bedwars.online' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'bedwars.players' => ['present', 'array', 'max:10000'],
            'bedwars.players.*' => ['array:uuid,name,wins,losses,games,kills,final_kills,deaths,final_deaths,beds'],
            'bedwars.players.*.uuid' => ['required', 'uuid'],
            'bedwars.players.*.name' => ['required', 'regex:/^[A-Za-z0-9_]{3,16}$/'],
            'bedwars.arenas' => ['present', 'array', 'max:128'],
            'bedwars.arenas.*' => ['array:id,name,teams,team_size,players,max_players,state'],
            'bedwars.arenas.*.id' => ['required', 'string', 'max:64'],
            'bedwars.arenas.*.name' => ['required', 'string', 'max:100'],
            'bedwars.arenas.*.state' => ['required', Rule::in(['waiting', 'starting', 'playing', 'restarting'])],
            'fastbridge' => ['required', 'array:configured,online,tables,history'],
            'fastbridge.configured' => ['required', 'boolean'],
            'fastbridge.online' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'fastbridge.tables' => ['present', 'array:current,islands,classic'],
            'fastbridge.tables.*' => ['array', 'max:20000'],
            'fastbridge.tables.*.*' => ['array:uuid,name,map,best,attempts24,finishes24'],
            'fastbridge.tables.*.*.uuid' => ['required', 'uuid'],
            'fastbridge.tables.*.*.name' => ['required', 'regex:/^[A-Za-z0-9_]{3,16}$/'],
            'fastbridge.tables.*.*.map' => ['required', Rule::in(['16', '24', '32', '64', 'inf'])],
            'fastbridge.tables.*.*.best' => ['required', 'integer', 'min:-1', 'max:1000000000000'],
            'fastbridge.history' => ['present', 'array', 'max:20000'],
            'fastbridge.history.*' => ['array:uuid,map,public,at,outcome,elapsed,value,blocks,distance,left_cps,right_cps'],
            'fastbridge.history.*.uuid' => ['required', 'uuid'],
            'fastbridge.history.*.map' => ['required', Rule::in(['16', '24', '32', '64', 'inf'])],
            'fastbridge.history.*.public' => ['required', 'accepted'],
            'fastbridge.history.*.at' => ['required', 'integer', 'min:0', 'max:9999999999999'],
            'fastbridge.history.*.outcome' => ['required', 'string', 'max:100'],
            'fastbridge.history.*.elapsed' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'fastbridge.history.*.value' => ['nullable', 'integer', 'min:-1', 'max:1000000000000'],
        ];
        foreach (['wins', 'losses', 'games', 'kills', 'final_kills', 'deaths', 'final_deaths', 'beds'] as $key) {
            $rules['bedwars.players.*.'.$key] = ['required', 'integer', 'min:0', 'max:2147483647'];
        }
        foreach (['teams', 'team_size', 'players', 'max_players'] as $key) {
            $rules['bedwars.arenas.*.'.$key] = ['required', 'integer', 'min:0', 'max:100000'];
        }
        foreach (['attempts24', 'finishes24'] as $key) {
            $rules['fastbridge.tables.*.*.'.$key] = ['required', 'integer', 'min:0', 'max:1000000000'];
        }
        foreach (['blocks', 'distance', 'left_cps', 'right_cps'] as $key) {
            $rules['fastbridge.history.*.'.$key] = ['nullable', 'numeric', 'min:0', 'max:1000000000'];
        }
        $this->modes->store($request->validate($rules));

        return response()->json(['ok' => true]);
    }

    public function show(Request $request, string $mode): JsonResponse
    {
        $filters = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'metric' => ['nullable', Rule::in(['wins', 'final_kills', 'beds'])],
            'distance' => ['nullable', Rule::in(['16', '24', '32', '64', 'inf'])],
            'table' => ['nullable', Rule::in(['current', 'islands', 'classic'])],
            'user' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($this->modes->read($mode, $filters, $request->user()));
    }
}
