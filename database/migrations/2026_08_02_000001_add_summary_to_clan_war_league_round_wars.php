<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clan_war_league_round_wars', function (Blueprint $table) {
            $table->string('state')->nullable()->after('status');
            $table->unsignedInteger('team_size')->nullable()->after('state');
            $table->timestamp('preparation_start_time')->nullable()->after('team_size');
            $table->timestamp('start_time')->nullable()->after('preparation_start_time');
            $table->timestamp('end_time')->nullable()->after('start_time');
            $table->string('clan_tag', 20)->nullable()->after('end_time');
            $table->string('clan_name')->nullable()->after('clan_tag');
            $table->string('clan_badge_url')->nullable()->after('clan_name');
            $table->unsignedInteger('clan_attacks')->nullable()->after('clan_badge_url');
            $table->unsignedInteger('clan_stars')->default(0)->after('clan_attacks');
            $table->decimal('clan_destruction_percentage', 8, 4)->default(0)->after('clan_stars');
            $table->string('opponent_tag', 20)->nullable()->after('clan_destruction_percentage');
            $table->string('opponent_name')->nullable()->after('opponent_tag');
            $table->string('opponent_badge_url')->nullable()->after('opponent_name');
            $table->unsignedInteger('opponent_attacks')->nullable()->after('opponent_badge_url');
            $table->unsignedInteger('opponent_stars')->default(0)->after('opponent_attacks');
            $table->decimal('opponent_destruction_percentage', 8, 4)->default(0)->after('opponent_stars');
            $table->string('winner_tag', 20)->nullable()->after('opponent_destruction_percentage');
            $table->timestamp('summary_synced_at')->nullable()->after('winner_tag');
            $table->index(['state', 'winner_tag']);
            $table->index('clan_tag');
            $table->index('opponent_tag');
        });
    }

    public function down(): void
    {
        Schema::table('clan_war_league_round_wars', function (Blueprint $table) {
            $table->dropIndex(['state', 'winner_tag']);
            $table->dropIndex(['clan_tag']);
            $table->dropIndex(['opponent_tag']);
            $table->dropColumn([
                'state',
                'team_size',
                'preparation_start_time',
                'start_time',
                'end_time',
                'clan_tag',
                'clan_name',
                'clan_badge_url',
                'clan_attacks',
                'clan_stars',
                'clan_destruction_percentage',
                'opponent_tag',
                'opponent_name',
                'opponent_badge_url',
                'opponent_attacks',
                'opponent_stars',
                'opponent_destruction_percentage',
                'winner_tag',
                'summary_synced_at',
            ]);
        });
    }
};
