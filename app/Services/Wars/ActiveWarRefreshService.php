<?php

namespace App\Services\Wars;

use App\Models\Clan;
use App\Models\ClanWarLeagueRoundWar;
use App\Models\War;
use App\Services\ClashOfClans\ClashOfClansService;
use Illuminate\Database\Eloquent\Collection;

class ActiveWarRefreshService
{
    public function __construct(
        private readonly ClashOfClansService $clashOfClans,
        private readonly WarSyncService $wars,
        private readonly CwlSyncService $cwl,
    ) {}

    /**
     * @return array{checked: int, updated: int, skipped: int}
     */
    public function refreshAll(): array
    {
        $summary = ['checked' => 0, 'updated' => 0, 'skipped' => 0];

        Clan::query()->orderBy('id')->each(function (Clan $clan) use (&$summary): void {
            $checkedBefore = $summary['checked'];
            $war = $this->activeRegularWarFor($clan);

            if ($war !== null) {
                $summary['checked']++;

                if ($this->refreshRegularWar($clan)) {
                    $summary['updated']++;
                }
            }

            foreach ($this->refreshableCwlEntries($clan) as $entry) {
                $summary['checked']++;

                if ($this->cwl->refreshWar($clan, $entry)) {
                    $summary['updated']++;
                }
            }

            if ($summary['checked'] === $checkedBefore) {
                $summary['skipped']++;
            }
        });

        return $summary;
    }

    private function activeRegularWarFor(Clan $clan): ?War
    {
        return $clan->wars()
            ->where('type', 'regular')
            ->where(function ($query): void {
                $query->where('state', 'inWar')
                    ->orWhere(function ($preparation): void {
                        $preparation
                            ->where('state', 'preparation')
                            ->whereNotNull('start_time')
                            ->where('start_time', '<=', now());
                    });
            })
            ->orderByRaw("CASE state WHEN 'inWar' THEN 0 ELSE 1 END")
            ->orderBy('start_time')
            ->first();
    }

    private function refreshRegularWar(Clan $clan): bool
    {
        $payload = $this->clashOfClans->currentClanWar($clan->tag);

        if ($payload === null) {
            return false;
        }

        $this->wars->persistDetailedWar($clan, $payload, 'regular');

        return true;
    }

    /**
     * @return Collection<int, ClanWarLeagueRoundWar>
     */
    private function refreshableCwlEntries(Clan $clan): Collection
    {
        return ClanWarLeagueRoundWar::query()
            ->where('is_placeholder', false)
            ->whereNotNull('war_tag')
            ->whereHas('round.league', fn ($query) => $query
                ->where('clan_id', $clan->id)
                ->whereIn('state', ['preparation', 'inWar']))
            ->where(function ($query): void {
                $query->where('state', 'inWar')
                    ->orWhere(function ($preparation): void {
                        $preparation
                            ->where('state', 'preparation')
                            ->whereNotNull('start_time')
                            ->where('start_time', '<=', now());
                    })
                    ->orWhere(function ($ended): void {
                        $ended
                            ->whereIn('state', ['warEnded', 'ended'])
                            ->whereNull('final_synced_at');
                    });
            })
            ->orderBy('clan_war_league_round_id')
            ->orderBy('id')
            ->get();
    }
}
