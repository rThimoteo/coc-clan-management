<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'war_id',
    'attacker_tag',
    'defender_tag',
    'attack_order',
    'stars',
    'destruction_percentage',
    'duration',
])]
class WarAttack extends Model
{
    protected function casts(): array
    {
        return [
            'destruction_percentage' => 'float',
        ];
    }

    public function war(): BelongsTo
    {
        return $this->belongsTo(War::class);
    }
}
