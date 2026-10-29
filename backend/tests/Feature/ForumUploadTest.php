<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Загрузка вложений форума — ей пользуются Ctrl+V и перетаскивание
 * скриншотов в форму темы и ответа.
 */
class ForumUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_upload(): void
    {
        Storage::fake('public');

        $this->post('/api/forum/upload', ['file' => UploadedFile::fake()->image('shot.png')], ['Accept' => 'application/json'])
            ->assertUnauthorized();
    }

    public function test_pasted_screenshot_is_accepted_as_image(): void
    {
        Storage::fake('public');

        // Так называет вставленную картинку страница: screenshot-<время>.png
        $this->actingAs(User::factory()->create())
            ->post('/api/forum/upload', ['file' => UploadedFile::fake()->image('screenshot-20261029150000.png', 1280, 720)], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('attachment.is_image', true)
            ->assertJsonPath('attachment.name', 'screenshot-20261029150000.png');
    }

    public function test_wrong_type_and_oversize_are_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/api/forum/upload', ['file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->actingAs($user)
            ->post('/api/forum/upload', ['file' => UploadedFile::fake()->image('huge.png')->size(11 * 1024)], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }
}
