<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AccessCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = request()->user();
        $search = trim($request->string('search')->toString());

        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()
                ->with([
                    'role:id,name,slug',
                    'players' => fn ($query) => $query
                        ->with('memberships.clan:id,name,tag,badge_url')
                        ->orderBy('name'),
                ])
                ->when($search, fn ($query, string $value) => $query
                    ->where('name', 'like', "%{$value}%"))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'roles' => Role::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'players' => Player::query()
                ->with([
                    'user:id,name',
                    'memberships' => fn ($query) => $query
                        ->with([
                            'clan:id,name,tag,badge_url',
                            'status:id,slug',
                        ])
                        ->orderByDesc('left_at')
                        ->orderBy('clan_id'),
                ])
                ->orderBy('name')
                ->get(['id', 'user_id', 'name', 'player_tag', 'town_hall_level']),
            'permissions' => [
                'createUsers' => $actor->isAdmin(),
                'deleteUsers' => $actor->isAdmin(),
                'generateCodes' => $actor->isAdmin(),
                'linkPlayers' => $actor->canManageUserRoles(),
            ],
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request, AccessCodeGenerator $codes): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:'.User::class],
            'role_id' => ['required', 'integer', Rule::exists(Role::class, 'id')],
        ]);

        $accessCode = $codes->generateUnique();
        $user = User::query()->create([
            ...$validated,
            'access_code' => $accessCode,
        ]);

        return back()
            ->with('status', 'user-created')
            ->with('generatedAccess', [
                'user_name' => $user->name,
                'code' => $accessCode,
                'action' => 'created',
            ]);
    }

    public function regenerate(User $user, AccessCodeGenerator $codes): RedirectResponse
    {
        $accessCode = $codes->generateUnique();
        $user->update(['access_code' => $accessCode]);

        return back()
            ->with('status', 'access-code-regenerated')
            ->with('generatedAccess', [
                'user_name' => $user->name,
                'code' => $accessCode,
                'action' => 'regenerated',
            ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        abort_if($actor->is($user), 403);
        abort_if($user->isAdmin(), 403);

        $validated = $request->validate([
            'role_id' => ['required', 'integer', Rule::exists(Role::class, 'id')],
            'confirm_admin' => ['nullable', 'boolean'],
        ]);
        $role = Role::query()->findOrFail($validated['role_id']);

        if ($actor->isAdmin()) {
            if (
                $role->slug === UserRole::Admin->value
                && $user->role->slug !== UserRole::Admin->value
                && ($validated['confirm_admin'] ?? false) !== true
            ) {
                return back()->withErrors([
                    'role_id' => 'Confirme explicitamente a promoção para administrador.',
                ]);
            }
        } else {
            $leaderManagedRoles = [
                UserRole::CoLeader->value,
                UserRole::Member->value,
            ];

            abort_unless(
                in_array($user->role->slug, $leaderManagedRoles, true)
                && in_array($role->slug, $leaderManagedRoles, true),
                403,
            );
        }

        $user->update(['role_id' => $role->id]);

        return back()->with('status', 'user-role-updated');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403);
        abort_if($user->isAdmin(), 403);

        $user->delete();

        return back()->with('status', 'user-deleted');
    }

    public function updatePlayers(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'player_ids' => ['array'],
            'player_ids.*' => ['integer', 'distinct', Rule::exists(Player::class, 'id')],
        ]);
        $playerIds = $validated['player_ids'] ?? [];

        DB::transaction(function () use ($user, $playerIds): void {
            Player::query()
                ->where('user_id', $user->id)
                ->whereNotIn('id', $playerIds)
                ->update(['user_id' => null]);

            Player::query()
                ->whereIn('id', $playerIds)
                ->update(['user_id' => $user->id]);
        });

        return back()->with('status', 'user-players-updated');
    }
}
