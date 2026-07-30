<?php

namespace Tests\Feature;

use App\Enums\MemberStatus as MemberStatusEnum;
use App\Enums\UserRole;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\MemberStatus;
use App\Models\Player;
use App\Models\Role;
use App\Models\User;
use App\Services\Clans\ActiveClanContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MemberPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_panel_returns_paginated_members(): void
    {
        $clan = $this->clan();
        $inStatus = $this->memberStatus(MemberStatusEnum::In);
        $this->createMembership($clan, $inStatus, '#PQLG2', 'Jogador');

        $this->actingAs(User::factory()->create())
            ->get('/members')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Members/Index')
                ->has('members.data', 1)
                ->where('members.data.0.status.slug', MemberStatusEnum::In->value)
                ->where('memberStats.total', 1)
                ->where('memberStats.inClan', 1)
                ->where('memberStats.outClan', 0));
    }

    public function test_member_panel_paginates_twenty_members_at_a_time(): void
    {
        $clan = $this->clan();
        $inStatus = $this->memberStatus(MemberStatusEnum::In);

        foreach (range(1, 50) as $index) {
            $this->createMembership(
                $clan,
                $inStatus,
                "#PAGE{$index}",
                "Jogador {$index}",
            );
        }
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/members')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('members.data', 20)
                ->where('members.current_page', 1)
                ->where('members.total', 50)
                ->where('memberStats.total', 50));

        $this->actingAs($user)
            ->get('/members?page=3')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('members.data', 10)
                ->where('members.current_page', 3)
                ->where('members.total', 50));
    }

    public function test_member_filters_and_sorting_are_applied_server_side(): void
    {
        $clan = $this->clan();
        $inStatus = $this->memberStatus(MemberStatusEnum::In);
        $outStatus = $this->memberStatus(MemberStatusEnum::Out);
        $members = [
            [$inStatus->id, '#ALPHA', 'Alpha', 'leader', 16],
            [$inStatus->id, '#BRAVO', 'Bravo', 'member', 14],
            [$outStatus->id, '#CHARLIE', 'Charlie', 'coLeader', 16],
        ];

        foreach ($members as [$statusId, $tag, $name, $role, $townHall]) {
            $this->createMembership(
                $clan,
                MemberStatus::query()->findOrFail($statusId),
                $tag,
                $name,
                $role,
                $townHall,
            );
        }
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/members')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('members.data', 2)
                ->where('filters.status', 'in'));

        $this->actingAs($user)
            ->get('/members?status=all&search=Charlie&town_hall=16&role=coLeader')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('members.data', 1)
                ->where('members.data.0.name', 'Charlie')
                ->where('members.data.0.town_hall_level', 16));

        $this->actingAs($user)
            ->get('/members?status=all&sort=town_hall&direction=desc')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('members.data.0.town_hall_level', 16)
                ->where('members.data.2.town_hall_level', 14));

        $this->actingAs($user)
            ->get('/members?status=all&sort=role&direction=asc')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('members.data.0.name', 'Alpha')
                ->where('members.data.1.name', 'Charlie')
                ->where('members.data.2.name', 'Bravo'));
    }

    public function test_sync_adds_new_members_and_only_updates_status_of_known_members(): void
    {
        config([
            'services.clash_of_clans.base_url' => 'https://api.clashofclans.test/v1',
            'services.clash_of_clans.token' => 'api-token',
            'services.clash_of_clans.demo_mode' => false,
        ]);

        $clan = $this->clan();
        $inStatus = $this->memberStatus(MemberStatusEnum::In);
        $outStatus = $this->memberStatus(MemberStatusEnum::Out);

        $returningMember = $this->createMembership(
            $clan,
            $outStatus,
            '#PQLG2',
            'Nome preservado',
        );
        $departedMember = $this->createMembership(
            $clan,
            $inStatus,
            '#V9Y20',
            'Jogador que saiu',
        );

        Http::fake([
            'api.clashofclans.test/v1/clans/*' => Http::response([
                'tag' => '#QGRJ2',
                'memberList' => [
                    [
                        'tag' => '#PQLG2',
                        'name' => 'Nome alterado na API',
                        'role' => 'member',
                        'townHallLevel' => 16,
                    ],
                    [
                        'tag' => '#QGRJ9',
                        'name' => 'Novo jogador',
                        'role' => 'coLeader',
                        'townHallLevel' => 15,
                    ],
                ],
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->post('/members/sync')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('syncSummary', [
                'added' => 1,
                'moved_in' => 1,
                'moved_out' => 1,
            ]);

        $returningMember->refresh();
        $departedMember->refresh();

        $this->assertSame($inStatus->id, $returningMember->member_status_id);
        $this->assertSame('Nome preservado', $returningMember->player->name);
        $this->assertSame($outStatus->id, $departedMember->member_status_id);
        $this->assertDatabaseHas(Player::class, [
            'player_tag' => '#QGRJ9',
            'name' => 'Novo jogador',
            'town_hall_level' => 15,
        ]);
        $this->assertDatabaseHas(ClanMembership::class, [
            'clan_id' => $clan->id,
            'member_status_id' => $inStatus->id,
        ]);
        $this->assertSame(16, $returningMember->player->town_hall_level);
        $this->assertDatabaseCount(Player::class, 3);
        $this->assertDatabaseCount(ClanMembership::class, 3);
        $this->assertNotNull($clan->fresh()->members_synced_at);
    }

    public function test_sync_requires_a_configured_clan(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/members/sync')
            ->assertSessionHasErrors('sync');

        $this->assertDatabaseCount(Player::class, 0);
        $this->assertDatabaseCount(ClanMembership::class, 0);
    }

    public function test_member_panel_only_displays_the_active_clan(): void
    {
        $primary = $this->clan();
        $secondary = Clan::query()->create(['tag' => '#V9Y20']);
        $status = $this->memberStatus(MemberStatusEnum::In);
        $this->createMembership($primary, $status, '#PQLG2', 'Principal');
        $this->createMembership($secondary, $status, '#QGRJ9', 'Secundário');

        $this->actingAs(User::factory()->create())
            ->withSession([ActiveClanContext::SESSION_KEY => $secondary->id])
            ->get('/members?status=all')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('members.data', 1)
                ->where('members.data.0.name', 'Secundário')
                ->where('memberStats.total', 1)
                ->where('clan.id', $secondary->id));
    }

    public function test_member_sync_does_not_change_another_clan_memberships(): void
    {
        config([
            'services.clash_of_clans.base_url' => 'https://api.clashofclans.test/v1',
            'services.clash_of_clans.token' => 'api-token',
            'services.clash_of_clans.demo_mode' => false,
        ]);
        $primary = $this->clan();
        $secondary = Clan::query()->create(['tag' => '#V9Y20']);
        $status = $this->memberStatus(MemberStatusEnum::In);
        $secondaryMembership = $this->createMembership(
            $secondary,
            $status,
            '#PQLG2',
            'Secundário',
        );
        Http::fake([
            'api.clashofclans.test/v1/clans/*' => Http::response([
                'tag' => '#QGRJ2',
                'memberList' => [],
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->withSession([ActiveClanContext::SESSION_KEY => $primary->id])
            ->post('/members/sync')
            ->assertSessionHasNoErrors();

        $this->assertSame(
            MemberStatusEnum::In->value,
            $secondaryMembership->fresh()->status->slug,
        );
    }

    public function test_guest_can_not_access_or_sync_members(): void
    {
        $this->get('/members')->assertRedirect(route('login'));
        $this->post('/members/sync')->assertRedirect(route('login'));
    }

    public function test_member_and_coleader_can_view_but_can_not_sync_members(): void
    {
        foreach ([UserRole::Member, UserRole::CoLeader] as $userRole) {
            $role = Role::query()
                ->where('slug', $userRole->value)
                ->sole();
            $user = User::factory()->create(['role_id' => $role->id]);

            $this->actingAs($user)->get('/members')->assertOk();
            $this->actingAs($user)->post('/members/sync')->assertForbidden();
        }
    }

    private function memberStatus(MemberStatusEnum $status): MemberStatus
    {
        return MemberStatus::query()->where('slug', $status->value)->sole();
    }

    private function clan(): Clan
    {
        return Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);
    }

    private function createMembership(
        Clan $clan,
        MemberStatus $status,
        string $tag,
        string $name,
        ?string $role = null,
        ?int $townHallLevel = null,
    ): ClanMembership {
        $player = Player::query()->create([
            'player_tag' => $tag,
            'name' => $name,
            'town_hall_level' => $townHallLevel,
        ]);

        return ClanMembership::query()->create([
            'clan_id' => $clan->id,
            'player_id' => $player->id,
            'member_status_id' => $status->id,
            'role' => $role,
        ]);
    }
}
