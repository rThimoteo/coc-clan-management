<?php

namespace Database\Seeders;

use App\Enums\MemberStatus as MemberStatusEnum;
use App\Enums\UserRole;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\MemberStatus;
use App\Models\Player;
use App\Models\Role;
use App\Models\User;
use App\Models\War;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $users = $this->seedUsers();
            $clans = $this->seedClans();
            $players = $this->seedPlayers($users);
            $this->seedMemberships($clans, $players);
            $this->seedWars($clans, $players);
        });
    }

    /**
     * @return array<string, User>
     */
    private function seedUsers(): array
    {
        $demoUsers = [
            'leader' => ['name' => 'Líder Demo', 'role' => UserRole::Leader, 'code' => '111111'],
            'co_leader' => ['name' => 'Colíder Demo', 'role' => UserRole::CoLeader, 'code' => '222222'],
            'member' => ['name' => 'Membro Demo', 'role' => UserRole::Member, 'code' => '333333'],
        ];

        return collect($demoUsers)->mapWithKeys(function (array $demoUser, string $key): array {
            $role = Role::query()->where('slug', $demoUser['role']->value)->sole();

            return [$key => User::query()->updateOrCreate(
                ['name' => $demoUser['name']],
                [
                    'role_id' => $role->id,
                    'access_code' => $demoUser['code'],
                ],
            )];
        })->all();
    }

    /**
     * @return array<string, Clan>
     */
    private function seedClans(): array
    {
        Clan::query()->where('is_default', true)->update(['is_default' => false]);

        $definitions = [
            'principal' => ['#2Q8L9Y0JP', 'Clã de Demonstração', true, 3, 2],
            'academy' => ['#2Y0V8R2JG', 'Academia Demo', false, 5, 4],
        ];

        return collect($definitions)->mapWithKeys(function (array $data, string $key): array {
            [$tag, $name, $default, $memberHours, $warHours] = $data;

            return [$key => Clan::query()->updateOrCreate(
                ['tag' => $tag],
                [
                    'name' => $name,
                    'badge_url' => null,
                    'is_default' => $default,
                    'members_synced_at' => now()->subHours($memberHours),
                    'wars_synced_at' => now()->subHours($warHours),
                ],
            )];
        })->all();
    }

    /**
     * @param  array<string, User>  $users
     * @return array<string, Player>
     */
    private function seedPlayers(array $users): array
    {
        $definitions = [
            '#P0Y8L2QG' => ['Ayla', 17, $users['leader']->id],
            '#Q2G9R8JV' => ['Breno', 17, $users['co_leader']->id],
            '#L8Y2P0CU' => ['Caio', 16, null],
            '#G9Q2J8RV' => ['Dandara', 16, null],
            '#R2V8C0YL' => ['Enzo', 15, null],
            '#J8P2L9QG' => ['Fernanda', 15, $users['member']->id],
            '#C2U9V8RY' => ['Gael', 14, null],
            '#V8R2G0JP' => ['Helena', 14, null],
            '#Y2L9Q8CU' => ['Ícaro', 13, null],
            '#U8C2V0RG' => ['Joana', 13, null],
            '#P2Q9L8YV' => ['Kaique', 12, null],
            '#G8J2R0CU' => ['Luna', 12, null],
            '#R9V2C8YL' => ['Miguel', 11, null],
            '#J2P8Q0LG' => ['Nina', 15, null],
            '#Y0P2L8QG' => ['Ayla Mini', 14, $users['leader']->id],
            '#V2R8G0JC' => ['Otávio', 13, null],
            '#C8U2V0RY' => ['Pietra', 12, null],
            '#L2Y9Q8CU' => ['Ravi', 11, null],
        ];

        return collect($definitions)->mapWithKeys(function (array $data, string $tag): array {
            [$name, $townHall, $userId] = $data;

            return [$tag => Player::query()->updateOrCreate(
                ['player_tag' => $tag],
                [
                    'user_id' => $userId,
                    'name' => $name,
                    'town_hall_level' => $townHall,
                ],
            )];
        })->all();
    }

    /**
     * @param  array<string, Clan>  $clans
     * @param  array<string, Player>  $players
     */
    private function seedMemberships(array $clans, array $players): void
    {
        $statusIds = MemberStatus::query()
            ->whereIn('slug', [MemberStatusEnum::In->value, MemberStatusEnum::Out->value])
            ->pluck('id', 'slug');
        $definitions = [
            'principal' => [
                ['#P0Y8L2QG', 'leader', 'in'],
                ['#Q2G9R8JV', 'coLeader', 'in'],
                ['#L8Y2P0CU', 'coLeader', 'in'],
                ['#G9Q2J8RV', 'admin', 'in'],
                ['#R2V8C0YL', 'admin', 'in'],
                ['#J8P2L9QG', 'member', 'in'],
                ['#C2U9V8RY', 'member', 'in'],
                ['#V8R2G0JP', 'member', 'in'],
                ['#Y2L9Q8CU', 'member', 'in'],
                ['#U8C2V0RG', 'member', 'in'],
                ['#P2Q9L8YV', 'member', 'in'],
                ['#G8J2R0CU', 'member', 'in'],
                ['#R9V2C8YL', 'member', 'in'],
                ['#J2P8Q0LG', 'member', 'out'],
            ],
            'academy' => [
                ['#Y0P2L8QG', 'leader', 'in'],
                ['#V2R8G0JC', 'coLeader', 'in'],
                ['#C8U2V0RY', 'admin', 'in'],
                ['#L2Y9Q8CU', 'member', 'in'],
                ['#C2U9V8RY', 'member', 'in'],
                ['#V8R2G0JP', 'member', 'in'],
                ['#J2P8Q0LG', 'member', 'in'],
            ],
        ];

        foreach ($definitions as $clanKey => $memberships) {
            foreach ($memberships as [$tag, $role, $status]) {
                ClanMembership::query()->updateOrCreate(
                    [
                        'clan_id' => $clans[$clanKey]->id,
                        'player_id' => $players[$tag]->id,
                    ],
                    [
                        'member_status_id' => $statusIds[$status],
                        'role' => $role,
                        'joined_at' => $status === 'in' ? now()->subMonths(4) : now()->subYear(),
                        'left_at' => $status === 'out' ? now()->subWeeks(3) : null,
                    ],
                );
            }
        }
    }

    /**
     * @param  array<string, Clan>  $clans
     * @param  array<string, Player>  $players
     */
    private function seedWars(array $clans, array $players): void
    {
        $definitions = [
            'principal' => [
                ['win', 2, 'Fúria Tropical', '#2Y8Q0L9PV', 14, 11, 96.4, 89.1, true],
                ['lose', 6, 'Titãs do Norte', '#9R2V8C0YL', 11, 13, 84.7, 92.8, false],
                ['win', 10, 'Guardiões BR', '#8J2P0Q9LG', 15, 12, 100.0, 94.6, true],
                ['tie', 16, 'Império Lunar', '#2C8U0V9RY', 13, 13, 91.5, 91.5, false],
            ],
            'academy' => [
                ['win', 3, 'Novos Heróis', '#8Y2L0Q9CU', 13, 9, 94.2, 80.7, true],
                ['lose', 9, 'Base Forte', '#9G2R8J0PV', 10, 12, 82.1, 90.3, false],
                ['win', 18, 'Mini Legends', '#2P8J0CU9L', 14, 11, 97.8, 88.4, true],
                ['win', 29, 'Clã Escola', '#8V2R0G9JP', 12, 8, 90.4, 75.2, false],
            ],
        ];
        $rosters = [
            'principal' => ['#P0Y8L2QG', '#Q2G9R8JV', '#L8Y2P0CU', '#G9Q2J8RV', '#R2V8C0YL'],
            'academy' => ['#Y0P2L8QG', '#V2R8G0JC', '#C8U2V0RY', '#L2Y9Q8CU', '#C2U9V8RY'],
        ];

        foreach ($definitions as $clanKey => $wars) {
            foreach ($wars as $index => $data) {
                [$result, $daysAgo, $opponentName, $opponentTag, $clanStars, $opponentStars, $clanDestruction, $opponentDestruction, $details] = $data;
                $endTime = now()->subDays($daysAgo)->setTime(21, 0);
                $war = War::query()->updateOrCreate(
                    ['external_key' => hash('sha256', "demo-{$clanKey}-war-{$index}")],
                    [
                        'clan_id' => $clans[$clanKey]->id,
                        'type' => 'regular',
                        'state' => 'warEnded',
                        'result' => $result,
                        'team_size' => 5,
                        'preparation_start_time' => $endTime->copy()->subDays(2),
                        'start_time' => $endTime->copy()->subDay(),
                        'end_time' => $endTime,
                        'has_details' => $details,
                        'clan_attacks' => 9,
                        'clan_stars' => $clanStars,
                        'clan_destruction_percentage' => $clanDestruction,
                        'opponent_tag' => $opponentTag,
                        'opponent_name' => $opponentName,
                        'opponent_badge_url' => null,
                        'opponent_attacks' => 9,
                        'opponent_stars' => $opponentStars,
                        'opponent_destruction_percentage' => $opponentDestruction,
                    ],
                );

                $war->members()->delete();
                $war->attacks()->delete();

                if ($details) {
                    $this->seedWarDetails($war, $players, $rosters[$clanKey], $index);
                }
            }
        }
    }

    /**
     * @param  array<string, Player>  $players
     * @param  list<string>  $roster
     */
    private function seedWarDetails(War $war, array $players, array $roster, int $warIndex): void
    {
        $rivalTags = [
            '#V9Y20QGR', '#L2P8J0CU', '#R8G2V9YL', '#Q0C8U2JP', '#Y9L2P8VG',
        ];

        foreach ($roster as $position => $tag) {
            $player = $players[$tag];
            $mapPosition = $position + 1;
            $rivalTag = $rivalTags[($warIndex + $position) % count($rivalTags)];

            $war->members()->create([
                'player_id' => $player->id,
                'side' => 'clan',
                'player_tag' => $player->player_tag,
                'name' => $player->name,
                'map_position' => $mapPosition,
                'townhall_level' => $player->town_hall_level,
                'opponent_attacks' => $position === 4 ? 1 : 2,
            ]);
            $war->members()->create([
                'side' => 'opponent',
                'player_tag' => $rivalTag,
                'name' => "Rival {$mapPosition}",
                'map_position' => $mapPosition,
                'townhall_level' => max(1, $player->town_hall_level - ($position % 2)),
                'opponent_attacks' => 2,
            ]);
            $war->attacks()->create([
                'attacker_player_id' => $player->id,
                'attacker_tag' => $player->player_tag,
                'defender_tag' => $rivalTag,
                'attack_order' => ($position * 2) + 1,
                'stars' => $position === 4 ? 2 : 3,
                'destruction_percentage' => $position === 4 ? 87.5 : 100,
                'duration' => 130 + ($position * 9),
            ]);
            $war->attacks()->create([
                'defender_player_id' => $player->id,
                'attacker_tag' => $rivalTag,
                'defender_tag' => $player->player_tag,
                'attack_order' => ($position * 2) + 2,
                'stars' => $position < 2 ? 3 : 2,
                'destruction_percentage' => $position < 2 ? 100 : 78 + $position,
                'duration' => 142 + ($position * 7),
            ]);
        }
    }
}
