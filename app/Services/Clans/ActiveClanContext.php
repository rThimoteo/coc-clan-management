<?php

namespace App\Services\Clans;

use App\Models\Clan;
use Illuminate\Http\Request;

class ActiveClanContext
{
    public const SESSION_KEY = 'active_clan_id';

    public function active(Request $request): ?Clan
    {
        $selectedClanId = $request->session()->get(self::SESSION_KEY);

        if ($selectedClanId !== null) {
            $selectedClan = Clan::query()->find($selectedClanId);

            if ($selectedClan !== null) {
                return $selectedClan;
            }
        }

        $fallbackClan = Clan::query()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($fallbackClan === null) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        $request->session()->put(self::SESSION_KEY, $fallbackClan->id);

        return $fallbackClan;
    }

    public function select(Request $request, Clan $clan): void
    {
        $request->session()->put(self::SESSION_KEY, $clan->id);
    }

    /**
     * @return array{
     *     active: array{id: int, tag: string, name: string|null, badge_url: string|null, is_default: bool}|null,
     *     available: list<array{id: int, tag: string, name: string|null, badge_url: string|null, is_default: bool}>
     * }
     */
    public function shared(Request $request): array
    {
        $activeClan = $this->active($request);

        return [
            'active' => $activeClan ? $this->summary($activeClan) : null,
            'available' => Clan::query()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Clan $clan): array => $this->summary($clan))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id: int, tag: string, name: string|null, badge_url: string|null, is_default: bool}
     */
    private function summary(Clan $clan): array
    {
        return [
            'id' => $clan->id,
            'tag' => $clan->tag,
            'name' => $clan->name,
            'badge_url' => $clan->badge_url,
            'is_default' => $clan->is_default,
        ];
    }
}
