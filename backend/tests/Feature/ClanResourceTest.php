<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Clan\Models\ClanResource;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Страховочная сетка для ресурсов клана и публичных мелких эндпоинтов.
 */
class ClanResourceTest extends TestCase
{
    use RefreshDatabase;

    private function clanWithLeader(): array
    {
        $leader = User::factory()->create();

        $clan = Clan::create([
            'name' => 'Resource Clan',
            'tag' => 'RES',
            'leader_id' => $leader->id,
        ]);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $leader->id,
            'role' => 'leader',
        ]);

        return [$clan, $leader];
    }

    private function member(Clan $clan, string $role = 'member'): User
    {
        $user = User::factory()->create();

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $user;
    }

    public function test_resources_list_requires_clan_context(): void
    {
        [$clan, $leader] = $this->clanWithLeader();

        ClanResource::create([
            'clan_id' => $clan->id,
            'author_id' => $leader->id,
            'title' => 'Пак текстур',
            'file_path' => 'clans/1/resources/pack.zip',
            'file_name' => 'pack.zip',
            'file_size' => 1024,
            'mime_type' => 'application/zip',
            'category' => 'resource_pack',
            'is_public' => true,
        ]);

        $response = $this->actingAs($leader)->getJson('/api/my-clan/resources')->assertOk();

        $response->assertJsonStructure(['data']);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_member_can_upload_resource(): void
    {
        Storage::fake('public');

        [$clan, $leader] = $this->clanWithLeader();

        $response = $this->actingAs($leader)
            ->post('/api/my-clan/resources', [
                'title' => 'Мой конфиг',
                'description' => 'Настройки для бедварса',
                'category' => 'config',
                'file' => UploadedFile::fake()->create('config.yml', 10, 'text/plain'),
                'is_public' => true,
            ])
            ->assertCreated();

        $this->assertSame('Мой конфиг', $response->json('resource.title'));
        $this->assertDatabaseHas('clan_resources', [
            'clan_id' => $clan->id,
            'title' => 'Мой конфиг',
            'category' => 'config',
        ]);
    }

    public function test_resource_upload_validates_category_and_file(): void
    {
        Storage::fake('public');

        [, $leader] = $this->clanWithLeader();

        $this->actingAs($leader)
            ->post('/api/my-clan/resources', [
                'title' => 'Плохая категория',
                'category' => 'неверная',
                'file' => UploadedFile::fake()->create('x.txt', 1),
            ])
            ->assertStatus(422);

        $this->actingAs($leader)
            ->post('/api/my-clan/resources', ['title' => 'Без файла', 'category' => 'guide'])
            ->assertStatus(422);
    }

    public function test_download_increments_counter_and_returns_url(): void
    {
        [$clan, $leader] = $this->clanWithLeader();

        $resource = ClanResource::create([
            'clan_id' => $clan->id,
            'author_id' => $leader->id,
            'title' => 'Гайд',
            'file_path' => 'clans/1/resources/guide.pdf',
            'file_name' => 'guide.pdf',
            'file_size' => 2048,
            'mime_type' => 'application/pdf',
            'category' => 'guide',
            'is_public' => true,
            'downloads' => 0,
        ]);

        $this->actingAs($leader)
            ->postJson("/api/my-clan/resources/{$resource->id}/download")
            ->assertOk()
            ->assertJsonStructure(['url']);

        $this->assertSame(1, $resource->fresh()->downloads);
    }

    public function test_author_can_delete_resource(): void
    {
        Storage::fake('public');

        [$clan, $leader] = $this->clanWithLeader();

        Storage::disk('public')->put('clans/1/resources/x.zip', 'data');

        $resource = ClanResource::create([
            'clan_id' => $clan->id,
            'author_id' => $leader->id,
            'title' => 'На удаление',
            'file_path' => 'clans/1/resources/x.zip',
            'file_name' => 'x.zip',
            'file_size' => 4,
            'mime_type' => 'application/zip',
            'category' => 'other',
        ]);

        $this->actingAs($leader)
            ->deleteJson("/api/my-clan/resources/{$resource->id}")
            ->assertOk();

        $this->assertDatabaseMissing('clan_resources', ['id' => $resource->id]);
        Storage::disk('public')->assertMissing('clans/1/resources/x.zip');
    }

    public function test_leader_can_delete_someone_elses_resource_but_member_cannot(): void
    {
        Storage::fake('public');

        [$clan, $leader] = $this->clanWithLeader();
        $author = $this->member($clan);
        $other = $this->member($clan);

        $resource = ClanResource::create([
            'clan_id' => $clan->id,
            'author_id' => $author->id,
            'title' => 'Чужое',
            'file_path' => 'clans/1/resources/y.zip',
            'file_name' => 'y.zip',
            'file_size' => 4,
            'mime_type' => 'application/zip',
            'category' => 'other',
        ]);

        // Обычный участник не может удалить чужой ресурс
        $this->actingAs($other)
            ->deleteJson("/api/my-clan/resources/{$resource->id}")
            ->assertForbidden();

        // Лидер может
        $this->actingAs($leader)
            ->deleteJson("/api/my-clan/resources/{$resource->id}")
            ->assertOk();
    }

    public function test_resource_from_another_clan_is_not_found(): void
    {
        [$clan, $leader] = $this->clanWithLeader();

        $otherClan = Clan::create([
            'name' => 'Other Clan',
            'tag' => 'OTH',
            'leader_id' => User::factory()->create()->id,
        ]);

        $resource = ClanResource::create([
            'clan_id' => $otherClan->id,
            'author_id' => $leader->id,
            'title' => 'Чужой клан',
            'file_path' => 'clans/2/resources/z.zip',
            'file_name' => 'z.zip',
            'file_size' => 4,
            'mime_type' => 'application/zip',
            'category' => 'other',
        ]);

        $this->actingAs($leader)
            ->postJson("/api/my-clan/resources/{$resource->id}/download")
            ->assertNotFound();
    }

    public function test_guest_cannot_upload_resource(): void
    {
        Storage::fake('public');

        $this->postJson('/api/my-clan/resources', ['title' => 'Аноним'])->assertUnauthorized();
    }
}
