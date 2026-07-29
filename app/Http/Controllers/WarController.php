<?php

namespace App\Http\Controllers;

use App\Models\Clan;
use App\Models\War;
use App\Services\ClashOfClans\ClashOfClansException;
use App\Services\Wars\WarSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class WarController extends Controller
{
    public function index(Request $request): Response
    {
        $result = $request->string('result')->toString();
        $result = in_array($result, ['win', 'lose', 'tie'], true) ? $result : null;
        $wars = War::query()
            ->whereNotIn('opponent_tag', ['', '#'])
            ->when($result, fn ($query, string $value) => $query
                ->where('result', $value));
        $allWars = War::query()
            ->whereNotIn('opponent_tag', ['', '#']);

        return Inertia::render('Wars/Index', [
            'activeWar' => War::query()
                ->active()
                ->latest('end_time')
                ->first(),
            'warStats' => [
                'total' => (clone $allWars)->count(),
                'victories' => (clone $allWars)->where('result', 'win')->count(),
                'detailed' => (clone $allWars)->where('has_details', true)->count(),
            ],
            'filters' => ['result' => $result],
            'wars' => $wars
                ->latest('end_time')
                ->paginate(20)
                ->withQueryString(),
            'clan' => Clan::query()->first(),
        ]);
    }

    public function show(War $war): Response
    {
        abort_unless($war->has_details, 404);

        return Inertia::render('Wars/Show', [
            'clan' => Clan::query()->first(),
            'isActive' => $war->end_time->isFuture(),
            'war' => $war->load([
                'members' => fn ($query) => $query->orderBy('map_position'),
                'attacks' => fn ($query) => $query->orderBy('attack_order'),
            ]),
        ]);
    }

    public function sync(WarSyncService $warSync): RedirectResponse
    {
        try {
            $summary = $warSync->sync();
        } catch (ClashOfClansException|RuntimeException $exception) {
            return back()->withErrors([
                'sync' => $exception->getMessage(),
            ]);
        }

        return back()
            ->with('status', 'wars-synced')
            ->with('syncSummary', $summary);
    }
}
