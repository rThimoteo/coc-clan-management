<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['clan_war_league_id', 'round_number'])]
class ClanWarLeagueRound extends Model
{
    public function league(): BelongsTo
    {
        return $this->belongsTo(ClanWarLeague::class, 'clan_war_league_id');
    }

    public function wars(): HasMany
    {
        return $this->hasMany(ClanWarLeagueRoundWar::class);
    }
}
