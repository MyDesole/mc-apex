<?php

namespace App\Domains\Clan\Services;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanResource;
use App\Domains\Users\Models\User;
use App\Support\Concerns\StoresUserFiles;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

/**
 * Ресурсы клана: файлы, скриншоты, конфиги, гайды.
 */
class ClanResourceService
{
    use StoresUserFiles;

    private const PER_PAGE = 20;

    public function list(Clan $clan, ?string $category): LengthAwarePaginator
    {
        return ClanResource::where('clan_id', $clan->id)
            ->with('author:id,username,avatar')
            ->when($category, fn ($q) => $q->where('category', $category))
            ->latest()
            ->paginate(self::PER_PAGE);
    }

    public function create(
        Clan $clan,
        User $author,
        array $data,
        UploadedFile $file,
    ): ClanResource {
        $path = $file->store("clans/{$clan->id}/resources", 'public');

        return ClanResource::create([
            'clan_id' => $clan->id,
            'author_id' => $author->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'category' => $data['category'],
            'is_public' => $data['is_public'] ?? false,
        ])->load('author:id,username,avatar');
    }

    /**
     * Ссылка на скачивание. Счётчик скачиваний увеличивается.
     */
    public function download(ClanResource $resource): string
    {
        $resource->increment('downloads');

        return $resource->file_url;
    }

    public function delete(ClanResource $resource): void
    {
        $this->deleteStoredFile($resource->file_path);

        $resource->delete();
    }

    /**
     * Ресурс обязан принадлежать клану из контекста запроса.
     */
    public function assertBelongsToClan(ClanResource $resource, Clan $clan): void
    {
        abort_if($resource->clan_id !== $clan->id, 404);
    }
}
