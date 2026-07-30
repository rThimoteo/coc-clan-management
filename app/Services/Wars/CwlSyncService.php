<?php

namespace App\Services\Wars;

use App\Models\Clan;
use App\Models\ClanWarLeague;
use App\Models\ClanWarLeagueRoundWar;
use App\Services\ClashOfClans\ClashOfClansException;
use App\Services\ClashOfClans\ClashOfClansService;
use App\Services\ClashOfClans\CwlLeagueGroup;
use App\Services\ClashOfClans\CwlWarTag;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CwlSyncService
{
    public function __construct(
        private readonly ClashOfClansService $clashOfClans,
        private readonly WarSyncService $wars,
    ) {}

    /**
     * @return array{seasons: int, rounds: int, tags: int, detailed: int, pending: int, unrelated: int}
     */
    public function sync(Clan $clan): array
    {
        $this->persistWarLogSummaries($clan);
        $group = $this->clashOfClans->currentClanWarLeagueGroup($clan->tag);

        if ($group === null) {
            return [
                'seasons' => $clan->warLeagues()->count(),
                'rounds' => 0,
                'tags' => 0,
                'detailed' => 0,
                'pending' => 0,
                'unrelated' => 0,
            ];
        }

        $league = $this->persistGroup($clan, $group);
        $summary = [
            'seasons' => $clan->warLeagues()->count(),
            'rounds' => $league->rounds()->count(),
            'tags' => $league->rounds()->withCount('wars')->get()->sum('wars_count'),
            'detailed' => 0,
            'pending' => 0,
            'unrelated' => 0,
        ];

        $entries = ClanWarLeagueRoundWar::query()
            ->whereHas('round', fn ($query) => $query
                ->where('clan_war_league_id', $league->id))
            ->where('is_placeholder', false)
            ->where('status', 'pending')
            ->get();

        foreach ($entries as $entry) {
            try {
                $payload = $this->clashOfClans->clanWarLeagueWar($entry->war_tag);
            } catch (ClashOfClansException $exception) {
                if (str_contains($exception->getMessage(), 'ainda não está disponível')) {
                    $summary['pending']++;

                    continue;
                }

                throw $exception;
            }

            if ($payload === null) {
                $summary['pending']++;

                continue;
            }

            if (! $this->containsClan($payload, $clan->tag)) {
                $entry->update(['status' => 'unrelated']);
                $summary['unrelated']++;

                continue;
            }

            [$war] = $this->wars->persistDetailedWar($clan, $payload, 'cwl');
            $entry->update([
                'war_id' => $war->id,
                'status' => 'synced',
            ]);
            $summary['detailed']++;
        }

        $summary['pending'] += ClanWarLeagueRoundWar::query()
            ->whereHas('round', fn ($query) => $query
                ->where('clan_war_league_id', $league->id))
            ->where('is_placeholder', true)
            ->count();

        return $summary;
    }

    private function persistWarLogSummaries(Clan $clan): void
    {
        collect($this->clashOfClans->clanWarLog($clan->tag))
            ->filter(fn (array $payload): bool => $this->isLeagueSummary($payload))
            ->each(function (array $payload) use ($clan): void {
                $endTime = $this->parseTime(data_get($payload, 'endTime'));

                if ($endTime === null) {
                    return;
                }

                ClanWarLeague::query()->updateOrCreate(
                    [
                        'clan_id' => $clan->id,
                        'season' => $endTime->format('Y-m'),
                    ],
                    [
                        'state' => 'ended',
                        'has_summary' => true,
                        'end_time' => $endTime,
                        'team_size' => data_get($payload, 'teamSize'),
                        'attacks_per_member' => data_get($payload, 'attacksPerMember'),
                        'battle_modifier' => data_get($payload, 'battleModifier'),
                        'clan_badge_url' => data_get($payload, 'clan.badgeUrls.medium'),
                        'clan_attacks' => data_get($payload, 'clan.attacks'),
                        'clan_stars' => (int) data_get($payload, 'clan.stars', 0),
                        'clan_destruction_percentage' => (float) data_get(
                            $payload,
                            'clan.destructionPercentage',
                            0,
                        ),
                        'opponent_badge_url' => data_get($payload, 'opponent.badgeUrls.medium'),
                        'opponent_stars' => (int) data_get($payload, 'opponent.stars', 0),
                        'opponent_destruction_percentage' => (float) data_get(
                            $payload,
                            'opponent.destructionPercentage',
                            0,
                        ),
                        'synced_at' => now(),
                    ],
                );
            });
    }

    private function persistGroup(Clan $clan, CwlLeagueGroup $group): ClanWarLeague
    {
        return DB::transaction(function () use ($clan, $group): ClanWarLeague {
            $league = ClanWarLeague::query()->updateOrCreate(
                [
                    'clan_id' => $clan->id,
                    'season' => $group->season,
                ],
                [
                    'state' => $group->state,
                    'synced_at' => now(),
                ],
            );

            $participantTags = collect($group->clans)->pluck('tag');
            $league->participants()
                ->whereNotIn('clan_tag', $participantTags)
                ->delete();

            foreach ($group->clans as $participant) {
                $league->participants()->updateOrCreate(
                    ['clan_tag' => $participant->tag],
                    [
                        'name' => $participant->name,
                        'clan_level' => $participant->clanLevel,
                        'badge_url' => $participant->badgeUrl,
                    ],
                );
            }

            $roundNumbers = collect($group->rounds)->pluck('number');
            $league->rounds()
                ->whereNotIn('round_number', $roundNumbers)
                ->delete();

            foreach ($group->rounds as $roundData) {
                $round = $league->rounds()->updateOrCreate([
                    'round_number' => $roundData->number,
                ]);
                $realTags = collect($roundData->warTags)
                    ->reject(fn (CwlWarTag $tag): bool => $tag->isPlaceholder)
                    ->pluck('value');

                $round->wars()
                    ->where('is_placeholder', false)
                    ->whereNotIn('war_tag', $realTags)
                    ->delete();
                $round->wars()->where('is_placeholder', true)->delete();

                foreach ($roundData->warTags as $warTag) {
                    if ($warTag->isPlaceholder) {
                        $round->wars()->create([
                            'war_tag' => null,
                            'is_placeholder' => true,
                            'status' => 'pending',
                        ]);

                        continue;
                    }

                    $round->wars()->firstOrCreate(
                        ['war_tag' => $warTag->value],
                        ['is_placeholder' => false, 'status' => 'pending'],
                    );
                }
            }

            return $league;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function containsClan(array $payload, string $clanTag): bool
    {
        $clanTag = $this->clashOfClans->normalizeTag($clanTag);

        return collect(['clan.tag', 'opponent.tag'])
            ->contains(fn (string $path): bool => $this->clashOfClans->normalizeTag(
                (string) data_get($payload, $path),
            ) === $clanTag);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isLeagueSummary(array $payload): bool
    {
        return blank(data_get($payload, 'opponent.tag'))
            && is_numeric(data_get($payload, 'clan.stars'))
            && is_string(data_get($payload, 'endTime'));
    }

    private function parseTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::createFromFormat('Ymd\THis.v\Z', $value, 'UTC');
    }
}
