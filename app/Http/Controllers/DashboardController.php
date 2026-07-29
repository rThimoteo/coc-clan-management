<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Models\Clan;
use App\Models\Member;
use App\Models\War;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $reportableWars = War::query()
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
                ->active()
                ->latest('end_time')
                ->first(),
            'clan' => Clan::query()->first(),
            'metrics' => [
                'activeMembers' => Member::query()
                    ->whereHas('status', fn ($query) => $query
                        ->where('slug', MemberStatus::In->value))
                    ->count(),
                'monthlyWars' => (clone $monthlyWars)->count(),
                'winRate' => $completedWars > 0
                    ? round(($monthlyVictories / $completedWars) * 100, 1)
                    : null,
            ],
            'recentWars' => (clone $reportableWars)
                ->latest('end_time')
                ->limit(5)
                ->get(),
        ]);
    }
}
