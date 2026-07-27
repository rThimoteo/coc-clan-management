<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
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
    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()
                ->with([
                    'role:id,name,slug',
                    'members:id,user_id,name,player_tag',
                ])
                ->orderBy('name')
                ->get(),
            'roles' => Role::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'members' => Member::query()
                ->with([
                    'status:id,slug',
                    'user:id,name',
                ])
                ->orderBy('name')
                ->get(['id', 'user_id', 'member_status_id', 'name', 'player_tag']),
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

    public function updateMembers(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'member_ids' => ['array'],
            'member_ids.*' => ['integer', 'distinct', Rule::exists(Member::class, 'id')],
        ]);
        $memberIds = $validated['member_ids'] ?? [];

        DB::transaction(function () use ($user, $memberIds): void {
            Member::query()
                ->where('user_id', $user->id)
                ->whereNotIn('id', $memberIds)
                ->update(['user_id' => null]);

            Member::query()
                ->whereIn('id', $memberIds)
                ->update(['user_id' => $user->id]);
        });

        return back()->with('status', 'user-members-updated');
    }
}
