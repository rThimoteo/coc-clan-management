<?php

namespace App\Http\Controllers;

use App\Models\Clan;
use App\Services\Clans\ActiveClanContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClanContextController extends Controller
{
    public function update(Request $request, ActiveClanContext $context): RedirectResponse
    {
        $validated = $request->validate([
            'clan_id' => ['required', 'integer', Rule::exists(Clan::class, 'id')],
        ]);

        $context->select(
            $request,
            Clan::query()->findOrFail($validated['clan_id']),
        );

        return back()->with('status', 'clan-context-updated');
    }
}
