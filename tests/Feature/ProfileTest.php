<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk();
    }

    public function test_user_can_update_only_their_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Nome antigo',
            'access_code' => 'unchanged-access-code',
        ]);
        $originalAccessCode = $user->access_code;
        $originalRoleId = $user->role_id;

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Novo nome',
                'access_code' => 'attempted-new-code',
                'role_id' => 999,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Novo nome', $user->name);
        $this->assertSame($originalAccessCode, $user->access_code);
        $this->assertSame($originalRoleId, $user->role_id);
    }

    public function test_profile_name_must_be_unique(): void
    {
        User::factory()->create(['name' => 'Nome existente']);
        $user = User::factory()->create(['name' => 'Outro nome']);

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', ['name' => 'Nome existente'])
            ->assertRedirect('/profile')
            ->assertSessionHasErrors('name');

        $this->assertSame('Outro nome', $user->fresh()->name);
    }

    public function test_guest_can_not_access_profile(): void
    {
        $this->get('/profile')->assertRedirect(route('login'));
        $this->patch('/profile', ['name' => 'Visitante'])
            ->assertRedirect(route('login'));
    }
}
