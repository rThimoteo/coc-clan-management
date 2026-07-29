<?php

namespace Tests\Feature;

use App\Enums\MemberStatus as MemberStatusEnum;
use App\Enums\UserRole;
use App\Models\Clan;
use App\Models\Member;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MemberPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_panel_returns_paginated_members(): void
    {
        $inStatus = $this->memberStatus(MemberStatusEnum::In);
        Member::query()->create([
            'member_status_id' => $inStatus->id,
            'player_tag' => '#PQLG2',
            'name' => 'Jogador',
        ]);

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
        $inStatus = $this->memberStatus(MemberStatusEnum::In);

        foreach (range(1, 50) as $index) {
            Member::query()->create([
                'member_status_id' => $inStatus->id,
                'player_tag' => "#PAGE{$index}",
                'name' => "Jogador {$index}",
            ]);
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
        $inStatus = $this->memberStatus(MemberStatusEnum::In);
        $outStatus = $this->memberStatus(MemberStatusEnum::Out);
        $members = [
            [$inStatus->id, '#ALPHA', 'Alpha', 'leader', 16],
            [$inStatus->id, '#BRAVO', 'Bravo', 'member', 14],
            [$outStatus->id, '#CHARLIE', 'Charlie', 'coLeader', 16],
        ];

        foreach ($members as [$statusId, $tag, $name, $role, $townHall]) {
            Member::query()->create([
                'member_status_id' => $statusId,
                'player_tag' => $tag,
                'name' => $name,
                'role' => $role,
                'town_hall_level' => $townHall,
            ]);
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

        $clan = Clan::query()->create(['tag' => '#QGRJ2']);
        $inStatus = $this->memberStatus(MemberStatusEnum::In);
        $outStatus = $this->memberStatus(MemberStatusEnum::Out);

        $returningMember = Member::query()->create([
            'member_status_id' => $outStatus->id,
            'player_tag' => '#PQLG2',
            'name' => 'Nome preservado',
        ]);
        $departedMember = Member::query()->create([
            'member_status_id' => $inStatus->id,
            'player_tag' => '#V9Y20',
            'name' => 'Jogador que saiu',
        ]);

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
        $this->assertSame('Nome preservado', $returningMember->name);
        $this->assertSame($outStatus->id, $departedMember->member_status_id);
        $this->assertDatabaseHas(Member::class, [
            'player_tag' => '#QGRJ9',
            'name' => 'Novo jogador',
            'member_status_id' => $inStatus->id,
            'town_hall_level' => 15,
        ]);
        $this->assertSame(16, $returningMember->town_hall_level);
        $this->assertDatabaseCount(Member::class, 3);
        $this->assertNotNull($clan->fresh()->members_synced_at);
    }

    public function test_sync_requires_a_configured_clan(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/members/sync')
            ->assertSessionHasErrors('sync');

        $this->assertDatabaseCount(Member::class, 0);
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
}
