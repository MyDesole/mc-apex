<?php

namespace Tests\Feature;

use App\Models\ClanApplication;
use App\Models\ClanWar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ClanFixtures;
use Tests\TestCase;

/**
 * ClanController: список/топ/просмотр/создание/правка клана,
 * заявки на вступление, выход и кик.
 */
class ClanTest extends TestCase
{
    use ClanFixtures;
    use RefreshDatabase;

    // ------------------------------------------------------------------ index

    public function test_clan_index_is_public_and_orders_by_power(): void
    {
        $strong = $this->clan($this->user(), ['power' => 500]);
        $weak = $this->clan($this->user(), ['power' => 10]);

        $response = $this->getJson('/api/clans')->assertOk();

        $response->assertJsonPath('data.0.id', $strong->id)
            ->assertJsonPath('data.1.id', $weak->id)
            ->assertJsonPath('data.0.members_count', 1)
            ->assertJsonPath('total', 2);
    }

    public function test_clan_index_search_matches_name_and_tag(): void
    {
        $byName = $this->clan($this->user(), ['name' => 'Alpha Wolves', 'tag' => 'AWLF']);
        $byTag = $this->clan($this->user(), ['name' => 'Beta Squad', 'tag' => 'BETZ']);

        $nameSearch = $this->getJson('/api/clans?search=Wolves')->assertOk();
        $this->assertSame([$byName->id], collect($nameSearch->json('data'))->pluck('id')->all());

        $tagSearch = $this->getJson('/api/clans?search=BETZ')->assertOk();
        $this->assertSame([$byTag->id], collect($tagSearch->json('data'))->pluck('id')->all());
    }

    // -------------------------------------------------------------------- top

    public function test_top_returns_ten_best_clans_with_expected_shape(): void
    {
        foreach (range(1, 12) as $i) {
            $this->clan($this->user(), ['power' => $i * 10]);
        }

        $response = $this->getJson('/api/clans/top')->assertOk();

        $clans = $response->json('clans');

        $this->assertCount(10, $clans);
        $this->assertSame(120, $clans[0]['power']);

        foreach (['id', 'name', 'tag', 'avatar', 'banner_color', 'power', 'wins', 'losses', 'members_count', 'leader'] as $key) {
            $this->assertArrayHasKey($key, $clans[0], "Ключ {$key} отсутствует в /clans/top");
        }

        $this->assertSame(1, $clans[0]['members_count']);
    }

    // ------------------------------------------------------------------- show

    public function test_clan_show_is_public_and_returns_expected_shape(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $response = $this->getJson("/api/clans/{$clan->id}")->assertOk();

        foreach (['clan', 'is_member', 'my_clan_id', 'application', 'members_count', 'incoming_wars', 'outgoing_wars'] as $key) {
            $this->assertArrayHasKey($key, $response->json(), "Ключ {$key} отсутствует в ответе клана");
        }

        $response->assertJsonPath('clan.id', $clan->id)
            ->assertJsonPath('is_member', false)
            ->assertJsonPath('my_clan_id', null)
            ->assertJsonPath('application', null)
            ->assertJsonPath('members_count', 1)
            ->assertJsonPath('clan.leader.id', $leader->id);
    }

    public function test_clan_show_reports_membership_and_pending_application(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $member = $this->user();
        $this->member($clan, $member);

        $applicant = $this->user();
        ClanApplication::create([
            'clan_id' => $clan->id,
            'user_id' => $applicant->id,
            'message' => 'Возьмите меня',
            'status' => 'pending',
        ]);

        $this->actingAs($member)->getJson("/api/clans/{$clan->id}")
            ->assertOk()
            ->assertJsonPath('is_member', true)
            ->assertJsonPath('my_clan_id', $clan->id)
            ->assertJsonPath('application', null);

        $this->actingAs($applicant)->getJson("/api/clans/{$clan->id}")
            ->assertOk()
            ->assertJsonPath('is_member', false)
            ->assertJsonPath('my_clan_id', null)
            ->assertJsonPath('application.status', 'pending')
            ->assertJsonPath('application.message', 'Возьмите меня');
    }

    public function test_clan_show_splits_incoming_and_outgoing_wars(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);

        $otherLeader = $this->user();
        $otherClan = $this->clan($otherLeader);

        ClanWar::create([
            'challenger_clan_id' => $otherClan->id,
            'opponent_clan_id' => $myClan->id,
            'created_by' => $otherLeader->id,
            'status' => 'pending',
        ]);

        ClanWar::create([
            'challenger_clan_id' => $myClan->id,
            'opponent_clan_id' => $otherClan->id,
            'created_by' => $myLeader->id,
            'status' => 'accepted',
        ]);

        // Завершённая война не должна попадать ни в один список
        ClanWar::create([
            'challenger_clan_id' => $myClan->id,
            'opponent_clan_id' => $otherClan->id,
            'created_by' => $myLeader->id,
            'status' => 'completed',
        ]);

        $response = $this->getJson("/api/clans/{$myClan->id}")->assertOk();

        $this->assertCount(1, $response->json('incoming_wars'));
        $this->assertCount(1, $response->json('outgoing_wars'));
        $this->assertSame($otherClan->id, $response->json('incoming_wars.0.challenger_clan_id'));
        $this->assertArrayHasKey('challenger', $response->json('incoming_wars.0'));
        $this->assertArrayHasKey('opponent', $response->json('incoming_wars.0'));
        $this->assertArrayHasKey('participants', $response->json('incoming_wars.0'));
    }

    public function test_clan_show_returns_404_for_unknown_id_and_name(): void
    {
        $this->getJson('/api/clans/999999')->assertNotFound();
        $this->getJson('/api/clans/НетТакогоКлана')->assertNotFound();
    }

    // ------------------------------------------------------------------ store

    public function test_authenticated_user_can_create_clan_and_becomes_leader(): void
    {
        $leader = $this->user();

        $response = $this->actingAs($leader)->postJson('/api/clans', [
            'name' => 'Night Owls',
            'tag' => 'OWLS',
            'description' => 'Клан для тестов',
        ])->assertCreated();

        $clanId = $response->json('clan.id');

        $response->assertJsonPath('clan.name', 'Night Owls')
            ->assertJsonPath('clan.tag', 'OWLS')
            ->assertJsonPath('clan.description', 'Клан для тестов')
            ->assertJsonPath('clan.is_open', true)
            ->assertJsonPath('clan.banner_color', '#7c3aed')
            ->assertJsonPath('clan.leader_id', $leader->id);

        $this->assertDatabaseHas('clans', [
            'id' => $clanId,
            'name' => 'Night Owls',
            'tag' => 'OWLS',
            'leader_id' => $leader->id,
            'is_open' => 1,
        ]);
        $this->assertDatabaseHas('clan_members', [
            'clan_id' => $clanId,
            'user_id' => $leader->id,
            'role' => 'leader',
        ]);
        $this->assertNotNull($leader->fresh()->clan_joined_at);
    }

    public function test_clan_store_respects_is_open_and_banner_color(): void
    {
        $leader = $this->user();

        $this->actingAs($leader)->postJson('/api/clans', [
            'name' => 'Closed Circle',
            'tag' => 'CLSD',
            'is_open' => false,
            'banner_color' => '#ff0000',
        ])->assertCreated()->assertJsonPath('clan.is_open', false);

        $this->assertDatabaseHas('clans', [
            'name' => 'Closed Circle',
            'is_open' => 0,
            'banner_color' => '#ff0000',
        ]);
    }

    public function test_clan_store_rejects_user_already_in_a_clan(): void
    {
        $leader = $this->user();
        $this->clan($leader);

        $this->actingAs($leader)->postJson('/api/clans', [
            'name' => 'Second Clan',
            'tag' => 'SCND',
        ])->assertStatus(422)->assertJsonPath('message', 'Вы уже в клане.');

        $this->assertDatabaseMissing('clans', ['name' => 'Second Clan']);
    }

    public function test_clan_store_validates_unique_name_and_tag(): void
    {
        $existing = $this->clan($this->user(), ['name' => 'Taken Name', 'tag' => 'TAKEN']);
        $user = $this->user();

        $this->actingAs($user)->postJson('/api/clans', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'tag']);

        $this->actingAs($user)->postJson('/api/clans', ['name' => 'Taken Name', 'tag' => 'NEWTAG'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->actingAs($user)->postJson('/api/clans', ['name' => 'Fresh Name', 'tag' => 'TAKEN'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tag');

        $this->actingAs($user)->postJson('/api/clans', ['name' => 'ab', 'tag' => 'X'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'tag']);

        $this->assertDatabaseMissing('clans', ['name' => 'Fresh Name']);
        $this->assertSame('Taken Name', $existing->fresh()->name);
    }

    // ----------------------------------------------------------------- update

    public function test_leader_can_update_clan(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $response = $this->actingAs($leader)->putJson("/api/clans/{$clan->id}", [
            'name' => 'Renamed Clan',
            'description' => 'Новое описание',
            'banner_color' => '#00ff00',
            'is_open' => false,
            'is_highlighted' => true,
            'socials' => ['discord' => 'https://discord.gg/apex'],
        ])->assertOk();

        $response->assertJsonPath('clan.name', 'Renamed Clan')
            ->assertJsonPath('clan.description', 'Новое описание')
            ->assertJsonPath('clan.banner_color', '#00ff00');

        $fresh = $clan->fresh();

        $this->assertSame('Renamed Clan', $fresh->name);
        $this->assertSame('Новое описание', $fresh->description);
        $this->assertSame('#00ff00', $fresh->banner_color);
        $this->assertFalse($fresh->is_open);
        $this->assertTrue($fresh->is_highlighted);
        $this->assertSame(['discord' => 'https://discord.gg/apex'], $fresh->socials);

        $this->assertDatabaseHas('clans', ['id' => $clan->id, 'name' => 'Renamed Clan', 'is_highlighted' => 1]);
    }

    public function test_leader_can_upload_avatar_and_cover(): void
    {
        Storage::fake('public');

        $leader = $this->user();
        $clan = $this->clan($leader);

        $this->actingAs($leader)->post("/api/clans/{$clan->id}", [
            'avatar' => UploadedFile::fake()->image('logo.png', 64, 64),
        ])->assertOk();

        $avatar = $clan->fresh()->avatar;
        $this->assertNotNull($avatar);
        Storage::disk('public')->assertExists($avatar);

        $this->actingAs($leader)->post("/api/clans/{$clan->id}", [
            'cover' => UploadedFile::fake()->image('cover.png', 800, 300),
        ])->assertOk();

        $cover = $clan->fresh()->cover_path;
        $this->assertNotNull($cover);
        Storage::disk('public')->assertExists($cover);

        // Файлы кладутся в отдельные папки клана
        $this->assertStringContainsString('clans/'.$clan->id, $avatar);
        $this->assertStringContainsString('clans/'.$clan->id, $cover);
    }

    public function test_non_leader_cannot_update_clan(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $officer = $this->user();
        $this->member($clan, $officer, 'officer');

        $member = $this->user();
        $this->member($clan, $member);

        $stranger = $this->user();

        $this->actingAs($officer)->putJson("/api/clans/{$clan->id}", ['name' => 'Hacked'])->assertForbidden();
        $this->actingAs($member)->putJson("/api/clans/{$clan->id}", ['name' => 'Hacked'])->assertForbidden();
        $this->actingAs($stranger)->putJson("/api/clans/{$clan->id}", ['name' => 'Hacked'])->assertForbidden();

        $this->assertNotSame('Hacked', $clan->fresh()->name);
    }

    public function test_clan_update_validates_payload(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $this->actingAs($leader)->putJson("/api/clans/{$clan->id}", ['name' => 'ab'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->actingAs($leader)->putJson("/api/clans/{$clan->id}", ['tag' => 'toolongtag'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tag');

        $this->actingAs($leader)->putJson("/api/clans/{$clan->id}", ['socials' => ['website' => 'not-a-url']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('socials.website');
    }

    public function test_update_cannot_take_name_of_another_clan(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $this->clan($this->user(), ['name' => 'Other Clan', 'tag' => 'OTHR']);

        $this->actingAs($leader)->putJson("/api/clans/{$clan->id}", ['name' => 'Other Clan'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    // ------------------------------------------------------- avatar / cover remove

    public function test_leader_can_remove_avatar_and_cover(): void
    {
        Storage::fake('public');

        $leader = $this->user();
        $clan = $this->clan($leader, [
            'avatar' => 'clans/1/logo.png',
            'cover_path' => 'clans/1/covers/cover.png',
        ]);
        Storage::disk('public')->put('clans/1/logo.png', 'x');
        Storage::disk('public')->put('clans/1/covers/cover.png', 'x');

        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/avatar/remove")
            ->assertOk()
            ->assertJsonPath('clan.avatar', null);

        $this->assertNull($clan->fresh()->avatar);
        Storage::disk('public')->assertMissing('clans/1/logo.png');

        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/cover/remove")
            ->assertOk()
            ->assertJsonPath('clan.cover_path', null);

        $this->assertNull($clan->fresh()->cover_path);
        Storage::disk('public')->assertMissing('clans/1/covers/cover.png');
    }

    public function test_only_leader_can_remove_avatar_and_cover(): void
    {
        Storage::fake('public');

        $leader = $this->user();
        $clan = $this->clan($leader, ['avatar' => 'clans/1/logo.png', 'cover_path' => 'clans/1/cover.png']);

        $officer = $this->user();
        $this->member($clan, $officer, 'officer');

        $this->actingAs($officer)->postJson("/api/clans/{$clan->id}/avatar/remove")->assertForbidden();
        $this->actingAs($officer)->postJson("/api/clans/{$clan->id}/cover/remove")->assertForbidden();

        $this->assertSame('clans/1/logo.png', $clan->fresh()->avatar);
        $this->assertSame('clans/1/cover.png', $clan->fresh()->cover_path);
    }

    public function test_removing_empty_media_is_not_an_error(): void
    {
        Storage::fake('public');

        $leader = $this->user();
        $clan = $this->clan($leader);

        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/avatar/remove")->assertOk();
        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/cover/remove")->assertOk();

        $this->assertNull($clan->fresh()->avatar);
        $this->assertNull($clan->fresh()->cover_path);
    }

    // ------------------------------------------------------------------ apply

    public function test_user_can_apply_to_open_clan_and_leader_is_notified(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $applicant = $this->user();

        $response = $this->actingAs($applicant)
            ->postJson("/api/clans/{$clan->id}/apply", ['message' => 'Возьмите меня'])
            ->assertCreated();

        $response->assertJsonPath('application.status', 'pending')
            ->assertJsonPath('application.clan_id', $clan->id)
            ->assertJsonPath('application.user_id', $applicant->id)
            ->assertJsonPath('application.message', 'Возьмите меня');

        $this->assertDatabaseHas('clan_applications', [
            'clan_id' => $clan->id,
            'user_id' => $applicant->id,
            'status' => 'pending',
            'message' => 'Возьмите меня',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $leader->id,
            'notifiable_type' => User::class,
        ]);

        $this->assertDatabaseMissing('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $applicant->id,
        ]);
    }

    public function test_apply_without_message_is_allowed(): void
    {
        $clan = $this->clan($this->user());
        $applicant = $this->user();

        $this->actingAs($applicant)
            ->postJson("/api/clans/{$clan->id}/apply")
            ->assertCreated()
            ->assertJsonPath('application.message', null);
    }

    public function test_cannot_apply_twice_while_pending(): void
    {
        $clan = $this->clan($this->user());
        $applicant = $this->user();

        $this->actingAs($applicant)->postJson("/api/clans/{$clan->id}/apply")->assertCreated();

        $this->actingAs($applicant)->postJson("/api/clans/{$clan->id}/apply")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Заявка уже отправлена.');

        $this->assertDatabaseCount('clan_applications', 1);
    }

    public function test_user_can_reapply_after_decline(): void
    {
        $clan = $this->clan($this->user());
        $applicant = $this->user();

        ClanApplication::create([
            'clan_id' => $clan->id,
            'user_id' => $applicant->id,
            'status' => 'declined',
        ]);

        $this->actingAs($applicant)->postJson("/api/clans/{$clan->id}/apply")
            ->assertCreated()
            ->assertJsonPath('application.status', 'pending');

        $this->assertDatabaseCount('clan_applications', 1);
        $this->assertDatabaseHas('clan_applications', ['user_id' => $applicant->id, 'status' => 'pending']);
    }

    public function test_cannot_apply_to_closed_clan(): void
    {
        $clan = $this->clan($this->user(), ['is_open' => false]);
        $applicant = $this->user();

        $this->actingAs($applicant)->postJson("/api/clans/{$clan->id}/apply")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Клан закрыт для вступления.');

        $this->assertDatabaseCount('clan_applications', 0);
    }

    public function test_member_cannot_apply_to_another_clan(): void
    {
        $clan = $this->clan($this->user());
        $other = $this->clan($this->user());

        $member = $this->user();
        $this->member($other, $member);

        $this->actingAs($member)->postJson("/api/clans/{$clan->id}/apply")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Вы уже в клане.');
    }

    public function test_apply_validates_message_length(): void
    {
        $clan = $this->clan($this->user());
        $applicant = $this->user();

        $this->actingAs($applicant)->postJson("/api/clans/{$clan->id}/apply", [
            'message' => str_repeat('a', 501),
        ])->assertStatus(422)->assertJsonValidationErrors('message');
    }

    // ----------------------------------------------------------- applications

    public function test_applications_visible_to_leader_and_officer_only(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $officer = $this->user();
        $this->member($clan, $officer, 'officer');

        $member = $this->user();
        $this->member($clan, $member);

        $applicant = $this->user();
        ClanApplication::create([
            'clan_id' => $clan->id,
            'user_id' => $applicant->id,
            'message' => 'Заявка',
            'status' => 'pending',
        ]);

        // Отклонённая заявка не должна попадать в список
        $declined = $this->user();
        ClanApplication::create([
            'clan_id' => $clan->id,
            'user_id' => $declined->id,
            'status' => 'declined',
        ]);

        $leaderView = $this->actingAs($leader)->getJson("/api/clans/{$clan->id}/applications")->assertOk();
        $this->assertCount(1, $leaderView->json('applications'));
        $this->assertSame($applicant->id, $leaderView->json('applications.0.user.id'));
        $this->assertSame($applicant->username, $leaderView->json('applications.0.user.username'));

        $this->actingAs($officer)->getJson("/api/clans/{$clan->id}/applications")->assertOk();
        $this->actingAs($member)->getJson("/api/clans/{$clan->id}/applications")->assertForbidden();
    }

    // --------------------------------------------------------- accept / decline

    public function test_leader_can_accept_application(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $applicant = $this->user();

        $application = ClanApplication::create([
            'clan_id' => $clan->id,
            'user_id' => $applicant->id,
            'status' => 'pending',
        ]);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/applications/{$application->id}/accept")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('accepted', $application->fresh()->status);
        $this->assertDatabaseHas('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $applicant->id,
            'role' => 'member',
        ]);
        $this->assertNotNull($applicant->fresh()->clan_joined_at);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $applicant->id]);
    }

    public function test_officer_can_accept_application(): void
    {
        $clan = $this->clan($this->user());

        $officer = $this->user();
        $this->member($clan, $officer, 'officer');

        $applicant = $this->user();
        $application = ClanApplication::create([
            'clan_id' => $clan->id,
            'user_id' => $applicant->id,
            'status' => 'pending',
        ]);

        $this->actingAs($officer)
            ->postJson("/api/clans/{$clan->id}/applications/{$application->id}/accept")
            ->assertOk();

        $this->assertDatabaseHas('clan_members', ['clan_id' => $clan->id, 'user_id' => $applicant->id]);
    }

    /**
     * После принятия заявки остальные ожидающие заявки игрока в другие кланы
     * автоматически отклоняются (ClanService::acceptApplication).
     */
    public function test_accepting_application_declines_applicants_other_pending_requests(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $otherClan = $this->clan($this->user());
        $applicant = $this->user();

        $accepted = ClanApplication::create([
            'clan_id' => $clan->id,
            'user_id' => $applicant->id,
            'status' => 'pending',
        ]);

        $otherApplication = ClanApplication::create([
            'clan_id' => $otherClan->id,
            'user_id' => $applicant->id,
            'status' => 'pending',
        ]);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/applications/{$accepted->id}/accept")
            ->assertOk();

        $this->assertSame('accepted', $accepted->fresh()->status);
        $this->assertSame('declined', $otherApplication->fresh()->status);
        $this->assertDatabaseHas('clan_members', ['clan_id' => $clan->id, 'user_id' => $applicant->id]);
        $this->assertDatabaseMissing('clan_members', ['clan_id' => $otherClan->id, 'user_id' => $applicant->id]);
    }

    public function test_plain_member_cannot_accept_or_decline_application(): void
    {
        $clan = $this->clan($this->user());

        $member = $this->user();
        $this->member($clan, $member);

        $applicant = $this->user();
        $application = ClanApplication::create([
            'clan_id' => $clan->id,
            'user_id' => $applicant->id,
            'status' => 'pending',
        ]);

        $this->actingAs($member)
            ->postJson("/api/clans/{$clan->id}/applications/{$application->id}/accept")
            ->assertForbidden();

        $this->actingAs($member)
            ->postJson("/api/clans/{$clan->id}/applications/{$application->id}/decline")
            ->assertForbidden();

        $this->assertSame('pending', $application->fresh()->status);
        $this->assertDatabaseMissing('clan_members', ['clan_id' => $clan->id, 'user_id' => $applicant->id]);
    }

    public function test_accept_returns_404_for_application_of_another_clan(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $otherClan = $this->clan($this->user());
        $applicant = $this->user();
        $foreign = ClanApplication::create([
            'clan_id' => $otherClan->id,
            'user_id' => $applicant->id,
            'status' => 'pending',
        ]);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/applications/{$foreign->id}/accept")
            ->assertNotFound();

        $this->assertSame('pending', $foreign->fresh()->status);
        $this->assertDatabaseMissing('clan_members', ['clan_id' => $clan->id, 'user_id' => $applicant->id]);
    }

    public function test_accept_returns_404_for_unknown_application(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/applications/999999/accept")
            ->assertNotFound();
    }

    public function test_accept_returns_422_when_clan_is_full(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader, ['max_members' => 1]);

        $applicant = $this->user();
        $application = ClanApplication::create([
            'clan_id' => $clan->id,
            'user_id' => $applicant->id,
            'status' => 'pending',
        ]);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/applications/{$application->id}/accept")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Клан заполнен.');

        $this->assertSame('pending', $application->fresh()->status);
        $this->assertDatabaseMissing('clan_members', ['clan_id' => $clan->id, 'user_id' => $applicant->id]);
    }

    public function test_leader_can_decline_application(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $applicant = $this->user();

        $application = ClanApplication::create([
            'clan_id' => $clan->id,
            'user_id' => $applicant->id,
            'status' => 'pending',
        ]);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/applications/{$application->id}/decline")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('declined', $application->fresh()->status);
        $this->assertDatabaseMissing('clan_members', ['clan_id' => $clan->id, 'user_id' => $applicant->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $applicant->id]);
    }

    // ------------------------------------------------------------------ leave

    public function test_member_can_leave_clan(): void
    {
        $clan = $this->clan($this->user());
        $member = $this->user();
        $this->member($clan, $member);
        $member->update(['clan_joined_at' => now()]);

        $this->actingAs($member)
            ->postJson("/api/clans/{$clan->id}/leave")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('clan_members', ['clan_id' => $clan->id, 'user_id' => $member->id]);
        $this->assertNull($member->fresh()->clan_joined_at);
    }

    /**
     * Лидер с другими участниками выйти не может: сначала передача
     * лидерства. Лидер-одиночка распускает клан — см. ClanDissolveTest.
     */
    public function test_leader_with_members_cannot_leave_clan(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader, ['power' => 100]);

        $this->member($clan, $this->user(), 'member');

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/leave")
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Лидер не может покинуть клан. Передайте лидерство участнику '
                . '— кнопка с короной в списке участников.'
            );

        $this->assertDatabaseHas('clan_members', ['clan_id' => $clan->id, 'user_id' => $leader->id, 'role' => 'leader']);
        $this->assertSame(100, $clan->fresh()->power);
    }

    public function test_leave_returns_404_when_not_a_member(): void
    {
        $clan = $this->clan($this->user());
        $stranger = $this->user();

        $this->actingAs($stranger)->postJson("/api/clans/{$clan->id}/leave")->assertNotFound();
    }

    // ------------------------------------------------------------------- kick

    public function test_leader_can_kick_member(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $member = $this->user();
        $this->member($clan, $member, 'member', ['contribution' => 40]);
        $member->update(['clan_joined_at' => now()]);

        $this->actingAs($leader)
            ->deleteJson("/api/clans/{$clan->id}/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('clan_members', ['clan_id' => $clan->id, 'user_id' => $member->id]);
        $this->assertNull($member->fresh()->clan_joined_at);
        $this->assertSame(0, $clan->fresh()->power);
    }

    public function test_leader_cannot_kick_self(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $this->actingAs($leader)
            ->deleteJson("/api/clans/{$clan->id}/members/{$leader->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Нельзя кикнуть лидера.');

        $this->assertDatabaseHas('clan_members', ['clan_id' => $clan->id, 'user_id' => $leader->id]);
    }

    public function test_officer_cannot_kick_members(): void
    {
        $clan = $this->clan($this->user());
        $officer = $this->user();
        $this->member($clan, $officer, 'officer');
        $member = $this->user();
        $this->member($clan, $member);

        $this->actingAs($officer)
            ->deleteJson("/api/clans/{$clan->id}/members/{$member->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('clan_members', ['clan_id' => $clan->id, 'user_id' => $member->id]);
    }

    public function test_kick_returns_404_for_unknown_user(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $this->actingAs($leader)
            ->deleteJson("/api/clans/{$clan->id}/members/999999")
            ->assertNotFound();
    }

    /**
     * Текущее поведение: кик не проверяет, что пользователь вообще в этом клане,
     * поэтому запрос на «кик» постороннего завершается успехом.
     */
    public function test_kicking_user_outside_clan_is_silently_successful(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $stranger = $this->user();

        $this->actingAs($leader)
            ->deleteJson("/api/clans/{$clan->id}/members/{$stranger->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('clan_members', ['clan_id' => $clan->id, 'user_id' => $stranger->id]);
    }

    // ------------------------------------------------------------ guest access

    public function test_guest_cannot_access_protected_clan_routes(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $this->postJson('/api/clans', [])->assertUnauthorized();
        $this->putJson("/api/clans/{$clan->id}", [])->assertUnauthorized();
        $this->postJson("/api/clans/{$clan->id}/apply")->assertUnauthorized();
        $this->getJson("/api/clans/{$clan->id}/applications")->assertUnauthorized();
        $this->postJson("/api/clans/{$clan->id}/leave")->assertUnauthorized();
        $this->deleteJson("/api/clans/{$clan->id}/members/{$leader->id}")->assertUnauthorized();
        $this->postJson("/api/clans/{$clan->id}/avatar/remove")->assertUnauthorized();
        $this->postJson("/api/clans/{$clan->id}/cover/remove")->assertUnauthorized();
    }
}
