<?php

namespace App\Console\Commands;

use App\Models\Clan;
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
        if (config('services.clash_of_clans.demo_mode')) {
            $this->warn('A sincronização de guerras não está disponível no modo demo.');

            return self::SUCCESS;
        }

        $clans = Clan::query()->orderBy('id')->get();

        if ($clans->isEmpty()) {
            $this->error('Nenhum clã está configurado para sincronização.');

            return self::FAILURE;
        }

        $summary = ['added' => 0, 'updated' => 0, 'detailed' => 0];
        $failed = false;

        foreach ($clans as $clan) {
            try {
                $clanSummary = $warSync->sync($clan);

                foreach ($summary as $key => $value) {
                    $summary[$key] = $value + $clanSummary[$key];
                }
            } catch (Throwable $exception) {
                $failed = true;
                Log::error('Falha na sincronização automática de guerras.', [
                    'clan_id' => $clan->id,
                    'clan_tag' => $clan->tag,
                    'exception' => $exception,
                ]);
                $this->error("{$clan->tag}: {$exception->getMessage()}");
            }
        }

        Log::info('Sincronização automática de guerras concluída.', $summary);
        $this->info(sprintf(
            'Guerras sincronizadas: %d adicionadas, %d atualizadas e %d com detalhes.',
            $summary['added'],
            $summary['updated'],
            $summary['detailed'],
        ));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
