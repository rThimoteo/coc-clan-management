<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedBigInteger('league_id')->nullable()->index();
            $table->string('league_name')->nullable();
            $table->string('league_icon_url', 500)->nullable();
            $table->unsignedInteger('trophies')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex(['league_id']);
            $table->dropIndex(['trophies']);
            $table->dropColumn([
                'league_id',
                'league_name',
                'league_icon_url',
                'trophies',
            ]);
        });
    }
};
