<?php

namespace App\Console\Commands;

use App\Services\Wars\WarSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncWars extends Command
{
    protected $signature = 'wars:sync';

    protected $description = 'Sincroniza as guerras do clã com a API do Clash of Clans';

    public function handle(WarSyncService $warSync): int
    {
        try {
            $summary = $warSync->sync();
        } catch (Throwable $exception) {
            Log::error('Falha na sincronização automática de guerras.', [
                'exception' => $exception,
            ]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        Log::info('Sincronização automática de guerras concluída.', $summary);
        $this->info(sprintf(
            'Guerras sincronizadas: %d adicionadas, %d atualizadas e %d com detalhes.',
            $summary['added'],
            $summary['updated'],
            $summary['detailed'],
        ));

        return self::SUCCESS;
    }
}
