<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Clan;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Models\War;
use App\Services\Members\MemberSyncService;
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
            Member::query()->count(),
            War::query()->count(),
        ];
        $this->seed(DatabaseSeeder::class);

        $this->assertSame([1, 14, 6], $counts);
        $this->assertSame($counts, [
            Clan::query()->count(),
            Member::query()->count(),
            War::query()->count(),
        ]);
        $this->assertSame(3, War::query()->where('has_details', true)->count());
        $this->assertDatabaseHas(User::class, ['name' => 'Líder Demo']);
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

        $this->artisan('members:sync')
            ->expectsOutput('A sincronização de membros não está disponível no modo demo.')
            ->assertSuccessful();
        $this->artisan('wars:sync')
            ->expectsOutput('A sincronização de guerras não está disponível no modo demo.')
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
