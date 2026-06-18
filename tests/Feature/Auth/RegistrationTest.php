<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_users_can_register_in_demo_mode_without_calling_the_api(): void
    {
        config([
            'services.clash_of_clans.demo_mode' => true,
            'services.clash_of_clans.token' => null,
            'services.clash_of_clans.clan_tag' => '#QGRJ2',
        ]);

        Http::fake();

        $response = $this->post('/register', [
            'username' => 'demo_user',
            'player_tag' => '#PQLG2',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas(User::class, [
            'username' => 'demo_user',
            'player_tag' => '#PQLG2',
            'player_name' => 'Jogador de Demonstração',
        ]);
        Http::assertNothingSent();
    }

    public function test_new_users_can_register(): void
    {
        config([
            'services.clash_of_clans.base_url' => 'https://api.clashofclans.test/v1',
            'services.clash_of_clans.token' => 'api-token',
            'services.clash_of_clans.clan_tag' => '#V9Y20 | QGRJ2',
        ]);

        Http::fake([
            'api.clashofclans.test/v1/players/*' => Http::response([
                'tag' => '#PQLG2',
                'name' => 'Chief',
                'role' => 'member',
                'clan' => ['tag' => '#QGRJ2'],
            ]),
        ]);

        $response = $this->post('/register', [
            'username' => 'test_user',
            'player_tag' => '#PQLG2',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas(User::class, [
            'username' => 'test_user',
            'player_tag' => '#PQLG2',
            'player_name' => 'Chief',
        ]);
    }

    public function test_player_from_any_configured_clan_can_register(): void
    {
        config([
            'services.clash_of_clans.base_url' => 'https://api.clashofclans.test/v1',
            'services.clash_of_clans.token' => 'api-token',
            'services.clash_of_clans.clan_tag' => '#V9Y20|#QGRJ2',
        ]);

        Http::fake([
            'api.clashofclans.test/v1/players/*' => Http::response([
                'tag' => '#PQLG2',
                'name' => 'Second Clan Chief',
                'role' => 'member',
                'clan' => ['tag' => '#QGRJ2'],
            ]),
        ]);

        $response = $this->post('/register', [
            'username' => 'second_clan_user',
            'player_tag' => '#PQLG2',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_player_from_another_clan_can_not_register(): void
    {
        config([
            'services.clash_of_clans.base_url' => 'https://api.clashofclans.test/v1',
            'services.clash_of_clans.token' => 'api-token',
            'services.clash_of_clans.clan_tag' => '#QGRJ2',
        ]);

        Http::fake([
            'api.clashofclans.test/v1/players/*' => Http::response([
                'tag' => '#PQLG2',
                'name' => 'Visitor',
                'role' => 'member',
                'clan' => ['tag' => '#V9Y20'],
            ]),
        ]);

        $response = $this->from('/register')->post('/register', [
            'username' => 'visitor',
            'player_tag' => '#PQLG2',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('player_tag');
        $this->assertGuest();
        $this->assertDatabaseCount(User::class, 0);
    }

    public function test_unknown_player_tag_can_not_register(): void
    {
        config([
            'services.clash_of_clans.base_url' => 'https://api.clashofclans.test/v1',
            'services.clash_of_clans.token' => 'api-token',
            'services.clash_of_clans.clan_tag' => '#QGRJ2',
        ]);

        Http::fake([
            'api.clashofclans.test/v1/players/*' => Http::response([], 404),
        ]);

        $response = $this->from('/register')->post('/register', [
            'username' => 'unknown',
            'player_tag' => '#PQLG2',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('player_tag');
        $this->assertGuest();
    }
}
