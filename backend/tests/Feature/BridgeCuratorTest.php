<?php

namespace Tests\Feature;

use App\Domains\Bridge\Models\BridgeRank;
use App\Domains\Bridge\Models\BridgeTechnique;
use App\Domains\Bridge\Models\BridgeTechniqueVariant;
use App\Domains\Bridge\Services\BridgeService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Куратор бриджа: каталог видов и подвидов.
 *
 * Подвиды показываются пиллами под названием вида, поэтому проверяем, что
 * они приходят в каталоге к игроку.
 */
class BridgeCuratorTest extends TestCase
{
    use RefreshDatabase;

    private function curator(): User
    {
        return User::factory()->create(['role' => 'bridge_curator']);
    }

    private function service(): BridgeService
    {
        return app(BridgeService::class);
    }

    public function test_curator_adds_technique(): void
    {
        Sanctum::actingAs($this->curator());

        $this->postJson('/api/bridge-curator/techniques', [
            'label' => 'Andromeda Bridge',
            'description' => 'Диагональный бридж',
        ])
            ->assertCreated()
            ->assertJsonPath('technique.label', 'Andromeda Bridge')
            ->assertJsonPath('technique.key', 'andromeda_bridge');

        $this->assertDatabaseHas('bridge_techniques', [
            'label' => 'Andromeda Bridge',
            'is_active' => true,
        ]);
    }

    public function test_duplicate_labels_get_distinct_keys(): void
    {
        Sanctum::actingAs($this->curator());

        $this->postJson('/api/bridge-curator/techniques', ['label' => 'Telly'])->assertCreated();
        $this->postJson('/api/bridge-curator/techniques', ['label' => 'Telly'])->assertCreated();

        $this->assertSame(2, BridgeTechnique::query()->count());
        $this->assertNotSame(
            BridgeTechnique::query()->pluck('key')->unique()->count(),
            1,
            'Ключи должны различаться'
        );
    }

    public function test_regular_player_cannot_manage_catalog(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->postJson('/api/bridge-curator/techniques', ['label' => 'Читерский бридж'])
            ->assertForbidden();

        $this->assertSame(0, BridgeTechnique::query()->count());
    }

    public function test_bridge_tester_cannot_manage_catalog(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'bridge_tester']));

        $this->getJson('/api/bridge-curator/techniques')->assertForbidden();
    }

    public function test_admin_can_manage_catalog(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/bridge-curator/techniques', ['label' => 'Speed Bridge'])
            ->assertCreated();
    }

    public function test_curator_updates_technique(): void
    {
        $technique = BridgeTechnique::create([
            'key' => 'telly', 'label' => 'Telly', 'sort_order' => 1, 'is_active' => true,
        ]);

        Sanctum::actingAs($this->curator());

        $this->putJson("/api/bridge-curator/techniques/{$technique->id}", [
            'label' => 'Telly Bridge',
            'description' => 'Классика',
        ])
            ->assertOk()
            ->assertJsonPath('technique.label', 'Telly Bridge');
    }

    public function test_curator_adds_variant(): void
    {
        $technique = BridgeTechnique::create([
            'key' => 'telly', 'label' => 'Telly', 'sort_order' => 1, 'is_active' => true,
        ]);

        Sanctum::actingAs($this->curator());

        $this->postJson("/api/bridge-curator/techniques/{$technique->id}/variants", [
            'label' => 'С удержанием',
        ])
            ->assertCreated()
            ->assertJsonPath('variant.label', 'С удержанием');

        $this->assertDatabaseHas('bridge_technique_variants', [
            'technique_id' => $technique->id,
            'label' => 'С удержанием',
        ]);
    }

    public function test_variants_appear_in_player_catalog_as_pills(): void
    {
        $player = User::factory()->create();

        $technique = BridgeTechnique::create([
            'key' => 'telly', 'label' => 'Telly', 'sort_order' => 1, 'is_active' => true,
        ]);

        BridgeTechniqueVariant::create([
            'technique_id' => $technique->id, 'key' => 'held', 'label' => 'С удержанием',
            'sort_order' => 1, 'is_active' => true,
        ]);
        BridgeTechniqueVariant::create([
            'technique_id' => $technique->id, 'key' => 'hidden', 'label' => 'Скрытый',
            'sort_order' => 2, 'is_active' => false,
        ]);

        Sanctum::actingAs($player);

        $response = $this->getJson('/api/bridge/techniques')->assertOk();

        // Выключенный подвид игроку не показываем
        $this->assertCount(1, $response->json('data.0.technique.variants'));
        $response->assertJsonPath('data.0.technique.variants.0.label', 'С удержанием');
    }

    public function test_curator_deletes_variant(): void
    {
        $technique = BridgeTechnique::create([
            'key' => 'telly', 'label' => 'Telly', 'sort_order' => 1, 'is_active' => true,
        ]);

        $variant = BridgeTechniqueVariant::create([
            'technique_id' => $technique->id, 'key' => 'held', 'label' => 'С удержанием',
            'sort_order' => 1, 'is_active' => true,
        ]);

        Sanctum::actingAs($this->curator());

        $this->deleteJson("/api/bridge-curator/variants/{$variant->id}")->assertOk();

        $this->assertSame(0, BridgeTechniqueVariant::query()->count());
    }

    public function test_technique_with_submissions_is_hidden_not_deleted(): void
    {
        $player = User::factory()->create();
        $technique = BridgeTechnique::create([
            'key' => 'telly', 'label' => 'Telly', 'sort_order' => 1, 'is_active' => true,
        ]);

        $this->service()->declare($player, $technique->id, 'https://youtu.be/abc');

        Sanctum::actingAs($this->curator());

        $this->deleteJson("/api/bridge-curator/techniques/{$technique->id}")->assertOk();

        // Вид остался в базе, но выключен: иначе пропали бы заявки игроков
        $this->assertDatabaseHas('bridge_techniques', [
            'id' => $technique->id,
            'is_active' => false,
        ]);
    }

    public function test_curator_sees_disabled_techniques(): void
    {
        BridgeTechnique::create([
            'key' => 'off', 'label' => 'Выключенный', 'sort_order' => 1, 'is_active' => false,
        ]);

        Sanctum::actingAs($this->curator());

        $response = $this->getJson('/api/bridge-curator/techniques')->assertOk();

        $this->assertCount(1, $response->json('data'));

        // Игроку выключенный вид не показываем
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/bridge/techniques')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
