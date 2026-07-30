<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'clan_war_league_id',
    'clan_tag',
    'name',
    'clan_level',
    'badge_url',
])]
class ClanWarLeagueClan extends Model
{
    public function league(): BelongsTo
    {
        return $this->belongsTo(ClanWarLeague::class, 'clan_war_league_id');
    }
}
