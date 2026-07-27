<?php

namespace Database\Seeders;

use App\Enums\MemberStatus as MemberStatusEnum;
use App\Models\MemberStatus;
use Illuminate\Database\Seeder;

class MemberStatusSeeder extends Seeder
{
    public function run(): void
    {
        foreach (MemberStatusEnum::cases() as $status) {
            MemberStatus::query()->firstOrCreate(['slug' => $status->value]);
        }
    }
}
