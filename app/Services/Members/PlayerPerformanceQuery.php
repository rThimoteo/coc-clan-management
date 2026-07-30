<?php

namespace App\Services\Members;

use App\Models\Clan;
use App\Models\Player;
use App\Models\War;
use App\Models\WarAttack;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PlayerPerformanceQuery
{
    /**
     * @return array{
     *   metrics: array<string, int|float>,
     *   series: list<array<string, mixed>>,
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
            'series' => $wars
                ->sortBy('end_time')
                ->values()
                ->map(function (War $war) use ($allAttacks, $allDefenses): array {
                    $warAttacks = $allAttacks->where('war_id', $war->id);
                    $warDefenses = $allDefenses->where('war_id', $war->id);

                    return [
                        'war_id' => $war->id,
                        'type' => $war->type,
                        'opponent_name' => $war->opponent_name,
                        'end_time' => $war->end_time,
                        'attacks' => $warAttacks->count(),
                        'available_attacks' => $war->type === 'cwl' ? 1 : 2,
                        'average_stars' => $this->average($warAttacks, 'stars'),
                        'average_destruction' => $this->average(
                            $warAttacks,
                            'destruction_percentage',
                        ),
                        'defenses' => $warDefenses->count(),
                        'average_stars_conceded' => $this->average(
                            $warDefenses,
                            'stars',
                        ),
                        'average_destruction_conceded' => $this->average(
                            $warDefenses,
                            'destruction_percentage',
                        ),
                    ];
                })
                ->all(),
            'attacks' => $attacks,
            'defenses' => $defenses,
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
            ->where('end_time', '<=', now())
            ->when($type !== 'all', fn ($query) => $query->where('type', $type))
            ->whereHas('members', fn ($query) => $query
                ->where('side', 'clan')
                ->where('player_id', $player->id))
            ->latest('end_time')
            ->when($window !== 'all', fn ($query) => $query->limit($window))
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, WarAttack>  $attacks
     */
    private function average(
        \Illuminate\Support\Collection $attacks,
        string $field,
    ): float {
        return round((float) ($attacks->avg($field) ?? 0), 2);
    }
}
