<?php

namespace App\Console\Commands;

use App\Models\Clan;
use App\Services\Members\MemberSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncMembers extends Command
{
    protected $signature = 'members:sync';

    protected $description = 'Sincroniza os membros do clã com a API do Clash of Clans';

    public function handle(MemberSyncService $memberSync): int
    {
        if (config('services.clash_of_clans.demo_mode')) {
            $this->warn('A sincronização de membros não está disponível no modo demo.');

            return self::SUCCESS;
        }

        $clans = Clan::query()->orderBy('id')->get();

        if ($clans->isEmpty()) {
            $this->error('Nenhum clã está configurado para sincronização.');

            return self::FAILURE;
        }

        $summary = ['added' => 0, 'moved_in' => 0, 'moved_out' => 0];
        $failed = false;

        foreach ($clans as $clan) {
            try {
                $clanSummary = $memberSync->sync($clan);

                foreach ($summary as $key => $value) {
                    $summary[$key] = $value + $clanSummary[$key];
                }
            } catch (Throwable $exception) {
                $failed = true;
                Log::error('Falha na sincronização automática de membros.', [
                    'clan_id' => $clan->id,
                    'clan_tag' => $clan->tag,
                    'exception' => $exception,
                ]);
                $this->error("{$clan->tag}: {$exception->getMessage()}");
            }
        }

        Log::info('Sincronização automática de membros concluída.', $summary);
        $this->info(sprintf(
            'Membros sincronizados: %d adicionados, %d retornaram e %d saíram.',
            $summary['added'],
            $summary['moved_in'],
            $summary['moved_out'],
        ));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
