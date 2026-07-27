<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Clan;
use App\Models\Role;
use App\Models\User;
use App\Services\ClashOfClans\ClashOfClansService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClanSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_clan_settings(): void
    {
        $admin = $this->admin();
        Clan::query()->create(['tag' => '#QGRJ2']);

        $this->actingAs($admin)
            ->get('/admin/clan')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Clan/Edit')
                ->where('clan.tag', '#QGRJ2'));
    }

    public function test_admin_can_save_the_clan_tag(): void
    {
        config([
            'services.clash_of_clans.base_url' => 'https://api.clashofclans.test/v1',
            'services.clash_of_clans.token' => 'api-token',
            'services.clash_of_clans.demo_mode' => false,
        ]);

        Http::fake([
            'api.clashofclans.test/v1/clans/*' => Http::response([
                'tag' => '#QGRJ2',
                'name' => 'Test Clan',
                'badgeUrls' => [
                    'medium' => 'https://assets.test/clan-badge.png',
                ],
            ]),
        ]);

        $this->actingAs($this->admin())
            ->patch('/admin/clan', ['tag' => 'qgrj2'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas(Clan::class, [
            'tag' => '#QGRJ2',
            'name' => 'Test Clan',
            'badge_url' => 'https://assets.test/clan-badge.png',
        ]);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/clans/%23QGRJ2'));
    }

    public function test_clan_tag_is_not_saved_when_clan_does_not_exist(): void
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
            ->from('/admin/clan')
            ->patch('/admin/clan', ['tag' => '#QGRJ2'])
            ->assertRedirect('/admin/clan')
            ->assertSessionHasErrors('tag');

        $this->assertDatabaseCount(Clan::class, 0);
    }

    public function test_non_admin_can_not_manage_clan_settings(): void
    {
        $leader = User::factory()->create();

        $this->actingAs($leader)
            ->get('/admin/clan')
            ->assertForbidden();

        $this->actingAs($leader)
            ->patch('/admin/clan', ['tag' => '#QGRJ2'])
            ->assertForbidden();

        $this->assertDatabaseCount(Clan::class, 0);
    }

    public function test_clash_of_clans_service_reads_clan_tags_from_database(): void
    {
        Clan::query()->create(['tag' => '#QGRJ2']);

        $service = app(ClashOfClansService::class);

        $this->assertSame(['#QGRJ2'], $service->configuredClanTags());
        $this->assertTrue($service->isAuthorizedClan('qgrj2'));
        $this->assertFalse($service->isAuthorizedClan('#V9Y20'));
    }

    private function admin(): User
    {
        $adminRole = Role::query()
            ->where('slug', UserRole::Admin->value)
            ->sole();

        return User::factory()->create(['role_id' => $adminRole->id]);
    }
}
