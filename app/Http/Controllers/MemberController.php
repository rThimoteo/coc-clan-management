<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Services\Clans\ActiveClanContext;
use App\Services\ClashOfClans\ClashOfClansException;
use App\Services\Members\MemberSyncService;
use App\Services\Members\PlayerPerformanceQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class MemberController extends Controller
{
    public function index(
        Request $request,
        ActiveClanContext $context,
        PlayerPerformanceQuery $performance,
    ): Response {
        $clan = $context->active($request);
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

        $allMembers = ClanMembership::query()
            ->when($clan, fn ($query, Clan $activeClan) => $query
                ->whereBelongsTo($activeClan))
            ->when(! $clan, fn ($query) => $query->whereRaw('1 = 0'));
        $members = ClanMembership::query()
            ->join('players', 'players.id', '=', 'clan_memberships.player_id')
            ->select([
                'clan_memberships.*',
                'players.name',
                'players.player_tag',
                'players.town_hall_level',
            ])
            ->when($clan, fn ($query, Clan $activeClan) => $query
                ->where('clan_memberships.clan_id', $activeClan->id))
            ->when(! $clan, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($filters['search'], fn ($query, string $search) => $query
                ->where('players.name', 'like', "%{$search}%"))
            ->when($filters['townHall'], fn ($query, int $townHall) => $query
                ->where('players.town_hall_level', $townHall))
            ->when($filters['role'], fn ($query, string $role) => $query
                ->where('clan_memberships.role', $role))
            ->when($filters['status'] !== 'all', fn ($query) => $query
                ->whereHas('status', fn ($statusQuery) => $statusQuery
                    ->where('slug', $filters['status'])));

        match ($filters['sort']) {
            'town_hall' => $members
                ->orderBy('players.town_hall_level', $filters['direction'])
                ->orderBy('players.name'),
            'role' => $members
                ->orderByRaw(
                    "CASE clan_memberships.role WHEN 'leader' THEN 1 WHEN 'coLeader' THEN 2 WHEN 'admin' THEN 3 WHEN 'member' THEN 4 ELSE 5 END {$filters['direction']}",
                )
                ->orderBy('players.name'),
            default => $members->orderBy('players.name', $filters['direction']),
        };

        $members = $members
            ->with('status:id,slug')
            ->paginate(20)
            ->withQueryString();
        $performanceSummaries = $clan
            ? $performance->summaries(
                $clan,
                $members->getCollection()->pluck('player_id')->all(),
                10,
            )
            : [];
        $members->getCollection()->transform(function (ClanMembership $membership) use ($performanceSummaries): ClanMembership {
            $membership->setAttribute(
                'performance_summary',
                $performanceSummaries[$membership->player_id] ?? [
                    'wars' => 0,
                    'attacks' => 0,
                    'average_stars' => 0,
                ],
            );

            return $membership;
        });

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
                'townHalls' => ClanMembership::query()
                    ->join('players', 'players.id', '=', 'clan_memberships.player_id')
                    ->when($clan, fn ($query, Clan $activeClan) => $query
                        ->where('clan_memberships.clan_id', $activeClan->id))
                    ->when(! $clan, fn ($query) => $query->whereRaw('1 = 0'))
                    ->whereNotNull('players.town_hall_level')
                    ->distinct()
                    ->orderByDesc('players.town_hall_level')
                    ->pluck('players.town_hall_level'),
                'roles' => ClanMembership::query()
                    ->when($clan, fn ($query, Clan $activeClan) => $query
                        ->whereBelongsTo($activeClan))
                    ->when(! $clan, fn ($query) => $query->whereRaw('1 = 0'))
                    ->whereNotNull('role')
                    ->distinct()
                    ->orderBy('role')
                    ->pluck('role'),
            ],
            'members' => $members,
            'performanceWindow' => 10,
            'clan' => $clan,
        ]);
    }

    public function sync(
        Request $request,
        ActiveClanContext $context,
        MemberSyncService $memberSync,
    ): RedirectResponse {
        try {
            $clan = $context->active($request);

            if ($clan === null) {
                throw new RuntimeException('Configure um clã antes de sincronizar os membros.');
            }

            $summary = $memberSync->sync($clan);
        } catch (ClashOfClansException|RuntimeException $exception) {
            return back()->withErrors([
                'sync' => $exception->getMessage(),
            ]);
        }

        return back()
            ->with('status', 'members-synced')
            ->with('syncSummary', $summary);
    }

    public function show(
        Request $request,
        ClanMembership $membership,
        ActiveClanContext $context,
        PlayerPerformanceQuery $performance,
    ): Response {
        $clan = $context->active($request);

        abort_unless($clan && $membership->clan_id === $clan->id, 404);

        $type = $request->string('type')->toString();
        $type = in_array($type, ['all', 'regular', 'cwl'], true) ? $type : 'all';
        $windowInput = $request->string('window')->toString();
        $window = $windowInput === 'all' ? 'all' : (int) $windowInput;
        $window = in_array($window, [5, 10, 20, 'all'], true) ? $window : 10;
        $result = $performance->get(
            $clan,
            $membership->player,
            $type,
            $window,
        );

        return Inertia::render('Members/Show', [
            'clan' => $clan,
            'membership' => $membership->load([
                'player:id,player_tag,name,town_hall_level',
                'status:id,slug',
            ]),
            'filters' => [
                'type' => $type,
                'window' => $window,
            ],
            ...$result,
        ]);
    }
}
