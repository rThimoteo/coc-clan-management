<?php

namespace App\Services\ClashOfClans;

readonly class ClanProfile
{
    public function __construct(
        public string $tag,
        public string $name,
        public ?string $badgeUrl,
    ) {}
}
