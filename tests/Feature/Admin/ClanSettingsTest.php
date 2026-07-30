<?php

namespace Tests\Feature\Admin;

use App\Enums\MemberStatus as MemberStatusEnum;
use App\Enums\UserRole;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\MemberStatus;
use App\Models\Player;
use App\Models\Role;
use App\Models\User;
use App\Models\War;
use App\Services\Clans\ActiveClanContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClanSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_all_clans_with_impact_counts(): void
    {
        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'name' => 'Principal',
            'is_default' => true,
        ]);
        $player = Player::query()->create([
            'player_tag' => '#PQLG2',
            'name' => 'Ayla',
        ]);
        $this->membership($clan, $player);
        $this->war($clan, 'Rival');

        $this->actingAs($this->admin())
            ->get('/admin/clans')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Clans/Index')
                ->has('clans', 1)
                ->where('clans.0.id', $clan->id)
                ->where('clans.0.memberships_count', 1)
                ->where('clans.0.wars_count', 1));
    }

    public function test_admin_can_add_clans_and_only_the_first_is_default(): void
    {
        $this->fakeClanProfiles();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/clans', ['tag' => 'qgrj2'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'clan-created');
        $this->actingAs($admin)
            ->post('/admin/clans', ['tag' => '#v9y20'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas(Clan::class, [
            'tag' => '#QGRJ2',
            'name' => 'Clã #QGRJ2',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas(Clan::class, [
            'tag' => '#V9Y20',
            'name' => 'Clã #V9Y20',
            'is_default' => false,
        ]);
        $this->assertSame(1, Clan::query()->where('is_default', true)->count());
    }

    public function test_duplicate_clan_tag_is_rejected_before_calling_the_api(): void
    {
        Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);
        Http::fake();

        $this->actingAs($this->admin())
            ->post('/admin/clans', ['tag' => 'qgrj2'])
            ->assertSessionHasErrors('tag');

        Http::assertNothingSent();
        $this->assertDatabaseCount(Clan::class, 1);
    }

    public function test_clan_is_not_added_when_api_validation_fails(): void
    {
        config([
            'services.clash_of_clans.base_url' => 'https://api.clashofclans.test/v1',
            'services.clash_of_clans.token' => 'api-token',
            'services.clash_of_clans.demo_mode' => false,
        ]);
        Http::fake([
            'api.clashofclans.test/v1/clans/*' => Http::response([], 404),
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/clans', ['tag' => '#QGRJ2'])
            ->assertSessionHasErrors('tag');

        $this->assertDatabaseCount(Clan::class, 0);
    }

    public function test_admin_can_change_the_default_clan(): void
    {
        $primary = Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);
        $secondary = Clan::query()->create(['tag' => '#V9Y20']);

        $this->actingAs($this->admin())
            ->patch("/admin/clans/{$secondary->id}/default")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'clan-default-updated');

        $this->assertFalse($primary->fresh()->is_default);
        $this->assertTrue($secondary->fresh()->is_default);
        $this->assertSame(1, Clan::query()->where('is_default', true)->count());
    }

    public function test_admin_can_revalidate_an_existing_clan(): void
    {
        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'name' => 'Nome antigo',
            'badge_url' => 'https://assets.test/old.png',
            'is_default' => true,
        ]);
        $this->fakeClanProfiles();

        $this->actingAs($this->admin())
            ->patch("/admin/clans/{$clan->id}")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'clan-refreshed');

        $this->assertDatabaseHas(Clan::class, [
            'id' => $clan->id,
            'tag' => '#QGRJ2',
            'name' => 'Clã #QGRJ2',
            'badge_url' => 'https://assets.test/clan-badge.png',
        ]);
    }

    public function test_existing_clan_is_unchanged_when_revalidation_fails(): void
    {
        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'name' => 'Nome preservado',
            'is_default' => true,
        ]);
        config([
            'services.clash_of_clans.base_url' => 'https://api.clashofclans.test/v1',
            'services.clash_of_clans.token' => 'api-token',
            'services.clash_of_clans.demo_mode' => false,
        ]);
        Http::fake([
            'api.clashofclans.test/v1/clans/*' => Http::response([], 503),
        ]);

        $this->actingAs($this->admin())
            ->patch("/admin/clans/{$clan->id}")
            ->assertSessionHasErrors('clan');

        $this->assertSame('Nome preservado', $clan->fresh()->name);
    }

    public function test_deleting_a_clan_requires_both_confirmations(): void
    {
        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'name' => 'Principal',
            'is_default' => true,
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete("/admin/clans/{$clan->id}", [
                'confirmation' => '#QGRJ2',
            ])
            ->assertSessionHasErrors('acknowledge_data_loss');
        $this->actingAs($admin)
            ->delete("/admin/clans/{$clan->id}", [
                'acknowledge_data_loss' => true,
                'confirmation' => 'texto incorreto',
            ])
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas(Clan::class, ['id' => $clan->id]);
    }

    public function test_deletion_cascades_scoped_data_and_preserves_reusable_players(): void
    {
        $primary = Clan::query()->create([
            'tag' => '#QGRJ2',
            'name' => 'Principal',
            'is_default' => true,
        ]);
        $secondary = Clan::query()->create([
            'tag' => '#V9Y20',
            'name' => 'Secundário',
        ]);
        $orphan = Player::query()->create([
            'player_tag' => '#PQLG2',
            'name' => 'Órfão',
        ]);
        $linked = Player::query()->create([
            'user_id' => User::factory()->create()->id,
            'player_tag' => '#QGRJ9',
            'name' => 'Vinculado',
        ]);
        $shared = Player::query()->create([
            'player_tag' => '#V9Y29',
            'name' => 'Compartilhado',
        ]);
        foreach ([$orphan, $linked, $shared] as $player) {
            $this->membership($primary, $player);
        }
        $this->membership($secondary, $shared);
        $primaryWar = $this->war($primary, 'Rival principal');
        $secondaryWar = $this->war($secondary, 'Rival secundário');

        $this->actingAs($this->admin())
            ->withSession([ActiveClanContext::SESSION_KEY => $primary->id])
            ->delete("/admin/clans/{$primary->id}", [
                'acknowledge_data_loss' => true,
                'confirmation' => '#QGRJ2',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'clan-deleted')
            ->assertSessionHas(ActiveClanContext::SESSION_KEY, $secondary->id);

        $this->assertDatabaseMissing(Clan::class, ['id' => $primary->id]);
        $this->assertDatabaseMissing(Player::class, ['id' => $orphan->id]);
        $this->assertDatabaseHas(Player::class, ['id' => $linked->id]);
        $this->assertDatabaseHas(Player::class, ['id' => $shared->id]);
        $this->assertDatabaseMissing(War::class, ['id' => $primaryWar->id]);
        $this->assertDatabaseHas(War::class, ['id' => $secondaryWar->id]);
        $this->assertTrue($secondary->fresh()->is_default);
        $this->assertSame(1, $shared->fresh()->memberships()->count());
    }

    public function test_non_admin_can_not_manage_clans(): void
    {
        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'name' => 'Principal',
            'is_default' => true,
        ]);
        $leader = User::factory()->create();

        $this->actingAs($leader)->get('/admin/clans')->assertForbidden();
        $this->actingAs($leader)
            ->post('/admin/clans', ['tag' => '#V9Y20'])
            ->assertForbidden();
        $this->actingAs($leader)
            ->patch("/admin/clans/{$clan->id}")
            ->assertForbidden();
        $this->actingAs($leader)
            ->patch("/admin/clans/{$clan->id}/default")
            ->assertForbidden();
        $this->actingAs($leader)
            ->delete("/admin/clans/{$clan->id}", [
                'acknowledge_data_loss' => true,
                'confirmation' => '#QGRJ2',
            ])
            ->assertForbidden();
    }

    private function fakeClanProfiles(): void
    {
        config([
            'services.clash_of_clans.base_url' => 'https://api.clashofclans.test/v1',
            'services.clash_of_clans.token' => 'api-token',
            'services.clash_of_clans.demo_mode' => false,
        ]);
        Http::fake(fn ($request) => Http::response([
            'tag' => urldecode(basename($request->url())),
            'name' => 'Clã '.urldecode(basename($request->url())),
            'badgeUrls' => [
                'medium' => 'https://assets.test/clan-badge.png',
            ],
        ]));
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role_id' => Role::query()
                ->where('slug', UserRole::Admin->value)
                ->sole()
                ->id,
        ]);
    }

    private function membership(Clan $clan, Player $player): ClanMembership
    {
        return ClanMembership::query()->create([
            'clan_id' => $clan->id,
            'player_id' => $player->id,
            'member_status_id' => MemberStatus::query()
                ->where('slug', MemberStatusEnum::In->value)
                ->sole()
                ->id,
        ]);
    }

    private function war(Clan $clan, string $opponent): War
    {
        return War::query()->create([
            'clan_id' => $clan->id,
            'external_key' => hash('sha256', "{$clan->tag}|{$opponent}"),
            'team_size' => 15,
            'end_time' => now(),
            'opponent_tag' => '#RIVAL'.substr(md5($opponent), 0, 5),
            'opponent_name' => $opponent,
        ]);
    }
}
