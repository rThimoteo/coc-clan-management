<?php

namespace App\Console\Commands;

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

        try {
            $summary = $memberSync->sync();
        } catch (Throwable $exception) {
            Log::error('Falha na sincronização automática de membros.', [
                'exception' => $exception,
            ]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        Log::info('Sincronização automática de membros concluída.', $summary);
        $this->info(sprintf(
            'Membros sincronizados: %d adicionados, %d retornaram e %d saíram.',
            $summary['added'],
            $summary['moved_in'],
            $summary['moved_out'],
        ));

        return self::SUCCESS;
    }
}
