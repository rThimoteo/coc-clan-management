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
            ->get();

        foreach ($entries as $entry) {
            if (in_array($entry->state, ['warEnded', 'ended'], true)) {
                continue;
            }

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

            $this->persistMatchSummary($entry, $payload);

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

    public function refreshWar(Clan $clan, ClanWarLeagueRoundWar $entry): bool
    {
        $belongsToClan = $entry->round()
            ->whereHas('league', fn ($query) => $query
                ->where('clan_id', $clan->id))
            ->exists();

        if (! $belongsToClan || $entry->is_placeholder || $entry->war_tag === null) {
            return false;
        }

        $payload = $this->clashOfClans->clanWarLeagueWar($entry->war_tag);

        if ($payload === null || ! $this->containsClan($payload, $clan->tag)) {
            return false;
        }

        $this->persistMatchSummary($entry, $payload);
        [$war] = $this->wars->persistDetailedWar($clan, $payload, 'cwl');
        $entry->update([
            'war_id' => $war->id,
            'status' => 'synced',
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistMatchSummary(
        ClanWarLeagueRoundWar $entry,
        array $payload,
    ): void {
        $state = (string) data_get($payload, 'state');
        $clan = (array) data_get($payload, 'clan', []);
        $opponent = (array) data_get($payload, 'opponent', []);
        $clanTag = $this->clashOfClans->normalizeTag((string) data_get($clan, 'tag'));
        $opponentTag = $this->clashOfClans->normalizeTag((string) data_get($opponent, 'tag'));

        $entry->update([
            'state' => $state,
            'team_size' => data_get($payload, 'teamSize'),
            'preparation_start_time' => $this->parseTime(data_get($payload, 'preparationStartTime')),
            'start_time' => $this->parseTime(data_get($payload, 'startTime')),
            'end_time' => $this->parseTime(data_get($payload, 'endTime')),
            'clan_tag' => $clanTag,
            'clan_name' => data_get($clan, 'name'),
            'clan_badge_url' => data_get($clan, 'badgeUrls.medium'),
            'clan_attacks' => data_get($clan, 'attacks'),
            'clan_stars' => (int) data_get($clan, 'stars', 0),
            'clan_destruction_percentage' => (float) data_get($clan, 'destructionPercentage', 0),
            'opponent_tag' => $opponentTag,
            'opponent_name' => data_get($opponent, 'name'),
            'opponent_badge_url' => data_get($opponent, 'badgeUrls.medium'),
            'opponent_attacks' => data_get($opponent, 'attacks'),
            'opponent_stars' => (int) data_get($opponent, 'stars', 0),
            'opponent_destruction_percentage' => (float) data_get($opponent, 'destructionPercentage', 0),
            'winner_tag' => $this->winnerTag($state, $clan, $opponent),
            'summary_synced_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $clan
     * @param  array<string, mixed>  $opponent
     */
    private function winnerTag(string $state, array $clan, array $opponent): ?string
    {
        if (! in_array($state, ['warEnded', 'ended'], true)) {
            return null;
        }

        $clanStars = (int) data_get($clan, 'stars', 0);
        $opponentStars = (int) data_get($opponent, 'stars', 0);
        $clanDestruction = (float) data_get($clan, 'destructionPercentage', 0);
        $opponentDestruction = (float) data_get($opponent, 'destructionPercentage', 0);

        if (
            $clanStars === $opponentStars &&
            $clanDestruction === $opponentDestruction
        ) {
            return null;
        }

        $clanWon = $clanStars > $opponentStars || (
            $clanStars === $opponentStars &&
            $clanDestruction > $opponentDestruction
        );
        $winner = $clanWon ? $clan : $opponent;

        return $this->clashOfClans->normalizeTag((string) data_get($winner, 'tag'));
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
            $league = $this->reconcileSeason($clan, $group->season);
            $league->update([
                'state' => $group->state,
                'synced_at' => now(),
            ]);

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

    private function reconcileSeason(Clan $clan, string $season): ClanWarLeague
    {
        $candidates = ClanWarLeague::query()
            ->whereBelongsTo($clan)
            ->where(function ($query) use ($season): void {
                $query->where('season', $season)
                    ->orWhere('season', 'like', $season.'-%');
            })
            ->with(['participants', 'rounds.wars'])
            ->orderByRaw('CASE WHEN season = ? THEN 0 ELSE 1 END', [$season])
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            return ClanWarLeague::query()->create([
                'clan_id' => $clan->id,
                'season' => $season,
                'state' => 'preparation',
            ]);
        }

        $league = $candidates->shift();

        if ($league->season !== $season) {
            $league->update(['season' => $season]);
        }

        foreach ($candidates as $duplicate) {
            $this->mergeLeague($league, $duplicate);
        }

        return $league->fresh();
    }

    private function mergeLeague(
        ClanWarLeague $league,
        ClanWarLeague $duplicate,
    ): void {
        foreach ($duplicate->participants as $participant) {
            $league->participants()->updateOrCreate(
                ['clan_tag' => $participant->clan_tag],
                [
                    'name' => $participant->name,
                    'clan_level' => $participant->clan_level,
                    'badge_url' => $participant->badge_url,
                ],
            );
        }

        foreach ($duplicate->rounds as $sourceRound) {
            $targetRound = $league->rounds()->firstOrCreate([
                'round_number' => $sourceRound->round_number,
            ]);

            foreach ($sourceRound->wars->where('is_placeholder', false) as $sourceEntry) {
                $targetEntry = $targetRound->wars()->firstOrCreate(
                    ['war_tag' => $sourceEntry->war_tag],
                    ['is_placeholder' => false, 'status' => 'pending'],
                );

                $this->mergeRoundWar($targetEntry, $sourceEntry);
            }
        }

        if ($duplicate->has_summary && ! $league->has_summary) {
            $league->update($duplicate->only([
                'has_summary',
                'end_time',
                'team_size',
                'attacks_per_member',
                'battle_modifier',
                'clan_badge_url',
                'clan_attacks',
                'clan_stars',
                'clan_destruction_percentage',
                'opponent_badge_url',
                'opponent_stars',
                'opponent_destruction_percentage',
            ]));
        }

        $duplicate->delete();
    }

    private function mergeRoundWar(
        ClanWarLeagueRoundWar $target,
        ClanWarLeagueRoundWar $source,
    ): void {
        $summaryFields = [
            'state',
            'team_size',
            'preparation_start_time',
            'start_time',
            'end_time',
            'clan_tag',
            'clan_name',
            'clan_badge_url',
            'clan_attacks',
            'clan_stars',
            'clan_destruction_percentage',
            'opponent_tag',
            'opponent_name',
            'opponent_badge_url',
            'opponent_attacks',
            'opponent_stars',
            'opponent_destruction_percentage',
            'winner_tag',
            'summary_synced_at',
        ];
        $sourceIsNewer = $source->summary_synced_at && (
            ! $target->summary_synced_at ||
            $source->summary_synced_at->greaterThan($target->summary_synced_at)
        );

        if ($sourceIsNewer || ! $target->summary_synced_at) {
            $target->fill($source->only($summaryFields));
        }

        if (
            $target->status === 'pending' &&
            in_array($source->status, ['synced', 'unrelated'], true)
        ) {
            $target->status = $source->status;
        }

        if ($source->war_id !== null) {
            $sourceWarId = $source->war_id;

            if ($target->war_id === null) {
                $source->update(['war_id' => null]);
                $target->war_id = $sourceWarId;
            } elseif ($target->war_id === $sourceWarId) {
                $source->update(['war_id' => null]);
            }
        }

        $target->save();
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
