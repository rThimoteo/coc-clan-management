<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminAccessSeeder extends Seeder
{
    public function run(): void
    {
        $accessCode = (string) config('auth.admin_access_code');
        $adminName = (string) config('auth.admin_access_name');

        if ($accessCode === '') {
            throw new RuntimeException('ADMIN_ACCESS_CODE must be configured before running the database seeder.');
        }

        $adminRole = Role::query()
            ->where('slug', UserRole::Admin->value)
            ->sole();

        User::query()->updateOrCreate(
            ['name' => $adminName],
            [
                'access_code' => $accessCode,
                'role_id' => $adminRole->id,
            ],
        );
    }
}
