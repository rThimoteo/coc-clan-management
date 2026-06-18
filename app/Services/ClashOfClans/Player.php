<?php

namespace App\Services\ClashOfClans;

readonly class Player
{
    public function __construct(
        public string $tag,
        public string $name,
        public string $clanTag,
        public ?string $clanRole,
    ) {
    }
}
