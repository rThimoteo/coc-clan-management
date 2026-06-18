<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ClashOfClans\ClashOfClansException;
use App\Services\ClashOfClans\ClashOfClansService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(ClashOfClansService $clashOfClans): Response
    {
        return Inertia::render('Auth/Register', [
            'demoMode' => $clashOfClans->isDemoMode(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, ClashOfClansService $clashOfClans): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9_.-]+$/', 'unique:'.User::class],
            'player_tag' => ['required', 'string', 'max:20', 'regex:/^#?[0289PYLQGRJCUV]+$/i'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        try {
            $player = $clashOfClans->findPlayer($validated['player_tag']);

            if (! $clashOfClans->isAuthorizedClan($player->clanTag)) {
                throw ValidationException::withMessages([
                    'player_tag' => 'Este jogador não pertence a um dos clãs autorizados.',
                ]);
            }

            if (User::query()->where('player_tag', $player->tag)->exists()) {
                throw ValidationException::withMessages([
                    'player_tag' => 'Esta player tag já está vinculada a uma conta.',
                ]);
            }
        } catch (ClashOfClansException $exception) {
            throw ValidationException::withMessages([
                'player_tag' => $exception->getMessage(),
            ]);
        }

        $user = User::create([
            'username' => $validated['username'],
            'player_tag' => $player->tag,
            'player_name' => $player->name,
            'clan_role' => $player->clanRole,
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
