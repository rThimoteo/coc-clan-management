<?php

namespace App\Services\Members;

use App\Enums\MemberStatus as MemberStatusEnum;
use App\Models\Clan;
use App\Models\Member;
use App\Models\MemberStatus;
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
    public function sync(): array
    {
        $clan = Clan::query()->first();

        if ($clan === null) {
            throw new \RuntimeException('Configure a tag do clã antes de sincronizar os membros.');
        }

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

            $existingMembers = Member::query()
                ->whereIn('player_tag', $incomingTags)
                ->get()
                ->keyBy('player_tag');

            $added = 0;
            $movedIn = 0;

            foreach ($clanMembers as $clanMember) {
                $member = $existingMembers->get($clanMember->tag);

                if ($member === null) {
                    Member::query()->create([
                        'member_status_id' => $inStatus->id,
                        'player_tag' => $clanMember->tag,
                        'name' => $clanMember->name,
                        'role' => $clanMember->role,
                        'town_hall_level' => $clanMember->townHallLevel,
                    ]);
                    $added++;

                    continue;
                }

                $wasOut = $member->member_status_id !== $inStatus->id;
                $member->update([
                    'member_status_id' => $inStatus->id,
                    'role' => $clanMember->role,
                    'town_hall_level' => $clanMember->townHallLevel,
                ]);

                if ($wasOut) {
                    $movedIn++;
                }
            }

            $movedOut = Member::query()
                ->whereNotIn('player_tag', $incomingTags)
                ->where('member_status_id', '!=', $outStatus->id)
                ->update(['member_status_id' => $outStatus->id]);

            $clan->update(['members_synced_at' => now()]);

            return [
                'added' => $added,
                'moved_in' => $movedIn,
                'moved_out' => $movedOut,
            ];
        });
    }
}
