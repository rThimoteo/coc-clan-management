<?php

namespace Database\Seeders;

use App\Enums\MemberStatus as MemberStatusEnum;
use App\Enums\UserRole;
use App\Models\Clan;
use App\Models\Member;
use App\Models\MemberStatus;
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
            $members = $this->seedMembers($users);

            Clan::query()->updateOrCreate(
                ['tag' => '#2Q8L9Y0JP'],
                [
                    'name' => 'Clã de Demonstração',
                    'badge_url' => null,
                    'members_synced_at' => now()->subHours(3),
                    'wars_synced_at' => now()->subHours(2),
                ],
            );

            $this->seedWars($members);
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
     * @param  array<string, User>  $users
     * @return array<string, Member>
     */
    private function seedMembers(array $users): array
    {
        $statuses = MemberStatus::query()
            ->whereIn('slug', [MemberStatusEnum::In->value, MemberStatusEnum::Out->value])
            ->pluck('id', 'slug');
        $members = [
            ['#P0Y8L2QG', 'Ayla', 'leader', 17, 'in', $users['leader']->id],
            ['#Q2G9R8JV', 'Breno', 'coLeader', 17, 'in', $users['co_leader']->id],
            ['#L8Y2P0CU', 'Caio', 'coLeader', 16, 'in', null],
            ['#G9Q2J8RV', 'Dandara', 'admin', 16, 'in', null],
            ['#R2V8C0YL', 'Enzo', 'admin', 15, 'in', null],
            ['#J8P2L9QG', 'Fernanda', 'member', 15, 'in', $users['member']->id],
            ['#C2U9V8RY', 'Gael', 'member', 14, 'in', null],
            ['#V8R2G0JP', 'Helena', 'member', 14, 'in', null],
            ['#Y2L9Q8CU', 'Ícaro', 'member', 13, 'in', null],
            ['#U8C2V0RG', 'Joana', 'member', 13, 'in', null],
            ['#P2Q9L8YV', 'Kaique', 'member', 12, 'in', null],
            ['#G8J2R0CU', 'Luna', 'member', 12, 'in', null],
            ['#R9V2C8YL', 'Miguel', 'member', 11, 'in', null],
            ['#J2P8Q0LG', 'Nina', 'member', 15, 'out', null],
        ];

        return collect($members)->mapWithKeys(function (array $data) use ($statuses): array {
            [$tag, $name, $role, $townHall, $status, $userId] = $data;
            $member = Member::query()->updateOrCreate(
                ['player_tag' => $tag],
                [
                    'user_id' => $userId,
                    'member_status_id' => $statuses[$status],
                    'name' => $name,
                    'role' => $role,
                    'town_hall_level' => $townHall,
                ],
            );

            return [$tag => $member];
        })->all();
    }

    /**
     * @param  array<string, Member>  $members
     */
    private function seedWars(array $members): void
    {
        $wars = [
            ['win', 2, 'Fúria Tropical', '#2Y8Q0L9PV', 38, 34, 96.4, 89.1, true],
            ['lose', 6, 'Titãs do Norte', '#9R2V8C0YL', 31, 35, 84.7, 92.8, false],
            ['win', 10, 'Guardiões BR', '#8J2P0Q9LG', 40, 37, 98.2, 94.6, true],
            ['tie', 16, 'Império Lunar', '#2C8U0V9RY', 36, 36, 91.5, 91.5, false],
            ['win', 24, 'Lendas Unidas', '#9G2R8J0PV', 39, 32, 97.1, 86.3, false],
            ['lose', 35, 'Arena Latina', '#8Y2L0Q9CU', 33, 38, 88.9, 96.7, true],
        ];

        foreach ($wars as $index => $data) {
            [$result, $daysAgo, $opponentName, $opponentTag, $clanStars, $opponentStars, $clanDestruction, $opponentDestruction, $details] = $data;
            $endTime = now()->subDays($daysAgo)->setTime(21, 0);
            $war = War::query()->updateOrCreate(
                ['external_key' => hash('sha256', "demo-war-{$index}")],
                [
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

            if ($details) {
                $this->seedWarDetails($war, $members, $index);
            } else {
                $war->members()->delete();
                $war->attacks()->delete();
            }
        }
    }

    /**
     * @param  array<string, Member>  $members
     */
    private function seedWarDetails(War $war, array $members, int $warIndex): void
    {
        $war->members()->delete();
        $war->attacks()->delete();
        $clanMembers = array_slice(array_values($members), 0, 5);
        $rivalTags = [
            '#V9Y20QGR', '#L2P8J0CU', '#R8G2V9YL', '#Q0C8U2JP', '#Y9L2P8VG',
        ];

        foreach ($clanMembers as $position => $member) {
            $mapPosition = $position + 1;
            $rivalTag = $rivalTags[($warIndex + $position) % count($rivalTags)];

            $war->members()->create([
                'side' => 'clan',
                'player_tag' => $member->player_tag,
                'name' => $member->name,
                'map_position' => $mapPosition,
                'townhall_level' => $member->town_hall_level,
                'opponent_attacks' => $position === 4 ? 1 : 2,
            ]);
            $war->members()->create([
                'side' => 'opponent',
                'player_tag' => $rivalTag,
                'name' => "Rival {$mapPosition}",
                'map_position' => $mapPosition,
                'townhall_level' => max(1, $member->town_hall_level - ($position % 2)),
                'opponent_attacks' => 2,
            ]);
            $war->attacks()->create([
                'attacker_tag' => $member->player_tag,
                'defender_tag' => $rivalTag,
                'attack_order' => ($position * 2) + 1,
                'stars' => $position === 4 ? 2 : 3,
                'destruction_percentage' => $position === 4 ? 87.5 : 100,
                'duration' => 130 + ($position * 9),
            ]);
            $war->attacks()->create([
                'attacker_tag' => $rivalTag,
                'defender_tag' => $member->player_tag,
                'attack_order' => ($position * 2) + 2,
                'stars' => $position < 2 ? 3 : 2,
                'destruction_percentage' => $position < 2 ? 100 : 78 + $position,
                'duration' => 142 + ($position * 7),
            ]);
        }
    }
}
