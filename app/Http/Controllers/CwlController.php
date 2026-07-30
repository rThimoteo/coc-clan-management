<?php

namespace App\Http\Controllers;

use App\Models\Clan;
use App\Models\ClanWarLeague;
use App\Services\Clans\ActiveClanContext;
use App\Services\ClashOfClans\ClashOfClansException;
use App\Services\Wars\CwlSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CwlController extends Controller
{
    public function index(Request $request, ActiveClanContext $context): Response
    {
        $clan = $context->active($request);
        $leaguesQuery = ClanWarLeague::query()
            ->when($clan, fn ($query, Clan $activeClan) => $query
                ->whereBelongsTo($activeClan))
            ->when(! $clan, fn ($query) => $query->whereRaw('1 = 0'));
        $leagues = (clone $leaguesQuery)
            ->withCount([
                'rounds',
                'participants',
            ])
            ->latest('season')
            ->paginate(10);
        $lastSyncedAt = (clone $leaguesQuery)->max('synced_at');

        return Inertia::render('Cwl/Index', [
            'clan' => $clan,
            'leagues' => $leagues,
            'leagueStats' => [
                'total' => (clone $leaguesQuery)->count(),
                'detailed' => (clone $leaguesQuery)
                    ->whereHas('rounds')
                    ->count(),
                'last_synced_at' => $lastSyncedAt
                    ? CarbonImmutable::parse($lastSyncedAt)
                    : null,
            ],
        ]);
    }

    public function show(
        Request $request,
        ClanWarLeague $league,
        ActiveClanContext $context,
    ): Response {
        $clan = $context->active($request);

        abort_unless($clan && $league->clan_id === $clan->id, 404);

        return Inertia::render('Cwl/Show', [
            'clan' => $clan,
            'league' => $league->load([
                'participants' => fn ($query) => $query->orderBy('name'),
                'rounds' => fn ($query) => $query
                    ->orderBy('round_number')
                    ->with([
                        'wars' => fn ($query) => $query
                            ->orderBy('id')
                            ->with('war'),
                    ]),
            ]),
        ]);
    }

    public function sync(
        Request $request,
        ActiveClanContext $context,
        CwlSyncService $sync,
    ): RedirectResponse {
        try {
            $clan = $context->active($request);

            if ($clan === null) {
                throw new RuntimeException('Configure um clã antes de sincronizar a CWL.');
            }

            $summary = $sync->sync($clan);
        } catch (ClashOfClansException|RuntimeException $exception) {
            return back()->withErrors([
                'sync' => $exception->getMessage(),
            ]);
        }

        return back()
            ->with('status', 'cwl-synced')
            ->with('syncSummary', $summary);
    }
}
