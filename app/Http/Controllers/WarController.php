<?php

namespace App\Http\Controllers;

use App\Models\Clan;
use App\Models\War;
use App\Services\ClashOfClans\ClashOfClansException;
use App\Services\Wars\WarSyncService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class WarController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Wars/Index', [
            'wars' => War::query()
                ->whereNotIn('opponent_tag', ['', '#'])
                ->latest('end_time')
                ->get(),
            'clan' => Clan::query()->first(),
        ]);
    }

    public function show(War $war): Response
    {
        abort_unless($war->has_details, 404);

        return Inertia::render('Wars/Show', [
            'clan' => Clan::query()->first(),
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
