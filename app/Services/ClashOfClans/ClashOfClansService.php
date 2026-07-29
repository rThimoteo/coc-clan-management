<?php

namespace App\Services\ClashOfClans;

use App\Models\Clan;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ClashOfClansService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function clanWarLog(string $clanTag): array
    {
        if ($this->isDemoMode()) {
            return [$this->demoWar()];
        }

        $response = $this->clanEndpointResponse(
            $this->normalizeTag($clanTag),
            'warlog',
            ['limit' => 50],
        );

        if ($response->status() === 403) {
            throw new ClashOfClansException('O histórico de guerras do clã está privado ou a credencial foi recusada.');
        }

        $this->ensureSuccessfulResponse($response);

        return collect($response->json('items', []))
            ->filter(fn (mixed $war): bool => is_array($war))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentClanWar(string $clanTag): ?array
    {
        if ($this->isDemoMode()) {
            return [
                ...$this->demoWar(),
                'state' => 'warEnded',
                'preparationStartTime' => '20260720T180000.000Z',
                'startTime' => '20260721T180000.000Z',
                'attacksPerMember' => 2,
                'clan' => [
                    ...$this->demoWar()['clan'],
                    'members' => [
                        [
                            'tag' => '#PQLG2',
                            'name' => 'Chefe de Demonstração',
                            'mapPosition' => 1,
                            'townhallLevel' => 16,
                            'opponentAttacks' => 1,
                            'attacks' => [
                                ['attackerTag' => '#PQLG2', 'defenderTag' => '#V9Y20', 'stars' => 3, 'destructionPercentage' => 100, 'order' => 1, 'duration' => 155],
                            ],
                        ],
                    ],
                ],
                'opponent' => [
                    ...$this->demoWar()['opponent'],
                    'members' => [
                        [
                            'tag' => '#V9Y20',
                            'name' => 'Rival de Demonstração',
                            'mapPosition' => 1,
                            'townhallLevel' => 16,
                            'opponentAttacks' => 1,
                            'attacks' => [
                                ['attackerTag' => '#V9Y20', 'defenderTag' => '#PQLG2', 'stars' => 2, 'destructionPercentage' => 84.5, 'order' => 2, 'duration' => 172],
                            ],
                        ],
                    ],
                ],
            ];
        }

        $response = $this->clanEndpointResponse(
            $this->normalizeTag($clanTag),
            'currentwar',
        );

        if ($response->status() === 403) {
            throw new ClashOfClansException('Os dados da guerra atual estão privados ou a credencial foi recusada.');
        }

        $this->ensureSuccessfulResponse($response);

        if ($response->json('state') === 'notInWar') {
            return null;
        }

        return $response->json();
    }

    /**
     * @return list<ClanMember>
     */
    public function clanMembers(string $clanTag): array
    {
        $clanTag = $this->normalizeTag($clanTag);

        if ($this->isDemoMode()) {
            return [
                new ClanMember('#PQLG2', 'Chefe de Demonstração', 'leader', 16),
                new ClanMember('#QGRJ9', 'Guerreiro de Demonstração', 'member', 15),
            ];
        }

        $response = $this->clanResponse($clanTag);

        return collect($response->json('memberList', []))
            ->filter(fn (mixed $member): bool => is_array($member))
            ->map(fn (array $member): ClanMember => new ClanMember(
                tag: $this->normalizeTag((string) data_get($member, 'tag')),
                name: (string) data_get($member, 'name'),
                role: data_get($member, 'role'),
                townHallLevel: data_get($member, 'townHallLevel'),
            ))
            ->values()
            ->all();
    }

    public function clanProfile(string $clanTag): ClanProfile
    {
        $clanTag = $this->normalizeTag($clanTag);

        if ($this->isDemoMode()) {
            return new ClanProfile(
                tag: $clanTag,
                name: 'Clã de Demonstração',
                badgeUrl: null,
            );
        }

        $response = $this->clanResponse($clanTag);

        return new ClanProfile(
            tag: $this->normalizeTag((string) $response->json('tag', $clanTag)),
            name: (string) $response->json('name'),
            badgeUrl: $response->json('badgeUrls.medium'),
        );
    }

    public function findPlayer(string $playerTag): Player
    {
        if ($this->isDemoMode()) {
            return new Player(
                tag: $this->normalizeTag($playerTag),
                name: 'Jogador de Demonstração',
                clanTag: $this->configuredClanTags()[0],
                clanRole: 'member',
            );
        }

        $token = config('services.clash_of_clans.token');

        if (blank($token)) {
            throw new ClashOfClansException('A integração com o Clash of Clans não está configurada.');
        }

        try {
            $response = Http::baseUrl(rtrim(config('services.clash_of_clans.base_url'), '/'))
                ->acceptJson()
                ->withToken($token)
                ->timeout(10)
                ->retry(2, 250, throw: false)
                ->get('/players/'.rawurlencode($this->normalizeTag($playerTag)));
        } catch (ConnectionException) {
            throw new ClashOfClansException('Não foi possível consultar o Clash of Clans agora. Tente novamente.');
        }

        $this->ensureSuccessfulResponse($response);

        $clanTag = data_get($response->json(), 'clan.tag');

        if (! is_string($clanTag)) {
            throw new ClashOfClansException('Este jogador não pertence a um clã.');
        }

        return new Player(
            tag: $this->normalizeTag((string) $response->json('tag')),
            name: (string) $response->json('name'),
            clanTag: $this->normalizeTag($clanTag),
            clanRole: $response->json('role'),
        );
    }

    /**
     * @return list<string>
     */
    public function configuredClanTags(): array
    {
        $clanTags = Clan::query()
            ->pluck('tag')
            ->map(fn (string $tag): string => $this->normalizeTag($tag))
            ->unique()
            ->values()
            ->all();

        if ($clanTags === []) {
            throw new ClashOfClansException('Nenhum clã autorizado está configurado.');
        }

        return $clanTags;
    }

    public function isAuthorizedClan(string $clanTag): bool
    {
        return in_array(
            $this->normalizeTag($clanTag),
            $this->configuredClanTags(),
            true,
        );
    }

    public function isDemoMode(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('services.clash_of_clans.demo_mode');
    }

    public function normalizeTag(string $tag): string
    {
        $tag = strtoupper(trim($tag));

        return '#'.ltrim($tag, '#');
    }

    private function ensureSuccessfulResponse(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw new ClashOfClansException(match ($response->status()) {
            404 => 'Player tag não encontrada.',
            403 => 'A API do Clash of Clans recusou a credencial configurada.',
            429 => 'A API do Clash of Clans está temporariamente ocupada. Tente novamente em instantes.',
            default => 'Não foi possível validar a player tag no Clash of Clans.',
        });
    }

    private function clanResponse(string $clanTag): Response
    {
        $response = $this->clanEndpointResponse($clanTag);

        if ($response->status() === 404) {
            throw new ClashOfClansException('Clan tag não encontrada.');
        }

        $this->ensureSuccessfulResponse($response);

        return $response;
    }

    /**
     * @param  array<string, int|string>  $query
     */
    private function clanEndpointResponse(string $clanTag, ?string $endpoint = null, array $query = []): Response
    {
        $token = config('services.clash_of_clans.token');

        if (blank($token)) {
            throw new ClashOfClansException('A integração com o Clash of Clans não está configurada.');
        }

        try {
            return Http::baseUrl(rtrim(config('services.clash_of_clans.base_url'), '/'))
                ->acceptJson()
                ->withToken($token)
                ->timeout(10)
                ->retry(2, 250, throw: false)
                ->get(
                    '/clans/'.rawurlencode($clanTag).($endpoint ? '/'.$endpoint : ''),
                    $query,
                );
        } catch (ConnectionException) {
            throw new ClashOfClansException('Não foi possível consultar o Clash of Clans agora. Tente novamente.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function demoWar(): array
    {
        return [
            'result' => 'win',
            'endTime' => '20260722T180000.000Z',
            'teamSize' => 15,
            'attacksPerMember' => 2,
            'battleModifier' => 'none',
            'clan' => [
                'tag' => '#QGRJ2',
                'name' => 'Clã de Demonstração',
                'clanLevel' => 20,
                'attacks' => 27,
                'stars' => 41,
                'destructionPercentage' => 96.4,
                'expEarned' => 250,
                'badgeUrls' => ['medium' => null],
            ],
            'opponent' => [
                'tag' => '#V9Y20',
                'name' => 'Clã Rival',
                'clanLevel' => 18,
                'attacks' => 26,
                'stars' => 37,
                'destructionPercentage' => 89.2,
                'badgeUrls' => ['medium' => null],
            ],
        ];
    }
}
