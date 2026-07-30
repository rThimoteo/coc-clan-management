<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_war_leagues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained()->cascadeOnDelete();
            $table->string('season', 7);
            $table->string('state');
            $table->boolean('has_summary')->default(false);
            $table->timestamp('end_time')->nullable();
            $table->unsignedInteger('team_size')->nullable();
            $table->unsignedInteger('attacks_per_member')->nullable();
            $table->string('battle_modifier')->nullable();
            $table->string('clan_badge_url')->nullable();
            $table->unsignedInteger('clan_attacks')->nullable();
            $table->unsignedInteger('clan_stars')->default(0);
            $table->decimal('clan_destruction_percentage', 8, 4)->default(0);
            $table->string('opponent_badge_url')->nullable();
            $table->unsignedInteger('opponent_stars')->default(0);
            $table->decimal('opponent_destruction_percentage', 8, 4)->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['clan_id', 'season']);
            $table->index(['clan_id', 'state']);
        });

        Schema::create('clan_war_league_clans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_war_league_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('clan_tag', 20);
            $table->string('name');
            $table->unsignedInteger('clan_level')->nullable();
            $table->string('badge_url')->nullable();
            $table->timestamps();
            $table->unique(['clan_war_league_id', 'clan_tag']);
        });

        Schema::create('clan_war_league_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_war_league_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->timestamps();
            $table->unique(['clan_war_league_id', 'round_number']);
        });

        Schema::create('clan_war_league_round_wars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_war_league_round_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('war_tag', 20)->nullable();
            $table->boolean('is_placeholder')->default(false);
            $table->string('status')->default('pending');
            $table->foreignId('war_id')
                ->nullable()
                ->unique()
                ->constrained()
                ->nullOnDelete();
            $table->timestamps();
            $table->unique(['clan_war_league_round_id', 'war_tag']);
            $table->index('war_tag');
            $table->index(['clan_war_league_round_id', 'is_placeholder']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_war_league_round_wars');
        Schema::dropIfExists('clan_war_league_rounds');
        Schema::dropIfExists('clan_war_league_clans');
        Schema::dropIfExists('clan_war_leagues');
    }
};
