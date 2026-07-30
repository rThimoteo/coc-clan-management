<?php

namespace Tests\Feature\Admin;

use App\Enums\MemberStatus as MemberStatusEnum;
use App\Enums\UserRole;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\MemberStatus;
use App\Models\Player;
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
                ->has('users.data', 3)
                ->missing('users.data.0.access_code')
                ->has('roles', 4));
    }

    public function test_user_list_exposes_players_with_their_clan_memberships(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Jogador']);
        $primary = Clan::query()->create([
            'tag' => '#QGRJ2',
            'name' => 'Principal',
            'is_default' => true,
        ]);
        $secondary = Clan::query()->create([
            'tag' => '#V9Y20',
            'name' => 'Academia',
        ]);
        $player = Player::query()->create([
            'user_id' => $user->id,
            'player_tag' => '#PQLG2',
            'name' => 'Conta principal',
        ]);
        $this->membership($primary, $player);
        $this->membership($secondary, $player);

        $this->actingAs($admin)
            ->get('/admin/users?search=Jogador')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.players.0.id', $player->id)
                ->has('users.data.0.players.0.memberships', 2)
                ->where('players.0.memberships.0.clan.id', $primary->id)
                ->where('players.0.memberships.1.clan.id', $secondary->id));
    }

    public function test_user_list_is_paginated_twenty_at_a_time(): void
    {
        $admin = $this->admin();
        User::factory()->count(25)->create();

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('users.data', 20)
                ->where('users.current_page', 1)
                ->where('users.total', 27));

        $this->actingAs($admin)
            ->get('/admin/users?page=2')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('users.data', 7)
                ->where('users.current_page', 2)
                ->where('users.total', 27));
    }

    public function test_users_can_be_filtered_by_name(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Usuário Encontrável']);
        User::factory()->create(['name' => 'Outro acesso']);

        $this->actingAs($admin)
            ->get('/admin/users?search=Encontrável')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Usuário Encontrável')
                ->where('filters.search', 'Encontrável'));
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

    public function test_admin_can_change_another_users_role_but_not_their_own(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $memberRole = $this->role(UserRole::Member);

        $this->actingAs($admin)
            ->patch("/admin/users/{$target->id}/role", [
                'role_id' => $memberRole->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($memberRole->id, $target->fresh()->role_id);

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/role", [
                'role_id' => $memberRole->id,
            ])
            ->assertForbidden();
    }

    public function test_promoting_another_user_to_admin_requires_explicit_confirmation(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $adminRole = $this->role(UserRole::Admin);

        $this->actingAs($admin)
            ->patch("/admin/users/{$target->id}/role", [
                'role_id' => $adminRole->id,
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertNotSame($adminRole->id, $target->fresh()->role_id);

        $this->actingAs($admin)
            ->patch("/admin/users/{$target->id}/role", [
                'role_id' => $adminRole->id,
                'confirm_admin' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($adminRole->id, $target->fresh()->role_id);
    }

    public function test_admin_can_not_change_another_admins_role(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();

        $this->actingAs($admin)
            ->patch("/admin/users/{$otherAdmin->id}/role", [
                'role_id' => $this->role(UserRole::Member)->id,
            ])
            ->assertForbidden();
    }

    public function test_leader_can_only_switch_members_and_coleaders_between_those_roles(): void
    {
        $leader = User::factory()->create([
            'role_id' => $this->role(UserRole::Leader)->id,
        ]);
        $member = User::factory()->create([
            'role_id' => $this->role(UserRole::Member)->id,
        ]);
        $coLeader = User::factory()->create([
            'role_id' => $this->role(UserRole::CoLeader)->id,
        ]);

        $this->actingAs($leader)
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('permissions.createUsers', false)
                ->where('permissions.generateCodes', false)
                ->where('permissions.linkPlayers', true));

        $this->actingAs($leader)
            ->patch("/admin/users/{$member->id}/role", [
                'role_id' => $this->role(UserRole::CoLeader)->id,
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(
            UserRole::CoLeader->value,
            $member->fresh()->role->slug,
        );

        $this->actingAs($leader)
            ->patch("/admin/users/{$coLeader->id}/role", [
                'role_id' => $this->role(UserRole::Member)->id,
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(
            UserRole::Member->value,
            $coLeader->fresh()->role->slug,
        );
    }

    public function test_leader_cannot_assign_privileged_roles_or_change_a_leader_or_admin(): void
    {
        $leader = User::factory()->create([
            'role_id' => $this->role(UserRole::Leader)->id,
        ]);
        $member = User::factory()->create([
            'role_id' => $this->role(UserRole::Member)->id,
        ]);
        $otherLeader = User::factory()->create([
            'role_id' => $this->role(UserRole::Leader)->id,
        ]);
        $admin = $this->admin();

        foreach ([UserRole::Leader, UserRole::Admin] as $forbiddenRole) {
            $this->actingAs($leader)
                ->patch("/admin/users/{$member->id}/role", [
                    'role_id' => $this->role($forbiddenRole)->id,
                    'confirm_admin' => true,
                ])
                ->assertForbidden();
        }

        foreach ([$otherLeader, $admin] as $protectedUser) {
            $this->actingAs($leader)
                ->patch("/admin/users/{$protectedUser->id}/role", [
                    'role_id' => $this->role(UserRole::Member)->id,
                ])
                ->assertForbidden();
        }
    }

    public function test_admin_can_link_multiple_players_from_different_clans_to_a_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $primary = Clan::query()->create([
            'tag' => '#QGRJ2',
            'name' => 'Principal',
            'is_default' => true,
        ]);
        $secondary = Clan::query()->create([
            'tag' => '#V9Y20',
            'name' => 'Academia',
        ]);
        $firstPlayer = Player::query()->create([
            'player_tag' => '#PQLG2',
            'name' => 'Primeira conta',
        ]);
        $secondPlayer = Player::query()->create([
            'player_tag' => '#QGRJ9',
            'name' => 'Segunda conta',
        ]);
        $this->membership($primary, $firstPlayer);
        $this->membership($secondary, $secondPlayer);

        $this->actingAs($this->admin())
            ->put("/admin/users/{$user->id}/players", [
                'player_ids' => [$firstPlayer->id, $secondPlayer->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($user->id, $firstPlayer->fresh()->user_id);
        $this->assertSame($user->id, $secondPlayer->fresh()->user_id);

        $this->actingAs($this->admin())
            ->put("/admin/users/{$otherUser->id}/players", [
                'player_ids' => [$firstPlayer->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($otherUser->id, $firstPlayer->fresh()->user_id);
        $this->assertSame($user->id, $secondPlayer->fresh()->user_id);

        $this->actingAs($this->admin())
            ->put("/admin/users/{$user->id}/players", [
                'player_ids' => [],
            ]);

        $this->assertNull($secondPlayer->fresh()->user_id);
    }

    public function test_admin_can_delete_a_non_admin_and_linked_players_are_preserved(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create([
            'role_id' => $this->role(UserRole::Member)->id,
        ]);
        $player = Player::query()->create([
            'user_id' => $target->id,
            'player_tag' => '#QGRJ9',
            'name' => 'Conta preservada',
        ]);

        $this->actingAs($admin)
            ->delete("/admin/users/{$target->id}")
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($target);
        $this->assertNull($player->fresh()->user_id);
    }

    public function test_admin_can_not_delete_themselves_or_another_admin(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();

        foreach ([$admin, $otherAdmin] as $protectedAdmin) {
            $this->actingAs($admin)
                ->delete("/admin/users/{$protectedAdmin->id}")
                ->assertForbidden();

            $this->assertModelExists($protectedAdmin);
        }
    }

    public function test_leader_can_link_members_but_can_not_create_users_or_generate_codes(): void
    {
        $leader = User::factory()->create();
        $target = User::factory()->create();
        $role = Role::query()->where('slug', UserRole::Leader->value)->sole();

        $this->actingAs($leader)->get('/admin/users')->assertOk();
        $this->actingAs($leader)->post('/admin/users', [
            'name' => 'Acesso indevido',
            'role_id' => $role->id,
        ])->assertForbidden();
        $this->actingAs($leader)
            ->post("/admin/users/{$target->id}/access-code")
            ->assertForbidden();
        $this->actingAs($leader)
            ->delete("/admin/users/{$target->id}")
            ->assertForbidden();
        $this->actingAs($leader)
            ->put("/admin/users/{$target->id}/players", ['player_ids' => []])
            ->assertSessionHasNoErrors();
    }

    public function test_coleader_and_member_can_not_view_or_change_user_roles(): void
    {
        $target = User::factory()->create();

        foreach ([UserRole::CoLeader, UserRole::Member] as $role) {
            $actor = User::factory()->create([
                'role_id' => $this->role($role)->id,
            ]);

            $this->actingAs($actor)->get('/admin/users')->assertForbidden();
            $this->actingAs($actor)
                ->patch("/admin/users/{$target->id}/role", [
                    'role_id' => $this->role(UserRole::Member)->id,
                ])
                ->assertForbidden();
        }
    }

    private function admin(): User
    {
        $adminRole = Role::query()
            ->where('slug', UserRole::Admin->value)
            ->sole();

        return User::factory()->create(['role_id' => $adminRole->id]);
    }

    private function role(UserRole $role): Role
    {
        return Role::query()->where('slug', $role->value)->sole();
    }

    private function membership(Clan $clan, Player $player): ClanMembership
    {
        return ClanMembership::query()->create([
            'clan_id' => $clan->id,
            'player_id' => $player->id,
            'member_status_id' => MemberStatus::query()
                ->where('slug', MemberStatusEnum::In->value)
                ->sole()
                ->id,
        ]);
    }
}
