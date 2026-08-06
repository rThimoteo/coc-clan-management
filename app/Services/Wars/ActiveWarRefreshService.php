<?php

namespace App\Services\Wars;

use App\Models\Clan;
use App\Models\War;
use App\Services\ClashOfClans\ClashOfClansService;

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
            $war = $this->activeWarFor($clan);

            if ($war === null) {
                $summary['skipped']++;

                return;
            }

            $summary['checked']++;
            $updated = $war->type === 'cwl'
                ? $this->refreshCwlWar($clan, $war)
                : $this->refreshRegularWar($clan);

            if ($updated) {
                $summary['updated']++;
            }
        });

        return $summary;
    }

    private function activeWarFor(Clan $clan): ?War
    {
        return $clan->wars()
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

    private function refreshCwlWar(Clan $clan, War $war): bool
    {
        $entry = $war->leagueRoundWar;

        if ($entry === null || $entry->war_tag === null) {
            return false;
        }

        return $this->cwl->refreshWar($clan, $entry);
    }
}
