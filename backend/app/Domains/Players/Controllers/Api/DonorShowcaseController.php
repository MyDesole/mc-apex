<?php

namespace App\Domains\Players\Controllers\Api;

use App\Domains\Players\Services\DonorShowcaseService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DonorShowcaseController extends Controller
{
    public function __construct(private readonly DonorShowcaseService $showcase) {}

    public function show(Request $request, User $user): JsonResponse
    {
        return response()->json($this->showcase->show($user, $request->user('sanctum')));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'mode' => ['required', Rule::in(DonorShowcaseService::MODES)],
            'theme' => ['required', Rule::in(DonorShowcaseService::THEMES)],
            'title' => ['nullable', 'string', 'max:48'],
            'duel_id' => ['nullable', 'integer', 'min:1'],
            'achievement_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($this->showcase->save($request->user(), $data));
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->forceFill(['donor_showcase' => null])->save();

        return response()->json($this->showcase->show($request->user(), $request->user()));
    }
}
