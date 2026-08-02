<?php

namespace App\Services\Wars;

use App\Models\ClanWarLeague;
use App\Models\ClanWarLeagueRoundWar;

class CwlStandings
{
    /**
     * @return list<array<string, int|float|string|null>>
     */
    public function forLeague(ClanWarLeague $league): array
    {
        $standings = $league->participants
            ->mapWithKeys(fn ($participant): array => [
                $participant->clan_tag => [
                    'clan_tag' => $participant->clan_tag,
                    'name' => $participant->name,
                    'badge_url' => $participant->badge_url,
                    'played' => 0,
                    'wins' => 0,
                    'draws' => 0,
                    'losses' => 0,
                    'stars' => 0,
                    'bonus_stars' => 0,
                    'score' => 0,
                    'destruction_percentage' => 0.0,
                ],
            ]);

        $league->rounds
            ->flatMap->wars
            ->filter(fn (ClanWarLeagueRoundWar $match): bool =>
                ! $match->is_placeholder && $match->clan_tag && $match->opponent_tag)
            ->each(function (ClanWarLeagueRoundWar $match) use ($standings): void {
                $this->addMatchSide(
                    $standings,
                    $match,
                    $match->clan_tag,
                    (int) $match->clan_stars,
                    (float) $match->clan_destruction_percentage,
                    $match->opponent_tag,
                );
                $this->addMatchSide(
                    $standings,
                    $match,
                    $match->opponent_tag,
                    (int) $match->opponent_stars,
                    (float) $match->opponent_destruction_percentage,
                    $match->clan_tag,
                );
            });

        return $standings
            ->sortBy([
                ['score', 'desc'],
                ['destruction_percentage', 'desc'],
                ['name', 'asc'],
            ])
            ->values()
            ->map(function (array $standing, int $index): array {
                $standing['position'] = $index + 1;
                $standing['destruction_percentage'] = round(
                    $standing['destruction_percentage'],
                    2,
                );

                return $standing;
            })
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<string, array<string, int|float|string|null>>  $standings
     */
    private function addMatchSide(
        $standings,
        ClanWarLeagueRoundWar $match,
        string $tag,
        int $stars,
        float $destruction,
        string $opponentTag,
    ): void {
        if (! $standings->has($tag)) {
            return;
        }

        $standing = $standings->get($tag);
        $standing['stars'] += $stars;
        $standing['destruction_percentage'] += $destruction;

        if (in_array($match->state, ['warEnded', 'ended'], true)) {
            $standing['played']++;

            if ($match->winner_tag === $tag) {
                $standing['wins']++;
                $standing['bonus_stars'] += 10;
            } elseif ($match->winner_tag === $opponentTag) {
                $standing['losses']++;
            } else {
                $standing['draws']++;
            }
        }

        $standing['score'] = $standing['stars'] + $standing['bonus_stars'];
        $standings->put($tag, $standing);
    }
}
