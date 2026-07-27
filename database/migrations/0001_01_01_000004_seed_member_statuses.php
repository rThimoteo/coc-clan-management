<?php

use Database\Seeders\MemberStatusSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(MemberStatusSeeder::class)->run();
    }

    public function down(): void
    {
        //
    }
};
