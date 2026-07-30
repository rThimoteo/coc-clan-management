<?php

namespace App\Services\ClashOfClans;

readonly class CwlLeagueGroup
{
    /**
     * @param  list<CwlLeagueClan>  $clans
     * @param  list<CwlRound>  $rounds
     */
    public function __construct(
        public string $state,
        public string $season,
        public array $clans,
        public array $rounds,
    ) {}
}
