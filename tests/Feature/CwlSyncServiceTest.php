<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\ClanWarLeague;
use App\Models\ClanWarLeagueRoundWar;
use App\Models\War;
use App\Services\Wars\CwlSyncService;
use App\Services\Wars\WarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CwlSyncServiceTest extends TestCase
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

    public function test_it_persists_group_rounds_tags_and_only_the_managed_clans_wars(): void
    {
        $clan = $this->clan();
        $this->fakeCwlApi();

        $summary = app(CwlSyncService::class)->sync($clan);
        $league = ClanWarLeague::query()->sole();

        $this->assertSame([
            'seasons' => 1,
            'rounds' => 2,
            'tags' => 4,
            'detailed' => 1,
            'pending' => 2,
            'unrelated' => 1,
        ], $summary);
        $this->assertSame('2026-08', $league->season);
        $this->assertSame('inWar', $league->state);
        $this->assertNotNull($league->synced_at);
        $this->assertSame(2, $league->participants()->count());
        $this->assertSame(2, $league->rounds()->count());
        $this->assertSame(4, ClanWarLeagueRoundWar::query()->count());
        $this->assertSame(1, ClanWarLeagueRoundWar::query()->where('status', 'synced')->count());
        $this->assertSame(1, ClanWarLeagueRoundWar::query()->where('status', 'unrelated')->count());
        $this->assertSame(1, ClanWarLeagueRoundWar::query()->where('is_placeholder', true)->count());
        $this->assertDatabaseHas(ClanWarLeagueRoundWar::class, [
            'war_tag' => '#2PQ',
            'state' => 'inWar',
            'clan_tag' => '#OTHER1',
            'opponent_tag' => '#OTHER2',
            'clan_stars' => 0,
            'opponent_stars' => 0,
            'war_id' => null,
        ]);
        $this->assertNotNull(
            ClanWarLeagueRoundWar::query()
                ->where('war_tag', '#2PQ')
                ->sole()
                ->summary_synced_at,
        );
        $this->assertDatabaseHas(War::class, [
            'clan_id' => $clan->id,
            'type' => 'cwl',
            'opponent_tag' => '#9R2V8C0YL',
            'has_details' => true,
        ]);
    }

    public function test_it_persists_a_cwl_summary_from_the_war_log_without_an_opponent(): void
    {
        $clan = Clan::query()->create([
            'tag' => '#29R0RUU2R',
            'name' => 'APOCALIPSE NOW',
            'is_default' => true,
        ]);
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/warlog')) {
                return Http::response([
                    'items' => [$this->fixture('cwl_warlog_summary.json')],
                ]);
            }

            return Http::response(['state' => 'notInWar']);
        });

        $summary = app(CwlSyncService::class)->sync($clan);
        $league = ClanWarLeague::query()->sole();

        $this->assertSame(1, $summary['seasons']);
        $this->assertSame('2026-07', $league->season);
        $this->assertSame('ended', $league->state);
        $this->assertTrue($league->has_summary);
        $this->assertSame(15, $league->team_size);
        $this->assertSame(102, $league->clan_attacks);
        $this->assertSame(313, $league->clan_stars);
        $this->assertSame(658.8, $league->clan_destruction_percentage);
        $this->assertSame(264, $league->opponent_stars);
        $this->assertSame('2026-07-11 04:31:42', $league->end_time->format('Y-m-d H:i:s'));
    }

    public function test_sync_is_idempotent_retries_pending_tags_and_refreshes_active_wars(): void
    {
        $clan = $this->clan();
        $this->fakeCwlApi();
        $sync = app(CwlSyncService::class);

        $sync->sync($clan);
        $counts = [
            ClanWarLeague::query()->count(),
            ClanWarLeagueRoundWar::query()->count(),
            War::query()->count(),
        ];
        $summary = $sync->sync($clan);

        $this->assertSame($counts, [
            ClanWarLeague::query()->count(),
            ClanWarLeagueRoundWar::query()->count(),
            War::query()->count(),
        ]);
        $this->assertSame(1, $summary['detailed']);
        $this->assertSame(2, $summary['pending']);
    }

    public function test_it_normalizes_a_dated_group_season_to_the_month(): void
    {
        $clan = $this->clan();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/warlog')) {
                return Http::response(['items' => []]);
            }

            if (str_contains($request->url(), 'leaguegroup')) {
                $group = $this->fixture('cwl_league_group.json');
                $group['season'] = '2026-08-02';

                return Http::response($group);
            }

            return Http::response([], 404);
        });

        app(CwlSyncService::class)->sync($clan);

        $this->assertDatabaseHas(ClanWarLeague::class, [
            'clan_id' => $clan->id,
            'season' => '2026-08',
        ]);
    }

    public function test_it_reconciles_legacy_and_normalized_seasons_without_duplicating_the_war_link(): void
    {
        $clan = $this->clan();
        [$war] = app(WarSyncService::class)->persistDetailedWar(
            $clan,
            $this->fixture('cwl_war.json'),
            'cwl',
        );
        $legacy = $clan->warLeagues()->create([
            'season' => '2026-08-02',
            'state' => 'preparation',
        ]);
        $legacyRound = $legacy->rounds()->create(['round_number' => 1]);
        $legacyRound->wars()->create([
            'war_tag' => '#2PP',
            'status' => 'synced',
            'war_id' => $war->id,
        ]);
        $normalized = $clan->warLeagues()->create([
            'season' => '2026-08',
            'state' => 'preparation',
        ]);
        $normalized->rounds()
            ->create(['round_number' => 1])
            ->wars()
            ->create([
                'war_tag' => '#2PP',
                'status' => 'pending',
            ]);
        $this->fakeCwlApi();

        app(CwlSyncService::class)->sync($clan);

        $this->assertDatabaseCount(ClanWarLeague::class, 1);
        $this->assertDatabaseHas(ClanWarLeague::class, [
            'clan_id' => $clan->id,
            'season' => '2026-08',
        ]);
        $this->assertSame(
            $war->id,
            ClanWarLeagueRoundWar::query()
                ->where('war_tag', '#2PP')
                ->sole()
                ->war_id,
        );
        $this->assertDatabaseCount(War::class, 1);
    }

    public function test_it_refreshes_a_managed_war_until_its_final_state(): void
    {
        $clan = $this->clan();
        $warRequests = 0;
        Http::fake(function (Request $request) use (&$warRequests) {
            if (str_contains($request->url(), '/warlog')) {
                return Http::response(['items' => []]);
            }

            if (str_contains($request->url(), 'leaguegroup')) {
                $group = $this->fixture('cwl_league_group.json');
                $group['rounds'] = [['warTags' => ['#2PP']]];

                return Http::response($group);
            }

            if (str_contains($request->url(), '%232PP')) {
                $warRequests++;
                $war = $this->fixture('cwl_war.json');

                if ($warRequests === 1) {
                    $war['state'] = 'preparation';
                    $war['clan']['stars'] = 0;
                } else {
                    $war['state'] = 'warEnded';
                    $war['clan']['stars'] = 31;
                }

                return Http::response($war);
            }

            return Http::response([], 404);
        });
        $sync = app(CwlSyncService::class);

        $sync->sync($clan);
        $this->assertDatabaseHas(War::class, [
            'clan_id' => $clan->id,
            'state' => 'preparation',
            'clan_stars' => 0,
        ]);

        $sync->sync($clan);
        $sync->sync($clan);

        $this->assertSame(2, $warRequests);
        $this->assertDatabaseHas(War::class, [
            'clan_id' => $clan->id,
            'state' => 'warEnded',
            'clan_stars' => 31,
        ]);
    }

    public function test_it_orients_a_war_when_the_managed_clan_is_the_api_opponent(): void
    {
        $clan = $this->clan();
        $war = $this->fixture('cwl_war.json');
        [$war['clan'], $war['opponent']] = [$war['opponent'], $war['clan']];
        Http::fake(function (Request $request) use ($war) {
            if (str_contains($request->url(), 'leaguegroup')) {
                return Http::response($this->fixture('cwl_league_group.json'));
            }

            return str_contains($request->url(), '%232PP')
                ? Http::response($war)
                : Http::response(['state' => 'notInWar']);
        });

        app(CwlSyncService::class)->sync($clan);

        $stored = War::query()->sole();
        $this->assertSame('#9R2V8C0YL', $stored->opponent_tag);
        $this->assertSame('Clã Rival', $stored->opponent_name);
        $this->assertSame('#P0Y8L2QG', $stored->members()->where('side', 'clan')->sole()->player_tag);
    }

    public function test_the_same_group_can_be_persisted_independently_for_two_managed_clans(): void
    {
        $primary = $this->clan();
        $secondary = Clan::query()->create([
            'tag' => '#9R2V8C0YL',
            'name' => 'Clã Rival',
        ]);
        $this->fakeCwlApi();
        $sync = app(CwlSyncService::class);

        $sync->sync($primary);
        $sync->sync($secondary);

        $this->assertDatabaseCount(ClanWarLeague::class, 2);
        $this->assertSame(2, ClanWarLeagueRoundWar::query()->where('war_tag', '#2PP')->count());
        $this->assertSame(2, War::query()->where('type', 'cwl')->count());
        $this->assertDatabaseHas(War::class, [
            'clan_id' => $primary->id,
            'opponent_tag' => $secondary->tag,
        ]);
        $this->assertDatabaseHas(War::class, [
            'clan_id' => $secondary->id,
            'opponent_tag' => $primary->tag,
        ]);
    }

    public function test_a_pending_tag_is_filled_when_details_become_available(): void
    {
        $clan = $this->clan();
        $attempt = 0;
        $war = $this->fixture('cwl_war.json');
        $war['opponent']['tag'] = '#NEW20';
        $war['opponent']['name'] = 'Novo Rival';
        $war['endTime'] = '20260804T180000.000Z';
        Http::fake(function (Request $request) use (&$attempt, $war) {
            if (str_contains($request->url(), 'leaguegroup')) {
                return Http::response($this->fixture('cwl_league_group.json'));
            }

            if (str_contains($request->url(), '%232PR')) {
                $attempt++;

                return $attempt <= 3
                    ? Http::response([], 404)
                    : Http::response($war);
            }

            return Http::response(['state' => 'notInWar']);
        });
        $sync = app(CwlSyncService::class);

        $sync->sync($clan);
        $entry = ClanWarLeagueRoundWar::query()->where('war_tag', '#2PR')->sole();
        $this->assertSame('pending', $entry->status);
        $this->assertNull($entry->war_id);

        $sync->sync($clan);

        $this->assertSame('synced', $entry->fresh()->status);
        $this->assertNotNull($entry->fresh()->war_id);
        $this->assertDatabaseHas(War::class, [
            'clan_id' => $clan->id,
            'opponent_tag' => '#NEW20',
            'type' => 'cwl',
        ]);
    }

    public function test_not_in_cwl_does_not_create_a_season(): void
    {
        Http::fake([
            'api.clash.test/v1/clans/*/warlog*' => Http::response([
                'items' => [],
            ]),
            'api.clash.test/v1/clans/*/currentwar/leaguegroup' => Http::response([
                'state' => 'notInWar',
            ]),
        ]);

        $summary = app(CwlSyncService::class)->sync($this->clan());

        $this->assertSame(0, $summary['seasons']);
        $this->assertDatabaseCount(ClanWarLeague::class, 0);
    }

    private function fakeCwlApi(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/warlog')) {
                return Http::response(['items' => []]);
            }

            if (str_contains($request->url(), 'leaguegroup')) {
                return Http::response($this->fixture('cwl_league_group.json'));
            }

            if (str_contains($request->url(), '%232PP')) {
                return Http::response($this->fixture('cwl_war.json'));
            }

            if (str_contains($request->url(), '%232PQ')) {
                return Http::response([
                    'state' => 'inWar',
                    'clan' => ['tag' => '#OTHER1'],
                    'opponent' => ['tag' => '#OTHER2'],
                ]);
            }

            return Http::response([], 404);
        });
    }

    private function clan(): Clan
    {
        return Clan::query()->create([
            'tag' => '#2Q8L9Y0JP',
            'name' => 'Clã Principal',
            'is_default' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(string $name): array
    {
        return json_decode(
            file_get_contents(base_path("tests/Fixtures/clash_of_clans/{$name}")),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
