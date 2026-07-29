<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'access_code', 'role_id'])]
#[Hidden(['access_code', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_code' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isAdmin(): bool
    {
        return $this->role?->slug === UserRole::Admin->value;
    }

    public function canSyncClanData(): bool
    {
        return in_array($this->role?->slug, [
            UserRole::Admin->value,
            UserRole::Leader->value,
        ], true);
    }

    public function canManageUserRoles(): bool
    {
        return in_array($this->role?->slug, [
            UserRole::Admin->value,
            UserRole::Leader->value,
        ], true);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
