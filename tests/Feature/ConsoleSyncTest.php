<?php

namespace Tests\Feature;

use App\Services\Members\MemberSyncService;
use App\Services\Wars\WarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsoleSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_sync_command_runs_the_member_service(): void
    {
        $this->mock(MemberSyncService::class)
            ->shouldReceive('sync')
            ->once()
            ->andReturn([
                'added' => 2,
                'moved_in' => 1,
                'moved_out' => 3,
            ]);

        $this->artisan('members:sync')
            ->expectsOutput('Membros sincronizados: 2 adicionados, 1 retornaram e 3 saíram.')
            ->assertSuccessful();
    }

    public function test_wars_sync_command_runs_the_war_service(): void
    {
        $this->mock(WarSyncService::class)
            ->shouldReceive('sync')
            ->once()
            ->andReturn([
                'added' => 1,
                'updated' => 4,
                'detailed' => 2,
            ]);

        $this->artisan('wars:sync')
            ->expectsOutput('Guerras sincronizadas: 1 adicionadas, 4 atualizadas e 2 com detalhes.')
            ->assertSuccessful();
    }

    public function test_daily_sync_commands_are_registered_in_the_scheduler(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('members:sync')
            ->expectsOutputToContain('wars:sync')
            ->assertSuccessful();
    }
}
