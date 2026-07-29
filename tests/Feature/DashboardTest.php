<?php

namespace Tests\Feature;

use App\Enums\MemberStatus as MemberStatusEnum;
use App\Models\Member;
use App\Models\MemberStatus;
use App\Models\User;
use App\Models\War;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays_operational_metrics_and_recent_wars(): void
    {
        $inStatus = MemberStatus::query()
            ->where('slug', MemberStatusEnum::In->value)
            ->sole();
        $outStatus = MemberStatus::query()
            ->where('slug', MemberStatusEnum::Out->value)
            ->sole();

        foreach (range(1, 3) as $index) {
            Member::query()->create([
                'member_status_id' => $inStatus->id,
                'player_tag' => "#ACTIVE{$index}",
                'name' => "Membro ativo {$index}",
            ]);
        }
        Member::query()->create([
            'member_status_id' => $outStatus->id,
            'player_tag' => '#INACTIVE',
            'name' => 'Membro fora',
        ]);

        $this->createWar('win', now()->subDays(2), 'Vitória recente');
        $this->createWar('lose', now()->subDay(), 'Derrota recente');
        $this->createWar('win', now()->subMonthNoOverflow(), 'Guerra anterior');

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

    private function createWar(string $result, mixed $endTime, string $opponent): War
    {
        return War::query()->create([
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
