<?php

namespace App\Console\Commands;

use App\Models\Clan;
use App\Services\Wars\CwlSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncCwl extends Command
{
    protected $signature = 'cwl:sync';

    protected $description = 'Sincroniza as temporadas e guerras da Liga de Guerra de todos os clãs';

    public function handle(CwlSyncService $sync): int
    {
        if (config('services.clash_of_clans.demo_mode')) {
            $this->warn('A sincronização da Liga de Guerra não está disponível no modo demo.');

            return self::SUCCESS;
        }

        $clans = Clan::query()->orderBy('id')->get();

        if ($clans->isEmpty()) {
            $this->error('Nenhum clã está configurado para sincronização.');

            return self::FAILURE;
        }

        $summary = [
            'seasons' => 0,
            'rounds' => 0,
            'tags' => 0,
            'detailed' => 0,
            'pending' => 0,
            'unrelated' => 0,
        ];
        $failed = false;

        foreach ($clans as $clan) {
            try {
                $clanSummary = $sync->sync($clan);

                foreach ($summary as $key => $value) {
                    $summary[$key] = $value + $clanSummary[$key];
                }
            } catch (Throwable $exception) {
                $failed = true;
                Log::error('Falha na sincronização da Liga de Guerra.', [
                    'clan_id' => $clan->id,
                    'clan_tag' => $clan->tag,
                    'exception' => $exception,
                ]);
                $this->error("{$clan->tag}: {$exception->getMessage()}");
            }
        }

        Log::info('Sincronização da Liga de Guerra concluída.', $summary);
        $this->info(sprintf(
            'CWL sincronizada: %d temporadas, %d rodadas, %d guerras detalhadas e %d pendências.',
            $summary['seasons'],
            $summary['rounds'],
            $summary['detailed'],
            $summary['pending'],
        ));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
