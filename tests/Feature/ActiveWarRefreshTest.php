<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\ClanWarLeague;
use App\Models\War;
use App\Services\ClashOfClans\ClashOfClansService;
use App\Services\Wars\ActiveWarRefreshService;
use App\Services\Wars\CwlSyncService;
use App\Services\Wars\WarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ActiveWarRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_call_the_api_without_a_local_active_war(): void
    {
        Clan::query()->create(['tag' => '#QGRJ2']);
        $clash = Mockery::mock(ClashOfClansService::class);
        $wars = Mockery::mock(WarSyncService::class);
        $cwl = Mockery::mock(CwlSyncService::class);
        $clash->shouldNotReceive('currentClanWar');
        $wars->shouldNotReceive('persistDetailedWar');
        $cwl->shouldNotReceive('refreshWar');

        $summary = (new ActiveWarRefreshService($clash, $wars, $cwl))
            ->refreshAll();

        $this->assertSame([
            'checked' => 0,
            'updated' => 0,
            'skipped' => 1,
        ], $summary);
    }

    public function test_it_does_not_refresh_a_preparation_before_its_start_time(): void
    {
        $clan = Clan::query()->create(['tag' => '#QGRJ2']);
        $this->war($clan, 'regular', 'preparation');
        $clash = Mockery::mock(ClashOfClansService::class);
        $wars = Mockery::mock(WarSyncService::class);
        $cwl = Mockery::mock(CwlSyncService::class);
        $clash->shouldNotReceive('currentClanWar');
        $wars->shouldNotReceive('persistDetailedWar');
        $cwl->shouldNotReceive('refreshWar');

        $summary = (new ActiveWarRefreshService($clash, $wars, $cwl))
            ->refreshAll();

        $this->assertSame(0, $summary['checked']);
        $this->assertSame(1, $summary['skipped']);
    }

    public function test_it_starts_refreshing_a_preparation_after_its_start_time(): void
    {
        $clan = Clan::query()->create(['tag' => '#QGRJ2']);
        $war = $this->war($clan, 'regular', 'preparation');
        $war->update(['start_time' => now()->subMinute()]);
        $payload = ['state' => 'inWar', 'clan' => [], 'opponent' => []];
        $clash = Mockery::mock(ClashOfClansService::class);
        $wars = Mockery::mock(WarSyncService::class);
        $cwl = Mockery::mock(CwlSyncService::class);
        $clash->shouldReceive('currentClanWar')
            ->once()
            ->with('#QGRJ2')
            ->andReturn($payload);
        $wars->shouldReceive('persistDetailedWar')
            ->once()
            ->withArgs(fn (Clan $argument, array $data, string $type): bool => $argument->is($clan) && $data === $payload && $type === 'regular')
            ->andReturn([new War, false]);
        $cwl->shouldNotReceive('refreshWar');

        $summary = (new ActiveWarRefreshService($clash, $wars, $cwl))
            ->refreshAll();

        $this->assertSame(1, $summary['updated']);
    }

    public function test_regular_preparation_becomes_in_war_without_creating_a_duplicate(): void
    {
        config([
            'services.clash_of_clans.base_url' => 'https://api.clashofclans.test/v1',
            'services.clash_of_clans.token' => 'api-token',
            'services.clash_of_clans.demo_mode' => false,
        ]);
        $clan = Clan::query()->create(['tag' => '#QGRJ2']);
        $war = $this->war($clan, 'regular', 'preparation');
        $war->update([
            'start_time' => '2026-08-06 12:00:00',
            'end_time' => '2026-08-07 12:00:00',
        ]);
        $warId = $war->id;

        Http::fake([
            'api.clashofclans.test/v1/clans/*/currentwar' => Http::response([
                'state' => 'inWar',
                'preparationStartTime' => '20260805T120000.000Z',
                'startTime' => '20260806T120000.000Z',
                'endTime' => '20260807T123000.000Z',
                'teamSize' => 15,
                'clan' => [
                    'tag' => '#QGRJ2',
                    'name' => 'Principal',
                    'stars' => 5,
                    'destructionPercentage' => 20,
                    'members' => [],
                ],
                'opponent' => [
                    'tag' => '#RIVAL',
                    'name' => 'Rival',
                    'stars' => 3,
                    'destructionPercentage' => 12,
                    'members' => [],
                ],
            ]),
        ]);

        $summary = app(ActiveWarRefreshService::class)->refreshAll();

        $this->assertSame(1, $summary['updated']);
        $this->assertDatabaseCount(War::class, 1);
        $stored = War::query()->sole();
        $this->assertSame($warId, $stored->id);
        $this->assertSame('inWar', $stored->state);
        $this->assertSame(5, $stored->clan_stars);
        $this->assertSame('2026-08-07 12:30:00', $stored->end_time->format('Y-m-d H:i:s'));
        Http::assertSentCount(1);
    }

    public function test_cwl_match_gets_one_final_refresh_after_it_ends(): void
    {
        config([
            'services.clash_of_clans.base_url' => 'https://api.clashofclans.test/v1',
            'services.clash_of_clans.token' => 'api-token',
            'services.clash_of_clans.demo_mode' => false,
        ]);
        $clan = Clan::query()->create(['tag' => '#QGRJ2']);
        $league = ClanWarLeague::query()->create([
            'clan_id' => $clan->id,
            'season' => '2026-08',
            'state' => 'inWar',
        ]);
        $round = $league->rounds()->create(['round_number' => 1]);
        $entry = $round->wars()->create([
            'war_tag' => '#WAR1',
            'is_placeholder' => false,
            'status' => 'unrelated',
            'state' => 'inWar',
        ]);
        Http::fake([
            'api.clashofclans.test/v1/clanwarleagues/wars/*' => Http::response([
                'state' => 'warEnded',
                'teamSize' => 15,
                'preparationStartTime' => '20260804T120000.000Z',
                'startTime' => '20260805T120000.000Z',
                'endTime' => '20260806T120000.000Z',
                'clan' => [
                    'tag' => '#OTHER1',
                    'name' => 'Outro 1',
                    'stars' => 30,
                    'destructionPercentage' => 90,
                ],
                'opponent' => [
                    'tag' => '#OTHER2',
                    'name' => 'Outro 2',
                    'stars' => 31,
                    'destructionPercentage' => 91,
                ],
            ]),
        ]);
        $refresh = app(ActiveWarRefreshService::class);

        $first = $refresh->refreshAll();
        $this->assertSame(1, $first['checked']);
        $this->assertNull($entry->fresh()->final_synced_at);

        $second = $refresh->refreshAll();
        $this->assertSame(1, $second['checked']);
        $this->assertNotNull($entry->fresh()->final_synced_at);

        $third = $refresh->refreshAll();
        $this->assertSame(0, $third['checked']);
        Http::assertSentCount(2);
    }

    public function test_it_refreshes_every_in_war_match_and_ignores_a_future_regular_preparation(): void
    {
        $clan = Clan::query()->create(['tag' => '#QGRJ2']);
        $this->war($clan, 'regular', 'preparation');
        $cwlWar = $this->war($clan, 'cwl', 'inWar');
        $league = ClanWarLeague::query()->create([
            'clan_id' => $clan->id,
            'season' => '2026-08',
            'state' => 'inWar',
        ]);
        $round = $league->rounds()->create(['round_number' => 1]);
        $entry = $round->wars()->create([
            'war_tag' => '#WAR1',
            'is_placeholder' => false,
            'status' => 'synced',
            'state' => 'inWar',
            'war_id' => $cwlWar->id,
        ]);
        $otherEntry = $round->wars()->create([
            'war_tag' => '#WAR2',
            'is_placeholder' => false,
            'status' => 'unrelated',
            'state' => 'inWar',
        ]);
        $clash = Mockery::mock(ClashOfClansService::class);
        $wars = Mockery::mock(WarSyncService::class);
        $cwl = Mockery::mock(CwlSyncService::class);
        $clash->shouldNotReceive('currentClanWar');
        $wars->shouldNotReceive('persistDetailedWar');
        $cwl->shouldReceive('refreshWar')
            ->once()
            ->withArgs(fn (Clan $argument, $roundWar): bool => $argument->is($clan) && $roundWar->is($entry))
            ->andReturnTrue()
            ->ordered();
        $cwl->shouldReceive('refreshWar')
            ->once()
            ->withArgs(fn (Clan $argument, $roundWar): bool => $argument->is($clan) && $roundWar->is($otherEntry))
            ->andReturnTrue()
            ->ordered();

        $summary = (new ActiveWarRefreshService($clash, $wars, $cwl))
            ->refreshAll();

        $this->assertSame(2, $summary['checked']);
        $this->assertSame(2, $summary['updated']);
    }

    private function war(Clan $clan, string $type, string $state): War
    {
        return $clan->wars()->create([
            'external_key' => hash('sha256', "{$clan->id}|{$type}|{$state}"),
            'type' => $type,
            'state' => $state,
            'team_size' => 15,
            'start_time' => now()->addHour(),
            'end_time' => now()->addDay(),
            'has_details' => true,
            'opponent_tag' => '#RIVAL',
            'opponent_name' => 'Rival',
        ]);
    }
}
