<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'hero' => SiteSetting::group('hero'),
            'socials' => SiteSetting::group('socials'),
            'footer' => SiteSetting::group('footer'),
            'stats' => SiteSetting::group('stats'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hero' => ['nullable', 'array'],
            'socials' => ['nullable', 'array'],
            'footer' => ['nullable', 'array'],
            'stats' => ['nullable', 'array'],
        ]);

        foreach ($validated as $group => $values) {
            foreach ($values as $key => $value) {
                // определяем тип
                $type = match (true) {
                    is_bool($value) => 'bool',
                    is_int($value) => 'int',
                    is_array($value) => 'json',
                    default => 'string',
                };

                SiteSetting::set("{$key}", $value, $type, $group);
            }
        }

        return response()->json(['ok' => true]);
    }
}
