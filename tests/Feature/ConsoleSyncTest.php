<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Services\Members\MemberSyncService;
use App\Services\Wars\CwlSyncService;
use App\Services\Wars\WarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ConsoleSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_sync_command_runs_the_member_service(): void
    {
        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);
        $this->mock(MemberSyncService::class)
            ->shouldReceive('sync')->with(Mockery::on(
                fn (Clan $argument): bool => $argument->is($clan),
            ))
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
        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);
        $this->mock(WarSyncService::class)
            ->shouldReceive('sync')->with(Mockery::on(
                fn (Clan $argument): bool => $argument->is($clan),
            ))
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

    public function test_cwl_sync_command_runs_for_every_clan(): void
    {
        $primary = Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);
        $secondary = Clan::query()->create(['tag' => '#V9Y20']);
        $sync = $this->mock(CwlSyncService::class);
        $summary = [
            'seasons' => 1,
            'rounds' => 7,
            'tags' => 28,
            'detailed' => 1,
            'pending' => 2,
            'unrelated' => 25,
        ];
        $sync->shouldReceive('sync')->with(Mockery::on(
            fn (Clan $argument): bool => $argument->is($primary),
        ))->once()->andReturn($summary);
        $sync->shouldReceive('sync')->with(Mockery::on(
            fn (Clan $argument): bool => $argument->is($secondary),
        ))->once()->andReturn($summary);

        $this->artisan('cwl:sync')
            ->expectsOutput('CWL sincronizada: 2 temporadas, 14 rodadas, 2 guerras detalhadas e 4 pendências.')
            ->assertSuccessful();
    }

    public function test_daily_sync_commands_are_registered_in_the_scheduler(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('members:sync')
            ->expectsOutputToContain('wars:sync')
            ->expectsOutputToContain('cwl:sync')
            ->assertSuccessful();
    }

    public function test_sync_commands_report_service_failures(): void
    {
        $clan = Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);
        $this->mock(MemberSyncService::class)
            ->shouldReceive('sync')->with(Mockery::on(
                fn (Clan $argument): bool => $argument->is($clan),
            ))
            ->once()
            ->andThrow(new RuntimeException('API de membros indisponível.'));
        $this->artisan('members:sync')
            ->expectsOutput('#QGRJ2: API de membros indisponível.')
            ->assertFailed();

        $this->mock(WarSyncService::class)
            ->shouldReceive('sync')->with(Mockery::on(
                fn (Clan $argument): bool => $argument->is($clan),
            ))
            ->once()
            ->andThrow(new RuntimeException('API de guerras indisponível.'));
        $this->artisan('wars:sync')
            ->expectsOutput('#QGRJ2: API de guerras indisponível.')
            ->assertFailed();
    }

    public function test_sync_commands_continue_after_one_clan_fails(): void
    {
        $primary = Clan::query()->create([
            'tag' => '#QGRJ2',
            'is_default' => true,
        ]);
        $secondary = Clan::query()->create(['tag' => '#V9Y20']);

        $memberSync = $this->mock(MemberSyncService::class);
        $memberSync->shouldReceive('sync')->with(Mockery::on(
            fn (Clan $argument): bool => $argument->is($primary),
        ))->once()
            ->andThrow(new RuntimeException('falha isolada'));
        $memberSync->shouldReceive('sync')->with(Mockery::on(
            fn (Clan $argument): bool => $argument->is($secondary),
        ))->once()
            ->andReturn(['added' => 2, 'moved_in' => 0, 'moved_out' => 1]);

        $this->artisan('members:sync')
            ->expectsOutput('#QGRJ2: falha isolada')
            ->expectsOutput('Membros sincronizados: 2 adicionados, 0 retornaram e 1 saíram.')
            ->assertFailed();
    }
}
