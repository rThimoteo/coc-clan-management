<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tag', 'name', 'badge_url', 'is_default', 'members_synced_at', 'wars_synced_at'])]
class Clan extends Model
{
    protected function casts(): array
    {
        return [
            'members_synced_at' => 'datetime',
            'wars_synced_at' => 'datetime',
            'is_default' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ClanMembership::class);
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'clan_memberships')
            ->withPivot([
                'id',
                'member_status_id',
                'role',
                'joined_at',
                'left_at',
            ])
            ->withTimestamps();
    }

    public function wars(): HasMany
    {
        return $this->hasMany(War::class);
    }

    public function warLeagues(): HasMany
    {
        return $this->hasMany(ClanWarLeague::class);
    }
}
