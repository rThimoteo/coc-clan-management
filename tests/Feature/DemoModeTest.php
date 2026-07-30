<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\Player;
use App\Models\Role;
use App\Models\User;
use App\Models\War;
use App\Services\Clans\ActiveClanContext;
use App\Services\Members\MemberSyncService;
use App\Services\Wars\CwlSyncService;
use App\Services\Wars\WarSyncService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_loads_representative_data_without_duplicates(): void
    {
        config(['services.clash_of_clans.demo_mode' => true]);

        $this->seed(DatabaseSeeder::class);
        $counts = [
            Clan::query()->count(),
            Player::query()->count(),
            ClanMembership::query()->count(),
            War::query()->count(),
        ];
        $this->seed(DatabaseSeeder::class);

        $this->assertSame([2, 18, 21, 8], $counts);
        $this->assertSame($counts, [
            Clan::query()->count(),
            Player::query()->count(),
            ClanMembership::query()->count(),
            War::query()->count(),
        ]);
        $this->assertSame(4, War::query()->where('has_details', true)->count());
        $this->assertSame(1, Clan::query()->where('is_default', true)->count());
        $this->assertDatabaseHas(User::class, ['name' => 'Líder Demo']);
    }

    public function test_demo_data_has_isolated_clans_and_a_user_with_accounts_in_both(): void
    {
        config(['services.clash_of_clans.demo_mode' => true]);
        $this->seed(DatabaseSeeder::class);

        $primary = Clan::query()->where('tag', '#2Q8L9Y0JP')->sole();
        $academy = Clan::query()->where('tag', '#2Y0V8R2JG')->sole();
        $leader = User::query()->where('name', 'Líder Demo')->sole();

        $this->assertSame(4, $primary->wars()->count());
        $this->assertSame(4, $academy->wars()->count());
        $this->assertSame(2, $leader->players()->count());
        $this->assertTrue($leader->players()->whereHas(
            'memberships',
            fn ($query) => $query->where('clan_id', $primary->id),
        )->exists());
        $this->assertTrue($leader->players()->whereHas(
            'memberships',
            fn ($query) => $query->where('clan_id', $academy->id),
        )->exists());
    }

    public function test_switching_demo_clan_changes_the_operational_data(): void
    {
        config(['services.clash_of_clans.demo_mode' => true]);
        $this->seed(DatabaseSeeder::class);
        $user = User::query()->where('name', 'Líder Demo')->sole();
        $primary = Clan::query()->where('tag', '#2Q8L9Y0JP')->sole();
        $academy = Clan::query()->where('tag', '#2Y0V8R2JG')->sole();

        $this->actingAs($user)
            ->withSession([ActiveClanContext::SESSION_KEY => $primary->id])
            ->get('/members?status=all')
            ->assertInertia(fn ($page) => $page
                ->where('clan.id', $primary->id)
                ->where('members.total', 14));

        $this->actingAs($user)
            ->withSession([ActiveClanContext::SESSION_KEY => $academy->id])
            ->get('/members?status=all')
            ->assertInertia(fn ($page) => $page
                ->where('clan.id', $academy->id)
                ->where('members.total', 7));
    }

    public function test_sync_routes_are_forbidden_in_demo_mode(): void
    {
        config(['services.clash_of_clans.demo_mode' => true]);
        $adminRole = Role::query()->where('slug', UserRole::Admin->value)->sole();
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $this->actingAs($admin)->post('/members/sync')->assertForbidden();
        $this->actingAs($admin)->post('/wars/sync')->assertForbidden();
    }

    public function test_sync_commands_do_not_call_services_in_demo_mode(): void
    {
        config(['services.clash_of_clans.demo_mode' => true]);
        $this->mock(MemberSyncService::class)->shouldNotReceive('sync');
        $this->mock(WarSyncService::class)->shouldNotReceive('sync');
        $this->mock(CwlSyncService::class)->shouldNotReceive('sync');

        $this->artisan('members:sync')
            ->expectsOutput('A sincronização de membros não está disponível no modo demo.')
            ->assertSuccessful();
        $this->artisan('wars:sync')
            ->expectsOutput('A sincronização de guerras não está disponível no modo demo.')
            ->assertSuccessful();
        $this->artisan('cwl:sync')
            ->expectsOutput('A sincronização da Liga de Guerra não está disponível no modo demo.')
            ->assertSuccessful();
    }

    public function test_demo_mode_is_shared_with_the_frontend(): void
    {
        config(['services.clash_of_clans.demo_mode' => true]);

        $this->actingAs(User::factory()->create())
            ->get('/members')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('demoMode', true));
    }
}
