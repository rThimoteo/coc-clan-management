<?php

namespace App\Services\Members;

use App\Models\Clan;
use App\Models\Player;
use App\Models\War;
use App\Models\WarAttack;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class PlayerPerformanceQuery
{
    /**
     * @param  list<int>  $playerIds
     * @return array<int, array{wars: int, attacks: int, average_stars: float}>
     */
    public function summaries(Clan $clan, array $playerIds, int $window = 10): array
    {
        if ($playerIds === []) {
            return [];
        }

        $warsByPlayer = War::query()
            ->whereBelongsTo($clan)
            ->where('has_details', true)
            ->where('end_time', '<=', now()->addDay())
            ->whereHas('members', fn ($query) => $query
                ->where('side', 'clan')
                ->whereIn('player_id', $playerIds))
            ->with(['members' => fn ($query) => $query
                ->where('side', 'clan')
                ->whereIn('player_id', $playerIds)
                ->select('id', 'war_id', 'player_id')])
            ->latest('end_time')
            ->get()
            ->reduce(function (SupportCollection $grouped, War $war) use ($window) {
                foreach ($war->members as $member) {
                    $playerWars = $grouped->get($member->player_id, collect());

                    if ($playerWars->count() < $window) {
                        $playerWars->push($war);
                        $grouped->put($member->player_id, $playerWars);
                    }
                }

                return $grouped;
            }, collect());

        $eligibleWarIds = $warsByPlayer
            ->flatMap(fn (SupportCollection $wars) => $wars->pluck('id'))
            ->unique()
            ->values();
        $attacks = WarAttack::query()
            ->whereIn('war_id', $eligibleWarIds)
            ->whereIn('attacker_player_id', $playerIds)
            ->get(['war_id', 'attacker_player_id', 'stars']);

        return collect($playerIds)->mapWithKeys(function (int $playerId) use ($warsByPlayer, $attacks): array {
            $wars = $warsByPlayer->get($playerId, collect());
            $warIds = $wars->pluck('id');
            $playerAttacks = $attacks
                ->where('attacker_player_id', $playerId)
                ->whereIn('war_id', $warIds);

            return [$playerId => [
                'wars' => $wars->count(),
                'attacks' => $playerAttacks->count(),
                'average_stars' => $this->average($playerAttacks, 'stars'),
            ]];
        })->all();
    }

    /**
     * @return array{
     *   metrics: array<string, int|float>,
     *   series: array{attacks: list<array<string, mixed>>, defenses: list<array<string, mixed>>},
     *   attacks: LengthAwarePaginator,
     *   defenses: LengthAwarePaginator
     * }
     */
    public function get(
        Clan $clan,
        Player $player,
        string $type = 'all',
        int|string $window = 10,
        int $perPage = 15,
    ): array {
        $type = in_array($type, ['all', 'regular', 'cwl'], true) ? $type : 'all';
        $window = in_array($window, [5, 10, 20, 'all'], true) ? $window : 10;
        $wars = $this->eligibleWars($clan, $player, $type, $window);
        $warIds = $wars->pluck('id');
        $attacks = WarAttack::query()
            ->whereIn('war_id', $warIds)
            ->where('attacker_player_id', $player->id)
            ->with('war:id,type,end_time,opponent_name,opponent_tag')
            ->orderByDesc(
                War::query()
                    ->select('end_time')
                    ->whereColumn('wars.id', 'war_attacks.war_id'),
            )
            ->paginate($perPage, ['*'], 'attacks_page')
            ->withQueryString();
        $defenses = WarAttack::query()
            ->whereIn('war_id', $warIds)
            ->where('defender_player_id', $player->id)
            ->with('war:id,type,end_time,opponent_name,opponent_tag')
            ->orderByDesc(
                War::query()
                    ->select('end_time')
                    ->whereColumn('wars.id', 'war_attacks.war_id'),
            )
            ->paginate($perPage, ['*'], 'defenses_page')
            ->withQueryString();
        $allAttacks = WarAttack::query()
            ->whereIn('war_id', $warIds)
            ->where('attacker_player_id', $player->id)
            ->get();
        $allDefenses = WarAttack::query()
            ->whereIn('war_id', $warIds)
            ->where('defender_player_id', $player->id)
            ->get();
        $wars->load(['members' => fn ($query) => $query
            ->where('side', 'opponent')
            ->select('id', 'war_id', 'player_tag', 'name', 'map_position')]);

        $chronologicalWars = $wars->sortBy('end_time')->values();
        $attackSeries = $chronologicalWars
            ->flatMap(function (War $war) use ($allAttacks): SupportCollection {
                $opponents = $war->members->keyBy('player_tag');

                return $allAttacks
                    ->where('war_id', $war->id)
                    ->sortBy('attack_order')
                    ->values()
                    ->map(function (WarAttack $attack) use ($war, $opponents): array {
                        $target = $opponents->get($attack->defender_tag);

                        return $this->seriesPoint(
                            $war,
                            $attack,
                            $attack->defender_tag,
                            $target?->name,
                            $target?->map_position,
                        );
                    });
            })
            ->values()
            ->all();
        $defenseSeries = $chronologicalWars
            ->map(function (War $war) use ($allDefenses): ?array {
                $bestAttack = $allDefenses
                    ->where('war_id', $war->id)
                    ->sort(function (WarAttack $left, WarAttack $right): int {
                        return [$right->stars, $right->destruction_percentage, -$right->attack_order]
                            <=> [$left->stars, $left->destruction_percentage, -$left->attack_order];
                    })
                    ->first();

                if (! $bestAttack) {
                    return null;
                }

                $attacker = $war->members->firstWhere('player_tag', $bestAttack->attacker_tag);

                return $this->seriesPoint(
                    $war,
                    $bestAttack,
                    $bestAttack->attacker_tag,
                    $attacker?->name,
                    $attacker?->map_position,
                );
            })
            ->filter()
            ->values()
            ->all();

        return [
            'metrics' => [
                'wars' => $wars->count(),
                'attacks_used' => $allAttacks->count(),
                'attacks_available' => $wars->sum(
                    fn (War $war): int => $war->type === 'cwl' ? 1 : 2,
                ),
                'average_stars' => $this->average($allAttacks, 'stars'),
                'average_destruction' => $this->average(
                    $allAttacks,
                    'destruction_percentage',
                ),
                'defenses' => $allDefenses->count(),
                'average_stars_conceded' => $this->average($allDefenses, 'stars'),
                'average_destruction_conceded' => $this->average(
                    $allDefenses,
                    'destruction_percentage',
                ),
            ],
            'series' => [
                'attacks' => $attackSeries,
                'defenses' => $defenseSeries,
            ],
            'attacks' => $attacks,
            'defenses' => $defenses,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seriesPoint(
        War $war,
        WarAttack $attack,
        string $counterpartTag,
        ?string $counterpartName,
        ?int $counterpartPosition,
    ): array {
        return [
            'id' => $attack->id,
            'war_id' => $war->id,
            'type' => $war->type,
            'opponent_name' => $war->opponent_name,
            'end_time' => $war->end_time,
            'stars' => $attack->stars,
            'destruction_percentage' => $attack->destruction_percentage,
            'counterpart_tag' => $counterpartTag,
            'counterpart_name' => $counterpartName,
            'counterpart_position' => $counterpartPosition,
            'attack_order' => $attack->attack_order,
        ];
    }

    /**
     * @return Collection<int, War>
     */
    private function eligibleWars(
        Clan $clan,
        Player $player,
        string $type,
        int|string $window,
    ): Collection {
        return War::query()
            ->whereBelongsTo($clan)
            ->where('has_details', true)
            ->where('end_time', '<=', now()->addDay())
            ->when($type !== 'all', fn ($query) => $query->where('type', $type))
            ->whereHas('members', fn ($query) => $query
                ->where('side', 'clan')
                ->where('player_id', $player->id))
            ->latest('end_time')
            ->when($window !== 'all', fn ($query) => $query->limit($window))
            ->get();
    }

    /**
     * @param  SupportCollection<int, WarAttack>  $attacks
     */
    private function average(
        SupportCollection $attacks,
        string $field,
    ): float {
        return round((float) ($attacks->avg($field) ?? 0), 2);
    }
}
