<?php

use Database\Seeders\AdminAccessSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed the roles and initial administrator after creating their tables.
     */
    public function up(): void
    {
        app(RoleSeeder::class)->run();
        app(AdminAccessSeeder::class)->run();
    }

    /**
     * Seeded access data is intentionally preserved on a standalone rollback.
     */
    public function down(): void
    {
        //
    }
};
