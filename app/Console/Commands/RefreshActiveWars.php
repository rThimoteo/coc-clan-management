<?php

namespace App\Console\Commands;

use App\Services\Wars\ActiveWarRefreshService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshActiveWars extends Command
{
    protected $signature = 'wars:refresh-active';

    protected $description = 'Atualiza somente a guerra ativa de cada clã';

    public function handle(ActiveWarRefreshService $refresh): int
    {
        if (config('services.clash_of_clans.demo_mode')) {
            $this->warn('A atualização de guerras não está disponível no modo demo.');

            return self::SUCCESS;
        }

        try {
            $summary = $refresh->refreshAll();
        } catch (Throwable $exception) {
            Log::error('Falha na atualização das guerras ativas.', [
                'exception' => $exception,
            ]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        Log::info('Atualização das guerras ativas concluída.', $summary);
        $this->info(sprintf(
            'Guerras ativas: %d consultadas, %d atualizadas e %d clãs sem guerra ativa.',
            $summary['checked'],
            $summary['updated'],
            $summary['skipped'],
        ));

        return self::SUCCESS;
    }
}
