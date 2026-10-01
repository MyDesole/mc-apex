<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\ClanResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ClanFixtures;
use Tests\TestCase;

/**
 * Api\ClanResourceController: права, валидация и 404 для файлов клана.
 *
 * Дополняет ClanResourceTest.php расширенными проверками прав
 * (офицер/участник), фильтра по категории, изоляции кланов и гостевого доступа.
 */
class ClanResourceAccessTest extends TestCase
{
    use ClanFixtures;
    use RefreshDatabase;

    private function resource(Clan $clan, User $author, array $attributes = []): ClanResource
    {
        return ClanResource::create(array_merge([
            'clan_id' => $clan->id,
            'author_id' => $author->id,
            'title' => 'Материал',
            'file_path' => "clans/{$clan->id}/resources/file.zip",
            'file_name' => 'file.zip',
            'file_size' => 2048,
            'mime_type' => 'application/zip',
            'category' => 'other',
        ], $attributes));
    }

    // ------------------------------------------------------------------ index

    public function test_index_lists_only_own_clan_resources(): void
    {
        Storage::fake('public');

        $leader = $this->user();
        $clan = $this->clan($leader);
        $guide = $this->resource($clan, $leader, ['title' => 'Гайд', 'category' => 'guide']);

        $foreignClan = $this->clan($this->user());
        $this->resource($foreignClan, User::find($foreignClan->leader_id), ['title' => 'Чужой файл']);

        $response = $this->actingAs($leader)->getJson('/api/my-clan/resources')->assertOk();

        $this->assertSame(1, $response->json('total'));
        $this->assertSame($guide->id, $response->json('data.0.id'));
        $this->assertSame($leader->username, $response->json('data.0.author.username'));
        $this->assertSame('guide', $response->json('data.0.category'));
        $this->assertArrayHasKey('file_url', $response->json('data.0'));
        $this->assertArrayHasKey('file_size_human', $response->json('data.0'));
    }

    public function test_index_filters_by_category(): void
    {
        Storage::fake('public');

        $leader = $this->user();
        $clan = $this->clan($leader);

        $guide = $this->resource($clan, $leader, ['category' => 'guide']);
        $this->resource($clan, $leader, ['category' => 'config']);

        $filtered = $this->actingAs($leader)->getJson('/api/my-clan/resources?category=guide')->assertOk();

        $this->assertSame(1, $filtered->json('total'));
        $this->assertSame($guide->id, $filtered->json('data.0.id'));
    }

    public function test_index_hidden_from_users_without_clan(): void
    {
        $this->getJson('/api/my-clan/resources')->assertUnauthorized();

        $this->actingAs($this->user())->getJson('/api/my-clan/resources')
            ->assertForbidden()
            ->assertJsonPath('message', 'Вы не в клане.');
    }

    // ------------------------------------------------------------------ store

    public function test_leader_can_upload_resource(): void
    {
        Storage::fake('public');

        $leader = $this->user();
        $clan = $this->clan($leader);

        $response = $this->actingAs($leader)->post('/api/my-clan/resources', [
            'title' => 'Ресурспак клана',
            'description' => 'Сборка для войн',
            'category' => 'resource_pack',
            'is_public' => true,
            'file' => UploadedFile::fake()->create('apex.zip', 100, 'application/zip'),
        ])->assertCreated();

        $response->assertJsonPath('resource.title', 'Ресурспак клана')
            ->assertJsonPath('resource.description', 'Сборка для войн')
            ->assertJsonPath('resource.category', 'resource_pack')
            ->assertJsonPath('resource.is_public', true)
            ->assertJsonPath('resource.clan_id', $clan->id)
            ->assertJsonPath('resource.author_id', $leader->id)
            ->assertJsonPath('resource.file_name', 'apex.zip');

        $path = $response->json('resource.file_path');
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString("clans/{$clan->id}/resources", $path);

        $this->assertDatabaseHas('clan_resources', [
            'clan_id' => $clan->id,
            'author_id' => $leader->id,
            'title' => 'Ресурспак клана',
            'file_name' => 'apex.zip',
            'category' => 'resource_pack',
            'is_public' => 1,
        ]);
    }

    public function test_officer_can_upload_resource(): void
    {
        Storage::fake('public');

        $clan = $this->clan($this->user());
        $officer = $this->user();
        $this->member($clan, $officer, 'officer');

        $this->actingAs($officer)->post('/api/my-clan/resources', [
            'title' => 'Конфиг',
            'category' => 'config',
            'file' => UploadedFile::fake()->create('config.cfg', 10, 'text/plain'),
        ])->assertCreated();

        $this->assertDatabaseCount('clan_resources', 1);
        $this->assertDatabaseHas('clan_resources', ['is_public' => 0]);
    }

    public function test_plain_member_cannot_upload_resource(): void
    {
        Storage::fake('public');

        $clan = $this->clan($this->user());
        $member = $this->user();
        $this->member($clan, $member);

        $this->actingAs($member)->post('/api/my-clan/resources', [
            'title' => 'Нельзя',
            'category' => 'other',
            'file' => UploadedFile::fake()->create('x.zip', 10),
        ])->assertForbidden();

        $this->assertDatabaseCount('clan_resources', 0);
    }

    public function test_resource_store_validates_payload(): void
    {
        Storage::fake('public');

        $leader = $this->user();
        $this->clan($leader);

        $this->actingAs($leader)->postJson('/api/my-clan/resources', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'category', 'file']);

        $this->actingAs($leader)->post('/api/my-clan/resources', [
            'title' => 'Плохая категория',
            'category' => 'nope',
            'file' => UploadedFile::fake()->create('x.zip', 10),
        ])->assertStatus(422)->assertJsonValidationErrors('category');

        $this->actingAs($leader)->postJson('/api/my-clan/resources', [
            'title' => str_repeat('t', 121),
            'category' => 'other',
        ])->assertStatus(422)->assertJsonValidationErrors('title');

        $this->assertDatabaseCount('clan_resources', 0);
    }

    // --------------------------------------------------------------- download

    public function test_download_increments_counter_and_returns_url(): void
    {
        Storage::fake('public');

        $leader = $this->user();
        $clan = $this->clan($leader);
        $resource = $this->resource($clan, $leader);

        $response = $this->actingAs($leader)
            ->postJson("/api/my-clan/resources/{$resource->id}/download")
            ->assertOk();

        $this->assertStringContainsString($resource->file_path, $response->json('url'));
        $this->assertSame(1, $resource->fresh()->downloads);

        $this->actingAs($leader)->postJson("/api/my-clan/resources/{$resource->id}/download")->assertOk();
        $this->assertSame(2, $resource->fresh()->downloads);
    }

    public function test_download_returns_404_for_foreign_resource(): void
    {
        Storage::fake('public');

        $foreignClan = $this->clan($this->user());
        $foreign = $this->resource($foreignClan, User::find($foreignClan->leader_id));

        $leader = $this->user();
        $this->clan($leader);

        $this->actingAs($leader)
            ->postJson("/api/my-clan/resources/{$foreign->id}/download")
            ->assertNotFound();

        $this->assertSame(0, $foreign->fresh()->downloads);
    }

    public function test_download_unknown_resource_returns_404(): void
    {
        Storage::fake('public');

        $leader = $this->user();
        $this->clan($leader);

        $this->actingAs($leader)->postJson('/api/my-clan/resources/999999/download')->assertNotFound();
    }

    // ---------------------------------------------------------------- destroy

    public function test_author_can_delete_own_resource(): void
    {
        Storage::fake('public');

        $clan = $this->clan($this->user());
        $author = $this->user();
        $this->member($clan, $author);
        $resource = $this->resource($clan, $author);
        Storage::disk('public')->put($resource->file_path, 'data');

        $this->actingAs($author)->deleteJson("/api/my-clan/resources/{$resource->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('clan_resources', ['id' => $resource->id]);
        Storage::disk('public')->assertMissing($resource->file_path);
    }

    public function test_leader_can_delete_foreign_resource(): void
    {
        Storage::fake('public');

        $leader = $this->user();
        $clan = $this->clan($leader);
        $member = $this->user();
        $this->member($clan, $member);
        $resource = $this->resource($clan, $member);

        $this->actingAs($leader)->deleteJson("/api/my-clan/resources/{$resource->id}")->assertOk();

        $this->assertDatabaseMissing('clan_resources', ['id' => $resource->id]);
    }

    /**
     * Текущее поведение: право resources даёт офицеру загрузку, но НЕ удаление —
     * destroy() разрешает только автору и лидеру.
     */
    public function test_officer_cannot_delete_foreign_resource(): void
    {
        Storage::fake('public');

        $clan = $this->clan($this->user());
        $officer = $this->user();
        $this->member($clan, $officer, 'officer');
        $resource = $this->resource($clan, $officer, ['author_id' => $clan->leader_id]);

        $this->actingAs($officer)->deleteJson("/api/my-clan/resources/{$resource->id}")->assertForbidden();

        $this->assertDatabaseHas('clan_resources', ['id' => $resource->id]);
    }

    public function test_destroy_returns_404_for_foreign_resource(): void
    {
        Storage::fake('public');

        $foreignClan = $this->clan($this->user());
        $foreign = $this->resource($foreignClan, User::find($foreignClan->leader_id));

        $leader = $this->user();
        $this->clan($leader);

        $this->actingAs($leader)->deleteJson("/api/my-clan/resources/{$foreign->id}")->assertNotFound();

        $this->assertDatabaseHas('clan_resources', ['id' => $foreign->id]);
    }

    // ------------------------------------------------------------ guest access

    public function test_guest_cannot_access_clan_resources(): void
    {
        Storage::fake('public');

        $leader = $this->user();
        $clan = $this->clan($leader);
        $resource = $this->resource($clan, $leader);

        $this->getJson('/api/my-clan/resources')->assertUnauthorized();
        $this->postJson('/api/my-clan/resources', [])->assertUnauthorized();
        $this->postJson("/api/my-clan/resources/{$resource->id}/download")->assertUnauthorized();
        $this->deleteJson("/api/my-clan/resources/{$resource->id}")->assertUnauthorized();
    }
}
