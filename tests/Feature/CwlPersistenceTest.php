<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\ClanWarLeague;
use App\Models\ClanWarLeagueClan;
use App\Models\ClanWarLeagueRound;
use App\Models\ClanWarLeagueRoundWar;
use App\Models\War;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CwlPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cwl_schema_and_model_relations_are_available(): void
    {
        $this->assertTrue(Schema::hasColumns('clan_war_leagues', [
            'clan_id',
            'season',
            'state',
            'has_summary',
            'end_time',
            'clan_stars',
            'opponent_stars',
            'synced_at',
        ]));
        $this->assertTrue(Schema::hasColumns('clan_war_league_clans', [
            'clan_war_league_id',
            'clan_tag',
            'name',
            'clan_level',
            'badge_url',
        ]));
        $this->assertTrue(Schema::hasColumns('clan_war_league_rounds', [
            'clan_war_league_id',
            'round_number',
        ]));
        $this->assertTrue(Schema::hasColumns('clan_war_league_round_wars', [
            'clan_war_league_round_id',
            'war_tag',
            'is_placeholder',
            'status',
            'war_id',
        ]));

        [$clan, $league, $round] = $this->leagueContext();
        $participant = $league->participants()->create([
            'clan_tag' => '#QGRJ2',
            'name' => 'Principal',
            'clan_level' => 20,
        ]);
        $roundWar = $round->wars()->create([
            'war_tag' => '#2PP',
            'is_placeholder' => false,
        ]);

        $this->assertTrue($clan->is($league->clan));
        $this->assertTrue($league->is($participant->league));
        $this->assertTrue($league->is($round->league));
        $this->assertTrue($round->is($roundWar->round));
        $this->assertSame(1, $clan->warLeagues()->count());
    }

    public function test_season_participant_round_and_real_war_tags_are_unique_in_their_scope(): void
    {
        [$clan, $league, $round] = $this->leagueContext();

        $this->assertConstraintViolation(fn () => ClanWarLeague::query()->create([
            'clan_id' => $clan->id,
            'season' => $league->season,
            'state' => 'inWar',
        ]));

        ClanWarLeagueClan::query()->create([
            'clan_war_league_id' => $league->id,
            'clan_tag' => '#QGRJ2',
            'name' => 'Principal',
        ]);
        $this->assertConstraintViolation(fn () => ClanWarLeagueClan::query()->create([
            'clan_war_league_id' => $league->id,
            'clan_tag' => '#QGRJ2',
            'name' => 'Duplicado',
        ]));

        $this->assertConstraintViolation(fn () => ClanWarLeagueRound::query()->create([
            'clan_war_league_id' => $league->id,
            'round_number' => $round->round_number,
        ]));

        ClanWarLeagueRoundWar::query()->create([
            'clan_war_league_round_id' => $round->id,
            'war_tag' => '#2PP',
        ]);
        $this->assertConstraintViolation(fn () => ClanWarLeagueRoundWar::query()->create([
            'clan_war_league_round_id' => $round->id,
            'war_tag' => '#2PP',
        ]));

        $otherRound = $league->rounds()->create(['round_number' => 2]);
        $otherRound->wars()->create(['war_tag' => '#2PP']);
        $this->assertSame(2, ClanWarLeagueRoundWar::query()->where('war_tag', '#2PP')->count());
    }

    public function test_multiple_placeholder_wars_can_be_stored(): void
    {
        [, , $round] = $this->leagueContext();

        $round->wars()->create([
            'war_tag' => null,
            'is_placeholder' => true,
        ]);
        $round->wars()->create([
            'war_tag' => null,
            'is_placeholder' => true,
        ]);

        $this->assertSame(2, $round->wars()->where('is_placeholder', true)->count());
    }

    public function test_detailed_war_can_only_belong_to_one_cwl_round_entry(): void
    {
        [$clan, $league, $round] = $this->leagueContext();
        $war = $this->war($clan);
        $roundWar = $round->wars()->create([
            'war_tag' => '#2PP',
            'war_id' => $war->id,
        ]);

        $this->assertTrue($war->is($roundWar->war));
        $this->assertTrue($roundWar->is($war->leagueRoundWar));

        $otherRound = $league->rounds()->create(['round_number' => 2]);
        $this->assertConstraintViolation(fn () => $otherRound->wars()->create([
            'war_tag' => '#2PQ',
            'war_id' => $war->id,
        ]));
    }

    public function test_deleting_a_war_keeps_the_pending_round_entry(): void
    {
        [$clan, , $round] = $this->leagueContext();
        $war = $this->war($clan);
        $roundWar = $round->wars()->create([
            'war_tag' => '#2PP',
            'war_id' => $war->id,
        ]);

        $war->delete();

        $this->assertModelExists($roundWar);
        $this->assertNull($roundWar->fresh()->war_id);
        $this->assertSame('#2PP', $roundWar->fresh()->war_tag);
    }

    public function test_deleting_a_league_cascades_its_structure_but_not_the_detailed_war(): void
    {
        [$clan, $league, $round] = $this->leagueContext();
        $participant = $league->participants()->create([
            'clan_tag' => '#QGRJ2',
            'name' => 'Principal',
        ]);
        $war = $this->war($clan);
        $roundWar = $round->wars()->create([
            'war_tag' => '#2PP',
            'war_id' => $war->id,
        ]);

        $league->delete();

        $this->assertModelMissing($participant);
        $this->assertModelMissing($round);
        $this->assertModelMissing($roundWar);
        $this->assertModelExists($war);
    }

    public function test_deleting_a_clan_cascades_all_cwl_and_war_data(): void
    {
        [$clan, $league, $round] = $this->leagueContext();
        $participant = $league->participants()->create([
            'clan_tag' => '#QGRJ2',
            'name' => 'Principal',
        ]);
        $war = $this->war($clan);
        $roundWar = $round->wars()->create([
            'war_tag' => '#2PP',
            'war_id' => $war->id,
        ]);

        $clan->delete();

        foreach ([$league, $participant, $round, $roundWar, $war] as $model) {
            $this->assertModelMissing($model);
        }
    }

    /**
     * @return array{Clan, ClanWarLeague, ClanWarLeagueRound}
     */
    private function leagueContext(): array
    {
        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'name' => 'Principal',
            'is_default' => true,
        ]);
        $league = $clan->warLeagues()->create([
            'season' => '2026-08',
            'state' => 'preparation',
            'synced_at' => now(),
        ]);
        $round = $league->rounds()->create(['round_number' => 1]);

        return [$clan, $league, $round];
    }

    private function war(Clan $clan): War
    {
        return War::query()->create([
            'clan_id' => $clan->id,
            'external_key' => hash('sha256', 'cwl-war-'.$clan->id),
            'type' => 'cwl',
            'team_size' => 15,
            'end_time' => now()->addDay(),
            'opponent_tag' => '#V9Y20',
            'opponent_name' => 'Rival',
        ]);
    }

    private function assertConstraintViolation(callable $callback): void
    {
        try {
            $callback();
            $this->fail('A restrição de unicidade deveria rejeitar este registro.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
