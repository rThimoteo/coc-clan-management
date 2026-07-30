<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->guardLegacyData();
        $this->backupWarDetails();

        Schema::table('clans', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
        });

        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('player_tag', 20)->unique();
            $table->string('name');
            $table->unsignedInteger('town_hall_level')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('clan_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_status_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('role')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->unique(['clan_id', 'player_id']);
            $table->index(['clan_id', 'member_status_id']);
        });

        Schema::table('wars', function (Blueprint $table) {
            $table->foreignId('clan_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->string('type')->default('regular');
            $table->index(['clan_id', 'end_time']);
            $table->index(['clan_id', 'result', 'end_time']);
            $table->index(['clan_id', 'type', 'end_time']);
        });

        Schema::table('war_members', function (Blueprint $table) {
            $table->foreignId('player_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('war_attacks', function (Blueprint $table) {
            $table->foreignId('attacker_player_id')
                ->nullable()
                ->constrained('players')
                ->nullOnDelete();
            $table->foreignId('defender_player_id')
                ->nullable()
                ->constrained('players')
                ->nullOnDelete();
        });

        $this->restoreWarDetails();
        $this->backfillLegacyData();
        $this->createSingleDefaultConstraint();
    }

    public function down(): void
    {
        $this->backupWarDetails();
        $this->dropSingleDefaultConstraint();

        Schema::table('war_attacks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attacker_player_id');
            $table->dropConstrainedForeignId('defender_player_id');
        });

        Schema::table('war_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('player_id');
        });

        Schema::table('wars', function (Blueprint $table) {
            $table->dropIndex(['clan_id', 'end_time']);
            $table->dropIndex(['clan_id', 'result', 'end_time']);
            $table->dropIndex(['clan_id', 'type', 'end_time']);
            $table->dropConstrainedForeignId('clan_id');
            $table->dropColumn('type');
        });

        $this->restoreWarDetails();
        Schema::dropIfExists('clan_memberships');
        Schema::dropIfExists('players');

        Schema::table('clans', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }

    private function guardLegacyData(): void
    {
        $clanCount = DB::table('clans')->count();
        $hasScopedData = DB::table('members')->exists()
            || DB::table('wars')->exists();

        if ($hasScopedData && $clanCount !== 1) {
            throw new RuntimeException(
                'A migração multi-clã exige exatamente um clã para associar os dados legados de membros e guerras.',
            );
        }
    }

    private function backfillLegacyData(): void
    {
        $clanId = DB::table('clans')->orderBy('id')->value('id');

        if ($clanId !== null) {
            DB::table('clans')->where('id', $clanId)->update(['is_default' => true]);
            DB::table('wars')->update(['clan_id' => $clanId]);
        }

        DB::table('members')
            ->orderBy('id')
            ->each(function (object $member) use ($clanId): void {
                $playerId = DB::table('players')->insertGetId([
                    'user_id' => $member->user_id,
                    'player_tag' => $member->player_tag,
                    'name' => $member->name,
                    'town_hall_level' => $member->town_hall_level,
                    'created_at' => $member->created_at,
                    'updated_at' => $member->updated_at,
                ]);

                DB::table('clan_memberships')->insert([
                    'clan_id' => $clanId,
                    'player_id' => $playerId,
                    'member_status_id' => $member->member_status_id,
                    'role' => $member->role,
                    'joined_at' => null,
                    'left_at' => null,
                    'created_at' => $member->created_at,
                    'updated_at' => $member->updated_at,
                ]);
            });

        DB::table('war_members')
            ->where('side', 'clan')
            ->update([
                'player_id' => DB::raw(
                    '(SELECT players.id FROM players WHERE players.player_tag = war_members.player_tag LIMIT 1)',
                ),
            ]);

        DB::table('war_attacks')->update([
            'attacker_player_id' => DB::raw(
                '(SELECT players.id FROM players WHERE players.player_tag = war_attacks.attacker_tag LIMIT 1)',
            ),
            'defender_player_id' => DB::raw(
                '(SELECT players.id FROM players WHERE players.player_tag = war_attacks.defender_tag LIMIT 1)',
            ),
        ]);
    }

    private function backupWarDetails(): void
    {
        Schema::create('multi_clan_war_members_backup', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('war_id');
            $table->string('side');
            $table->string('player_tag', 20);
            $table->string('name');
            $table->unsignedInteger('map_position');
            $table->unsignedInteger('townhall_level');
            $table->unsignedInteger('opponent_attacks')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('multi_clan_war_attacks_backup', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('war_id');
            $table->string('attacker_tag', 20);
            $table->string('defender_tag', 20);
            $table->unsignedInteger('attack_order');
            $table->unsignedInteger('stars');
            $table->decimal('destruction_percentage', 7, 4);
            $table->unsignedInteger('duration')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        DB::statement(
            'INSERT INTO multi_clan_war_members_backup
                (id, war_id, side, player_tag, name, map_position, townhall_level, opponent_attacks, created_at, updated_at)
             SELECT id, war_id, side, player_tag, name, map_position, townhall_level, opponent_attacks, created_at, updated_at
             FROM war_members',
        );
        DB::statement(
            'INSERT INTO multi_clan_war_attacks_backup
                (id, war_id, attacker_tag, defender_tag, attack_order, stars, destruction_percentage, duration, created_at, updated_at)
             SELECT id, war_id, attacker_tag, defender_tag, attack_order, stars, destruction_percentage, duration, created_at, updated_at
             FROM war_attacks',
        );
    }

    private function restoreWarDetails(): void
    {
        DB::table('war_attacks')->delete();
        DB::table('war_members')->delete();

        DB::statement(
            'INSERT INTO war_members
                (id, war_id, side, player_tag, name, map_position, townhall_level, opponent_attacks, created_at, updated_at)
             SELECT id, war_id, side, player_tag, name, map_position, townhall_level, opponent_attacks, created_at, updated_at
             FROM multi_clan_war_members_backup',
        );
        DB::statement(
            'INSERT INTO war_attacks
                (id, war_id, attacker_tag, defender_tag, attack_order, stars, destruction_percentage, duration, created_at, updated_at)
             SELECT id, war_id, attacker_tag, defender_tag, attack_order, stars, destruction_percentage, duration, created_at, updated_at
             FROM multi_clan_war_attacks_backup',
        );

        Schema::drop('multi_clan_war_attacks_backup');
        Schema::drop('multi_clan_war_members_backup');
    }

    private function createSingleDefaultConstraint(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX clans_single_default ON clans (is_default) WHERE is_default = 1',
        );
    }

    private function dropSingleDefaultConstraint(): void
    {
        DB::statement('DROP INDEX IF EXISTS clans_single_default');
    }
};
