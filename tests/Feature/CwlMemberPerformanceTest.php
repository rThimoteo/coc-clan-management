<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\ClanWarLeague;
use App\Models\War;
use App\Services\Wars\CwlMemberPerformance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CwlMemberPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ranks_members_using_only_the_selected_cwl(): void
    {
        $clan = Clan::query()->create(['tag' => '#CLAN', 'name' => 'Principal']);
        $league = $this->league($clan, '2026-08');
        $first = $this->leagueWar($clan, $league, 1, 'Rival 1');
        $second = $this->leagueWar($clan, $league, 2, 'Rival 2');

        foreach ([$first, $second] as $war) {
            $this->member($war, '#ALPHA', 'Alpha', 1);
            $this->member($war, '#BRAVO', 'Bravo', 2);
        }

        $this->attack($first, '#ALPHA', '#ENEMY1', 3, 100, 1);
        $this->attack($first, '#ENEMY1', '#ALPHA', 1, 50, 2);
        $this->attack($first, '#ENEMY2', '#ALPHA', 2, 80, 3);
        $this->attack($first, '#ENEMY3', '#BRAVO', 0, 20, 4);
        $this->attack($first, '#BRAVO', '#ENEMY7', 2, 70, 5);
        $this->attack($second, '#ALPHA', '#ENEMY4', 2, 85, 1);
        $this->attack($second, '#BRAVO', '#ENEMY5', 3, 100, 2);
        $this->attack($second, '#ENEMY5', '#BRAVO', 1, 45, 3);

        $otherLeague = $this->league($clan, '2026-07');
        $otherWar = $this->leagueWar($clan, $otherLeague, 1, 'Outro rival');
        $this->member($otherWar, '#BRAVO', 'Bravo', 1);
        $this->attack($otherWar, '#BRAVO', '#ENEMY6', 3, 100, 1);

        $preparation = $this->leagueWar(
            $clan,
            $league,
            3,
            'Próximo rival',
            'preparation',
        );
        $this->member($preparation, '#ALPHA', 'Alpha', 1);
        $this->member($preparation, '#BRAVO', 'Bravo', 2);

        $performance = app(CwlMemberPerformance::class)->forLeague($league);

        $this->assertSame([
            [
                'player_tag' => '#ALPHA',
                'name' => 'Alpha',
                'stars' => 5,
                'destruction' => 185.0,
                'defensive_stars' => 1,
                'attacks_made' => 2,
                'attacks_available' => 2,
            ],
            [
                'player_tag' => '#BRAVO',
                'name' => 'Bravo',
                'stars' => 5,
                'destruction' => 170.0,
                'defensive_stars' => 5,
                'attacks_made' => 2,
                'attacks_available' => 2,
            ],
        ], $performance);
    }

    private function league(Clan $clan, string $season): ClanWarLeague
    {
        return $clan->warLeagues()->create([
            'season' => $season,
            'state' => 'inWar',
        ]);
    }

    private function leagueWar(
        Clan $clan,
        ClanWarLeague $league,
        int $roundNumber,
        string $opponent,
        string $state = 'warEnded',
    ): War {
        $war = $clan->wars()->create([
            'external_key' => hash('sha256', "{$league->id}|{$roundNumber}"),
            'type' => 'cwl',
            'state' => $state,
            'team_size' => 15,
            'end_time' => now()->subDay(),
            'has_details' => true,
            'opponent_tag' => "#RIVAL{$roundNumber}",
            'opponent_name' => $opponent,
        ]);
        $round = $league->rounds()->create(['round_number' => $roundNumber]);
        $round->wars()->create([
            'war_tag' => "#WAR{$league->id}{$roundNumber}",
            'status' => 'synced',
            'state' => $state,
            'war_id' => $war->id,
        ]);

        return $war;
    }

    private function member(War $war, string $tag, string $name, int $position): void
    {
        $war->members()->create([
            'side' => 'clan',
            'player_tag' => $tag,
            'name' => $name,
            'map_position' => $position,
            'townhall_level' => 17,
        ]);
    }

    private function attack(
        War $war,
        string $attacker,
        string $defender,
        int $stars,
        float $destruction,
        int $order,
    ): void {
        $war->attacks()->create([
            'attacker_tag' => $attacker,
            'defender_tag' => $defender,
            'attack_order' => $order,
            'stars' => $stars,
            'destruction_percentage' => $destruction,
        ]);
    }
}
