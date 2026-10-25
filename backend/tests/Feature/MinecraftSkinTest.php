<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Скин игрока из Minecraft: сервер ходит к Mojang и кэширует PNG.
 */
class MinecraftSkinTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = '069a79f444e94726a5befca90e38aaf5';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Cache::flush();
    }

    private function png(int $width = 64, int $height = 64): string
    {
        $image = imagecreatetruecolor($width, $height);

        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    private function fakeMojang(string $model = 'slim', ?string $png = null, string $host = 'textures.minecraft.net'): void
    {
        $textures = base64_encode(json_encode([
            'textures' => [
                'SKIN' => [
                    'url' => "http://{$host}/texture/abc",
                    'metadata' => $model === 'slim' ? ['model' => 'slim'] : [],
                ],
            ],
        ]));

        Http::fake([
            'api.mojang.com/users/profiles/minecraft/*' => Http::response(['id' => self::UUID, 'name' => 'Notch']),
            'sessionserver.mojang.com/*' => Http::response([
                'id' => self::UUID,
                'properties' => [['name' => 'textures', 'value' => $textures]],
            ]),
            "{$host}/*" => Http::response($png ?? $this->png(), 200, ['Content-Type' => 'image/png']),
        ]);
    }

    public function test_returns_png_with_model_header_and_caches_it(): void
    {
        $this->fakeMojang('slim');

        $user = User::factory()->create(['username' => 'desole', 'minecraft_username' => 'Notch']);

        $this->get('/api/players/desole/skin')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Skin-Model', 'slim');

        Storage::disk('local')->assertExists('minecraft-skins/' . self::UUID . '.png');

        // Второй запрос не ходит к Mojang
        $this->get("/api/players/{$user->id}/skin")->assertOk();

        Http::assertSentCount(3);
    }

    public function test_player_without_nickname_has_no_skin(): void
    {
        Http::fake();

        User::factory()->create(['username' => 'desole']);

        $this->getJson('/api/players/desole/skin')->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_rejects_texture_from_foreign_host(): void
    {
        $this->fakeMojang('classic', null, 'evil.example.com');

        User::factory()->create(['username' => 'desole', 'minecraft_username' => 'Notch']);

        $this->getJson('/api/players/desole/skin')->assertNotFound();
    }

    public function test_rejects_image_that_is_not_a_skin(): void
    {
        $this->fakeMojang('classic', $this->png(128, 128));

        User::factory()->create(['username' => 'desole', 'minecraft_username' => 'Notch']);

        $this->getJson('/api/players/desole/skin')->assertNotFound();
    }

    public function test_serves_stale_skin_when_mojang_is_down(): void
    {
        Storage::disk('local')->put('minecraft-skins/' . self::UUID . '.png', $this->png());

        Http::fake(['*' => Http::response(null, 503)]);

        User::factory()->create([
            'username' => 'desole',
            'minecraft_username' => 'Notch',
            'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        ]);

        $this->get('/api/players/desole/skin')
            ->assertOk()
            ->assertHeader('X-Skin-Model', 'classic');
    }

    public function test_invalid_nickname_never_reaches_mojang(): void
    {
        Http::fake();

        User::factory()->create(['username' => 'desole', 'minecraft_username' => '../../etc']);

        $this->getJson('/api/players/desole/skin')->assertNotFound();

        Http::assertNothingSent();
    }
}
