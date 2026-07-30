<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'war_id',
    'player_id',
    'side',
    'player_tag',
    'name',
    'map_position',
    'townhall_level',
    'opponent_attacks',
])]
class WarMember extends Model
{
    public function war(): BelongsTo
    {
        return $this->belongsTo(War::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
