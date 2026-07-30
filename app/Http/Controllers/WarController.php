<?php

namespace App\Http\Controllers;

use App\Models\Clan;
use App\Models\War;
use App\Services\Clans\ActiveClanContext;
use App\Services\ClashOfClans\ClashOfClansException;
use App\Services\Wars\WarSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class WarController extends Controller
{
    public function index(Request $request, ActiveClanContext $context): Response
    {
        $clan = $context->active($request);
        $result = $request->string('result')->toString();
        $result = in_array($result, ['win', 'lose', 'tie'], true) ? $result : null;
        $wars = War::query()
            ->when($clan, fn ($query, Clan $activeClan) => $query
                ->whereBelongsTo($activeClan))
            ->when(! $clan, fn ($query) => $query->whereRaw('1 = 0'))
            ->where('type', 'regular')
            ->whereNotIn('opponent_tag', ['', '#'])
            ->when($result, fn ($query, string $value) => $query
                ->where('result', $value));
        $allWars = War::query()
            ->when($clan, fn ($query, Clan $activeClan) => $query
                ->whereBelongsTo($activeClan))
            ->when(! $clan, fn ($query) => $query->whereRaw('1 = 0'))
            ->where('type', 'regular')
            ->whereNotIn('opponent_tag', ['', '#']);

        return Inertia::render('Wars/Index', [
            'activeWar' => War::query()
                ->when($clan, fn ($query, Clan $activeClan) => $query
                    ->whereBelongsTo($activeClan))
                ->when(! $clan, fn ($query) => $query->whereRaw('1 = 0'))
                ->where('type', 'regular')
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
            'clan' => $clan,
        ]);
    }

    public function show(
        Request $request,
        War $war,
        ActiveClanContext $context,
    ): Response {
        $clan = $context->active($request);

        abort_unless($clan && $war->clan_id === $clan->id, 404);
        abort_unless($war->has_details, 404);

        return Inertia::render('Wars/Show', [
            'clan' => $clan,
            'isActive' => $war->end_time->isFuture(),
            'isPreparation' => $war->state === 'preparation',
            'war' => $war->load([
                'members' => fn ($query) => $query->orderBy('map_position'),
                'attacks' => fn ($query) => $query->orderBy('attack_order'),
            ]),
        ]);
    }

    public function sync(
        Request $request,
        ActiveClanContext $context,
        WarSyncService $warSync,
    ): RedirectResponse {
        try {
            $clan = $context->active($request);

            if ($clan === null) {
                throw new RuntimeException('Configure um clã antes de sincronizar as guerras.');
            }

            $summary = $warSync->sync($clan);
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
