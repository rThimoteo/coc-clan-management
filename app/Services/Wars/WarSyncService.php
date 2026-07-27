<?php

namespace App\Services\Wars;

use App\Models\Clan;
use App\Models\War;
use App\Services\ClashOfClans\ClashOfClansService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WarSyncService
{
    public function __construct(
        private readonly ClashOfClansService $clashOfClans,
    ) {}

    /**
     * @return array{added: int, updated: int, detailed: int}
     */
    public function sync(): array
    {
        $clan = Clan::query()->first();

        if ($clan === null) {
            throw new RuntimeException('Configure a tag do clã antes de sincronizar as guerras.');
        }

        $warLog = collect($this->clashOfClans->clanWarLog($clan->tag))
            ->filter(fn (array $war): bool => $this->hasOpponent($war))
            ->values()
            ->all();
        $currentWar = $this->clashOfClans->currentClanWar($clan->tag);

        if ($currentWar !== null && ! $this->hasOpponent($currentWar)) {
            $currentWar = null;
        }

        return DB::transaction(function () use ($clan, $warLog, $currentWar): array {
            War::query()
                ->whereIn('opponent_tag', ['', '#'])
                ->delete();

            $added = 0;
            $updated = 0;
            $detailed = 0;

            foreach ($warLog as $payload) {
                [$war, $wasRecentlyCreated] = $this->persistWar($clan->tag, $payload, false);
                $wasRecentlyCreated ? $added++ : $updated++;

                if ($this->hasMemberDetails($payload)) {
                    $this->persistDetails($war, $payload, $clan->tag);
                    $detailed++;
                }
            }

            if ($currentWar !== null) {
                [$war, $wasRecentlyCreated] = $this->persistWar($clan->tag, $currentWar, true);

                if ($wasRecentlyCreated) {
                    $added++;
                } elseif (! collect($warLog)->contains(
                    fn (array $payload): bool => $this->externalKey($clan->tag, $payload) === $war->external_key,
                )) {
                    $updated++;
                }

                $this->persistDetails($war, $currentWar, $clan->tag);
                $detailed++;
            }

            $identityPayload = $currentWar ?? $warLog[0] ?? null;
            $identityPayload = $identityPayload
                ? $this->orientPayload($clan->tag, $identityPayload)
                : null;
            $clanIdentity = (array) data_get($identityPayload, 'clan', []);

            $clan->update([
                'name' => data_get($clanIdentity, 'name', $clan->name),
                'badge_url' => data_get($clanIdentity, 'badgeUrls.medium', $clan->badge_url),
                'wars_synced_at' => now(),
            ]);

            return compact('added', 'updated', 'detailed');
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{War, bool}
     */
    private function persistWar(string $configuredClanTag, array $payload, bool $detailed): array
    {
        $payload = $this->orientPayload($configuredClanTag, $payload);
        $externalKey = $this->externalKey($configuredClanTag, $payload);
        $war = War::query()->firstOrNew(['external_key' => $externalKey]);
        $wasRecentlyCreated = ! $war->exists;

        $attributes = $this->warAttributes($payload, $detailed);

        if ($war->has_details && ! $detailed) {
            unset($attributes['has_details']);
        }

        $war->fill($attributes)->save();

        return [$war, $wasRecentlyCreated];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function warAttributes(array $payload, bool $detailed): array
    {
        $clan = (array) data_get($payload, 'clan', []);
        $opponent = (array) data_get($payload, 'opponent', []);

        return [
            'state' => data_get($payload, 'state'),
            'result' => data_get($payload, 'result') ?? $this->resultFromPayload($payload),
            'team_size' => (int) data_get($payload, 'teamSize', 0),
            'preparation_start_time' => $this->parseTime(data_get($payload, 'preparationStartTime')),
            'start_time' => $this->parseTime(data_get($payload, 'startTime')),
            'end_time' => $this->parseTime(data_get($payload, 'endTime')),
            'has_details' => $detailed || $this->hasMemberDetails($payload),
            'clan_attacks' => data_get($clan, 'attacks'),
            'clan_stars' => (int) data_get($clan, 'stars', 0),
            'clan_destruction_percentage' => (float) data_get($clan, 'destructionPercentage', 0),
            'opponent_tag' => $this->clashOfClans->normalizeTag((string) data_get($opponent, 'tag')),
            'opponent_name' => (string) data_get($opponent, 'name'),
            'opponent_badge_url' => data_get($opponent, 'badgeUrls.medium'),
            'opponent_attacks' => data_get($opponent, 'attacks'),
            'opponent_stars' => (int) data_get($opponent, 'stars', 0),
            'opponent_destruction_percentage' => (float) data_get($opponent, 'destructionPercentage', 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistDetails(War $war, array $payload, string $configuredClanTag): void
    {
        $payload = $this->orientPayload($configuredClanTag, $payload);

        $war->members()->delete();
        $war->attacks()->delete();

        foreach (['clan', 'opponent'] as $side) {
            foreach ((array) data_get($payload, $side.'.members', []) as $member) {
                $war->members()->create([
                    'side' => $side,
                    'player_tag' => $this->clashOfClans->normalizeTag((string) data_get($member, 'tag')),
                    'name' => (string) data_get($member, 'name'),
                    'map_position' => (int) data_get($member, 'mapPosition'),
                    'townhall_level' => (int) data_get($member, 'townhallLevel'),
                    'opponent_attacks' => (int) data_get($member, 'opponentAttacks', 0),
                ]);
            }
        }

        $attacks = collect(['clan', 'opponent'])
            ->flatMap(fn (string $side) => collect((array) data_get($payload, $side.'.members', []))
                ->flatMap(fn (array $member) => (array) data_get($member, 'attacks', [])))
            ->unique(fn (array $attack): int => (int) data_get($attack, 'order'));

        foreach ($attacks as $attack) {
            $war->attacks()->create([
                'attacker_tag' => $this->clashOfClans->normalizeTag((string) data_get($attack, 'attackerTag')),
                'defender_tag' => $this->clashOfClans->normalizeTag((string) data_get($attack, 'defenderTag')),
                'attack_order' => (int) data_get($attack, 'order'),
                'stars' => (int) data_get($attack, 'stars'),
                'destruction_percentage' => (float) data_get($attack, 'destructionPercentage'),
                'duration' => data_get($attack, 'duration'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function orientPayload(string $configuredClanTag, array $payload): array
    {
        $configuredClanTag = $this->clashOfClans->normalizeTag($configuredClanTag);
        $clanTag = $this->clashOfClans->normalizeTag((string) data_get($payload, 'clan.tag'));

        if ($clanTag !== $configuredClanTag) {
            [$payload['clan'], $payload['opponent']] = [$payload['opponent'], $payload['clan']];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function externalKey(string $configuredClanTag, array $payload): string
    {
        $payload = $this->orientPayload($configuredClanTag, $payload);

        return hash('sha256', implode('|', [
            $this->clashOfClans->normalizeTag($configuredClanTag),
            $this->clashOfClans->normalizeTag((string) data_get($payload, 'opponent.tag')),
            (string) data_get($payload, 'endTime'),
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasMemberDetails(array $payload): bool
    {
        return is_array(data_get($payload, 'clan.members'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasOpponent(array $payload): bool
    {
        $opponentTag = data_get($payload, 'opponent.tag');

        return is_string($opponentTag) && trim($opponentTag) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resultFromPayload(array $payload): ?string
    {
        if (data_get($payload, 'state') !== 'warEnded') {
            return null;
        }

        $clanStars = (int) data_get($payload, 'clan.stars', 0);
        $opponentStars = (int) data_get($payload, 'opponent.stars', 0);

        if ($clanStars !== $opponentStars) {
            return $clanStars > $opponentStars ? 'win' : 'lose';
        }

        $clanDestruction = (float) data_get($payload, 'clan.destructionPercentage', 0);
        $opponentDestruction = (float) data_get($payload, 'opponent.destructionPercentage', 0);

        return match (true) {
            $clanDestruction > $opponentDestruction => 'win',
            $clanDestruction < $opponentDestruction => 'lose',
            default => 'tie',
        };
    }

    private function parseTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::createFromFormat('Ymd\THis.v\Z', $value, 'UTC');
    }
}
