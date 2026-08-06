<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clan_war_league_round_wars', function (Blueprint $table) {
            $table->timestamp('final_synced_at')->nullable()->after('summary_synced_at');
            $table->index(['state', 'final_synced_at']);
        });

        DB::table('clan_war_league_round_wars')
            ->whereIn('state', ['warEnded', 'ended'])
            ->update([
                'final_synced_at' => DB::raw(
                    'COALESCE(summary_synced_at, updated_at)',
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('clan_war_league_round_wars', function (Blueprint $table) {
            $table->dropIndex(['state', 'final_synced_at']);
            $table->dropColumn('final_synced_at');
        });
    }
};
