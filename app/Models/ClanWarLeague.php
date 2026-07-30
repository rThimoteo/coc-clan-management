<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'clan_id',
    'season',
    'state',
    'has_summary',
    'end_time',
    'team_size',
    'attacks_per_member',
    'battle_modifier',
    'clan_badge_url',
    'clan_attacks',
    'clan_stars',
    'clan_destruction_percentage',
    'opponent_badge_url',
    'opponent_stars',
    'opponent_destruction_percentage',
    'synced_at',
])]
class ClanWarLeague extends Model
{
    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
            'end_time' => 'datetime',
            'has_summary' => 'boolean',
            'clan_destruction_percentage' => 'float',
            'opponent_destruction_percentage' => 'float',
        ];
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ClanWarLeagueClan::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(ClanWarLeagueRound::class);
    }
}
