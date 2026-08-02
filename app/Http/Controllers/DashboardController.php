<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\War;
use App\Services\Clans\ActiveClanContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ActiveClanContext $context): Response
    {
        $clan = $context->active($request);
        $reportableWars = War::query()
            ->when($clan, fn ($query, Clan $activeClan) => $query
                ->whereBelongsTo($activeClan))
            ->when(! $clan, fn ($query) => $query->whereRaw('1 = 0'))
            ->whereNotIn('opponent_tag', ['', '#']);
        $monthlyWars = (clone $reportableWars)
            ->whereBetween('end_time', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ]);
        $completedWars = (clone $monthlyWars)
            ->whereIn('result', ['win', 'lose', 'tie'])
            ->count();
        $monthlyVictories = (clone $monthlyWars)
            ->where('result', 'win')
            ->count();

        return Inertia::render('Dashboard', [
            'activeWar' => (clone $reportableWars)
                ->with('leagueRoundWar.round:id,clan_war_league_id')
                ->active()
                ->latest('end_time')
                ->first(),
            'clan' => $clan,
            'metrics' => [
                'activeMembers' => ClanMembership::query()
                    ->when($clan, fn ($query, Clan $activeClan) => $query
                        ->whereBelongsTo($activeClan))
                    ->when(! $clan, fn ($query) => $query->whereRaw('1 = 0'))
                    ->whereHas('status', fn ($query) => $query
                        ->where('slug', MemberStatus::In->value))
                    ->count(),
                'monthlyWars' => (clone $monthlyWars)->count(),
                'winRate' => $completedWars > 0
                    ? round(($monthlyVictories / $completedWars) * 100, 1)
                    : null,
            ],
            'recentWars' => (clone $reportableWars)
                ->with('leagueRoundWar.round:id,clan_war_league_id')
                ->latest('end_time')
                ->limit(5)
                ->get(),
        ]);
    }
}
