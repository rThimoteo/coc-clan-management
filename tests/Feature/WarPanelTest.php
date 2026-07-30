<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Clan;
use App\Models\Role;
use App\Models\User;
use App\Models\War;
use App\Models\WarAttack;
use App\Models\WarMember;
use App\Services\Clans\ActiveClanContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class WarPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_war_sync_persists_summary_and_available_details(): void
    {
        $this->fakeWarApi();
        $clan = Clan::query()->create(['tag' => '#QGRJ2']);

        $this->actingAs(User::factory()->create())
            ->post('/wars/sync')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('syncSummary', [
                'added' => 1,
                'updated' => 0,
                'detailed' => 1,
            ]);

        $war = War::query()->sole();

        $this->assertTrue($war->has_details);
        $this->assertSame('win', $war->result);
        $this->assertSame('Clã Rival', $war->opponent_name);
        $this->assertSame(41, $war->clan_stars);
        $this->assertSame(37, $war->opponent_stars);
        $this->assertDatabaseCount(WarMember::class, 4);
        $this->assertDatabaseCount(WarAttack::class, 4);
        $clan->refresh();
        $this->assertNotNull($clan->wars_synced_at);
        $this->assertSame('Nosso Clã', $clan->name);
        $this->assertSame('https://assets.test/clan.png', $clan->badge_url);
    }

    public function test_repeated_sync_updates_war_without_duplicating_details(): void
    {
        $this->fakeWarApi();
        Clan::query()->create(['tag' => '#QGRJ2']);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/wars/sync');
        $this->actingAs($user)->post('/wars/sync');

        $this->assertDatabaseCount(War::class, 1);
        $this->assertDatabaseCount(WarMember::class, 4);
        $this->assertDatabaseCount(WarAttack::class, 4);
    }

    public function test_war_list_and_detailed_page_are_available(): void
    {
        $this->fakeWarApi();
        Clan::query()->create(['tag' => '#QGRJ2']);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/wars/sync');
        $war = War::query()->sole();

        $this->actingAs($user)
            ->get('/wars')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Wars/Index')
                ->has('wars.data', 1)
                ->where('wars.data.0.has_details', true)
                ->where('warStats.total', 1));

        $this->actingAs($user)
            ->get("/wars/{$war->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Wars/Show')
                ->has('war.members', 4)
                ->has('war.attacks', 4));
    }

    public function test_war_sync_started_from_details_returns_to_the_same_page(): void
    {
        $this->fakeWarApi();
        Clan::query()->create(['tag' => '#QGRJ2']);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/wars/sync');
        $war = War::query()->sole();

        $this->actingAs($user)
            ->from("/wars/{$war->id}")
            ->post('/wars/sync')
            ->assertRedirect("/wars/{$war->id}");
    }

    public function test_active_war_is_highlighted_on_dashboard_and_war_list(): void
    {
        $activeWar = War::query()->create([
            ...$this->summaryAttributes(),
            'external_key' => hash('sha256', 'active-war'),
            'end_time' => now()->addHour(),
            'opponent_name' => 'Rival ativo',
        ]);
        War::query()->create([
            ...$this->summaryAttributes(),
            'external_key' => hash('sha256', 'finished-war'),
            'end_time' => now()->subMinute(),
            'opponent_name' => 'Rival encerrado',
        ]);
        $user = User::factory()->create();

        foreach (['/dashboard', '/wars'] as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('activeWar.id', $activeWar->id)
                    ->where('activeWar.opponent_name', 'Rival ativo'));
        }
    }

    public function test_war_history_is_paginated_twenty_at_a_time(): void
    {
        foreach (range(1, 25) as $index) {
            War::query()->create([
                ...$this->summaryAttributes(),
                'external_key' => hash('sha256', "paginated-war-{$index}"),
                'end_time' => now()->subDays($index),
                'opponent_tag' => "#RIVAL{$index}",
            ]);
        }
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/wars')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('wars.data', 20)
                ->where('wars.current_page', 1)
                ->where('wars.total', 25)
                ->where('warStats.total', 25));

        $this->actingAs($user)
            ->get('/wars?page=2')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('wars.data', 5)
                ->where('wars.current_page', 2)
                ->where('wars.total', 25));
    }

    public function test_wars_can_be_filtered_by_result_and_remain_ordered_by_end_time(): void
    {
        foreach ([
            ['win', now()->subDays(3), 'Vitória antiga'],
            ['lose', now()->subDays(2), 'Derrota'],
            ['win', now()->subDay(), 'Vitória recente'],
        ] as $index => [$result, $endTime, $opponent]) {
            War::query()->create([
                ...$this->summaryAttributes(),
                'external_key' => hash('sha256', "filtered-war-{$index}"),
                'result' => $result,
                'end_time' => $endTime,
                'opponent_name' => $opponent,
            ]);
        }

        $this->actingAs(User::factory()->create())
            ->get('/wars?result=win')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('wars.data', 2)
                ->where('filters.result', 'win')
                ->where('wars.data.0.opponent_name', 'Vitória recente')
                ->where('wars.data.1.opponent_name', 'Vitória antiga'));
    }

    public function test_finished_war_is_not_marked_as_active(): void
    {
        War::query()->create([
            ...$this->summaryAttributes(),
            'end_time' => now()->subSecond(),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('activeWar', null));
    }

    public function test_war_details_identify_when_battle_is_active(): void
    {
        $activeWar = War::query()->create([
            ...$this->summaryAttributes(),
            'external_key' => hash('sha256', 'active-war-details'),
            'end_time' => now()->addHour(),
            'has_details' => true,
        ]);
        $finishedWar = War::query()->create([
            ...$this->summaryAttributes(),
            'external_key' => hash('sha256', 'finished-war-details'),
            'end_time' => now()->subHour(),
            'has_details' => true,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get("/wars/{$activeWar->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('isActive', true));

        $this->actingAs($user)
            ->get("/wars/{$finishedWar->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('isActive', false));
    }

    public function test_preparation_is_distinguished_from_a_live_battle(): void
    {
        $war = War::query()->create([
            ...$this->summaryAttributes(),
            'external_key' => hash('sha256', 'preparation-war'),
            'state' => 'preparation',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDays(2),
            'has_details' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get("/wars/{$war->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('isActive', true)
                ->where('isPreparation', true)
                ->where('war.state', 'preparation')
                ->where('war.start_time', $war->start_time->toJSON()));
    }

    public function test_war_without_details_does_not_expose_details_page(): void
    {
        $war = War::query()->create($this->summaryAttributes());

        $this->actingAs(User::factory()->create())
            ->get("/wars/{$war->id}")
            ->assertNotFound();
    }

    public function test_war_pages_are_isolated_by_the_active_clan(): void
    {
        $primary = Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);
        $secondary = Clan::query()->create(['tag' => '#V9Y20']);
        $primaryWar = War::query()->create([
            ...$this->summaryAttributes(),
            'clan_id' => $primary->id,
            'external_key' => hash('sha256', 'primary-isolated-war'),
            'opponent_name' => 'Rival principal',
            'has_details' => true,
        ]);
        $secondaryWar = War::query()->create([
            ...$this->summaryAttributes(),
            'clan_id' => $secondary->id,
            'external_key' => hash('sha256', 'secondary-isolated-war'),
            'opponent_name' => 'Rival secundário',
            'has_details' => true,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([ActiveClanContext::SESSION_KEY => $secondary->id])
            ->get('/wars')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('wars.data', 1)
                ->where('wars.data.0.id', $secondaryWar->id)
                ->where('warStats.total', 1));

        $this->actingAs($user)
            ->withSession([ActiveClanContext::SESSION_KEY => $secondary->id])
            ->get("/wars/{$primaryWar->id}")
            ->assertNotFound();

        $this->actingAs($user)
            ->withSession([ActiveClanContext::SESSION_KEY => $secondary->id])
            ->get("/wars/{$secondaryWar->id}")
            ->assertOk();
    }

    public function test_war_sync_requires_a_configured_clan(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/wars/sync')
            ->assertSessionHasErrors('sync');
    }

    public function test_sync_ignores_aggregate_entries_without_opponent_tag(): void
    {
        config([
            'services.clash_of_clans.base_url' => 'https://api.clashofclans.test/v1',
            'services.clash_of_clans.token' => 'api-token',
            'services.clash_of_clans.demo_mode' => false,
        ]);

        Clan::query()->create(['tag' => '#QGRJ2']);
        War::query()->create([
            ...$this->summaryAttributes(),
            'external_key' => hash('sha256', 'legacy-ghost'),
            'opponent_tag' => '#',
            'opponent_name' => 'Resumo CWL',
        ]);

        Http::fake([
            'api.clashofclans.test/v1/clans/*/warlog*' => Http::response([
                'items' => [[
                    'result' => null,
                    'endTime' => '20260722T180000.000Z',
                    'teamSize' => 50,
                    'clan' => [
                        'tag' => '#QGRJ2',
                        'name' => 'Nosso Clã',
                        'stars' => 105,
                        'destructionPercentage' => 650,
                    ],
                    'opponent' => [
                        'name' => 'Resumo CWL',
                        'stars' => 328,
                        'destructionPercentage' => 650,
                    ],
                ]],
            ]),
            'api.clashofclans.test/v1/clans/*/currentwar' => Http::response([
                'state' => 'notInWar',
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->post('/wars/sync')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('syncSummary', [
                'added' => 0,
                'updated' => 0,
                'detailed' => 0,
            ]);

        $this->assertDatabaseCount(War::class, 0);
    }

    public function test_guest_can_not_access_wars(): void
    {
        $this->get('/wars')->assertRedirect(route('login'));
        $this->post('/wars/sync')->assertRedirect(route('login'));
    }

    public function test_member_and_coleader_can_view_but_can_not_sync_wars(): void
    {
        foreach ([UserRole::Member, UserRole::CoLeader] as $userRole) {
            $role = Role::query()
                ->where('slug', $userRole->value)
                ->sole();
            $user = User::factory()->create(['role_id' => $role->id]);

            $this->actingAs($user)->get('/wars')->assertOk();
            $this->actingAs($user)->post('/wars/sync')->assertForbidden();
        }
    }

    private function fakeWarApi(): void
    {
        config([
            'services.clash_of_clans.base_url' => 'https://api.clashofclans.test/v1',
            'services.clash_of_clans.token' => 'api-token',
            'services.clash_of_clans.demo_mode' => false,
        ]);

        $summary = $this->warSummary();
        $details = [
            ...$summary,
            'state' => 'warEnded',
            'preparationStartTime' => '20260720T180000.000Z',
            'startTime' => '20260721T180000.000Z',
            'attacksPerMember' => 2,
            'clan' => [
                ...$summary['clan'],
                'members' => [
                    [
                        'tag' => '#PQLG2',
                        'name' => 'Chefe',
                        'mapPosition' => 1,
                        'townhallLevel' => 16,
                        'opponentAttacks' => 1,
                        'attacks' => [
                            ['attackerTag' => '#PQLG2', 'defenderTag' => '#V9Y20', 'stars' => 3, 'destructionPercentage' => 100, 'order' => 1, 'duration' => 150],
                            ['attackerTag' => '#PQLG2', 'defenderTag' => '#V9Y29', 'stars' => 2, 'destructionPercentage' => 87.5, 'order' => 3, 'duration' => 170],
                        ],
                    ],
                    [
                        'tag' => '#QGRJ9',
                        'name' => 'Guerreiro',
                        'mapPosition' => 2,
                        'townhallLevel' => 15,
                        'opponentAttacks' => 1,
                    ],
                ],
            ],
            'opponent' => [
                ...$summary['opponent'],
                'members' => [
                    [
                        'tag' => '#V9Y20',
                        'name' => 'Rival Um',
                        'mapPosition' => 1,
                        'townhallLevel' => 16,
                        'opponentAttacks' => 1,
                        'attacks' => [
                            ['attackerTag' => '#V9Y20', 'defenderTag' => '#PQLG2', 'stars' => 2, 'destructionPercentage' => 85, 'order' => 2, 'duration' => 180],
                        ],
                    ],
                    [
                        'tag' => '#V9Y29',
                        'name' => 'Rival Dois',
                        'mapPosition' => 2,
                        'townhallLevel' => 15,
                        'opponentAttacks' => 1,
                        'attacks' => [
                            ['attackerTag' => '#V9Y29', 'defenderTag' => '#QGRJ9', 'stars' => 3, 'destructionPercentage' => 100, 'order' => 4, 'duration' => 160],
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            'api.clashofclans.test/v1/clans/*/warlog*' => Http::response([
                'items' => [$summary],
                'paging' => [],
            ]),
            'api.clashofclans.test/v1/clans/*/currentwar' => Http::response($details),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function warSummary(): array
    {
        return [
            'result' => 'win',
            'endTime' => '20260722T180000.000Z',
            'teamSize' => 15,
            'attacksPerMember' => 2,
            'battleModifier' => 'none',
            'clan' => [
                'tag' => '#QGRJ2',
                'name' => 'Nosso Clã',
                'clanLevel' => 20,
                'attacks' => 27,
                'stars' => 41,
                'destructionPercentage' => 96.4,
                'expEarned' => 250,
                'badgeUrls' => ['medium' => 'https://assets.test/clan.png'],
            ],
            'opponent' => [
                'tag' => '#V9Y20',
                'name' => 'Clã Rival',
                'clanLevel' => 18,
                'attacks' => 26,
                'stars' => 37,
                'destructionPercentage' => 89.2,
                'badgeUrls' => ['medium' => 'https://assets.test/opponent.png'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryAttributes(): array
    {
        $clan = Clan::query()->firstOrCreate(
            ['tag' => '#QGRJ2'],
            ['is_default' => true],
        );

        return [
            'clan_id' => $clan->id,
            'external_key' => hash('sha256', 'summary'),
            'result' => 'win',
            'team_size' => 15,
            'end_time' => now(),
            'has_details' => false,
            'clan_stars' => 40,
            'clan_destruction_percentage' => 95,
            'opponent_tag' => '#V9Y20',
            'opponent_name' => 'Rival',
            'opponent_stars' => 35,
            'opponent_destruction_percentage' => 88,
        ];
    }
}
