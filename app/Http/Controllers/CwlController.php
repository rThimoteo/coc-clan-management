<?php

namespace App\Http\Controllers;

use App\Models\Clan;
use App\Models\ClanWarLeague;
use App\Models\War;
use App\Services\Clans\ActiveClanContext;
use App\Services\ClashOfClans\ClashOfClansException;
use App\Services\Wars\CwlMemberPerformance;
use App\Services\Wars\CwlStandings;
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
        CwlStandings $standings,
        CwlMemberPerformance $memberPerformance,
    ): Response {
        $clan = $context->active($request);

        abort_unless($clan && $league->clan_id === $clan->id, 404);

        $league->load([
            'participants' => fn ($query) => $query->orderBy('name'),
            'rounds' => fn ($query) => $query
                ->orderBy('round_number')
                ->with([
                    'wars' => fn ($query) => $query
                        ->orderBy('id')
                        ->with('war'),
                ]),
        ]);

        return Inertia::render('Cwl/Show', [
            'clan' => $clan,
            'league' => $league,
            'standings' => $standings->forLeague($league),
            'memberPerformance' => $memberPerformance->forLeague($league),
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

    public function war(
        Request $request,
        ClanWarLeague $league,
        War $war,
        ActiveClanContext $context,
    ): Response {
        $clan = $context->active($request);

        abort_unless(
            $clan &&
            $league->clan_id === $clan->id &&
            $war->clan_id === $clan->id &&
            $war->type === 'cwl' &&
            $war->has_details &&
            $war->leagueRoundWar()
                ->whereHas('round', fn ($query) => $query
                    ->where('clan_war_league_id', $league->id))
                ->exists(),
            404,
        );

        return Inertia::render('Wars/Show', [
            'clan' => $clan,
            'isActive' => $war->end_time->isFuture(),
            'isPreparation' => $war->state === 'preparation',
            'navigation' => [
                'back_href' => route('cwl.show', $league),
                'back_label' => "Voltar para CWL {$league->season}",
                'sync_route' => route('cwl.sync'),
            ],
            'war' => $war->load([
                'members' => fn ($query) => $query->orderBy('map_position'),
                'attacks' => fn ($query) => $query->orderBy('attack_order'),
            ]),
        ]);
    }
}
