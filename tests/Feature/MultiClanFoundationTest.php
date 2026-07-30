<?php

namespace Tests\Feature;

use App\Enums\MemberStatus as MemberStatusEnum;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\Member;
use App\Models\MemberStatus;
use App\Models\Player;
use App\Models\User;
use App\Models\War;
use App\Models\WarAttack;
use App\Models\WarMember;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MultiClanFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_clan_schema_is_available_during_the_transition(): void
    {
        $this->assertTrue(Schema::hasColumns('players', [
            'user_id',
            'player_tag',
            'name',
            'town_hall_level',
        ]));
        $this->assertTrue(Schema::hasColumns('clan_memberships', [
            'clan_id',
            'player_id',
            'member_status_id',
            'role',
            'joined_at',
            'left_at',
        ]));
        $this->assertTrue(Schema::hasColumns('wars', [
            'clan_id',
            'type',
        ]));
        $this->assertTrue(Schema::hasColumns('war_members', ['player_id']));
        $this->assertTrue(Schema::hasColumns('war_attacks', [
            'attacker_player_id',
            'defender_player_id',
        ]));

        // A tabela legada permanece até todos os consumidores serem migrados.
        $this->assertTrue(Schema::hasTable('members'));
    }

    public function test_only_one_clan_can_be_marked_as_default(): void
    {
        Clan::query()->create([
            'tag' => '#QGRJ2',
            'name' => 'Principal',
            'is_default' => true,
        ]);

        $this->expectException(QueryException::class);

        Clan::query()->create([
            'tag' => '#V9Y20',
            'name' => 'Secundário',
            'is_default' => true,
        ]);
    }

    public function test_models_represent_players_memberships_and_clan_wars(): void
    {
        $user = User::factory()->create();
        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'name' => 'Principal',
            'is_default' => true,
        ]);
        $player = Player::query()->create([
            'user_id' => $user->id,
            'player_tag' => '#PQLG2',
            'name' => 'Ayla',
            'town_hall_level' => 17,
        ]);
        $membership = ClanMembership::query()->create([
            'clan_id' => $clan->id,
            'player_id' => $player->id,
            'member_status_id' => MemberStatus::query()
                ->where('slug', MemberStatusEnum::In->value)
                ->sole()
                ->id,
            'role' => 'leader',
            'joined_at' => now(),
        ]);
        $war = War::query()->create([
            'clan_id' => $clan->id,
            'external_key' => hash('sha256', 'multi-clan-foundation'),
            'type' => 'regular',
            'team_size' => 15,
            'end_time' => now(),
            'opponent_tag' => '#V9Y20',
            'opponent_name' => 'Rival',
        ]);
        $warMember = $war->members()->create([
            'player_id' => $player->id,
            'side' => 'clan',
            'player_tag' => $player->player_tag,
            'name' => $player->name,
            'map_position' => 1,
            'townhall_level' => 17,
        ]);
        $attack = $war->attacks()->create([
            'attacker_player_id' => $player->id,
            'attacker_tag' => $player->player_tag,
            'defender_tag' => '#V9Y20',
            'attack_order' => 1,
            'stars' => 3,
            'destruction_percentage' => 100,
        ]);

        $this->assertTrue($user->players->contains($player));
        $this->assertTrue($clan->memberships->contains($membership));
        $this->assertTrue($clan->players->contains($player));
        $this->assertTrue($clan->wars->contains($war));
        $this->assertTrue($player->memberships->contains($membership));
        $this->assertTrue($player->warMembers->contains($warMember));
        $this->assertTrue($player->attacks->contains($attack));
        $this->assertTrue($membership->status->is(
            MemberStatus::query()->where('slug', MemberStatusEnum::In->value)->sole(),
        ));
        $this->assertTrue($war->clan->is($clan));
        $this->assertTrue($warMember->player->is($player));
        $this->assertTrue($attack->attackerPlayer->is($player));
        $this->assertNull($attack->defenderPlayer);
    }

    public function test_deleting_a_clan_cascades_scoped_data_but_preserves_the_player(): void
    {
        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);
        $player = Player::query()->create([
            'player_tag' => '#PQLG2',
            'name' => 'Ayla',
        ]);
        ClanMembership::query()->create([
            'clan_id' => $clan->id,
            'player_id' => $player->id,
            'member_status_id' => MemberStatus::query()->firstOrFail()->id,
        ]);
        War::query()->create([
            'clan_id' => $clan->id,
            'external_key' => hash('sha256', 'cascade-war'),
            'team_size' => 15,
            'end_time' => now(),
            'opponent_tag' => '#V9Y20',
            'opponent_name' => 'Rival',
        ]);

        $clan->delete();

        $this->assertDatabaseCount(ClanMembership::class, 0);
        $this->assertDatabaseCount(War::class, 0);
        $this->assertDatabaseHas(Player::class, ['id' => $player->id]);
    }

    public function test_deleting_a_player_preserves_war_snapshots(): void
    {
        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);
        $player = Player::query()->create([
            'player_tag' => '#PQLG2',
            'name' => 'Ayla',
        ]);
        $war = War::query()->create([
            'clan_id' => $clan->id,
            'external_key' => hash('sha256', 'snapshot-war'),
            'team_size' => 15,
            'end_time' => now(),
            'opponent_tag' => '#V9Y20',
            'opponent_name' => 'Rival',
        ]);
        $warMember = $war->members()->create([
            'player_id' => $player->id,
            'side' => 'clan',
            'player_tag' => '#PQLG2',
            'name' => 'Ayla',
            'map_position' => 1,
            'townhall_level' => 17,
        ]);
        $attack = $war->attacks()->create([
            'attacker_player_id' => $player->id,
            'attacker_tag' => '#PQLG2',
            'defender_tag' => '#V9Y20',
            'attack_order' => 1,
            'stars' => 3,
            'destruction_percentage' => 100,
        ]);

        $player->delete();

        $this->assertNull($warMember->fresh()->player_id);
        $this->assertNull($attack->fresh()->attacker_player_id);
        $this->assertSame('#PQLG2', $warMember->fresh()->player_tag);
        $this->assertSame('#PQLG2', $attack->fresh()->attacker_tag);
    }

    public function test_migration_backfills_a_populated_single_clan_database(): void
    {
        $migration = require database_path(
            'migrations/2026_07_30_000000_create_multi_clan_foundation.php',
        );
        $migration->down();

        $user = User::factory()->create();
        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'name' => 'Clã legado',
        ]);
        $member = Member::query()->create([
            'user_id' => $user->id,
            'member_status_id' => MemberStatus::query()
                ->where('slug', MemberStatusEnum::In->value)
                ->sole()
                ->id,
            'player_tag' => '#PQLG2',
            'name' => 'Ayla',
            'role' => 'leader',
            'town_hall_level' => 17,
        ]);
        $war = War::query()->create([
            'external_key' => hash('sha256', 'legacy-war'),
            'team_size' => 15,
            'end_time' => now(),
            'opponent_tag' => '#V9Y20',
            'opponent_name' => 'Rival',
        ]);
        $warMember = WarMember::query()->create([
            'war_id' => $war->id,
            'side' => 'clan',
            'player_tag' => $member->player_tag,
            'name' => $member->name,
            'map_position' => 1,
            'townhall_level' => 17,
        ]);
        $attack = WarAttack::query()->create([
            'war_id' => $war->id,
            'attacker_tag' => $member->player_tag,
            'defender_tag' => '#V9Y20',
            'attack_order' => 1,
            'stars' => 3,
            'destruction_percentage' => 100,
        ]);

        $migration->up();

        $player = Player::query()->sole();
        $membership = ClanMembership::query()->sole();

        $this->assertTrue($clan->fresh()->is_default);
        $this->assertSame($user->id, $player->user_id);
        $this->assertSame($member->player_tag, $player->player_tag);
        $this->assertSame($clan->id, $membership->clan_id);
        $this->assertSame($player->id, $membership->player_id);
        $this->assertSame($clan->id, $war->fresh()->clan_id);
        $this->assertSame('regular', $war->fresh()->type);
        $this->assertSame($player->id, $warMember->fresh()->player_id);
        $this->assertSame($player->id, $attack->fresh()->attacker_player_id);
        $this->assertNull($attack->fresh()->defender_player_id);
    }

    public function test_migration_refuses_to_guess_the_clan_for_legacy_data(): void
    {
        $migration = require database_path(
            'migrations/2026_07_30_000000_create_multi_clan_foundation.php',
        );
        $migration->down();

        $primaryClan = Clan::query()->create(['tag' => '#QGRJ2']);
        $secondaryClan = Clan::query()->create(['tag' => '#V9Y20']);
        Member::query()->create([
            'member_status_id' => MemberStatus::query()->firstOrFail()->id,
            'player_tag' => '#PQLG2',
            'name' => 'Ayla',
        ]);

        try {
            $migration->up();
            $this->fail('A migração não deveria escolher um clã arbitrariamente.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'A migração multi-clã exige exatamente um clã para associar os dados legados de membros e guerras.',
                $exception->getMessage(),
            );
        }

        $secondaryClan->delete();
        $migration->up();

        $this->assertTrue($primaryClan->fresh()->is_default);
        $this->assertDatabaseCount(Player::class, 1);
        $this->assertDatabaseCount(ClanMembership::class, 1);
    }
}
