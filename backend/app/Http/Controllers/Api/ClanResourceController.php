<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClanResourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clan = $request->attributes->get('clan');

        $query = ClanResource::where('clan_id', $clan->id)
            ->with('author:id,username,avatar');

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        $resources = $query->latest()->paginate(20);

        return response()->json($resources);
    }

    public function store(Request $request): JsonResponse
    {
        $clan = $request->attributes->get('clan');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', 'in:resource_pack,screenshot,config,guide,other'],
            'file' => ['required', 'file', 'max:51200'], // 50 MB
            'is_public' => ['boolean'],
        ]);

        $file = $request->file('file');
        $path = $file->store("clans/{$clan->id}/resources", 'public');

        $resource = ClanResource::create([
            'clan_id' => $clan->id,
            'author_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'category' => $validated['category'],
            'is_public' => $validated['is_public'] ?? false,
        ]);

        return response()->json(['resource' => $resource->load('author:id,username,avatar')], 201);
    }

    public function download(Request $request, ClanResource $resource): JsonResponse
    {
        $clan = $request->attributes->get('clan');
        abort_if($resource->clan_id !== $clan->id, 404);

        $resource->increment('downloads');

        return response()->json(['url' => $resource->file_url]);
    }

    public function destroy(Request $request, ClanResource $resource): JsonResponse
    {
        $clan = $request->attributes->get('clan');
        $user = $request->user();
        $membership = $request->attributes->get('clan_membership');

        abort_if($resource->clan_id !== $clan->id, 404);

        $canDelete = $resource->author_id === $user->id || $membership->role === 'leader';
        abort_unless($canDelete, 403);

        Storage::disk('public')->delete($resource->file_path);
        $resource->delete();

        return response()->json(['ok' => true]);
    }
}
