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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays_operational_metrics_and_recent_wars(): void
    {
        $this->travelTo('2026-08-15 12:00:00');

        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);
        $inStatus = MemberStatus::query()
            ->where('slug', MemberStatusEnum::In->value)
            ->sole();
        $outStatus = MemberStatus::query()
            ->where('slug', MemberStatusEnum::Out->value)
            ->sole();

        foreach (range(1, 3) as $index) {
            $player = Player::query()->create([
                'player_tag' => "#ACTIVE{$index}",
                'name' => "Membro ativo {$index}",
            ]);
            ClanMembership::query()->create([
                'clan_id' => $clan->id,
                'player_id' => $player->id,
                'member_status_id' => $inStatus->id,
            ]);
        }
        $inactivePlayer = Player::query()->create([
            'player_tag' => '#INACTIVE',
            'name' => 'Membro fora',
        ]);
        ClanMembership::query()->create([
            'clan_id' => $clan->id,
            'player_id' => $inactivePlayer->id,
            'member_status_id' => $outStatus->id,
        ]);

        $this->createWar($clan, 'win', now()->subDays(2), 'Vitória recente');
        $this->createWar($clan, 'lose', now()->subDay(), 'Derrota recente');
        $this->createWar($clan, 'win', now()->subMonthNoOverflow(), 'Guerra anterior');

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('metrics.activeMembers', 3)
                ->where('metrics.monthlyWars', 2)
                ->where('metrics.winRate', 50)
                ->has('recentWars', 3)
                ->where('recentWars.0.opponent_name', 'Derrota recente')
                ->where('recentWars.1.opponent_name', 'Vitória recente'));
    }

    public function test_dashboard_handles_an_empty_database(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('metrics.activeMembers', 0)
                ->where('metrics.monthlyWars', 0)
                ->where('metrics.winRate', null)
                ->has('recentWars', 0)
                ->where('activeWar', null));
    }

    public function test_dashboard_only_aggregates_the_active_clan(): void
    {
        $primary = Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);
        $secondary = Clan::query()->create(['tag' => '#V9Y20']);
        $status = MemberStatus::query()
            ->where('slug', MemberStatusEnum::In->value)
            ->sole();

        foreach ([$primary, $secondary] as $index => $clan) {
            $player = Player::query()->create([
                'player_tag' => "#PLAYER{$index}",
                'name' => "Player {$index}",
            ]);
            ClanMembership::query()->create([
                'clan_id' => $clan->id,
                'player_id' => $player->id,
                'member_status_id' => $status->id,
            ]);
        }
        $this->createWar($primary, 'win', now()->subDay(), 'Rival principal');
        $this->createWar($secondary, 'lose', now()->subDay(), 'Rival secundário');

        $this->actingAs(User::factory()->create())
            ->withSession([ActiveClanContext::SESSION_KEY => $secondary->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('clan.id', $secondary->id)
                ->where('metrics.activeMembers', 1)
                ->where('metrics.monthlyWars', 1)
                ->where('metrics.winRate', 0)
                ->has('recentWars', 1)
                ->where('recentWars.0.opponent_name', 'Rival secundário'));
    }

    public function test_dashboard_exposes_the_cwl_context_for_an_active_league_war(): void
    {
        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);
        $league = $clan->warLeagues()->create([
            'season' => '2026-08',
            'state' => 'preparation',
        ]);
        $round = $league->rounds()->create(['round_number' => 1]);
        $war = $this->createWar(
            $clan,
            'win',
            now()->addDay(),
            'Rival CWL',
        );
        $war->update([
            'type' => 'cwl',
            'state' => 'preparation',
        ]);
        $round->wars()->create([
            'war_tag' => '#CWL01',
            'status' => 'synced',
            'war_id' => $war->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('activeWar.id', $war->id)
                ->where('activeWar.type', 'cwl')
                ->where(
                    'activeWar.league_round_war.round.clan_war_league_id',
                    $league->id,
                ));
    }

    private function createWar(Clan $clan, string $result, mixed $endTime, string $opponent): War
    {
        return War::query()->create([
            'clan_id' => $clan->id,
            'external_key' => hash('sha256', $opponent),
            'result' => $result,
            'team_size' => 15,
            'end_time' => $endTime,
            'has_details' => true,
            'clan_stars' => $result === 'win' ? 40 : 35,
            'clan_destruction_percentage' => 95,
            'opponent_tag' => '#RIVAL'.substr(md5($opponent), 0, 5),
            'opponent_name' => $opponent,
            'opponent_stars' => $result === 'win' ? 35 : 40,
            'opponent_destruction_percentage' => 90,
        ]);
    }
}
