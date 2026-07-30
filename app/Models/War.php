<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'clan_id',
    'external_key',
    'type',
    'state',
    'result',
    'team_size',
    'preparation_start_time',
    'start_time',
    'end_time',
    'has_details',
    'clan_attacks',
    'clan_stars',
    'clan_destruction_percentage',
    'opponent_tag',
    'opponent_name',
    'opponent_badge_url',
    'opponent_attacks',
    'opponent_stars',
    'opponent_destruction_percentage',
])]
class War extends Model
{
    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('end_time', '>', now());
    }

    protected function casts(): array
    {
        return [
            'preparation_start_time' => 'datetime',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'has_details' => 'boolean',
            'clan_destruction_percentage' => 'float',
            'opponent_destruction_percentage' => 'float',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(WarMember::class);
    }

    public function attacks(): HasMany
    {
        return $this->hasMany(WarAttack::class);
    }

    public function leagueRoundWar(): HasOne
    {
        return $this->hasOne(ClanWarLeagueRoundWar::class);
    }
}
