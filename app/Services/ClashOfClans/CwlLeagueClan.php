<?php

namespace App\Services\ClashOfClans;

readonly class CwlLeagueClan
{
    public function __construct(
        public string $tag,
        public string $name,
        public ?int $clanLevel,
        public ?string $badgeUrl,
    ) {}
}
