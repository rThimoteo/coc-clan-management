<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'member_status_id',
    'player_tag',
    'name',
    'role',
    'town_hall_level',
])]
class Member extends Model
{
    public function status(): BelongsTo
    {
        return $this->belongsTo(MemberStatus::class, 'member_status_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
