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
    'state',
    'team_size',
    'preparation_start_time',
    'start_time',
    'end_time',
    'clan_tag',
    'clan_name',
    'clan_badge_url',
    'clan_attacks',
    'clan_stars',
    'clan_destruction_percentage',
    'opponent_tag',
    'opponent_name',
    'opponent_badge_url',
    'opponent_attacks',
    'opponent_stars',
    'opponent_destruction_percentage',
    'winner_tag',
    'summary_synced_at',
    'final_synced_at',
    'war_id',
])]
class ClanWarLeagueRoundWar extends Model
{
    protected function casts(): array
    {
        return [
            'is_placeholder' => 'boolean',
            'preparation_start_time' => 'datetime',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'clan_destruction_percentage' => 'float',
            'opponent_destruction_percentage' => 'float',
            'summary_synced_at' => 'datetime',
            'final_synced_at' => 'datetime',
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
