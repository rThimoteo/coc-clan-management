<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Services\ClashOfClans\ClashOfClansException;
use App\Services\ClashOfClans\ClashOfClansService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClanController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Clan/Edit', [
            'clan' => Clan::query()->first(),
        ]);
    }

    public function update(Request $request, ClashOfClansService $clashOfClans): RedirectResponse
    {
        $validated = $request->validate([
            'tag' => ['required', 'string', 'max:20', 'regex:/^#?[0289PYLQGRJCUV]+$/i'],
        ]);

        try {
            $profile = $clashOfClans->clanProfile($validated['tag']);
        } catch (ClashOfClansException $exception) {
            throw ValidationException::withMessages([
                'tag' => $exception->getMessage(),
            ]);
        }

        $clan = Clan::query()->firstOrNew();
        $clan->fill([
            'tag' => $profile->tag,
            'name' => $profile->name,
            'badge_url' => $profile->badgeUrl,
        ])->save();

        return back()->with('status', 'clan-updated');
    }
}
