<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tag', 'name', 'badge_url', 'members_synced_at', 'wars_synced_at'])]
class Clan extends Model
{
    protected function casts(): array
    {
        return [
            'members_synced_at' => 'datetime',
            'wars_synced_at' => 'datetime',
        ];
    }
}
