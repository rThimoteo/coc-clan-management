<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->timestamp('wars_synced_at')->nullable();
        });

        Schema::create('wars', function (Blueprint $table) {
            $table->id();
            $table->string('external_key', 64)->unique();
            $table->string('state')->nullable();
            $table->string('result')->nullable();
            $table->unsignedInteger('team_size');
            $table->timestamp('preparation_start_time')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->index();
            $table->boolean('has_details')->default(false);
            $table->unsignedInteger('clan_attacks')->nullable();
            $table->unsignedInteger('clan_stars')->default(0);
            $table->decimal('clan_destruction_percentage', 7, 4)->default(0);
            $table->string('opponent_tag', 20);
            $table->string('opponent_name');
            $table->string('opponent_badge_url')->nullable();
            $table->unsignedInteger('opponent_attacks')->nullable();
            $table->unsignedInteger('opponent_stars')->default(0);
            $table->decimal('opponent_destruction_percentage', 7, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('war_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('war_id')->constrained()->cascadeOnDelete();
            $table->string('side');
            $table->string('player_tag', 20);
            $table->string('name');
            $table->unsignedInteger('map_position');
            $table->unsignedInteger('townhall_level');
            $table->unsignedInteger('opponent_attacks')->default(0);
            $table->timestamps();
            $table->unique(['war_id', 'side', 'player_tag']);
        });

        Schema::create('war_attacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('war_id')->constrained()->cascadeOnDelete();
            $table->string('attacker_tag', 20)->index();
            $table->string('defender_tag', 20)->index();
            $table->unsignedInteger('attack_order');
            $table->unsignedInteger('stars');
            $table->decimal('destruction_percentage', 7, 4);
            $table->unsignedInteger('duration')->nullable();
            $table->timestamps();
            $table->unique(['war_id', 'attack_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('war_attacks');
        Schema::dropIfExists('war_members');
        Schema::dropIfExists('wars');

        Schema::table('clans', function (Blueprint $table) {
            $table->dropColumn('wars_synced_at');
        });
    }
};
