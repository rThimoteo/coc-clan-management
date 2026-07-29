<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Models\Clan;
use App\Models\Member;
use App\Services\ClashOfClans\ClashOfClansException;
use App\Services\Members\MemberSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim($request->string('search')->toString()),
            'townHall' => $request->integer('town_hall') ?: null,
            'role' => $request->string('role')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: MemberStatus::In->value,
            'sort' => $request->string('sort')->toString() ?: 'name',
            'direction' => $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc',
        ];
        $filters['status'] = in_array($filters['status'], ['all', 'in', 'out'], true)
            ? $filters['status']
            : MemberStatus::In->value;
        $filters['sort'] = in_array($filters['sort'], ['name', 'town_hall', 'role'], true)
            ? $filters['sort']
            : 'name';

        $allMembers = Member::query();
        $members = Member::query()
            ->when($filters['search'], fn ($query, string $search) => $query
                ->where('name', 'like', "%{$search}%"))
            ->when($filters['townHall'], fn ($query, int $townHall) => $query
                ->where('town_hall_level', $townHall))
            ->when($filters['role'], fn ($query, string $role) => $query
                ->where('role', $role))
            ->when($filters['status'] !== 'all', fn ($query) => $query
                ->whereHas('status', fn ($statusQuery) => $statusQuery
                    ->where('slug', $filters['status'])));

        match ($filters['sort']) {
            'town_hall' => $members
                ->orderBy('town_hall_level', $filters['direction'])
                ->orderBy('name'),
            'role' => $members
                ->orderByRaw(
                    "CASE role WHEN 'leader' THEN 1 WHEN 'coLeader' THEN 2 WHEN 'admin' THEN 3 WHEN 'member' THEN 4 ELSE 5 END {$filters['direction']}",
                )
                ->orderBy('name'),
            default => $members->orderBy('name', $filters['direction']),
        };

        return Inertia::render('Members/Index', [
            'memberStats' => [
                'total' => (clone $allMembers)->count(),
                'inClan' => (clone $allMembers)
                    ->whereHas('status', fn ($query) => $query
                        ->where('slug', MemberStatus::In->value))
                    ->count(),
                'outClan' => (clone $allMembers)
                    ->whereHas('status', fn ($query) => $query
                        ->where('slug', MemberStatus::Out->value))
                    ->count(),
            ],
            'filters' => $filters,
            'filterOptions' => [
                'townHalls' => Member::query()
                    ->whereNotNull('town_hall_level')
                    ->distinct()
                    ->orderByDesc('town_hall_level')
                    ->pluck('town_hall_level'),
                'roles' => Member::query()
                    ->whereNotNull('role')
                    ->distinct()
                    ->orderBy('role')
                    ->pluck('role'),
            ],
            'members' => $members
                ->with('status:id,slug')
                ->paginate(20)
                ->withQueryString(),
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
