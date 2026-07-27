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

    public function test_member_panel_lists_all_members_without_pagination(): void
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
                ->has('members', 1)
                ->where('members.0.status.slug', MemberStatusEnum::In->value)
                ->missing('members.data'));
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
                    ],
                    [
                        'tag' => '#QGRJ9',
                        'name' => 'Novo jogador',
                        'role' => 'coLeader',
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
        ]);
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

    public function test_member_user_can_view_but_can_not_sync_members(): void
    {
        $memberRole = Role::query()
            ->where('slug', UserRole::Member->value)
            ->sole();
        $user = User::factory()->create(['role_id' => $memberRole->id]);

        $this->actingAs($user)->get('/members')->assertOk();
        $this->actingAs($user)->post('/members/sync')->assertForbidden();
    }

    private function memberStatus(MemberStatusEnum $status): MemberStatus
    {
        return MemberStatus::query()->where('slug', $status->value)->sole();
    }
}
