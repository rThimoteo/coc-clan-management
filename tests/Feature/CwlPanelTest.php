<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Clan;
use App\Models\ClanWarLeague;
use App\Models\Role;
use App\Models\User;
use App\Models\War;
use App\Services\Clans\ActiveClanContext;
use App\Services\Wars\CwlSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CwlPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_cwl_page_is_separate_and_scoped_to_the_active_clan(): void
    {
        $primary = $this->clan('#QGRJ2', true);
        $secondary = $this->clan('#V9Y20');
        $primaryLeague = $this->league($primary, '2026-08');
        $this->league($secondary, '2026-07');
        $participant = $primaryLeague->participants()->create([
            'clan_tag' => $primary->tag,
            'name' => 'Principal',
        ]);
        $round = $primaryLeague->rounds()->create(['round_number' => 1]);
        $regularWar = $this->war($primary, 'regular', 'Regular Rival');
        $cwlWar = $this->war($primary, 'cwl', 'CWL Rival');
        $entry = $round->wars()->create([
            'war_tag' => '#2PP',
            'status' => 'synced',
            'war_id' => $cwlWar->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->withSession([ActiveClanContext::SESSION_KEY => $primary->id])
            ->get('/cwl')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cwl/Index')
                ->where('clan.id', $primary->id)
                ->has('leagues.data', 1)
                ->where('leagues.data.0.id', $primaryLeague->id)
                ->where('leagues.data.0.participants_count', 1)
                ->where('leagues.data.0.rounds_count', 1)
                ->where('leagueStats.total', 1)
                ->where('leagueStats.detailed', 1)
                ->missing('leagues.data.0.rounds'));

        $this->actingAs(User::factory()->create())
            ->withSession([ActiveClanContext::SESSION_KEY => $primary->id])
            ->get("/cwl/{$primaryLeague->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cwl/Show')
                ->where('league.id', $primaryLeague->id)
                ->where('league.participants.0.id', $participant->id)
                ->where('league.rounds.0.wars.0.id', $entry->id)
                ->where('league.rounds.0.wars.0.war.id', $cwlWar->id));

        $this->actingAs(User::factory()->create())
            ->withSession([ActiveClanContext::SESSION_KEY => $secondary->id])
            ->get("/cwl/{$primaryLeague->id}")
            ->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->withSession([ActiveClanContext::SESSION_KEY => $primary->id])
            ->get('/wars')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('warStats.total', 1)
                ->where('wars.data.0.id', $regularWar->id));
    }

    public function test_cwl_summary_metrics_include_all_pages_for_the_active_clan(): void
    {
        $primary = $this->clan('#QGRJ2', true);
        $secondary = $this->clan('#V9Y20');
        $syncReference = now()->startOfSecond();

        foreach (range(1, 11) as $month) {
            $league = $this->league(
                $primary,
                sprintf('2025-%02d', $month),
                [
                    'synced_at' => $syncReference->copy()
                        ->subMonths(12 - $month),
                ],
            );

            if ($month <= 3) {
                $league->rounds()->create(['round_number' => 1]);
            }
        }

        $this->league($secondary, '2025-12', [
            'synced_at' => $syncReference->copy()->addDay(),
        ])->rounds()->create(['round_number' => 1]);

        $this->actingAs(User::factory()->create())
            ->withSession([ActiveClanContext::SESSION_KEY => $primary->id])
            ->get('/cwl?page=2')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('leagues.data', 1)
                ->where('leagueStats.total', 11)
                ->where('leagueStats.detailed', 3)
                ->where(
                    'leagueStats.last_synced_at',
                    $syncReference->copy()->subMonth()->toJSON(),
                ));
    }

    public function test_authorized_user_can_sync_the_active_clans_cwl(): void
    {
        $clan = $this->clan('#QGRJ2', true);
        $leader = User::factory()->create([
            'role_id' => Role::query()
                ->where('slug', UserRole::Leader->value)
                ->sole()
                ->id,
        ]);
        $this->mock(CwlSyncService::class)
            ->shouldReceive('sync')
            ->once()
            ->withArgs(fn (Clan $argument): bool => $argument->is($clan))
            ->andReturn([
                'seasons' => 1,
                'rounds' => 7,
                'tags' => 28,
                'detailed' => 1,
                'pending' => 2,
                'unrelated' => 25,
            ]);

        $this->actingAs($leader)
            ->withSession([ActiveClanContext::SESSION_KEY => $clan->id])
            ->post('/cwl/sync')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'cwl-synced')
            ->assertSessionHas('syncSummary.detailed', 1);
    }

    public function test_member_and_demo_mode_can_not_trigger_cwl_sync(): void
    {
        $clan = $this->clan('#QGRJ2', true);
        $member = User::factory()->create([
            'role_id' => Role::query()
                ->where('slug', UserRole::Member->value)
                ->sole()
                ->id,
        ]);

        $this->actingAs($member)->post('/cwl/sync')->assertForbidden();

        config(['services.clash_of_clans.demo_mode' => true]);
        $admin = User::factory()->create([
            'role_id' => Role::query()
                ->where('slug', UserRole::Admin->value)
                ->sole()
                ->id,
        ]);
        $this->actingAs($admin)
            ->withSession([ActiveClanContext::SESSION_KEY => $clan->id])
            ->post('/cwl/sync')
            ->assertForbidden();
    }

    private function clan(string $tag, bool $default = false): Clan
    {
        return Clan::query()->create([
            'tag' => $tag,
            'name' => "Clã {$tag}",
            'is_default' => $default,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function league(
        Clan $clan,
        string $season,
        array $attributes = [],
    ): ClanWarLeague
    {
        return $clan->warLeagues()->create(array_merge([
            'season' => $season,
            'state' => 'inWar',
            'synced_at' => now(),
        ], $attributes));
    }

    private function war(Clan $clan, string $type, string $opponent): War
    {
        return War::query()->create([
            'clan_id' => $clan->id,
            'external_key' => hash('sha256', "{$clan->tag}|{$type}|{$opponent}"),
            'type' => $type,
            'team_size' => 15,
            'end_time' => now()->subDay(),
            'opponent_tag' => '#RIVAL'.substr(md5($opponent), 0, 5),
            'opponent_name' => $opponent,
            'has_details' => true,
        ]);
    }
}
