<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'clan_war_league_round_id',
    'war_tag',
    'is_placeholder',
    'status',
    'war_id',
])]
class ClanWarLeagueRoundWar extends Model
{
    protected function casts(): array
    {
        return [
            'is_placeholder' => 'boolean',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(ClanWarLeagueRound::class, 'clan_war_league_round_id');
    }

    public function war(): BelongsTo
    {
        return $this->belongsTo(War::class);
    }
}
