<?php

namespace App\Services\ClashOfClans;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ClashOfClansService
{
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
        $clanTags = collect(explode('|', (string) config('services.clash_of_clans.clan_tag')))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
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
}
