<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Leader = 'leader';
    case CoLeader = 'co_leader';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Leader => 'Líder',
            self::CoLeader => 'Colíder',
            self::Member => 'Membro',
        };
    }
}
