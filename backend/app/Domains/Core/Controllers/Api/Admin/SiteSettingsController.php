<?php

namespace App\Domains\Core\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Domains\Core\Requests\Admin\UpdateSiteSettingsRequest;
use App\Domains\Core\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

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

    public function update(UpdateSiteSettingsRequest $request): JsonResponse
    {
        foreach ($request->validated() as $group => $values) {
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
