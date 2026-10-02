<?php

namespace Tests\Feature;

use App\Domains\Chat\Requests\Chat\UploadAttachmentRequest;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Загрузка вложений в том виде, в каком её шлёт браузер.
 *
 * Отличие от ChatMultiUploadTest: там файлы передаются через
 * $this->post(), и хелпер складывает массив в input запроса. Живой
 * браузер шлёт multipart, и файлы попадают только в $_FILES.
 *
 * Проверка пакета смотрела на input('files'), поэтому в браузере
 * вложения молча не сохранялись (ответ приходил с пустым списком), а
 * тесты этого не видели.
 */
class ChatMultipartUploadTest extends TestCase
{
    use RefreshDatabase;

    /** Настоящий JPEG: минимальный валидный файл. */
    private function realJpeg(int $width = 60, int $height = 60): string
    {
        $image = imagecreatetruecolor($width, $height);

        imagefill($image, 0, 0, imagecolorallocate($image, 120, 80, 200));

        ob_start();
        imagejpeg($image, null, 85);
        $data = ob_get_clean();

        imagedestroy($image);

        return $data;
    }

    /** Запрос с файлами только в $_FILES — как из браузера. */
    public function test_batch_from_real_multipart_request_is_stored(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $jpeg = UploadedFile::fake()->createWithContent('photo.jpg', $this->realJpeg());

        $response = $this->actingAs($user)
            ->call('POST', '/api/chat/attachments', [], [], [
                'files' => [0 => $jpeg],
            ], ['HTTP_ACCEPT' => 'application/json']);

        $response->assertCreated();

        $this->assertCount(1, $response->json('attachments'), 'файл сохранён');
        $this->assertSame('photo.jpg', $response->json('attachment.name'));
        $this->assertTrue($response->json('attachment.is_image'));
        $this->assertSame(201, $response->getStatusCode());
        $this->assertDatabaseCount('message_attachments', 1);
    }

    /** Пакет распознаётся по файлам, а не по полю ввода. */
    public function test_has_batch_detects_files_outside_input(): void
    {
        $file = UploadedFile::fake()->createWithContent('a.jpg', $this->realJpeg());

        $request = UploadAttachmentRequest::create(
            '/api/chat/attachments',
            'POST',
            [],
            [],
            ['files' => [0 => $file]],
            ['CONTENT_TYPE' => 'multipart/form-data']
        );

        $this->assertSame([], $request->input(), 'input пуст, как в браузере');
        $this->assertTrue($request->hasBatch(), 'пакет распознан по файлам');
        $this->assertCount(1, $request->filesToStore());
    }

    /** Одиночный файл по-прежнему работает. */
    public function test_single_file_still_works(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->call('POST', '/api/chat/attachments', [], [], [
                'file' => UploadedFile::fake()->image('one.png', 40, 40),
            ], ['HTTP_ACCEPT' => 'application/json'])
            ->assertCreated();
    }
}
