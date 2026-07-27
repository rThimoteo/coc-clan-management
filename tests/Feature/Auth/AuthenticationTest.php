<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminAccessSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_an_access_code(): void
    {
        $user = User::factory()->create([
            'access_code' => 'valid-access-code',
        ]);

        $response = $this->post('/login', [
            'access_code' => 'valid-access-code',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_an_invalid_access_code(): void
    {
        User::factory()->create([
            'access_code' => 'valid-access-code',
        ]);

        $this->post('/login', [
            'access_code' => 'invalid-access-code',
        ])->assertSessionHasErrors('access_code');

        $this->assertGuest();
    }

    public function test_admin_seeder_creates_the_initial_admin_access(): void
    {
        config(['auth.admin_access_code' => 'environment-admin-code']);

        $this->seed(RoleSeeder::class);
        $this->seed(AdminAccessSeeder::class);

        $admin = User::query()->sole();

        $this->assertDatabaseCount(Role::class, count(UserRole::cases()));
        $this->assertSame(UserRole::Admin->value, $admin->role->slug);
        $this->assertTrue(Hash::check('environment-admin-code', $admin->access_code));
    }

    public function test_admin_seeder_is_idempotent_and_updates_the_configured_code(): void
    {
        config(['auth.admin_access_code' => 'first-code']);
        $this->seed(RoleSeeder::class);
        $this->seed(AdminAccessSeeder::class);

        config(['auth.admin_access_code' => 'rotated-code']);
        $this->seed(AdminAccessSeeder::class);

        $this->assertDatabaseCount(User::class, 1);
        $this->assertTrue(Hash::check(
            'rotated-code',
            User::query()->sole()->access_code,
        ));
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }
}
