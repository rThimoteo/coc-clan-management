<?php

namespace Tests\Feature;

use App\Enums\MemberStatus as MemberStatusEnum;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\MemberStatus;
use App\Models\Player;
use App\Models\User;
use App\Models\War;
use App\Services\Clans\ActiveClanContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MemberDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_detail_exposes_identity_metrics_series_and_histories(): void
    {
        [$clan, $player, $membership] = $this->context();
        $war = $this->war($clan, $player);
        $war->attacks()->create([
            'attacker_player_id' => $player->id,
            'attacker_tag' => $player->player_tag,
            'defender_tag' => '#TARGET',
            'attack_order' => 1,
            'stars' => 3,
            'destruction_percentage' => 100,
        ]);
        $war->attacks()->create([
            'defender_player_id' => $player->id,
            'attacker_tag' => '#ENEMY',
            'defender_tag' => $player->player_tag,
            'attack_order' => 2,
            'stars' => 2,
            'destruction_percentage' => 75,
        ]);

        $this->actingAs(User::factory()->create())
            ->withSession([ActiveClanContext::SESSION_KEY => $clan->id])
            ->get("/members/{$membership->id}?type=regular&window=5")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Members/Show')
                ->where('membership.id', $membership->id)
                ->where('membership.player.id', $player->id)
                ->where('filters.type', 'regular')
                ->where('filters.window', 5)
                ->where('metrics.wars', 1)
                ->where('metrics.attacks_used', 1)
                ->where('metrics.attacks_available', 2)
                ->where('metrics.average_stars', 3)
                ->where('metrics.defenses', 1)
                ->has('series', 1)
                ->has('attacks.data', 1)
                ->has('defenses.data', 1));
    }

    public function test_member_detail_uses_safe_filter_defaults(): void
    {
        [$clan, , $membership] = $this->context();

        $this->actingAs(User::factory()->create())
            ->withSession([ActiveClanContext::SESSION_KEY => $clan->id])
            ->get("/members/{$membership->id}?type=invalid&window=999")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.type', 'all')
                ->where('filters.window', 10));
    }

    public function test_member_detail_is_isolated_by_the_active_clan(): void
    {
        [$primary, , $membership] = $this->context();
        $secondary = Clan::query()->create([
            'tag' => '#V9Y20',
            'name' => 'Secundário',
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([ActiveClanContext::SESSION_KEY => $primary->id])
            ->get("/members/{$membership->id}")
            ->assertOk();

        $this->actingAs($user)
            ->withSession([ActiveClanContext::SESSION_KEY => $secondary->id])
            ->get("/members/{$membership->id}")
            ->assertNotFound();
    }

    public function test_guest_can_not_open_member_detail(): void
    {
        [, , $membership] = $this->context();

        $this->get("/members/{$membership->id}")
            ->assertRedirect(route('login'));
    }

    /**
     * @return array{Clan, Player, ClanMembership}
     */
    private function context(): array
    {
        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'name' => 'Principal',
            'is_default' => true,
        ]);
        $player = Player::query()->create([
            'player_tag' => '#PQLG2',
            'name' => 'Ayla',
            'town_hall_level' => 17,
        ]);
        $membership = ClanMembership::query()->create([
            'clan_id' => $clan->id,
            'player_id' => $player->id,
            'member_status_id' => MemberStatus::query()
                ->where('slug', MemberStatusEnum::In->value)
                ->sole()
                ->id,
            'role' => 'leader',
        ]);

        return [$clan, $player, $membership];
    }

    private function war(Clan $clan, Player $player): War
    {
        $war = War::query()->create([
            'clan_id' => $clan->id,
            'external_key' => hash('sha256', "member-detail-{$clan->id}"),
            'type' => 'regular',
            'state' => 'warEnded',
            'team_size' => 15,
            'end_time' => now()->subDay(),
            'opponent_tag' => '#RIVAL',
            'opponent_name' => 'Rival',
            'has_details' => true,
        ]);
        $war->members()->create([
            'player_id' => $player->id,
            'side' => 'clan',
            'player_tag' => $player->player_tag,
            'name' => $player->name,
            'map_position' => 1,
            'townhall_level' => 17,
        ]);

        return $war;
    }
}
