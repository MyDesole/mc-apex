<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Друзья: список, заявки, принятие, удаление.
 *
 * До рефакторинга контроллер сам собирал payload игрока тремя копиями
 * одного массива.
 */
class FriendTest extends TestCase
{
    use RefreshDatabase;

    private function pair(string $status = 'accepted'): array
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        Friendship::create([
            'user_id' => $me->id,
            'friend_id' => $other->id,
            'status' => $status,
        ]);

        return [$me, $other];
    }

    public function test_guest_cannot_access_friends(): void
    {
        $this->getJson('/api/friends')->assertUnauthorized();
        $this->postJson('/api/friends/1')->assertUnauthorized();
    }

    public function test_list_shows_friends_and_both_request_directions(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $incomingSender = User::factory()->create();
        $outgoingTarget = User::factory()->create();

        Friendship::create(['user_id' => $me->id, 'friend_id' => $friend->id, 'status' => 'accepted']);
        Friendship::create(['user_id' => $incomingSender->id, 'friend_id' => $me->id, 'status' => 'pending']);
        Friendship::create(['user_id' => $me->id, 'friend_id' => $outgoingTarget->id, 'status' => 'pending']);

        $response = $this->actingAs($me)->getJson('/api/friends')->assertOk();

        $this->assertCount(1, $response->json('friends'));
        $this->assertSame($friend->id, $response->json('friends.0.id'));

        $this->assertCount(1, $response->json('incoming_requests'));
        $this->assertSame($incomingSender->id, $response->json('incoming_requests.0.user.id'));

        $this->assertCount(1, $response->json('outgoing_requests'));
        $this->assertSame($outgoingTarget->id, $response->json('outgoing_requests.0.user.id'));
    }

    public function test_friend_payload_contains_expected_fields(): void
    {
        [$me, $other] = $this->pair();

        $response = $this->actingAs($me)->getJson('/api/friends')->assertOk();

        $response->assertJsonStructure([
            'friends' => [['id', 'username', 'avatar_url', 'tier', 'tier_score', 'is_verified']],
        ]);
    }

    public function test_reverse_friendship_is_listed_too(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        // Заявку отправлял не я — в списке друзей всё равно должен быть он
        Friendship::create(['user_id' => $other->id, 'friend_id' => $me->id, 'status' => 'accepted']);

        $response = $this->actingAs($me)->getJson('/api/friends')->assertOk();

        $this->assertSame($other->id, $response->json('friends.0.id'));
    }

    public function test_request_can_be_sent(): void
    {
        $me = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($me)->postJson("/api/friends/{$target->id}")->assertCreated();

        $this->assertSame('pending', $response->json('friendship.status'));

        $this->assertDatabaseHas('friendships', [
            'user_id' => $me->id,
            'friend_id' => $target->id,
            'status' => 'pending',
        ]);
    }

    public function test_cannot_add_self(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)
            ->postJson("/api/friends/{$me->id}")
            ->assertStatus(422);
    }

    public function test_duplicate_request_is_rejected(): void
    {
        [$me, $other] = $this->pair('pending');

        $this->actingAs($me)
            ->postJson("/api/friends/{$other->id}")
            ->assertStatus(422);

        $this->assertSame(1, Friendship::count());
    }

    public function test_reverse_pending_request_blocks_duplicate(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        Friendship::create(['user_id' => $other->id, 'friend_id' => $me->id, 'status' => 'pending']);

        // Заявка уже есть в обратную сторону — новую создавать нельзя
        $this->actingAs($me)
            ->postJson("/api/friends/{$other->id}")
            ->assertStatus(422);

        $this->assertSame(1, Friendship::count());
    }

    public function test_incoming_request_can_be_accepted(): void
    {
        $me = User::factory()->create();
        $sender = User::factory()->create();

        Friendship::create(['user_id' => $sender->id, 'friend_id' => $me->id, 'status' => 'pending']);

        $this->actingAs($me)
            ->postJson("/api/friends/{$sender->id}/accept")
            ->assertOk();

        $this->assertDatabaseHas('friendships', [
            'user_id' => $sender->id,
            'friend_id' => $me->id,
            'status' => 'accepted',
        ]);
    }

    public function test_cannot_accept_request_i_sent_myself(): void
    {
        [$me, $target] = $this->pair('pending');

        // Я отправитель — принять заявку может только получатель
        $this->actingAs($me)
            ->postJson("/api/friends/{$target->id}/accept")
            ->assertNotFound();
    }

    public function test_friend_can_be_removed(): void
    {
        [$me, $other] = $this->pair();

        $this->actingAs($me)
            ->deleteJson("/api/friends/{$other->id}")
            ->assertOk();

        $this->assertSame(0, Friendship::count());
    }

    public function test_removal_works_from_either_direction(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        Friendship::create(['user_id' => $other->id, 'friend_id' => $me->id, 'status' => 'accepted']);

        $this->actingAs($me)
            ->deleteJson("/api/friends/{$other->id}")
            ->assertOk();

        $this->assertSame(0, Friendship::count());
    }
}
