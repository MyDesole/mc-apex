<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Превью ссылок для ботов: HTML с og-тегами и PNG-карточка.
 */
class OgPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_page_has_open_graph_tags(): void
    {
        $player = User::factory()->create(['username' => 'Steve', 'tier' => 'B', 'tier_score' => 60]);

        $this->get('/og/user/Steve')
            ->assertOk()
            ->assertSee('<meta property="og:title" content="Steve — тир B · 60%">', false)
            ->assertSee("/og/user/{$player->id}/image.png", false)
            ->assertSee('summary_large_image', false);
    }

    public function test_user_image_is_png(): void
    {
        $player = User::factory()->create(['tier' => 'A', 'tier_score' => 80]);

        $response = $this->get("/og/user/{$player->id}/image.png")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $size = getimagesizefromstring($response->getContent());
        $this->assertSame([1200, 630], [$size[0], $size[1]]);
    }

    public function test_unknown_player_is_404(): void
    {
        $this->get('/og/user/nobody_here')->assertNotFound();
    }
}
