<?php

namespace App\Services\ClashOfClans;

readonly class ClanMember
{
    public function __construct(
        public string $tag,
        public string $name,
        public ?string $role,
        public ?int $townHallLevel,
    ) {}
}
