<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\User;
use App\Services\Clans\ActiveClanContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClanContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_clan_is_selected_and_shared_with_inertia(): void
    {
        $secondary = Clan::query()->create([
            'tag' => '#V9Y20',
            'name' => 'Secundário',
        ]);
        $default = Clan::query()->create([
            'tag' => '#QGRJ2',
            'name' => 'Principal',
            'is_default' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSessionHas(ActiveClanContext::SESSION_KEY, $default->id)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('clanContext.active.id', $default->id)
                ->where('clanContext.active.tag', '#QGRJ2')
                ->where('clanContext.active.is_default', true)
                ->has('clanContext.available', 2)
                ->where('clanContext.available.0.id', $default->id)
                ->where('clanContext.available.1.id', $secondary->id));
    }

    public function test_selected_clan_takes_precedence_over_the_default(): void
    {
        $default = Clan::query()->create([
            'tag' => '#QGRJ2',
            'name' => 'Principal',
            'is_default' => true,
        ]);
        $selected = Clan::query()->create([
            'tag' => '#V9Y20',
            'name' => 'Secundário',
        ]);

        $this->actingAs(User::factory()->create())
            ->withSession([ActiveClanContext::SESSION_KEY => $selected->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertSessionHas(ActiveClanContext::SESSION_KEY, $selected->id)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('clanContext.active.id', $selected->id)
                ->where('clanContext.active.is_default', false)
                ->where('clanContext.available.0.id', $default->id));
    }

    public function test_stale_selection_falls_back_to_the_default_clan(): void
    {
        $default = Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->withSession([ActiveClanContext::SESSION_KEY => 999999])
            ->get('/dashboard')
            ->assertOk()
            ->assertSessionHas(ActiveClanContext::SESSION_KEY, $default->id)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('clanContext.active.id', $default->id));
    }

    public function test_first_clan_is_used_when_no_default_exists(): void
    {
        $first = Clan::query()->create(['tag' => '#QGRJ2']);
        Clan::query()->create(['tag' => '#V9Y20']);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSessionHas(ActiveClanContext::SESSION_KEY, $first->id);
    }

    public function test_context_is_empty_when_there_are_no_clans(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession([ActiveClanContext::SESSION_KEY => 999999])
            ->get('/dashboard')
            ->assertOk()
            ->assertSessionMissing(ActiveClanContext::SESSION_KEY)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('clanContext.active', null)
                ->has('clanContext.available', 0));
    }

    public function test_authenticated_user_can_switch_the_active_clan(): void
    {
        $default = Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);
        $secondary = Clan::query()->create(['tag' => '#V9Y20']);

        $this->actingAs(User::factory()->create())
            ->withSession([ActiveClanContext::SESSION_KEY => $default->id])
            ->from('/wars')
            ->put('/clan-context', ['clan_id' => $secondary->id])
            ->assertRedirect('/wars')
            ->assertSessionHas(ActiveClanContext::SESSION_KEY, $secondary->id)
            ->assertSessionHas('status', 'clan-context-updated');
    }

    public function test_clan_switch_rejects_an_unknown_clan(): void
    {
        $default = Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->withSession([ActiveClanContext::SESSION_KEY => $default->id])
            ->put('/clan-context', ['clan_id' => 999999])
            ->assertSessionHasErrors('clan_id')
            ->assertSessionHas(ActiveClanContext::SESSION_KEY, $default->id);
    }

    public function test_guest_can_not_switch_the_active_clan(): void
    {
        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);

        $this->put('/clan-context', ['clan_id' => $clan->id])
            ->assertRedirect(route('login'));
    }
}
