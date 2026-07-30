<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'clan_id',
    'player_id',
    'member_status_id',
    'role',
    'joined_at',
    'left_at',
])]
class ClanMembership extends Model
{
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(MemberStatus::class, 'member_status_id');
    }
}
