<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'player_tag',
    'name',
    'town_hall_level',
    'league_id',
    'league_name',
    'league_icon_url',
    'trophies',
])]
class Player extends Model
{
    protected function casts(): array
    {
        return [
            'league_id' => 'integer',
            'trophies' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ClanMembership::class);
    }

    public function warMembers(): HasMany
    {
        return $this->hasMany(WarMember::class);
    }

    public function attacks(): HasMany
    {
        return $this->hasMany(WarAttack::class, 'attacker_player_id');
    }

    public function defenses(): HasMany
    {
        return $this->hasMany(WarAttack::class, 'defender_player_id');
    }
}
