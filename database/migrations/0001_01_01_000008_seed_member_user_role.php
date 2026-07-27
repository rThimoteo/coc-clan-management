<?php

use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(RoleSeeder::class)->run();
    }

    public function down(): void
    {
        //
    }
};
