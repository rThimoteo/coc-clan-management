<?php

namespace App\Services\Members;

use App\Enums\MemberStatus as MemberStatusEnum;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\MemberStatus;
use App\Models\Player;
use App\Services\ClashOfClans\ClanMember;
use App\Services\ClashOfClans\ClashOfClansService;
use Illuminate\Support\Facades\DB;

class MemberSyncService
{
    public function __construct(
        private readonly ClashOfClansService $clashOfClans,
    ) {}

    /**
     * @return array{added: int, moved_in: int, moved_out: int}
     */
    public function sync(Clan $clan): array
    {
        $clanMembers = $this->clashOfClans->clanMembers($clan->tag);

        return DB::transaction(function () use ($clan, $clanMembers): array {
            $inStatus = MemberStatus::query()
                ->where('slug', MemberStatusEnum::In->value)
                ->sole();
            $outStatus = MemberStatus::query()
                ->where('slug', MemberStatusEnum::Out->value)
                ->sole();

            $incomingTags = collect($clanMembers)
                ->map(fn (ClanMember $member): string => $member->tag)
                ->unique()
                ->values();

            $players = Player::query()
                ->whereIn('player_tag', $incomingTags)
                ->get()
                ->keyBy('player_tag');
            $memberships = ClanMembership::query()
                ->whereBelongsTo($clan)
                ->whereIn('player_id', $players->pluck('id'))
                ->get()
                ->keyBy('player_id');

            $added = 0;
            $movedIn = 0;

            foreach ($clanMembers as $clanMember) {
                $player = $players->get($clanMember->tag);

                if ($player === null) {
                    $player = Player::query()->create([
                        'player_tag' => $clanMember->tag,
                        'name' => $clanMember->name,
                        'town_hall_level' => $clanMember->townHallLevel,
                    ]);
                    $players->put($clanMember->tag, $player);
                } else {
                    $player->update([
                        'town_hall_level' => $clanMember->townHallLevel,
                    ]);
                }

                $membership = $memberships->get($player->id);

                if ($membership === null) {
                    $membership = ClanMembership::query()->create([
                        'clan_id' => $clan->id,
                        'player_id' => $player->id,
                        'member_status_id' => $inStatus->id,
                        'role' => $clanMember->role,
                        'joined_at' => now(),
                    ]);
                    $memberships->put($player->id, $membership);
                    $added++;

                    continue;
                }

                $wasOut = $membership->member_status_id !== $inStatus->id;
                $membership->update([
                    'member_status_id' => $inStatus->id,
                    'role' => $clanMember->role,
                    'joined_at' => $wasOut ? now() : $membership->joined_at,
                    'left_at' => null,
                ]);

                if ($wasOut) {
                    $movedIn++;
                }
            }

            $movedOutMemberships = ClanMembership::query()
                ->whereBelongsTo($clan)
                ->whereNotIn('player_id', $players
                    ->whereIn('player_tag', $incomingTags)
                    ->pluck('id'))
                ->where('member_status_id', '!=', $outStatus->id)
                ->get();
            $movedOut = $movedOutMemberships->count();

            ClanMembership::query()
                ->whereKey($movedOutMemberships->modelKeys())
                ->update([
                    'member_status_id' => $outStatus->id,
                    'left_at' => now(),
                ]);

            $clan->update(['members_synced_at' => now()]);

            return [
                'added' => $added,
                'moved_in' => $movedIn,
                'moved_out' => $movedOut,
            ];
        });
    }
}
