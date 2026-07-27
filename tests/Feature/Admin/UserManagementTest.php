<?php

namespace Tests\Feature\Admin;

use App\Enums\MemberStatus as MemberStatusEnum;
use App\Enums\UserRole;
use App\Models\Member;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users_without_exposing_access_codes(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Outro usuário']);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Users/Index')
                ->has('users', 3)
                ->missing('users.0.access_code')
                ->has('roles', 4));
    }

    public function test_admin_can_create_user_and_access_code_is_shown_once(): void
    {
        $role = Role::query()->where('slug', UserRole::CoLeader->value)->sole();

        $response = $this->actingAs($this->admin())
            ->post('/admin/users', [
                'name' => 'Novo usuário',
                'role_id' => $role->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('generatedAccess');

        $generatedAccess = $response->getSession()->get('generatedAccess');
        $user = User::query()->where('name', 'Novo usuário')->sole();

        $this->assertMatchesRegularExpression(
            '/^\d{6}$/',
            $generatedAccess['code'],
        );
        $this->assertTrue(Hash::check($generatedAccess['code'], $user->access_code));
        $this->assertSame($role->id, $user->role_id);
    }

    public function test_admin_can_regenerate_an_access_code(): void
    {
        $user = User::factory()->create(['access_code' => 'old-access-code']);

        $response = $this->actingAs($this->admin())
            ->post("/admin/users/{$user->id}/access-code")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('generatedAccess');

        $generatedCode = $response->getSession()->get('generatedAccess')['code'];
        $user->refresh();

        $this->assertFalse(Hash::check('old-access-code', $user->access_code));
        $this->assertTrue(Hash::check($generatedCode, $user->access_code));
    }

    public function test_admin_can_link_multiple_members_to_a_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $status = MemberStatus::query()
            ->where('slug', MemberStatusEnum::In->value)
            ->sole();
        $firstMember = Member::query()->create([
            'member_status_id' => $status->id,
            'player_tag' => '#PQLG2',
            'name' => 'Primeira conta',
        ]);
        $secondMember = Member::query()->create([
            'member_status_id' => $status->id,
            'player_tag' => '#QGRJ9',
            'name' => 'Segunda conta',
        ]);

        $this->actingAs($this->admin())
            ->put("/admin/users/{$user->id}/members", [
                'member_ids' => [$firstMember->id, $secondMember->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($user->id, $firstMember->fresh()->user_id);
        $this->assertSame($user->id, $secondMember->fresh()->user_id);

        $this->actingAs($this->admin())
            ->put("/admin/users/{$otherUser->id}/members", [
                'member_ids' => [$firstMember->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($otherUser->id, $firstMember->fresh()->user_id);
        $this->assertSame($user->id, $secondMember->fresh()->user_id);

        $this->actingAs($this->admin())
            ->put("/admin/users/{$user->id}/members", [
                'member_ids' => [],
            ]);

        $this->assertNull($secondMember->fresh()->user_id);
    }

    public function test_non_admin_can_not_manage_users(): void
    {
        $leader = User::factory()->create();
        $target = User::factory()->create();
        $role = Role::query()->where('slug', UserRole::Leader->value)->sole();

        $this->actingAs($leader)->get('/admin/users')->assertForbidden();
        $this->actingAs($leader)->post('/admin/users', [
            'name' => 'Acesso indevido',
            'role_id' => $role->id,
        ])->assertForbidden();
        $this->actingAs($leader)
            ->post("/admin/users/{$target->id}/access-code")
            ->assertForbidden();
        $this->actingAs($leader)
            ->put("/admin/users/{$target->id}/members", ['member_ids' => []])
            ->assertForbidden();
    }

    private function admin(): User
    {
        $adminRole = Role::query()
            ->where('slug', UserRole::Admin->value)
            ->sole();

        return User::factory()->create(['role_id' => $adminRole->id]);
    }
}
