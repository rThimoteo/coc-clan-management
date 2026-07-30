<?php

namespace Tests\Feature;

use App\Enums\MemberStatus as MemberStatusEnum;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\MemberStatus;
use App\Models\Player;
use App\Models\War;
use App\Services\Members\PlayerPerformanceQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerPerformanceQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_manual_offensive_and_defensive_examples(): void
    {
        [$clan, $player] = $this->context();
        $first = $this->war($clan, $player, 'regular', 3);
        $second = $this->war($clan, $player, 'regular', 2);
        $cwl = $this->war($clan, $player, 'cwl', 1);
        $this->attack($first, $player, true, 1, 3, 100);
        $this->attack($first, $player, true, 2, 2, 80);
        $this->attack($cwl, $player, true, 1, 1, 50);
        $this->attack($first, $player, false, 3, 3, 100);
        $this->attack($second, $player, false, 1, 1, 40);

        $result = app(PlayerPerformanceQuery::class)
            ->get($clan, $player, 'all', 10);

        $this->assertSame([
            'wars' => 3,
            'attacks_used' => 3,
            'attacks_available' => 5,
            'average_stars' => 2.0,
            'average_destruction' => 76.67,
            'defenses' => 2,
            'average_stars_conceded' => 2.0,
            'average_destruction_conceded' => 70.0,
        ], $result['metrics']);
        $this->assertCount(3, $result['series']);
        $this->assertSame($first->id, $result['series'][0]['war_id']);
        $this->assertSame(2, $result['series'][0]['attacks']);
        $this->assertSame(0, $result['series'][1]['attacks']);
        $this->assertSame(1, $result['series'][2]['available_attacks']);
    }

    public function test_unused_attacks_are_not_counted_as_zero_star_attacks(): void
    {
        [$clan, $player] = $this->context();
        $used = $this->war($clan, $player, 'regular', 2);
        $this->war($clan, $player, 'regular', 1);
        $this->attack($used, $player, true, 1, 3, 100);

        $metrics = app(PlayerPerformanceQuery::class)
            ->get($clan, $player)['metrics'];

        $this->assertSame(1, $metrics['attacks_used']);
        $this->assertSame(4, $metrics['attacks_available']);
        $this->assertSame(3.0, $metrics['average_stars']);
        $this->assertSame(100.0, $metrics['average_destruction']);
    }

    public function test_it_filters_regular_and_cwl_wars(): void
    {
        [$clan, $player] = $this->context();
        $regular = $this->war($clan, $player, 'regular', 2);
        $cwl = $this->war($clan, $player, 'cwl', 1);
        $this->attack($regular, $player, true, 1, 3, 100);
        $this->attack($cwl, $player, true, 1, 1, 50);

        $query = app(PlayerPerformanceQuery::class);
        $regularMetrics = $query->get($clan, $player, 'regular')['metrics'];
        $cwlMetrics = $query->get($clan, $player, 'cwl')['metrics'];

        $this->assertSame(1, $regularMetrics['wars']);
        $this->assertSame(2, $regularMetrics['attacks_available']);
        $this->assertSame(3.0, $regularMetrics['average_stars']);
        $this->assertSame(1, $cwlMetrics['wars']);
        $this->assertSame(1, $cwlMetrics['attacks_available']);
        $this->assertSame(1.0, $cwlMetrics['average_stars']);
    }

    public function test_it_supports_all_configured_war_windows(): void
    {
        [$clan, $player] = $this->context();

        foreach (range(1, 25) as $daysAgo) {
            $this->war($clan, $player, 'regular', $daysAgo);
        }
        $query = app(PlayerPerformanceQuery::class);

        $this->assertSame(5, $query->get($clan, $player, 'all', 5)['metrics']['wars']);
        $this->assertSame(10, $query->get($clan, $player, 'all', 10)['metrics']['wars']);
        $this->assertSame(20, $query->get($clan, $player, 'all', 20)['metrics']['wars']);
        $this->assertSame(25, $query->get($clan, $player, 'all', 'all')['metrics']['wars']);
    }

    public function test_attacks_and_defenses_are_paginated_independently(): void
    {
        [$clan, $player] = $this->context();

        foreach (range(1, 8) as $daysAgo) {
            $war = $this->war($clan, $player, 'regular', $daysAgo);
            $this->attack($war, $player, true, 1, 3, 100);
            $this->attack($war, $player, true, 2, 2, 80);
            $this->attack($war, $player, false, 3, 2, 75);
            $this->attack($war, $player, false, 4, 1, 45);
        }

        $result = app(PlayerPerformanceQuery::class)
            ->get($clan, $player, 'all', 10, 5);

        $this->assertSame(16, $result['attacks']->total());
        $this->assertCount(5, $result['attacks']->items());
        $this->assertSame('attacks_page', $result['attacks']->getPageName());
        $this->assertSame(16, $result['defenses']->total());
        $this->assertCount(5, $result['defenses']->items());
        $this->assertSame('defenses_page', $result['defenses']->getPageName());
    }

    public function test_it_isolates_the_same_player_by_clan(): void
    {
        [$primary, $player] = $this->context();
        $secondary = Clan::query()->create([
            'tag' => '#V9Y20',
            'name' => 'Secundário',
        ]);
        $this->membership($secondary, $player);
        $primaryWar = $this->war($primary, $player, 'regular', 2);
        $secondaryWar = $this->war($secondary, $player, 'regular', 1);
        $this->attack($primaryWar, $player, true, 1, 3, 100);
        $this->attack($secondaryWar, $player, true, 1, 1, 40);

        $query = app(PlayerPerformanceQuery::class);

        $this->assertSame(
            3.0,
            $query->get($primary, $player)['metrics']['average_stars'],
        );
        $this->assertSame(
            1.0,
            $query->get($secondary, $player)['metrics']['average_stars'],
        );
    }

    public function test_it_excludes_ongoing_wars_and_wars_without_details(): void
    {
        [$clan, $player] = $this->context();
        $completed = $this->war($clan, $player, 'regular', 2);
        $ongoing = $this->war($clan, $player, 'regular', -1);
        $summary = $this->war($clan, $player, 'regular', 1, false);
        $this->attack($completed, $player, true, 1, 3, 100);
        $this->attack($ongoing, $player, true, 1, 1, 20);
        $this->attack($summary, $player, true, 1, 0, 0);

        $metrics = app(PlayerPerformanceQuery::class)
            ->get($clan, $player)['metrics'];

        $this->assertSame(1, $metrics['wars']);
        $this->assertSame(3.0, $metrics['average_stars']);
    }

    /**
     * @return array{Clan, Player}
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
        $this->membership($clan, $player);

        return [$clan, $player];
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

    private function war(
        Clan $clan,
        Player $player,
        string $type,
        int $daysAgo,
        bool $detailed = true,
    ): War {
        $war = War::query()->create([
            'clan_id' => $clan->id,
            'external_key' => hash('sha256', implode('|', [
                $clan->id,
                $type,
                $daysAgo,
                (int) $detailed,
            ])),
            'type' => $type,
            'state' => $daysAgo < 0 ? 'inWar' : 'warEnded',
            'team_size' => 15,
            'end_time' => now()->subDays($daysAgo),
            'opponent_tag' => '#RIVAL'.str_pad((string) abs($daysAgo), 2, '0', STR_PAD_LEFT),
            'opponent_name' => "Rival {$daysAgo}",
            'has_details' => $detailed,
        ]);
        $war->members()->create([
            'player_id' => $player->id,
            'side' => 'clan',
            'player_tag' => $player->player_tag,
            'name' => $player->name,
            'map_position' => 1,
            'townhall_level' => $player->town_hall_level,
        ]);

        return $war;
    }

    private function attack(
        War $war,
        Player $player,
        bool $offense,
        int $order,
        int $stars,
        float $destruction,
    ): void {
        $war->attacks()->create([
            'attacker_player_id' => $offense ? $player->id : null,
            'defender_player_id' => $offense ? null : $player->id,
            'attacker_tag' => $offense ? $player->player_tag : '#ENEMY',
            'defender_tag' => $offense ? '#ENEMY' : $player->player_tag,
            'attack_order' => $order,
            'stars' => $stars,
            'destruction_percentage' => $destruction,
        ]);
    }
}
