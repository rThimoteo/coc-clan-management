<?php

namespace App\Http\Controllers;

use App\Models\Clan;
use App\Models\Member;
use App\Services\ClashOfClans\ClashOfClansException;
use App\Services\Members\MemberSyncService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class MemberController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Members/Index', [
            'members' => Member::query()
                ->with('status:id,slug')
                ->orderBy('name')
                ->get(),
            'clan' => Clan::query()->first(),
        ]);
    }

    public function sync(MemberSyncService $memberSync): RedirectResponse
    {
        try {
            $summary = $memberSync->sync();
        } catch (ClashOfClansException|RuntimeException $exception) {
            return back()->withErrors([
                'sync' => $exception->getMessage(),
            ]);
        }

        return back()
            ->with('status', 'members-synced')
            ->with('syncSummary', $summary);
    }
}
