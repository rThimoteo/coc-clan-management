<?php

namespace App\Services\ClashOfClans;

readonly class CwlWarTag
{
    public function __construct(
        public string $value,
        public bool $isPlaceholder,
    ) {}
}
