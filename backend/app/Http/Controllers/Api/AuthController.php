<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:32',
                'regex:/^[a-zA-Z0-9_]+$/',
                'unique:users,username',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $user = User::create([
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        Auth::login($user);

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Регистрация выполнена успешно.',
            'user' => $user,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => [
                'required',
                'string',
            ],
            'password' => [
                'required',
                'string',
            ],
            'remember' => [
                'boolean',
            ],
        ]);

        $field = filter_var($validated['login'], FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'username';

        $credentials = [
            $field => $validated['login'],
            'password' => $validated['password'],
        ];

        if (!Auth::attempt(
            $credentials,
            $validated['remember'] ?? false
        )) {
            throw ValidationException::withMessages([
                'login' => ['Неверный логин или пароль.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Вход выполнен успешно.',
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Вы вышли из аккаунта.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'aspects',
            'clanMember.clan' => fn ($q) => $q->withCount('members'),
        ]);

        return response()->json([
            'user' => $user,
            'rank' => $this->getUserRank($user),
        ]);
    }

    /**
     * Место в общем топе по tier_score.
     */
    private function getUserRank(\App\Models\User $user): array
    {
        if ($user->tier_score <= 0) {
            return ['position' => null, 'total' => 0];
        }

        $position = \App\Models\User::where('tier_score', '>', $user->tier_score)->count() + 1;

        $total = \App\Models\User::where('tier_score', '>', 0)->count();

        return [
            'position' => $position,
            'total' => $total,
        ];
    }
}
