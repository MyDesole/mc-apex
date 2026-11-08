<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Роли «куратор» (выше всех) и «хелпер» (помощник персонала).
 */
class CuratorHelperRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_curator_passes_where_admin_passes(): void
    {
        $curator = User::factory()->create(['role' => 'curator']);

        // раздел «только админ» (role:admin) и общая админка (role:moderator,admin)
        $this->actingAs($curator)->getJson('/api/admin/users')->assertSuccessful();
        $this->assertTrue($curator->isAdmin());
        $this->assertTrue($curator->isModerator());
        $this->assertTrue($curator->isStaff());
        $this->assertTrue($curator->isUnranked());
    }

    public function test_helper_is_staff_but_has_no_admin_panel(): void
    {
        $helper = User::factory()->create(['role' => 'helper']);

        $this->assertTrue($helper->isStaff());
        $this->assertFalse($helper->isAdmin());
        $this->assertFalse($helper->isModerator());
        $this->actingAs($helper)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_only_curator_can_grant_curator_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $curator = User::factory()->create(['role' => 'curator']);
        $target = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)->postJson("/api/admin/users/{$target->id}/role", ['role' => 'curator'])->assertForbidden();
        $this->assertSame('user', $target->fresh()->role);

        $this->actingAs($curator)->postJson("/api/admin/users/{$target->id}/role", ['role' => 'curator'])->assertSuccessful();
        $this->assertSame('curator', $target->fresh()->role);
    }

    public function test_admin_cannot_change_curator_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $curator = User::factory()->create(['role' => 'curator']);

        $this->actingAs($admin)->postJson("/api/admin/users/{$curator->id}/role", ['role' => 'user'])->assertForbidden();
        $this->assertSame('curator', $curator->fresh()->role);
    }

    public function test_helper_role_can_be_assigned_and_follows_server_rank(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)->postJson("/api/admin/users/{$target->id}/role", ['role' => 'helper'])->assertSuccessful();
        $this->assertSame('helper', $target->fresh()->role);
        $this->assertSame('Хелпер', $target->fresh()->roleLabel());
    }
}
