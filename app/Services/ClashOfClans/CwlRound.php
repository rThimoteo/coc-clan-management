<?php

namespace App\Services\ClashOfClans;

readonly class CwlRound
{
    /**
     * @param  list<CwlWarTag>  $warTags
     */
    public function __construct(
        public int $number,
        public array $warTags,
    ) {}
}
