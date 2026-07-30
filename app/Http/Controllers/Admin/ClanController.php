<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Models\Player;
use App\Services\Clans\ActiveClanContext;
use App\Services\ClashOfClans\ClashOfClansException;
use App\Services\ClashOfClans\ClashOfClansService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Clans/Index', [
            'clans' => Clan::query()
                ->withCount(['memberships', 'wars'])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, ClashOfClansService $clashOfClans): RedirectResponse
    {
        $request->merge([
            'tag' => $clashOfClans->normalizeTag($request->string('tag')->toString()),
        ]);
        $validated = $request->validate([
            'tag' => [
                'required',
                'string',
                'max:20',
                'regex:/^#[0289PYLQGRJCUV]+$/i',
                Rule::unique(Clan::class, 'tag'),
            ],
        ]);

        try {
            $profile = $clashOfClans->clanProfile($validated['tag']);
        } catch (ClashOfClansException $exception) {
            throw ValidationException::withMessages([
                'tag' => $exception->getMessage(),
            ]);
        }

        DB::transaction(function () use ($profile): void {
            Clan::query()->create([
                'tag' => $profile->tag,
                'name' => $profile->name,
                'badge_url' => $profile->badgeUrl,
                'is_default' => ! Clan::query()->exists(),
            ]);
        });

        return back()->with('status', 'clan-created');
    }

    public function setDefault(Clan $clan): RedirectResponse
    {
        DB::transaction(function () use ($clan): void {
            Clan::query()
                ->where('is_default', true)
                ->update(['is_default' => false]);
            $clan->update(['is_default' => true]);
        });

        return back()->with('status', 'clan-default-updated');
    }

    public function refresh(Clan $clan, ClashOfClansService $clashOfClans): RedirectResponse
    {
        try {
            $profile = $clashOfClans->clanProfile($clan->tag);
        } catch (ClashOfClansException $exception) {
            throw ValidationException::withMessages([
                'clan' => $exception->getMessage(),
            ]);
        }

        $clan->update([
            'tag' => $profile->tag,
            'name' => $profile->name,
            'badge_url' => $profile->badgeUrl,
        ]);

        return back()->with('status', 'clan-refreshed');
    }

    public function destroy(
        Request $request,
        Clan $clan,
        ActiveClanContext $context,
    ): RedirectResponse {
        $validated = $request->validate([
            'acknowledge_data_loss' => ['accepted'],
            'confirmation' => ['required', 'string'],
        ]);
        $confirmation = trim($validated['confirmation']);

        if (! in_array($confirmation, [$clan->tag, $clan->name], true)) {
            throw ValidationException::withMessages([
                'confirmation' => 'Digite exatamente a tag ou o nome do clã para confirmar.',
            ]);
        }

        $wasActive = $request->session()->get(ActiveClanContext::SESSION_KEY) === $clan->id;
        $wasDefault = $clan->is_default;

        DB::transaction(function () use ($clan, $wasDefault): void {
            $playerIds = $clan->memberships()->pluck('player_id');

            $clan->delete();

            Player::query()
                ->whereKey($playerIds)
                ->whereNull('user_id')
                ->whereDoesntHave('memberships')
                ->delete();

            if ($wasDefault) {
                Clan::query()
                    ->orderBy('id')
                    ->first()
                    ?->update(['is_default' => true]);
            }
        });

        if ($wasActive) {
            $request->session()->forget(ActiveClanContext::SESSION_KEY);
            $context->active($request);
        }

        return back()->with('status', 'clan-deleted');
    }
}
