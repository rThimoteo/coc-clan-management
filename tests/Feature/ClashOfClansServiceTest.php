<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Services\ClashOfClans\ClashOfClansException;
use App\Services\ClashOfClans\ClashOfClansService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClashOfClansServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.clash_of_clans.base_url' => 'https://api.clash.test/v1',
            'services.clash_of_clans.token' => 'api-token',
            'services.clash_of_clans.demo_mode' => false,
        ]);
    }

    public function test_it_maps_clan_profile_members_and_player_responses(): void
    {
        Http::fake([
            'api.clash.test/v1/clans/*' => Http::response([
                'tag' => '#QGRJ2',
                'name' => 'Nosso Clã',
                'badgeUrls' => ['medium' => 'https://assets.test/badge.png'],
                'memberList' => [
                    ['tag' => '#PQLG2', 'name' => 'Ayla', 'role' => 'leader', 'townHallLevel' => 17],
                    'resposta-inválida',
                ],
            ]),
            'api.clash.test/v1/players/*' => Http::response([
                'tag' => '#PQLG2',
                'name' => 'Ayla',
                'role' => 'leader',
                'clan' => ['tag' => '#QGRJ2'],
            ]),
        ]);
        $service = app(ClashOfClansService::class);

        $profile = $service->clanProfile('qgrj2');
        $members = $service->clanMembers('#QGRJ2');
        $player = $service->findPlayer('pqlg2');

        $this->assertSame('#QGRJ2', $profile->tag);
        $this->assertSame('Nosso Clã', $profile->name);
        $this->assertSame('https://assets.test/badge.png', $profile->badgeUrl);
        $this->assertCount(1, $members);
        $this->assertSame('#PQLG2', $members[0]->tag);
        $this->assertSame(17, $members[0]->townHallLevel);
        $this->assertSame('#QGRJ2', $player->clanTag);
        $this->assertSame('leader', $player->clanRole);
    }

    public function test_it_maps_war_log_and_not_in_war_response(): void
    {
        Http::fake([
            'api.clash.test/v1/clans/*/warlog*' => Http::response([
                'items' => [['result' => 'win'], 'resposta-inválida'],
            ]),
            'api.clash.test/v1/clans/*/currentwar' => Http::response([
                'state' => 'notInWar',
            ]),
        ]);
        $service = app(ClashOfClansService::class);

        $this->assertSame([['result' => 'win']], $service->clanWarLog('#QGRJ2'));
        $this->assertNull($service->currentClanWar('#QGRJ2'));
    }

    public function test_it_returns_the_current_war_payload(): void
    {
        Http::fake([
            'api.clash.test/v1/clans/*/currentwar' => Http::response([
                'state' => 'inWar',
                'teamSize' => 15,
            ]),
        ]);

        $this->assertSame(
            ['state' => 'inWar', 'teamSize' => 15],
            app(ClashOfClansService::class)->currentClanWar('#QGRJ2'),
        );
    }

    public function test_it_maps_the_documented_cwl_league_group_contract(): void
    {
        Http::fake([
            'api.clash.test/v1/clans/*/currentwar/leaguegroup' => Http::response(
                $this->fixture('cwl_league_group.json'),
            ),
        ]);

        $group = app(ClashOfClansService::class)
            ->currentClanWarLeagueGroup('#2Q8L9Y0JP');

        $this->assertNotNull($group);
        $this->assertSame('inWar', $group->state);
        $this->assertSame('2026-08', $group->season);
        $this->assertCount(2, $group->clans);
        $this->assertSame('#2Q8L9Y0JP', $group->clans[0]->tag);
        $this->assertSame(20, $group->clans[0]->clanLevel);
        $this->assertSame('https://assets.example/clan-principal-medium.png', $group->clans[0]->badgeUrl);
        $this->assertCount(2, $group->rounds);
        $this->assertSame(2, $group->rounds[1]->number);
        $this->assertSame('#2PR', $group->rounds[1]->warTags[0]->value);
        $this->assertTrue($group->rounds[1]->warTags[1]->isPlaceholder);
    }

    public function test_it_returns_the_documented_cwl_war_payload(): void
    {
        $payload = $this->fixture('cwl_war.json');
        Http::fake([
            'api.clash.test/v1/clanwarleagues/wars/*' => Http::response($payload),
        ]);

        $war = app(ClashOfClansService::class)->clanWarLeagueWar('#2PP');

        $this->assertSame($payload, $war);
        Http::assertSent(fn ($request): bool => $request->url()
            === 'https://api.clash.test/v1/clanwarleagues/wars/%232PP');
    }

    #[DataProvider('cwlStateProvider')]
    public function test_it_treats_supported_cwl_states(string $state, bool $returnsNull): void
    {
        $payload = $state === 'notInWar'
            ? ['state' => $state]
            : [
                ...$this->fixture('cwl_league_group.json'),
                'state' => $state,
            ];
        Http::fake([
            'api.clash.test/v1/clans/*/currentwar/leaguegroup' => Http::response($payload),
        ]);

        $group = app(ClashOfClansService::class)
            ->currentClanWarLeagueGroup('#QGRJ2');

        $returnsNull
            ? $this->assertNull($group)
            : $this->assertSame($state, $group->state);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function cwlStateProvider(): array
    {
        return [
            'not participating' => ['notInWar', true],
            'preparation' => ['preparation', false],
            'live' => ['inWar', false],
            'ended' => ['ended', false],
        ];
    }

    #[DataProvider('cwlWarStateProvider')]
    public function test_it_treats_supported_cwl_war_states(string $state, bool $returnsNull): void
    {
        Http::fake([
            'api.clash.test/v1/clanwarleagues/wars/*' => Http::response(
                $state === 'notInWar'
                    ? ['state' => $state]
                    : ['state' => $state, 'clan' => [], 'opponent' => []],
            ),
        ]);

        $war = app(ClashOfClansService::class)->clanWarLeagueWar('#2PP');

        $returnsNull
            ? $this->assertNull($war)
            : $this->assertSame($state, $war['state']);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function cwlWarStateProvider(): array
    {
        return [
            'not available' => ['notInWar', true],
            'preparation' => ['preparation', false],
            'live' => ['inWar', false],
            'api ended name' => ['warEnded', false],
            'documented ended name' => ['ended', false],
        ];
    }

    #[DataProvider('cwlIncompletePayloadProvider')]
    public function test_it_rejects_incomplete_cwl_payloads(
        string $method,
        array $payload,
        string $message,
    ): void {
        Http::fake([
            'api.clash.test/v1/*' => Http::response($payload),
        ]);

        $this->expectException(ClashOfClansException::class);
        $this->expectExceptionMessage($message);

        app(ClashOfClansService::class)->{$method}('#QGRJ2');
    }

    /**
     * @return array<string, array{string, array<string, mixed>, string}>
     */
    public static function cwlIncompletePayloadProvider(): array
    {
        return [
            'unknown group state' => [
                'currentClanWarLeagueGroup',
                ['state' => 'unknown'],
                'estado de Liga de Guerra desconhecido',
            ],
            'group without season' => [
                'currentClanWarLeagueGroup',
                ['state' => 'inWar', 'clans' => [], 'rounds' => []],
                'grupo de Liga de Guerra incompleto',
            ],
            'incomplete participant' => [
                'currentClanWarLeagueGroup',
                ['state' => 'inWar', 'season' => '2026-08', 'clans' => [[]], 'rounds' => []],
                'participante incompleto',
            ],
            'round without tags' => [
                'currentClanWarLeagueGroup',
                ['state' => 'inWar', 'season' => '2026-08', 'clans' => [], 'rounds' => [[]]],
                'rodada incompleta',
            ],
            'unknown war state' => [
                'clanWarLeagueWar',
                ['state' => 'unknown'],
                'estado de guerra da Liga desconhecido',
            ],
            'war without sides' => [
                'clanWarLeagueWar',
                ['state' => 'preparation'],
                'detalhes incompletos',
            ],
        ];
    }

    #[DataProvider('cwlErrorProvider')]
    public function test_cwl_api_errors_have_specific_messages(
        int $status,
        string $message,
    ): void {
        Http::fake([
            'api.clash.test/v1/clans/*/currentwar/leaguegroup' => Http::response([], $status),
        ]);

        $this->expectException(ClashOfClansException::class);
        $this->expectExceptionMessage($message);

        app(ClashOfClansService::class)->currentClanWarLeagueGroup('#QGRJ2');
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function cwlErrorProvider(): array
    {
        return [
            'private' => [403, 'dados da Liga de Guerra do clã estão privados'],
            'not available' => [404, 'ainda não está disponível'],
            'rate limited' => [429, 'temporariamente ocupada'],
            'unexpected' => [500, 'Não foi possível consultar a Liga de Guerra'],
        ];
    }

    #[DataProvider('apiErrorProvider')]
    public function test_player_api_errors_have_actionable_messages(int $status, string $message): void
    {
        Http::fake(['api.clash.test/v1/players/*' => Http::response([], $status)]);

        $this->expectException(ClashOfClansException::class);
        $this->expectExceptionMessage($message);

        app(ClashOfClansService::class)->findPlayer('#PQLG2');
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function apiErrorProvider(): array
    {
        return [
            'not found' => [404, 'Player tag não encontrada.'],
            'invalid credential' => [403, 'A API do Clash of Clans recusou a credencial configurada.'],
            'rate limited' => [429, 'A API do Clash of Clans está temporariamente ocupada.'],
            'unexpected error' => [500, 'Não foi possível validar a player tag no Clash of Clans.'],
        ];
    }

    #[DataProvider('privateWarEndpointProvider')]
    public function test_private_war_endpoints_have_specific_messages(string $method, string $path, string $message): void
    {
        Http::fake(["api.clash.test/v1/clans/*/{$path}*" => Http::response([], 403)]);

        $this->expectException(ClashOfClansException::class);
        $this->expectExceptionMessage($message);

        app(ClashOfClansService::class)->{$method}('#QGRJ2');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function privateWarEndpointProvider(): array
    {
        return [
            'war log' => ['clanWarLog', 'warlog', 'O histórico de guerras do clã está privado'],
            'current war' => ['currentClanWar', 'currentwar', 'Os dados da guerra atual estão privados'],
        ];
    }

    public function test_clan_lookup_reports_missing_clan_and_missing_token(): void
    {
        Http::fake(['api.clash.test/v1/clans/*' => Http::response([], 404)]);

        try {
            app(ClashOfClansService::class)->clanProfile('#QGRJ2');
            $this->fail('A consulta deveria rejeitar uma clan tag inexistente.');
        } catch (ClashOfClansException $exception) {
            $this->assertSame('Clan tag não encontrada.', $exception->getMessage());
        }

        config(['services.clash_of_clans.token' => null]);

        $this->expectException(ClashOfClansException::class);
        $this->expectExceptionMessage('A integração com o Clash of Clans não está configurada.');

        app(ClashOfClansService::class)->clanMembers('#QGRJ2');
    }

    public function test_player_must_belong_to_a_clan(): void
    {
        Http::fake(['api.clash.test/v1/players/*' => Http::response([
            'tag' => '#PQLG2',
            'name' => 'Sem clã',
        ])]);

        $this->expectException(ClashOfClansException::class);
        $this->expectExceptionMessage('Este jogador não pertence a um clã.');

        app(ClashOfClansService::class)->findPlayer('#PQLG2');
    }

    public function test_player_lookup_reports_missing_configuration(): void
    {
        config(['services.clash_of_clans.token' => null]);

        $this->expectException(ClashOfClansException::class);
        $this->expectExceptionMessage('A integração com o Clash of Clans não está configurada.');

        app(ClashOfClansService::class)->findPlayer('#PQLG2');
    }

    #[DataProvider('connectionFailureProvider')]
    public function test_connection_failures_have_a_retryable_message(string $method): void
    {
        Http::fake(fn () => Http::failedConnection());

        $this->expectException(ClashOfClansException::class);
        $this->expectExceptionMessage('Não foi possível consultar o Clash of Clans agora. Tente novamente.');

        app(ClashOfClansService::class)->{$method}('#QGRJ2');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function connectionFailureProvider(): array
    {
        return [
            'player endpoint' => ['findPlayer'],
            'clan endpoint' => ['clanProfile'],
            'league group endpoint' => ['currentClanWarLeagueGroup'],
            'league war endpoint' => ['clanWarLeagueWar'],
        ];
    }

    public function test_configured_clan_tags_requires_at_least_one_clan(): void
    {
        $this->expectException(ClashOfClansException::class);
        $this->expectExceptionMessage('Nenhum clã autorizado está configurado.');

        app(ClashOfClansService::class)->configuredClanTags();
    }

    public function test_demo_mode_provides_local_identity_data(): void
    {
        config(['services.clash_of_clans.demo_mode' => true]);
        Clan::query()->create(['tag' => '#QGRJ2']);
        $service = app(ClashOfClansService::class);

        $this->assertTrue($service->isDemoMode());
        $this->assertSame('Clã de Demonstração', $service->clanProfile('qgrj2')->name);
        $this->assertSame('Jogador de Demonstração', $service->findPlayer('#PQLG2')->name);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(string $name): array
    {
        $contents = file_get_contents(base_path("tests/Fixtures/clash_of_clans/{$name}"));
        $this->assertIsString($contents);
        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
