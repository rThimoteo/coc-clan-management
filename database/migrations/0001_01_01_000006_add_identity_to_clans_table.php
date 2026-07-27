<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->string('name')->nullable()->after('tag');
            $table->string('badge_url')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->dropColumn(['name', 'badge_url']);
        });
    }
};
