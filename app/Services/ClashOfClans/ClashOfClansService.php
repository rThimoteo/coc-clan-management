<?php

namespace App\Services\ClashOfClans;

use App\Models\Clan;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ClashOfClansService
{
    private const CWL_STATES = ['notInWar', 'preparation', 'inWar', 'ended'];

    private const CWL_WAR_STATES = ['notInWar', 'preparation', 'inWar', 'warEnded', 'ended'];

    /**
     * @return list<array<string, mixed>>
     */
    public function clanWarLog(string $clanTag): array
    {
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

    public function currentClanWarLeagueGroup(string $clanTag): ?CwlLeagueGroup
    {
        $response = $this->clanEndpointResponse(
            $this->normalizeTag($clanTag),
            'currentwar/leaguegroup',
        );

        if ($response->status() === 403) {
            throw new ClashOfClansException('Os dados da Liga de Guerra do clã estão privados ou a credencial foi recusada.');
        }

        $this->ensureCwlSuccessfulResponse($response);
        $payload = $response->json();
        $state = data_get($payload, 'state');

        if (! is_string($state) || ! in_array($state, self::CWL_STATES, true)) {
            throw new ClashOfClansException('A API retornou um estado de Liga de Guerra desconhecido.');
        }

        if ($state === 'notInWar') {
            return null;
        }

        $season = data_get($payload, 'season');
        $clans = data_get($payload, 'clans');
        $rounds = data_get($payload, 'rounds');

        if (! is_string($season) || $season === '' || ! is_array($clans) || ! is_array($rounds)) {
            throw new ClashOfClansException('A API retornou um grupo de Liga de Guerra incompleto.');
        }

        return new CwlLeagueGroup(
            state: $state,
            season: $this->normalizeCwlSeason($season),
            clans: collect($clans)
                ->map(fn (mixed $clan): CwlLeagueClan => $this->mapCwlClan($clan))
                ->values()
                ->all(),
            rounds: collect($rounds)
                ->map(fn (mixed $round, int $index): CwlRound => $this->mapCwlRound($round, $index))
                ->values()
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function clanWarLeagueWar(string $warTag): ?array
    {
        $response = $this->apiResponse('/clanwarleagues/wars/'.rawurlencode($this->normalizeTag($warTag)));

        if ($response->status() === 403) {
            throw new ClashOfClansException('Os detalhes da guerra da Liga estão privados ou a credencial foi recusada.');
        }

        $this->ensureCwlSuccessfulResponse($response);
        $payload = $response->json();
        $state = data_get($payload, 'state');

        if (! is_string($state) || ! in_array($state, self::CWL_WAR_STATES, true)) {
            throw new ClashOfClansException('A API retornou um estado de guerra da Liga desconhecido.');
        }

        if ($state === 'notInWar') {
            return null;
        }

        if (! is_array(data_get($payload, 'clan')) || ! is_array(data_get($payload, 'opponent'))) {
            throw new ClashOfClansException('A API retornou detalhes incompletos da guerra da Liga.');
        }

        return $payload;
    }

    /**
     * @return list<ClanMember>
     */
    public function clanMembers(string $clanTag): array
    {
        $clanTag = $this->normalizeTag($clanTag);

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

    private function ensureCwlSuccessfulResponse(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw new ClashOfClansException(match ($response->status()) {
            404 => 'A Liga de Guerra ou war tag consultada ainda não está disponível.',
            429 => 'A API do Clash of Clans está temporariamente ocupada. Tente novamente em instantes.',
            default => 'Não foi possível consultar a Liga de Guerra no Clash of Clans.',
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

        return $this->apiResponse(
            '/clans/'.rawurlencode($clanTag).($endpoint ? '/'.$endpoint : ''),
            $query,
        );
    }

    /**
     * @param  array<string, int|string>  $query
     */
    private function apiResponse(string $path, array $query = []): Response
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
                ->get($path, $query);
        } catch (ConnectionException) {
            throw new ClashOfClansException('Não foi possível consultar o Clash of Clans agora. Tente novamente.');
        }
    }

    private function mapCwlClan(mixed $clan): CwlLeagueClan
    {
        if (! is_array($clan) || ! is_string(data_get($clan, 'tag')) || ! is_string(data_get($clan, 'name'))) {
            throw new ClashOfClansException('A API retornou um participante incompleto na Liga de Guerra.');
        }

        return new CwlLeagueClan(
            tag: $this->normalizeTag(data_get($clan, 'tag')),
            name: data_get($clan, 'name'),
            clanLevel: is_int(data_get($clan, 'clanLevel')) ? data_get($clan, 'clanLevel') : null,
            badgeUrl: is_string(data_get($clan, 'badgeUrls.medium')) ? data_get($clan, 'badgeUrls.medium') : null,
        );
    }

    private function normalizeCwlSeason(string $season): string
    {
        if (! preg_match('/^\d{4}-\d{2}(?:-\d{2})?$/', $season)) {
            throw new ClashOfClansException('A API retornou uma temporada de Liga de Guerra inválida.');
        }

        return substr($season, 0, 7);
    }

    private function mapCwlRound(mixed $round, int $index): CwlRound
    {
        $warTags = is_array($round) ? data_get($round, 'warTags') : null;

        if (! is_array($warTags)) {
            throw new ClashOfClansException('A API retornou uma rodada incompleta na Liga de Guerra.');
        }

        return new CwlRound(
            number: $index + 1,
            warTags: collect($warTags)
                ->map(function (mixed $warTag): CwlWarTag {
                    if (! is_string($warTag) || $warTag === '') {
                        throw new ClashOfClansException('A API retornou uma war tag inválida na Liga de Guerra.');
                    }

                    $value = $this->normalizeTag($warTag);

                    return new CwlWarTag(
                        value: $value,
                        isPlaceholder: $value === '#0',
                    );
                })
                ->values()
                ->all(),
        );
    }
}
