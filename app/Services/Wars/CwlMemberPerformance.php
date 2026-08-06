<?php

namespace App\Services\Wars;

use App\Models\ClanWarLeague;
use App\Models\War;

class CwlMemberPerformance
{
    /**
     * @return list<array{
     *   player_tag: string,
     *   name: string,
     *   stars: int,
     *   destruction: float,
     *   defensive_stars: int,
     *   attacks_made: int,
     *   attacks_available: int
     * }>
     */
    public function forLeague(ClanWarLeague $league): array
    {
        $wars = War::query()
            ->where('type', 'cwl')
            ->whereIn('state', ['inWar', 'warEnded', 'ended'])
            ->whereHas('leagueRoundWar.round', fn ($query) => $query
                ->where('clan_war_league_id', $league->id))
            ->with([
                'members' => fn ($query) => $query
                    ->where('side', 'clan')
                    ->orderBy('map_position'),
                'attacks',
            ])
            ->get();
        $members = collect();

        foreach ($wars as $war) {
            foreach ($war->members as $member) {
                $tag = $member->player_tag;
                $performance = $members->get($tag, [
                    'player_tag' => $tag,
                    'name' => $member->name,
                    'stars' => 0,
                    'destruction' => 0.0,
                    'defensive_stars' => 0,
                    'attacks_made' => 0,
                    'attacks_available' => 0,
                ]);
                $attacks = $war->attacks->where('attacker_tag', $tag);
                $defenses = $war->attacks->where('defender_tag', $tag);
                $bestDefense = $defenses
                    ->sort(fn ($left, $right): int => $right->stars <=> $left->stars
                        ?: $right->destruction_percentage <=> $left->destruction_percentage)
                    ->first();

                $performance['stars'] += $attacks->sum('stars');
                $performance['destruction'] += $attacks->sum(
                    'destruction_percentage',
                );
                $performance['attacks_made'] += $attacks->count();
                $performance['attacks_available']++;

                if ($bestDefense !== null) {
                    $performance['defensive_stars'] += max(
                        0,
                        3 - $bestDefense->stars,
                    );
                }

                $members->put($tag, $performance);
            }
        }

        return $members
            ->sort(fn (array $left, array $right): int => $right['stars'] <=> $left['stars']
                ?: $right['destruction'] <=> $left['destruction']
                ?: $right['defensive_stars'] <=> $left['defensive_stars']
                ?: strcasecmp($left['name'], $right['name']))
            ->map(function (array $performance): array {
                $performance['destruction'] = round(
                    $performance['destruction'],
                    2,
                );

                return $performance;
            })
            ->values()
            ->all();
    }
}
